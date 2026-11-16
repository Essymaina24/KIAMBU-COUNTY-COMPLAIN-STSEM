<?php
require_once __DIR__ . '/includes/db.php';

if (isLoggedIn()) {
    $u = currentUser();

    if (in_array($u['role'], ['admin', 'supervisor'], true)) {
        header('Location: admin.php');
    } else {
        header('Location: officer.php');
    }
} else {
    header('Location: login.php');
}

exit;