<?php
require_once 'includes/db.php';
requireRole(['officer','supervisor','admin']);

$user   = currentUser();
$pdo    = db();

// Handle status update POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_complaint'])) {
    $cId    = (int)$_POST['complaint_id'];
    $status = $_POST['new_status'] ?? '';
    $notes  = trim($_POST['notes'] ?? '');
    $valid  = ['submitted','under_review','in_progress','resolved','closed'];

    if ($cId && in_array($status, $valid) && strlen($notes) >= 3) {
        // Verify officer belongs to this complaint's dept
        $check = $pdo->prepare(
            "SELECT dept_id FROM complaints WHERE complaint_id = ?"
        );
        $check->execute([$cId]);
        $row = $check->fetch();

        if ($row && ($user['role'] !== 'officer' || $row['dept_id'] == $user['dept_id'])) {
            $pdo->prepare(
                "UPDATE complaints SET status=?, last_updated_at=NOW() WHERE complaint_id=?"
            )->execute([$status, $cId]);

            $pdo->prepare(
                "INSERT INTO complaint_updates
                 (complaint_id, officer_id, new_status, notes, updated_at)
                 VALUES (?,?,?,?,NOW())"
            )->execute([$cId, $user['user_id'], $status, $notes]);

            flash('success', 'Complaint updated successfully.');
        }
    }
    redirect('officer.php');
}

// Filters
$filterStatus = $_GET['status'] ?? '';
$filterSearch = trim($_GET['q'] ?? '');

// Build complaints query
$where  = ['1=1'];
$params = [];

// Officers see only their department
if ($user['role'] === 'officer') {
    $where[]  = 'c.dept_id = ?';
    $params[] = $user['dept_id'];
}
if ($filterStatus) {
    $where[]  = 'c.status = ?';
    $params[] = $filterStatus;
}
if ($filterSearch) {
    $where[]  = '(c.reference_no LIKE ? OR c.citizen_name LIKE ?)';
    $params[] = "%$filterSearch%";
    $params[] = "%$filterSearch%";
}

$sql = "SELECT c.*, cat.category_name, d.dept_name
        FROM complaints c
        JOIN categories cat ON c.category_id = cat.category_id
        JOIN departments d  ON c.dept_id     = d.dept_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY c.submitted_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$complaints = $stmt->fetchAll();

// KPI counts
$deptFilter = $user['role'] === 'officer' ? "AND c.dept_id = {$user['dept_id']}" : '';
$kpis = [
    'total'       => $pdo->query("SELECT COUNT(*) FROM complaints c WHERE 1=1 $deptFilter")->fetchColumn(),
    'in_progress' => $pdo->query("SELECT COUNT(*) FROM complaints c WHERE c.status='in_progress' $deptFilter")->fetchColumn(),
    'escalated'   => $pdo->query("SELECT COUNT(*) FROM escalations e JOIN complaints c ON e.complaint_id=c.complaint_id WHERE e.resolved=0 $deptFilter")->fetchColumn(),
    'resolved'    => $pdo->query("SELECT COUNT(*) FROM complaints c WHERE c.status='resolved' $deptFilter")->fetchColumn(),
];

// Get single complaint for detail panel
$detail = null;
if (isset($_GET['view'])) {
    $ds = $pdo->prepare(
        "SELECT c.*, cat.category_name, d.dept_name
         FROM complaints c
         JOIN categories cat ON c.category_id = cat.category_id
         JOIN departments d  ON c.dept_id     = d.dept_id
         WHERE c.complaint_id = ?"
    );
    $ds->execute([(int)$_GET['view']]);
    $detail = $ds->fetch();

    if ($detail) {
        $uh = $pdo->prepare(
            "SELECT cu.*, u.name as officer_name
             FROM complaint_updates cu
             JOIN users u ON cu.officer_id = u.user_id
             WHERE cu.complaint_id = ? ORDER BY cu.updated_at DESC"
        );
        $uh->execute([$detail['complaint_id']]);
        $detailUpdates = $uh->fetchAll();
    }
}

// Get dept name
$deptName = 'All Departments';
if ($user['dept_id']) {
    $dn = $pdo->prepare("SELECT dept_name FROM departments WHERE dept_id=?");
    $dn->execute([$user['dept_id']]);
    $deptName = $dn->fetchColumn() ?: 'All Departments';
}
$initials = strtoupper(substr($user['name'],0,1) . (strpos($user['name'],' ')!==false ? substr($user['name'],strpos($user['name'],' ')+1,1) : ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Officer Dashboard — KCC</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
<style>
.detail-panel{background:white;border:1.5px solid var(--border);border-radius:10px;
  overflow:hidden;margin-top:20px}
.detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px}
.detail-item label{font-size:11px;color:var(--muted);display:block;margin-bottom:2px}
.detail-item span{font-size:13px;font-weight:500}
.desc-box{background:var(--slate);border-radius:7px;padding:14px;
  font-size:13px;line-height:1.6;margin-bottom:16px}
</style>
</head>
<body>
<div class="app-wrap">

  <!-- Sidebar -->
  <nav class="sidebar">
    <div class="brand">
      <div class="brand-name">Kiambu County — CMS</div>
      <div class="brand-sub">Complaint Management</div>
    </div>
    <div class="nav-user">
      <div class="avatar"><?= e($initials) ?></div>
      <div>
        <div class="nav-user-name"><?= e($user['name']) ?></div>
        <div class="nav-user-role"><?= e($deptName) ?></div>
      </div>
    </div>
    <div class="nav-links">
      <a href="officer.php" class="nav-link active">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
          <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
        </svg>
        My Complaints
        <span class="nav-badge"><?= $kpis['total'] ?></span>
      </a>
      <?php if (in_array($user['role'],['admin','supervisor'])): ?>
      <a href="admin.php" class="nav-link">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="18" y1="20" x2="18" y2="10"/>
          <line x1="12" y1="20" x2="12" y2="4"/>
          <line x1="6" y1="20" x2="6" y2="14"/>
        </svg>
        Reports
      </a>
      <?php endif; ?>
    </div>
    <div class="nav-bottom">
      <a href="logout.php">← Sign out</a>
      <a href="submit.php" style="color:#4a6a9a">Public submission form</a>
    </div>
  </nav>

  <!-- Main -->
  <div class="main">
    <div class="page-head">
      <h1>My Complaints</h1>
      <div style="display:flex;align-items:center;gap:12px">
        <span style="background:#dce8ff;color:var(--navy);padding:4px 10px;border-radius:12px;font-size:11px;font-weight:700">
          <?= e($deptName) ?>
        </span>
        <span style="font-size:12px;color:var(--muted)"><?= e($user['name']) ?> · <?= ucfirst($user['role']) ?></span>
      </div>
    </div>
    <div class="page-content">

      <?php if ($msg = flash('success')): ?>
        <div class="alert alert-success"><?= e($msg) ?></div>
      <?php endif; ?>

      <!-- KPI cards -->
      <div class="kpi-row">
        <div class="kpi blue"><div class="num"><?= $kpis['total'] ?></div><div class="lbl">Total assigned</div></div>
        <div class="kpi orange"><div class="num"><?= $kpis['in_progress'] ?></div><div class="lbl">In progress</div></div>
        <div class="kpi red"><div class="num"><?= $kpis['escalated'] ?></div><div class="lbl">Escalated</div></div>
        <div class="kpi green"><div class="num"><?= $kpis['resolved'] ?></div><div class="lbl">Resolved</div></div>
      </div>

      <!-- Filter bar -->
      <form method="GET" action="officer.php">
        <div class="filter-bar">
          <label>Status</label>
          <select name="status" onchange="this.form.submit()">
            <option value="">All statuses</option>
            <?php foreach (['submitted','under_review','in_progress','resolved','closed'] as $s): ?>
              <option value="<?= $s ?>" <?= $filterStatus===$s?'selected':'' ?>><?= statusLabel($s) ?></option>
            <?php endforeach; ?>
          </select>
          <label>Search</label>
          <input type="text" name="q" value="<?= e($filterSearch) ?>" placeholder="Name or ref no.">
          <button type="submit" class="btn btn-primary btn-sm">Search</button>
          <a href="officer.php" class="btn btn-outline btn-sm">Clear</a>
        </div>
      </form>

      <!-- Complaints table -->
      <div class="tbl-wrap">
        <table>
          <thead>
            <tr>
              <th>Reference</th><th>Citizen</th><th>Category</th>
              <th>Location</th><th>Submitted</th><th>Status</th><th>Action</th>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($complaints)): ?>
            <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:24px">No complaints found.</td></tr>
          <?php else: ?>
            <?php foreach ($complaints as $c): ?>
            <tr>
              <td class="td-ref">
                <?= e($c['reference_no']) ?>
                <?php if ($c['is_duplicate_flag']): ?>
                  <span class="dup-tag">DUP</span>
                <?php endif; ?>
              </td>
              <td>
                <?= e($c['citizen_name']) ?><br>
                <span style="font-size:11px;color:var(--muted)"><?= e($c['citizen_contact']) ?></span>
              </td>
              <td><?= e($c['category_name']) ?></td>
              <td><?= e($c['location']) ?></td>
              <td><?= date('d M Y', strtotime($c['submitted_at'])) ?></td>
              <td><span class="chip <?= chipClass($c['status']) ?>"><?= statusLabel($c['status']) ?></span></td>
              <td>
                <a href="officer.php?view=<?= $c['complaint_id'] ?>#detail" class="btn btn-outline btn-sm">Update</a>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Detail / Update panel -->
      <?php if ($detail): ?>
      <div class="detail-panel" id="detail">
        <div class="card-head">
          <h2>Complaint: <?= e($detail['reference_no']) ?></h2>
          <span class="chip <?= chipClass($detail['status']) ?>"><?= statusLabel($detail['status']) ?></span>
        </div>
        <div class="card-body">

          <?php if ($detail['is_duplicate_flag']): ?>
          <div class="alert alert-warn" style="margin-bottom:16px">
            ⚠ This complaint is flagged as a possible duplicate. Review before updating.
          </div>
          <?php endif; ?>

          <!-- Details -->
          <div class="detail-grid">
            <div class="detail-item"><label>Citizen Name</label><span><?= e($detail['citizen_name']) ?></span></div>
            <div class="detail-item"><label>Contact</label><span><?= e($detail['citizen_contact']) ?></span></div>
            <div class="detail-item"><label>Category</label><span><?= e($detail['category_name']) ?></span></div>
            <div class="detail-item"><label>Submitted</label><span><?= date('d M Y, H:i', strtotime($detail['submitted_at'])) ?></span></div>
            <div class="detail-item"><label>Location</label><span><?= e($detail['location']) ?></span></div>
            <div class="detail-item"><label>Department</label><span><?= e($detail['dept_name']) ?></span></div>
          </div>

          <!-- Description -->
          <div style="font-size:12px;font-weight:600;color:var(--muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:.04em">Description</div>
          <div class="desc-box"><?= e($detail['description']) ?></div>

          <!-- Update form -->
          <div style="background:var(--lgray);border-radius:8px;padding:18px;margin-bottom:18px">
            <div style="font-size:13px;font-weight:700;color:var(--navy);margin-bottom:14px">Update Status</div>
            <form method="POST" action="officer.php#detail">
              <input type="hidden" name="update_complaint" value="1">
              <input type="hidden" name="complaint_id" value="<?= $detail['complaint_id'] ?>">
              <div class="form-row">
                <div class="form-field">
                  <label>New Status *</label>
                  <select name="new_status">
                    <?php foreach (['submitted','under_review','in_progress','resolved','closed'] as $s): ?>
                      <option value="<?= $s ?>" <?= $detail['status']===$s?'selected':'' ?>><?= statusLabel($s) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="form-field" style="margin-bottom:14px">
                <label>Resolution / Action Notes *</label>
                <textarea name="notes" rows="3" placeholder="Describe the action taken or current status…" required></textarea>
              </div>
              <button type="submit" class="btn btn-primary">Save Update</button>
              <a href="officer.php" class="btn btn-outline" style="margin-left:8px">Cancel</a>
            </form>
          </div>

          <!-- Update history -->
          <?php if (!empty($detailUpdates)): ?>
          <div style="font-size:13px;font-weight:700;color:var(--navy);margin-bottom:12px">Update History</div>
          <?php foreach ($detailUpdates as $upd): ?>
          <div class="log-item">
            <div class="log-dot"></div>
            <div>
              <div class="log-status"><?= statusLabel($upd['new_status']) ?> — <?= e($upd['officer_name']) ?></div>
              <div class="log-note"><?= e($upd['notes']) ?></div>
              <div class="log-meta"><?= date('d M Y, H:i', strtotime($upd['updated_at'])) ?></div>
            </div>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>

        </div>
      </div>
      <?php endif; ?>

    </div>
  </div>
</div>
</body>
</html>
