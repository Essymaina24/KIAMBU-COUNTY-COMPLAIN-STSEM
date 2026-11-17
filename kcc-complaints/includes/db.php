<?php
// PostgreSQL/Supabase connection. Keep credentials in Vercel Environment Variables.
function db() {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $url = getenv('SUPABASE_DB_URL') ?: getenv('POSTGRES_URL') ?: getenv('DATABASE_URL');
    try {
        if ($url) {
            $parts = parse_url($url);
            if (!$parts || empty($parts['host']) || empty($parts['path'])) {
                throw new RuntimeException('The configured database URL is invalid.');
            }
            $host = $parts['host'];
            $port = $parts['port'] ?? 5432;
            $name = ltrim($parts['path'], '/');
            $user = rawurldecode($parts['user'] ?? '');
            $pass = rawurldecode($parts['pass'] ?? '');
            $dsn = "pgsql:host={$host};port={$port};dbname={$name};sslmode=require";
        } else {
            $host = getenv('DB_HOST') ?: getenv('POSTGRES_HOST');
            $name = getenv('DB_NAME') ?: getenv('POSTGRES_DATABASE');
            $user = getenv('DB_USER') ?: getenv('POSTGRES_USER');
            $pass = getenv('DB_PASS') ?: getenv('POSTGRES_PASSWORD');
            if (!$host || !$name || !$user || !$pass) {
                throw new RuntimeException('Set SUPABASE_DB_URL or POSTGRES_URL in Vercel Environment Variables.');
            }
            $dsn = "pgsql:host={$host};port=" . (getenv('DB_PORT') ?: '5432') . ";dbname={$name};sslmode=require";
        }

        if (!extension_loaded('pdo_pgsql')) {
            throw new RuntimeException('The PHP pdo_pgsql extension is missing from the deployment runtime.');
        }
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
    } catch (Throwable $e) {
        error_log('KCC database error: ' . $e->getMessage());
        http_response_code(503);
        die('<h2>Database connection unavailable</h2><p>Check the Supabase PostgreSQL connection settings and run <code>database/kcc_supabase.sql</code>.</p>');
    }
    return $pdo;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
function isLoggedIn() { return isset($_SESSION['user_id']); }
function currentUser() { return $_SESSION['user'] ?? null; }
function requireLogin() {
    if (!isLoggedIn()) { header('Location: login.php'); exit; }
}
function requireRole(array $roles) {
    requireLogin();
    $user = currentUser();
    if (!$user || !isset($user['role']) || !in_array($user['role'], $roles, true)) {
        header('Location: login.php?error=access_denied'); exit;
    }
}
function e($str) {
    return htmlspecialchars((string)($str ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function generateRefNo() {
    $pdo = db();
    $year = date('Y');
    // The sequence-backed ID is allocated on insert; use a random suffix to avoid race conditions.
    return 'KCC-' . $year . '-' . strtoupper(bin2hex(random_bytes(3)));
}
function redirect($url) { header("Location: $url"); exit; }
function flash($key, $msg = null) {
    if ($msg !== null) { $_SESSION['flash'][$key] = $msg; }
    else { $val = $_SESSION['flash'][$key] ?? null; unset($_SESSION['flash'][$key]); return $val; }
}
function statusLabel($status) {
    return match($status) {
        'submitted' => 'Submitted', 'under_review' => 'Under Review',
        'in_progress' => 'In Progress', 'resolved' => 'Resolved',
        'closed' => 'Closed', default => ucfirst((string)$status)
    };
}
function chipClass($status) {
    return match($status) {
        'submitted' => 'chip-sub', 'under_review' => 'chip-rev',
        'in_progress' => 'chip-prog', 'resolved' => 'chip-res',
        'closed' => 'chip-cls', default => 'chip-sub'
    };
}
?>