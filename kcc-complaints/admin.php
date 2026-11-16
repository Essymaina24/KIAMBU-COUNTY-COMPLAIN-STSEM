<?php
require_once 'includes/db.php';
requireRole(['admin','supervisor']);

$user = currentUser();
$pdo  = db();

// Date filters
$from       = $_GET['from']    ?? date('Y-m-01');
$to         = $_GET['to']      ?? date('Y-m-d');
$deptFilter = (int)($_GET['dept_id'] ?? 0);

$where  = ["c.submitted_at BETWEEN ? AND ?"];
$params = [$from . ' 00:00:00', $to . ' 23:59:59'];
if ($deptFilter) { $where[] = 'c.dept_id = ?'; $params[] = $deptFilter; }
$whereSQL = implode(' AND ', $where);

// KPIs
$kpis = [
    'total'       => $pdo->prepare("SELECT COUNT(*) FROM complaints c WHERE $whereSQL")->execute($params) ? $pdo->prepare("SELECT COUNT(*) FROM complaints c WHERE $whereSQL")->execute($params) : 0,
    'resolved'    => 0,
    'in_progress' => 0,
    'escalated'   => 0,
];

// Simpler KPI queries
$q = $pdo->prepare("SELECT
    COUNT(*) as total,
    SUM(c.status='resolved') as resolved,
    SUM(c.status='in_progress') as in_progress,
    SUM(c.status='submitted') as submitted,
    SUM(c.status='under_review') as under_review
    FROM complaints c WHERE $whereSQL");
$q->execute($params);
$kpis = $q->fetch();

// Escalated count
$eq = $pdo->prepare("SELECT COUNT(*) FROM escalations e
    JOIN complaints c ON e.complaint_id=c.complaint_id
    WHERE e.resolved=0 AND $whereSQL");
$eq->execute($params);
$kpis['escalated'] = $eq->fetchColumn();

// By department
$dq = $pdo->prepare("SELECT d.dept_name,
    COUNT(*) as total,
    SUM(c.status='resolved') as resolved,
    SUM(c.status='in_progress') as in_progress,
    SUM(c.status='submitted') as submitted
    FROM complaints c
    JOIN departments d ON c.dept_id=d.dept_id
    WHERE $whereSQL
    GROUP BY d.dept_id, d.dept_name ORDER BY total DESC");
$dq->execute($params);
$byDept = $dq->fetchAll();

// By status
$sq = $pdo->prepare("SELECT status, COUNT(*) as cnt FROM complaints c WHERE $whereSQL GROUP BY status");
$sq->execute($params);
$byStatus = $sq->fetchAll(PDO::FETCH_KEY_PAIR);

// Resolution rates
$rq = $pdo->prepare("SELECT d.dept_name,
    COUNT(*) as total,
    SUM(c.status='resolved') as resolved,
    ROUND(SUM(c.status='resolved')*100.0/COUNT(*),1) as rate,
    ROUND(AVG(TIMESTAMPDIFF(DAY,c.submitted_at,COALESCE(c.last_updated_at,NOW()))),1) as avg_days
    FROM complaints c JOIN departments d ON c.dept_id=d.dept_id
    WHERE $whereSQL GROUP BY d.dept_id, d.dept_name");
$rq->execute($params);
$resRates = $rq->fetchAll();

// Recent escalations
$escQ = $pdo->query("SELECT e.*, c.reference_no, c.location,
    d.dept_name, cat.category_name,
    u.name as supervisor_name
    FROM escalations e
    JOIN complaints c ON e.complaint_id=c.complaint_id
    JOIN departments d ON c.dept_id=d.dept_id
    JOIN categories cat ON c.category_id=cat.category_id
    JOIN users u ON e.notified_supervisor=u.user_id
    WHERE e.resolved=0
    ORDER BY e.escalated_at DESC LIMIT 10");
$escalations = $escQ->fetchAll();

// Duplicates log
$dupQ = $pdo->query("SELECT c.reference_no, c.location, c.submitted_at,
    cat.category_name, d.dept_name
    FROM complaints c
    JOIN categories cat ON c.category_id=cat.category_id
    JOIN departments d ON c.dept_id=d.dept_id
    WHERE c.is_duplicate_flag=1
    ORDER BY c.submitted_at DESC LIMIT 20");
$duplicates = $dupQ->fetchAll();

// All departments for filter
$depts = $pdo->query("SELECT dept_id, dept_name FROM departments WHERE is_active=1")->fetchAll();

// CSV Export
if (isset($_GET['export'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="kcc_complaints_' . $from . '_to_' . $to . '.csv"');
    $fp = fopen('php://output','w');
    fputcsv($fp, ['Reference No','Citizen Name','Contact','Category','Department','Location','Status','Submitted','Last Updated']);
    $ex = $pdo->prepare("SELECT c.reference_no, c.citizen_name, c.citizen_contact,
        cat.category_name, d.dept_name, c.location, c.status, c.submitted_at, c.last_updated_at
        FROM complaints c
        JOIN categories cat ON c.category_id=cat.category_id
        JOIN departments d ON c.dept_id=d.dept_id
        WHERE $whereSQL ORDER BY c.submitted_at DESC");
    $ex->execute($params);
    while ($row = $ex->fetch()) fputcsv($fp, $row);
    fclose($fp);
    exit;
}

$initials = strtoupper(substr($user['name'],0,1) . (strpos($user['name'],' ')!==false ? substr($user['name'],strpos($user['name'],' ')+1,1) : ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard — KCC</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
</head>
<body>
<div class="app-wrap">

  <!-- Sidebar -->
  <nav class="sidebar">
    <div class="brand">
      <div class="brand-name">Kiambu County — CMS</div>
      <div class="brand-sub">Admin Dashboard</div>
    </div>
    <div class="nav-user">
      <div class="avatar"><?= e($initials) ?></div>
      <div>
        <div class="nav-user-name"><?= e($user['name']) ?></div>
        <div class="nav-user-role"><?= ucfirst($user['role']) ?></div>
      </div>
    </div>
    <div class="nav-links">
      <a href="officer.php" class="nav-link">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
          <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
        </svg>Complaints
      </a>
      <a href="admin.php" class="nav-link active">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="18" y1="20" x2="18" y2="10"/>
          <line x1="12" y1="20" x2="12" y2="4"/>
          <line x1="6" y1="20" x2="6" y2="14"/>
        </svg>Reports
      </a>
    </div>
    <div class="nav-bottom">
      <a href="logout.php">← Sign out</a>
    </div>
  </nav>

  <!-- Main -->
  <div class="main">
    <div class="page-head">
      <h1>Complaints Overview</h1>
      <div style="display:flex;align-items:center;gap:12px">
        <span style="background:#D1FAE5;color:#065F46;padding:4px 10px;border-radius:12px;font-size:11px;font-weight:700">
          <?= ucfirst($user['role']) ?>
        </span>
        <span style="font-size:12px;color:var(--muted)"><?= e($user['name']) ?></span>
      </div>
    </div>
    <div class="page-content">

      <!-- Filter bar -->
      <form method="GET" action="admin.php">
        <div class="filter-bar">
          <label>From</label>
          <input type="date" name="from" value="<?= e($from) ?>">
          <label>To</label>
          <input type="date" name="to" value="<?= e($to) ?>">
          <label>Department</label>
          <select name="dept_id">
            <option value="">All departments</option>
            <?php foreach ($depts as $d): ?>
              <option value="<?= $d['dept_id'] ?>" <?= $deptFilter==$d['dept_id']?'selected':'' ?>>
                <?= e($d['dept_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-primary btn-sm">Apply</button>
          <a href="admin.php?<?= http_build_query(array_merge($_GET,['export'=>1])) ?>"
             class="btn btn-sm btn-green">Export CSV</a>
        </div>
      </form>

      <!-- KPI cards -->
      <div class="kpi-row">
        <div class="kpi blue">
          <div class="num"><?= (int)$kpis['total'] ?></div>
          <div class="lbl">Total complaints</div>
        </div>
        <div class="kpi green">
          <div class="num"><?= (int)$kpis['resolved'] ?></div>
          <div class="lbl">Resolved</div>
        </div>
        <div class="kpi orange">
          <div class="num"><?= (int)$kpis['in_progress'] ?></div>
          <div class="lbl">In progress</div>
        </div>
        <div class="kpi red">
          <div class="num"><?= (int)$kpis['escalated'] ?></div>
          <div class="lbl">Escalated</div>
        </div>
      </div>

      <!-- Charts -->
      <div class="charts-row">
        <div class="card" style="padding:0">
          <div class="card-head"><h2>Complaints by department</h2></div>
          <div class="card-body"><canvas id="barChart" height="200"></canvas></div>
        </div>
        <div class="card" style="padding:0">
          <div class="card-head"><h2>Status breakdown</h2></div>
          <div class="card-body"><canvas id="pieChart" height="200"></canvas></div>
        </div>
      </div>

      <!-- Tabs -->
      <div class="tabs">
        <button class="tab-btn active" onclick="switchTab('resolution',this)">Resolution Rates</button>
        <button class="tab-btn" onclick="switchTab('escalations',this)">Escalations (<?= count($escalations) ?>)</button>
        <button class="tab-btn" onclick="switchTab('duplicates',this)">Duplicate Log (<?= count($duplicates) ?>)</button>
      </div>

      <!-- Tab: Resolution rates -->
      <div class="tab-panel active" id="tab-resolution">
        <div class="tbl-wrap">
          <table>
            <thead><tr>
              <th>Department</th><th>Total</th><th>Resolved</th>
              <th>Resolution Rate</th><th>Avg. Days</th>
            </tr></thead>
            <tbody>
            <?php foreach ($resRates as $r): ?>
            <tr>
              <td><strong><?= e($r['dept_name']) ?></strong></td>
              <td><?= (int)$r['total'] ?></td>
              <td><?= (int)$r['resolved'] ?></td>
              <td>
                <div class="rate-bar">
                  <div class="bar-bg">
                    <div class="bar-fill <?= $r['rate'] >= 70 ? 'good' : '' ?>"
                         style="width:<?= min(100,(float)$r['rate']) ?>%"></div>
                  </div>
                  <?= $r['rate'] ?>%
                </div>
              </td>
              <td><?= $r['avg_days'] ?? '—' ?> days</td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($resRates)): ?>
            <tr><td colspan="5" style="text-align:center;color:var(--muted);padding:20px">No data for this period.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Tab: Escalations -->
      <div class="tab-panel" id="tab-escalations">
        <div class="tbl-wrap">
          <table>
            <thead><tr>
              <th>Reference</th><th>Department</th><th>Category</th>
              <th>Location</th><th>Days Overdue</th><th>Supervisor</th>
            </tr></thead>
            <tbody>
            <?php foreach ($escalations as $esc): ?>
            <tr>
              <td class="td-ref"><?= e($esc['reference_no']) ?></td>
              <td><?= e($esc['dept_name']) ?></td>
              <td><?= e($esc['category_name']) ?></td>
              <td><?= e($esc['location']) ?></td>
              <td>
                <span class="chip" style="background:#FEE2E2;color:#991B1B">
                  <?= (int)$esc['days_overdue'] ?> days
                </span>
              </td>
              <td><?= e($esc['supervisor_name']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($escalations)): ?>
            <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:20px">✓ No active escalations.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Tab: Duplicates -->
      <div class="tab-panel" id="tab-duplicates">
        <div class="tbl-wrap">
          <table>
            <thead><tr>
              <th>Reference</th><th>Category</th><th>Department</th>
              <th>Location</th><th>Submitted</th>
            </tr></thead>
            <tbody>
            <?php foreach ($duplicates as $dup): ?>
            <tr>
              <td class="td-ref"><?= e($dup['reference_no']) ?> <span class="dup-tag">DUP</span></td>
              <td><?= e($dup['category_name']) ?></td>
              <td><?= e($dup['dept_name']) ?></td>
              <td><?= e($dup['location']) ?></td>
              <td><?= date('d M Y', strtotime($dup['submitted_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($duplicates)): ?>
            <tr><td colspan="5" style="text-align:center;color:var(--muted);padding:20px">No duplicate flags found.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
// Bar chart
new Chart(document.getElementById('barChart'), {
  type:'bar',
  data:{
    labels: <?= json_encode(array_column($byDept,'dept_name')) ?>,
    datasets:[
      {label:'Resolved',   data:<?= json_encode(array_column($byDept,'resolved')) ?>,   backgroundColor:'#1B6B3A',borderRadius:4},
      {label:'In Progress',data:<?= json_encode(array_column($byDept,'in_progress')) ?>,backgroundColor:'#E8920A',borderRadius:4},
      {label:'Submitted',  data:<?= json_encode(array_column($byDept,'submitted')) ?>,  backgroundColor:'#2A5298',borderRadius:4},
    ]
  },
  options:{responsive:true,
    plugins:{legend:{position:'bottom',labels:{font:{size:11},boxWidth:12}}},
    scales:{x:{stacked:true,grid:{display:false}},y:{stacked:true,grid:{color:'#F1F4F9'}}}}
});

// Doughnut chart
const statusData = <?= json_encode($byStatus) ?>;
new Chart(document.getElementById('pieChart'), {
  type:'doughnut',
  data:{
    labels: Object.keys(statusData).map(s => s.replace('_',' ').replace(/\b\w/g,c=>c.toUpperCase())),
    datasets:[{
      data: Object.values(statusData),
      backgroundColor:['#1B6B3A','#E8920A','#B91C1C','#2A5298','#6b7a99'],
      borderWidth:0,hoverOffset:6
    }]
  },
  options:{responsive:true,cutout:'65%',
    plugins:{legend:{position:'bottom',labels:{font:{size:11},boxWidth:12}}}}
});

// Tabs
function switchTab(id,btn){
  document.querySelectorAll('.tab-panel').forEach(p=>p.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
  document.getElementById('tab-'+id).classList.add('active');
  btn.classList.add('active');
}
</script>
</body>
</html>
