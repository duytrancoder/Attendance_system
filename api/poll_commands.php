<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
$pdo = db();

header('Content-Type: application/json');

// --- PART 1: ESP32 REPORTS COMPLETION (ACTUAL DELETE) ---
if (isset($_GET['done_id'])) {
    $cmdId = (int)$_GET['done_id'];
    
    try {
        // 1. Find the command details
        $stmt = $pdo->prepare("SELECT * FROM device_commands WHERE id = ?");
        $stmt->execute([$cmdId]);
        $cmd = $stmt->fetch();
        
        if (!$cmd) {
            error_log("Command not found: $cmdId");
            echo json_encode(['status' => 'error', 'message' => 'Command not found']);
            exit();
        }
        
        if ($cmd['command'] === 'DELETE') {
            $fingerId = (int)$cmd['data'];
            
            error_log("ESP32 confirmed deletion of fingerprint_id: $fingerId");
            
            // 2. NOW actually delete from employees table (HARD DELETE)
            // This employee should already be soft-deleted (deleted_at IS NOT NULL)
            $del = $pdo->prepare("DELETE FROM employees WHERE fingerprint_id = ?");
            $del->execute([$fingerId]);
            
            $deletedCount = $del->rowCount();
            
            if ($deletedCount > 0) {
                error_log("Successfully hard-deleted employee with fingerprint_id: $fingerId from database");
            } else {
                error_log("WARNING: Employee with fingerprint_id $fingerId not found (may have been manually deleted)");
            }
            
            // 3. Remove command from queue
            $pdo->prepare("DELETE FROM device_commands WHERE id = ?")->execute([$cmdId]);
            
            echo json_encode([
                'status' => 'ok', 
                'message' => 'Employee deleted from database',
                'fingerprint_id' => $fingerId,
                'deleted_count' => $deletedCount
            ]);
        } else {
            error_log("Unknown command type: " . $cmd['command']);
            echo json_encode(['status' => 'error', 'message' => 'Unknown command type']);
        }
    } catch (Exception $e) {
        error_log("Error in done_id handler: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit();
}

// --- PART 2: ESP32 POLLS FOR COMMANDS ---
$dept = isset($_GET['dept']) ? $_GET['dept'] : '';

if (empty($dept)) {
    echo json_encode(['has_cmd' => false, 'error' => 'No department specified']);
    exit();
}

try {
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
        
        error_log("Sent command to ESP32: device=$dept, cmd_id={$row['id']}, type={$row['command']}, fid={$row['data']}");
    } else {
        echo json_encode(['has_cmd' => false]);
    }
    
    // Periodic cleanup: Remove old completed commands (1% chance per request)
    if (rand(1, 100) == 1) {
        $cleanupStmt = $pdo->prepare("DELETE FROM device_commands WHERE status = 'completed' AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $cleanupStmt->execute();
        $cleaned = $cleanupStmt->rowCount();
        if ($cleaned > 0) {
            error_log("Cleaned up $cleaned old completed commands");
        }
    }
    
    // Cleanup pending commands older than 24 hours (device might be offline)
    if (rand(1, 50) == 1) {
        $cleanupOld = $pdo->prepare("DELETE FROM device_commands WHERE status = 'pending' AND created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");
        $cleanupOld->execute();
        $cleanedOld = $cleanupOld->rowCount();
        if ($cleanedOld > 0) {
            error_log("Cleaned up $cleanedOld old pending commands (device offline)");
        }
    }
    
} catch (Exception $e) {
    error_log("Error in poll_commands: " . $e->getMessage());
    echo json_encode(['has_cmd' => false, 'error' => 'Database error']);
}
?>