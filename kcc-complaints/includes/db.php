```php
<?php

// ── DATABASE CONNECTION ─────────────────────────────────────────────────────

define('DB_HOST', 'localhost');
define('DB_NAME', 'kcc_complaints');
define('DB_USER', 'root');
define('DB_PASS', '');

function db() {
    static $pdo = null;

    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );

        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());

            die("
                <div style='font-family:Arial;padding:30px;background:#fee;
                border:2px solid red;margin:20px;border-radius:8px'>

                    <h2>⚠ Database Connection Failed</h2>

                    <p>Unable to connect to the database.</p>

                    <p>Please check the following:</p>

                    <ul>
                        <li>Database hostname</li>
                        <li>Database name</li>
                        <li>Database username</li>
                        <li>Database password</li>
                        <li>Whether the SQL database has been imported</li>
                    </ul>

                    <p>Check your database settings in
                    <code>includes/db.php</code>.</p>

                </div>
            ");
        }
    }

    return $pdo;
}


// ── SESSION ──────────────────────────────────────────────────────────────────

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// ── AUTH HELPERS ─────────────────────────────────────────────────────────────

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}


function currentUser() {
    return $_SESSION['user'] ?? null;
}


function requireLogin() {

    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }

}


function requireRole(array $roles) {

    requireLogin();

    $user = currentUser();

    if (
        !$user ||
        !isset($user['role']) ||
        !in_array($user['role'], $roles, true)
    ) {

        header('Location: login.php?error=access_denied');
        exit;

    }

}


// ── GENERAL HELPERS ──────────────────────────────────────────────────────────

function e($str) {
    return htmlspecialchars(
        $str ?? '',
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}


function generateRefNo() {

    $pdo = db();

    $year = date('Y');

    $max = $pdo->query(
        "SELECT MAX(complaint_id) AS m FROM complaints"
    )->fetch()['m'] ?? 0;

    return "KCC-{$year}-" . str_pad(
        (int)$max + 1,
        5,
        '0',
        STR_PAD_LEFT
    );

}


function redirect($url) {

    header("Location: $url");
    exit;

}


// ── FLASH MESSAGES ───────────────────────────────────────────────────────────

function flash($key, $msg = null) {

    if ($msg !== null) {

        $_SESSION['flash'][$key] = $msg;

    } else {

        $val = $_SESSION['flash'][$key] ?? null;

        unset($_SESSION['flash'][$key]);

        return $val;

    }

}


// ── COMPLAINT STATUS HELPERS ─────────────────────────────────────────────────

function statusLabel($status) {

    return match($status) {

        'submitted'    => 'Submitted',

        'under_review' => 'Under Review',

        'in_progress'  => 'In Progress',

        'resolved'     => 'Resolved',

        'closed'       => 'Closed',

        default        => ucfirst($status)

    };

}


function chipClass($status) {

    return match($status) {

        'submitted'    => 'chip-sub',

        'under_review' => 'chip-rev',

        'in_progress'  => 'chip-prog',

        'resolved'     => 'chip-res',

        'closed'       => 'chip-cls',

        default        => 'chip-sub'

    };

}

?>
```