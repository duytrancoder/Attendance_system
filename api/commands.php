<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
$pdo = db();

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

// === CONFIRMATION: Arduino báo đã xong (POST) ===
if ($method === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (isset($data['command_id'])) {
        $cmdId = (int)$data['command_id'];
        
        // Get command details
        $stmt = $pdo->prepare("SELECT * FROM device_commands WHERE id = ?");
        $stmt->execute([$cmdId]);
        $cmd = $stmt->fetch();
        
        if ($cmd && $cmd['command'] === 'DELETE') {
            $fingerId = (int)$cmd['data'];
            
            // NOW delete from employees table
            $del = $pdo->prepare("DELETE FROM employees WHERE fingerprint_id = ?");
            $del->execute([$fingerId]);
            
            // Remove command from queue
            $pdo->prepare("DELETE FROM device_commands WHERE id = ?")->execute([$cmdId]);
            
            echo json_encode(['status' => 'OK', 'message' => 'Deleted from database']);
        } else {
            echo json_encode(['status' => 'ERROR', 'message' => 'Command not found']);
        }
    } else {
        echo json_encode(['status' => 'ERROR', 'message' => 'Missing command_id']);
    }
    exit;
}

// === POLLING: Arduino hỏi có lệnh không (GET) ===
if ($method === 'GET') {
    // ESP32 should send ?dept=IT but we'll handle both with and without
    $dept = isset($_GET['dept']) ? $_GET['dept'] : 'IT'; // Default to IT for backward compat
    
    // Get oldest pending command for this device
    $stmt = $pdo->prepare("SELECT * FROM device_commands WHERE device_dept = ? AND status = 'pending' ORDER BY id ASC LIMIT 1");
    $stmt->execute([$dept]);
    $cmd = $stmt->fetch();
    
    if ($cmd) {
        // Return in format Arduino expects
        echo json_encode([
            'status' => 'HAS_COMMAND',
            'command' => 'DELETE_FINGER',
            'data' => (string)$cmd['data'], // Fingerprint ID
            'command_id' => (int)$cmd['id']
        ]);
    } else {
        echo json_encode([
            'status' => 'NO_COMMAND'
        ]);
    }
    exit;
}

echo json_encode(['status' => 'ERROR', 'message' => 'Method not allowed']);
?>
