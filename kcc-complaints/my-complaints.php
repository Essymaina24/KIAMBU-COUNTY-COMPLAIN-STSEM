<?php
require_once 'includes/db.php';
requireRole(['citizen']);
$user = currentUser();
$pdo = db();
$stmt = $pdo->prepare("SELECT complaint_id,reference_no,location,status,submitted_at,last_updated_at FROM complaints WHERE citizen_id = ? ORDER BY submitted_at DESC");
$stmt->execute([$user['user_id']]);
$complaints = $stmt->fetchAll();
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My complaints — KCC</title><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"><link rel="stylesheet" href="assets/css/style.css"></head>
<body><header class="topbar"><div class="topbar-brand"><div class="topbar-logo">K</div><div><div class="topbar-name">Kiambu County Government</div><div class="topbar-sub">Complaint Management System</div></div></div><div class="topbar-links"><a href="submit.php">Submit complaint</a><a href="logout.php">Sign out</a></div></header>
<div class="hero"><h1>My complaints</h1><p>Signed in as <?= e($user['email']) ?>. View and track complaints submitted through your account.</p></div>
<div style="max-width:900px;margin:28px auto;padding:0 24px"><div class="card"><div class="card-head" style="display:flex;justify-content:space-between;align-items:center"><h2>My submissions</h2><a class="btn btn-primary" href="submit.php">Submit a complaint</a></div><div class="card-body">
<?php if (!$complaints): ?><p>You have not submitted any complaints through this account yet.</p><a href="submit.php" class="btn btn-primary">Submit your first complaint</a>
<?php else: ?><div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse"><thead><tr><th style="text-align:left;padding:10px">Reference</th><th style="text-align:left;padding:10px">Location</th><th style="text-align:left;padding:10px">Status</th><th style="text-align:left;padding:10px">Submitted</th><th></th></tr></thead><tbody>
<?php foreach ($complaints as $c): ?><tr><td style="padding:10px;border-top:1px solid #e5e7eb"><?= e($c['reference_no']) ?></td><td style="padding:10px;border-top:1px solid #e5e7eb"><?= e($c['location']) ?></td><td style="padding:10px;border-top:1px solid #e5e7eb"><?= e(statusLabel($c['status'])) ?></td><td style="padding:10px;border-top:1px solid #e5e7eb"><?= date('d M Y',strtotime($c['submitted_at'])) ?></td><td style="padding:10px;border-top:1px solid #e5e7eb"><a href="track.php?ref=<?= urlencode($c['reference_no']) ?>">Track</a></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?></div></div></div></body></html>