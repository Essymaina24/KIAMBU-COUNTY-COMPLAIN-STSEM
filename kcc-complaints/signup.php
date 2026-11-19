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
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
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
<div class="login-field"><label>Password</label><input type="password" name="password" placeholder="At least 8 characters" autocomplete="new-password" required minlength="8" maxlength="200"></div>
<button type="submit" class="btn btn-primary btn-full" style="margin-top:6px">Sign up</button></form>
<div class="divider">Already have an account?</div><a href="login.php" class="btn btn-outline btn-full">Sign in</a></div></div></body></html>