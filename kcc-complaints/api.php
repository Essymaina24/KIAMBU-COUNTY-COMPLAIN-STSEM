<?php
require_once 'includes/db.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

try {
    $pdo = db();

    if ($action === 'stats') {
        $total    = $pdo->query("SELECT COUNT(*) FROM complaints")->fetchColumn();
        $resolved = $pdo->query("SELECT COUNT(*) FROM complaints WHERE status='resolved'")->fetchColumn();
        $rate     = $total > 0 ? round($resolved / $total * 100, 1) : 0;
        echo json_encode(['total'=>(int)$total,'resolved'=>(int)$resolved,'rate'=>$rate]);
    } else {
        echo json_encode(['error'=>'Unknown action']);
    }
} catch (Exception $e) {
    echo json_encode(['error'=>$e->getMessage()]);
}
