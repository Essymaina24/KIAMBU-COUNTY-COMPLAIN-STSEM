<?php
require_once 'includes/db.php';

if (isLoggedIn()) {
    $user = currentUser();
    redirect(in_array($user['role'], ['admin','supervisor']) ? 'admin.php' : 'officer.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pwd   = $_POST['password'] ?? '';

    if (!$email || !$pwd) {
        $error = 'Please enter your email and password.';
    } else {
        $pdo  = db();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = TRUE LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($pwd, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user']    = [
                'user_id'  => $user['user_id'],
                'name'     => $user['name'],
                'email'    => $user['email'],
                'role'     => $user['role'],
                'dept_id'  => $user['dept_id'],
            ];
            redirect(in_array($user['role'], ['admin','supervisor']) ? 'admin.php' : 'officer.php');
        } else {
            $error = 'Invalid email or password.';
        }
    }
}

if (isset($_GET['error']) && $_GET['error'] === 'access_denied') {
    $error = 'You do not have permission to access that page.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — KCC Complaint System</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="login-wrap">

  <!-- Left panel -->
  <div class="login-left">
    <div>
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:48px">
        <div style="width:40px;height:40px;background:rgba(255,255,255,.15);border-radius:50%;display:flex;align-items:center;justify-content:center">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5">
            <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9,22 9,12 15,12 15,22"/>
          </svg>
        </div>
        <div>
          <div style="font-size:13px;font-weight:700;color:white">Kiambu County</div>
          <div style="font-size:11px;color:#8ab0e0">Government — CMS</div>
        </div>
      </div>
      <h2 class="ll-title">Complaints resolved faster when they're tracked.</h2>
      <p class="ll-desc">The Citizen Complaint Management System gives county staff a structured way to receive, assign, and resolve public grievances — every complaint tracked from submission to closure.</p>
      <div class="ll-stats">
        <div class="ll-stat"><div class="s-num" id="totalCount">—</div><div class="s-lbl">Total complaints</div></div>
        <div class="ll-stat"><div class="s-num" id="resolvedCount">—</div><div class="s-lbl">Resolved</div></div>
        <div class="ll-stat"><div class="s-num" id="rateCount">—</div><div class="s-lbl">Resolution rate</div></div>
      </div>
    </div>
    <div class="ll-links">
      <a href="submit.php">→ Submit a complaint (no login needed)</a>
      <a href="track.php">→ Track a complaint (no login needed)</a>
      <div style="margin-top:12px;color:#4a6a9a">Kiambu County Government · CMS v1.0</div>
    </div>
  </div>

  <!-- Right panel -->
  <div class="login-right">
    <h1>Staff login</h1>
    <p class="sub">Sign in to access your department dashboard.</p>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php">
      <div class="login-field">
        <label>Email address</label>
        <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>"
               placeholder="officer@kiambu.go.ke" autocomplete="email" required>
      </div>
      <div class="login-field">
        <label>Password</label>
        <input type="password" name="password" placeholder="••••••••" autocomplete="current-password" required>
      </div>
      <button type="submit" class="btn btn-primary btn-full" style="margin-top:6px">Sign in</button>
    </form>

    <div class="divider">or</div>

    <a href="submit.php" class="btn btn-outline btn-full" style="margin-bottom:8px">Submit a complaint — no login needed</a>
    <a href="track.php"  class="btn btn-outline btn-full">Track a complaint — no login needed</a>

    <div class="demo-box">
      <h4>Demo accounts — click to fill</h4>
      <div class="demo-row"><span>Admin</span><a href="#" onclick="fill('admin@kiambu.go.ke')">admin@kiambu.go.ke</a></div>
      <div class="demo-row"><span>Roads Officer</span><a href="#" onclick="fill('officer1@kiambu.go.ke')">officer1@kiambu.go.ke</a></div>
      <div class="demo-row"><span>Water Officer</span><a href="#" onclick="fill('officer2@kiambu.go.ke')">officer2@kiambu.go.ke</a></div>
      <div style="font-size:11px;color:var(--muted);margin-top:6px">Password for all: <strong>Admin@1234</strong></div>
    </div>
  </div>
</div>

<script>
function fill(email) {
  document.querySelector('[name=email]').value = email;
  document.querySelector('[name=password]').value = 'Admin@1234';
  return false;
}
// Load live stats
fetch('api.php?action=stats').then(r=>r.json()).then(d=>{
  document.getElementById('totalCount').textContent    = d.total ?? '—';
  document.getElementById('resolvedCount').textContent = d.resolved ?? '—';
  document.getElementById('rateCount').textContent     = d.rate ? d.rate+'%' : '—';
}).catch(()=>{});
</script>
</body>
</html>
