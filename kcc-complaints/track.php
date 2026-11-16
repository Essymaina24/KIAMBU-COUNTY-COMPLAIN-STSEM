<?php
require_once 'includes/db.php';

$complaint = null;
$updates   = [];
$error     = '';
$refNo     = trim($_GET['ref'] ?? $_POST['ref_no'] ?? '');

if ($refNo) {
    $refNo = strtoupper($refNo);
    $pdo   = db();

    $stmt = $pdo->prepare(
        "SELECT c.*, cat.category_name, d.dept_name
         FROM complaints c
         JOIN categories cat ON c.category_id = cat.category_id
         JOIN departments d  ON c.dept_id     = d.dept_id
         WHERE c.reference_no = ?"
    );
    $stmt->execute([$refNo]);
    $complaint = $stmt->fetch();

    if (!$complaint) {
        $error = 'No complaint found with that reference number. Please check and try again.';
    } else {
        $upd = $pdo->prepare(
            "SELECT cu.*, u.name as officer_name
             FROM complaint_updates cu
             JOIN users u ON cu.officer_id = u.user_id
             WHERE cu.complaint_id = ?
             ORDER BY cu.updated_at DESC"
        );
        $upd->execute([$complaint['complaint_id']]);
        $updates = $upd->fetchAll();
    }
}

$stages  = ['submitted','under_review','in_progress','resolved','closed'];
$labels  = ['Submitted','Under Review','In Progress','Resolved','Closed'];
$current = $complaint ? array_search($complaint['status'], $stages) : -1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Track Complaint — KCC</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
<style>
.track-wrap{max-width:720px;margin:28px auto;padding:0 24px}
.search-card{background:white;border:1.5px solid var(--border);border-radius:10px;
  padding:22px 26px;margin-bottom:18px}
.search-card h2{font-size:14px;font-weight:700;color:var(--navy);margin-bottom:14px}
.search-row{display:flex;gap:12px}
.search-row input{flex:1;padding:11px 14px;border:1.5px solid var(--border);
  border-radius:7px;font-size:14px;font-family:inherit;background:var(--slate);
  color:var(--text);outline:none;transition:border-color .15s}
.search-row input:focus{border-color:var(--navy);box-shadow:0 0 0 3px rgba(15,42,82,.08)}
.result-header{background:var(--navy);color:white;padding:18px 26px;
  display:flex;justify-content:space-between;align-items:flex-start}
.ref-big{font-size:18px;font-weight:700;letter-spacing:.06em;font-family:monospace}
.status-chip{display:inline-flex;padding:6px 14px;border-radius:20px;
  font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em}
.sc-submitted,.sc-under_review{background:#dce8ff;color:#0F2A52}
.sc-in_progress{background:#FEF3C7;color:#78350F}
.sc-resolved{background:#D1FAE5;color:#065F46}
.sc-closed{background:#E5E7EB;color:#374151}
.dup-strip{background:#FEF3C7;border-bottom:1.5px solid #F59E0B;
  padding:10px 26px;font-size:12px;color:#78350F}
</style>
</head>
<body>

<header class="topbar">
  <div class="topbar-brand">
    <div class="topbar-logo">
      <svg viewBox="0 0 24 24" fill="none" stroke="#0F2A52" stroke-width="2.5">
        <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
        <polyline points="9,22 9,12 15,12 15,22"/>
      </svg>
    </div>
    <div><div class="topbar-name">Kiambu County Government</div>
    <div class="topbar-sub">Complaint Management System</div></div>
  </div>
  <div class="topbar-links">
    <a href="submit.php">Submit complaint</a>
    <a href="login.php">Staff login</a>
  </div>
</header>

<div class="hero">
  <h1>Track Your Complaint</h1>
  <p>Enter your reference number to check the current status. No login required.</p>
</div>

<div class="track-wrap">

  <!-- Search box -->
  <div class="search-card">
    <h2>Enter reference number</h2>
    <form method="POST" action="track.php">
      <div class="search-row">
        <input type="text" name="ref_no"
               value="<?= e($refNo) ?>"
               placeholder="e.g. KCC-2026-00001"
               style="text-transform:uppercase"
               oninput="this.value=this.value.toUpperCase()">
        <button type="submit" class="btn btn-primary">Track Complaint</button>
      </div>
    </form>
    <?php if ($error): ?>
      <div class="alert alert-error" style="margin-top:12px;margin-bottom:0"><?= e($error) ?></div>
    <?php endif; ?>
    <div style="font-size:11px;color:var(--muted);margin-top:8px">
      No reference number? <a href="submit.php" style="color:var(--navyl)">Submit a new complaint here.</a>
    </div>
  </div>

  <!-- Result -->
  <?php if ($complaint): ?>
  <div class="card" style="padding:0">

    <!-- Header -->
    <div class="result-header">
      <div>
        <div class="ref-big"><?= e($complaint['reference_no']) ?></div>
        <div style="font-size:12px;color:#a8c4e8;margin-top:3px">
          <?= e($complaint['category_name']) ?> — <?= e($complaint['dept_name']) ?>
        </div>
      </div>
      <span class="status-chip sc-<?= e($complaint['status']) ?>">
        <?= statusLabel($complaint['status']) ?>
      </span>
    </div>

    <!-- Duplicate warning -->
    <?php if ($complaint['is_duplicate_flag']): ?>
    <div class="dup-strip">
      ⚠ This complaint was flagged as a possible duplicate. A county officer is reviewing both records.
    </div>
    <?php endif; ?>

    <!-- Info grid -->
    <div class="info-grid">
      <div class="info-cell">
        <label>Location</label>
        <span><?= e($complaint['location']) ?></span>
      </div>
      <div class="info-cell">
        <label>Assigned Department</label>
        <span><?= e($complaint['dept_name']) ?></span>
      </div>
      <div class="info-cell">
        <label>Submitted On</label>
        <span><?= date('d M Y', strtotime($complaint['submitted_at'])) ?></span>
      </div>
      <div class="info-cell">
        <label>Last Updated</label>
        <span><?= $complaint['last_updated_at']
              ? date('d M Y', strtotime($complaint['last_updated_at']))
              : 'Not yet updated' ?></span>
      </div>
    </div>

    <!-- Progress timeline -->
    <div style="padding:22px 26px;border-bottom:1.5px solid var(--border)">
      <div style="font-size:13px;font-weight:700;color:var(--navy);margin-bottom:16px">Progress</div>
      <div class="timeline">
        <?php foreach ($stages as $i => $stage): ?>
          <?php if ($i > 0): ?>
            <div class="t-line <?= $i <= $current ? 'done' : '' ?>"></div>
          <?php endif; ?>
          <div class="t-step">
            <div class="t-circle <?= $i < $current ? 'done' : ($i === $current ? 'current' : '') ?>">
              <?php if ($i < $current)     echo '✓';
                    elseif ($i === $current) echo '▶'; ?>
            </div>
            <div class="t-label <?= $i < $current ? 'done' : ($i === $current ? 'current' : '') ?>">
              <?= $labels[$i] ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Update history -->
    <div style="padding:22px 26px">
      <div style="font-size:13px;font-weight:700;color:var(--navy);margin-bottom:14px">
        Update History
      </div>
      <?php if (empty($updates)): ?>
        <div style="font-size:13px;color:var(--muted)">No updates yet — complaint is awaiting officer review.</div>
      <?php else: ?>
        <?php foreach ($updates as $upd): ?>
        <div class="log-item">
          <div class="log-dot"></div>
          <div>
            <div class="log-status">
              <?= statusLabel($upd['new_status']) ?> — <?= e($upd['officer_name']) ?>
            </div>
            <div class="log-note"><?= e($upd['notes']) ?></div>
            <div class="log-meta">
              <?= date('d M Y, H:i', strtotime($upd['updated_at'])) ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </div>
  <?php endif; ?>

</div>
</body>
</html>
