<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
$pdo = db();

header('Content-Type: application/json');

// --- PART 1: ESP32 REPORTS COMPLETION (ACTUAL DELETE) ---
if (isset($_GET['done_id'])) {
    $cmdId = (int)$_GET['done_id'];
    
    // 1. Find the command details
    $stmt = $pdo->prepare("SELECT * FROM device_commands WHERE id = ?");
    $stmt->execute([$cmdId]);
    $cmd = $stmt->fetch();
    
    if ($cmd && $cmd['command'] === 'DELETE') {
        $fingerId = (int)$cmd['data'];
        
        // 2. NOW actually delete from employees table
        // Use fingerprint_id to delete (more reliable than department match)
        $del = $pdo->prepare("DELETE FROM employees WHERE fingerprint_id = ?");
        $del->execute([$fingerId]);
        
        // 3. Remove command from queue
        $pdo->prepare("DELETE FROM device_commands WHERE id = ?")->execute([$cmdId]);
        
        echo json_encode(['status' => 'ok', 'message' => 'Employee deleted from database']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Command not found']);
    }
    exit();
}

// --- PART 2: ESP32 POLLS FOR COMMANDS ---
$dept = isset($_GET['dept']) ? $_GET['dept'] : '';

if (empty($dept)) {
    echo json_encode(['has_cmd' => false, 'error' => 'No department specified']);
    exit();
}

// Get oldest pending command for this department (FIFO)
$stmt = $pdo->prepare("SELECT * FROM device_commands WHERE device_dept = ? AND status = 'pending' ORDER BY id ASC LIMIT 1");
$stmt->execute([$dept]);
$row = $stmt->fetch();

if ($row) {
    // Return command to ESP32
    echo json_encode([
        'has_cmd' => true,
        'cmd_id'  => (int)$row['id'],
        'type'    => $row['command'], // 'DELETE'
        'fid'     => (int)$row['data'] // Fingerprint ID to delete
    ]);
} else {
    echo json_encode(['has_cmd' => false]);
}
?>