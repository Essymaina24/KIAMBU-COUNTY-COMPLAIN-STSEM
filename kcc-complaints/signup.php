<?php
require_once 'includes/db.php';
if (isLoggedIn()) {
    $u = currentUser();
    redirect(($u['role'] ?? '') === 'citizen' ? 'my-complaints.php' : 'login.php');
}
$error = '';
$emailValue = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $emailValue = $email;
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } elseif (strlen($password) < 12
        || !preg_match('/[A-Z]/', $password)
        || !preg_match('/[a-z]/', $password)
        || !preg_match('/[0-9]/', $password)
        || !preg_match('/[^A-Za-z0-9]/', $password)) {
        $error = 'Use at least 12 characters, including uppercase and lowercase letters, a number, and a special character.';
    } elseif (strlen($password) > 200) {
        $error = 'Password is too long.';
    } else {
        try {
            $pdo = db();
            $name = strstr($email, '@', true) ?: $email;
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name,email,password_hash,role,is_active) VALUES (?,?,?,'citizen',TRUE) RETURNING user_id,email,role,dept_id");
            $stmt->execute([$name, $email, $hash]);
            $user = $stmt->fetch();
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user'] = ['user_id'=>$user['user_id'],'name'=>$name,'email'=>$user['email'],'role'=>'citizen','dept_id'=>null];
            redirect('my-complaints.php');
        } catch (PDOException $e) {
            if (($e->errorInfo[0] ?? '') === '23505') {
                $error = 'An account with this email already exists. Please sign in instead.';
            } else {
                error_log('KCC signup error: ' . $e->getMessage());
                $error = 'We could not create your account right now. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create account — KCC Complaint System</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css"></head>
<body><div class="login-wrap">
<div class="login-left"><div>
<div style="display:flex;align-items:center;gap:12px;margin-bottom:48px">
<div style="width:40px;height:40px;background:rgba(255,255,255,.15);border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-weight:700">K</div>
<div><div style="font-size:13px;font-weight:700;color:white">Kiambu County</div><div style="font-size:11px;color:#8ab0e0">Government — CMS</div></div></div>
<h2 class="ll-title">Your voice matters.</h2><p class="ll-desc">Create an account to keep track of complaints you submit and follow their progress.</p></div>
<div class="ll-links"><a href="submit.php">→ Submit a complaint</a><a href="track.php">→ Track a complaint</a></div></div>
<div class="login-right"><h1>Create account</h1><p class="sub">Register with your email address and password. No email verification required.</p>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<form method="POST" action="signup.php" autocomplete="on">
<div class="login-field"><label>Email address</label><input type="email" name="email" value="<?= e($emailValue) ?>" placeholder="you@example.com" autocomplete="email" required maxlength="150"></div>
<div class="login-field">
<label for="password">Create a strong password</label>
<div style="position:relative">
<input id="password" type="password" name="password" placeholder="Create a strong password" autocomplete="new-password" required minlength="12" maxlength="200" aria-describedby="password-rules" style="padding-right:76px">
<button type="button" id="toggle-password" aria-label="Show password" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);border:0;background:transparent;color:#24518a;cursor:pointer;font-weight:600">Show</button>
</div>
<div id="password-rules" style="font-size:12px;line-height:1.8;margin-top:8px;color:#64748b">
<div id="rule-length">○ At least 12 characters</div>
<div id="rule-upper">○ One uppercase letter (A–Z)</div>
<div id="rule-lower">○ One lowercase letter (a–z)</div>
<div id="rule-number">○ One number (0–9)</div>
<div id="rule-symbol">○ One special character (e.g. !@#$%)</div>
</div>
<div style="height:6px;background:#e2e8f0;border-radius:99px;margin-top:8px;overflow:hidden"><div id="password-strength" style="height:100%;width:0;background:#dc2626;transition:width .2s"></div></div>
<div id="strength-label" aria-live="polite" style="font-size:12px;margin-top:4px;color:#64748b">Password strength: not entered</div>
</div>
<button type="submit" class="btn btn-primary btn-full" style="margin-top:6px">Sign up</button></form>
<div class="divider">Already have an account?</div><a href="login.php" class="btn btn-outline btn-full">Sign in</a></div></div>
<script>
(function () {
  const input = document.getElementById('password');
  const toggle = document.getElementById('toggle-password');
  const bar = document.getElementById('password-strength');
  const label = document.getElementById('strength-label');
  const rules = {
    length: value => value.length >= 12,
    upper: value => /[A-Z]/.test(value),
    lower: value => /[a-z]/.test(value),
    number: value => /[0-9]/.test(value),
    symbol: value => /[^A-Za-z0-9]/.test(value)
  };
  const ids = {length:'rule-length',upper:'rule-upper',lower:'rule-lower',number:'rule-number',symbol:'rule-symbol'};
  input.addEventListener('input', function () {
    const value = input.value;
    let passed = 0;
    Object.entries(rules).forEach(([key, test]) => {
      const ok = test(value);
      if (ok) passed++;
      const el = document.getElementById(ids[key]);
      el.textContent = (ok ? '✓ ' : '○ ') + el.textContent.replace(/^[✓○] /, '');
      el.style.color = ok ? '#15803d' : '#64748b';
    });
    bar.style.width = (passed * 20) + '%';
    const names = ['Very weak','Weak','Fair','Good','Strong','Strong'];
    label.textContent = 'Password strength: ' + (value ? names[passed] : 'not entered');
    label.style.color = passed === 5 ? '#15803d' : '#64748b';
  });
  toggle.addEventListener('click', function () {
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    toggle.textContent = show ? 'Hide' : 'Show';
    toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
  });
})();
</script>
</body></html>