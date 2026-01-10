<?php
// DEBUG LOG VIEWER
// Xem các request DELETE từ Arduino
// File: debug_delete_log.php

require_once __DIR__ . '/includes/db.php';
$pdo = db();

header('Content-Type: text/plain; charset=utf-8');

echo "=== DEBUG DELETE LOGS ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

// 1. Check PHP error log
echo "1. PHP ERROR LOG:\n";
echo "================\n";
$phpLog = 'C:\\xampp\\php\\logs\\php_error_log';
if (file_exists($phpLog)) {
    $lines = file($phpLog);
    $recentLines = array_slice($lines, -50); // Last 50 lines
    foreach ($recentLines as $line) {
        if (stripos($line, 'delete') !== false || stripos($line, 'arduino') !== false) {
            echo $line;
        }
    }
} else {
    echo "⚠️  Log file not found at: $phpLog\n";
    echo "Try: C:\\xampp\\apache\\logs\\error.log\n";
}

echo "\n\n2. APACHE ACCESS LOG (Last DELETE requests):\n";
echo "===========================================\n";
$apacheLog = 'C:\\xampp\\apache\\logs\\access.log';
if (file_exists($apacheLog)) {
    $lines = file($apacheLog);
    $recentLines = array_slice($lines, -100);
    foreach ($recentLines as $line) {
        if (stripos($line, 'delete.php') !== false) {
            echo $line;
        }
    }
} else {
    echo "⚠️  Log file not found\n";
}

echo "\n\n3. DATABASE: device_commands (pending DELETE commands):\n";
echo "======================================================\n";
$stmt = $pdo->query("SELECT * FROM device_commands WHERE status = 'pending' ORDER BY id DESC LIMIT 10");
$commands = $stmt->fetchAll();
if ($commands) {
    foreach ($commands as $cmd) {
        echo "ID: {$cmd['id']} | Dept: {$cmd['device_dept']} | Command: {$cmd['command']} | Data: {$cmd['data']} | Created: {$cmd['created_at']}\n";
    }
} else {
    echo "✅ No pending commands\n";
}

echo "\n\n4. DATABASE: Recent employees:\n";
echo "==============================\n";
$stmt = $pdo->query("SELECT id, fingerprint_id, full_name, department, created_at FROM employees ORDER BY id DESC LIMIT 10");
$employees = $stmt->fetchAll();
foreach ($employees as $emp) {
    echo "ID: {$emp['id']} | FP_ID: {$emp['fingerprint_id']} | Name: {$emp['full_name']} | Dept: {$emp['department']}\n";
}

echo "\n\n5. INSTRUCTIONS TO DEBUG:\n";
echo "=========================\n";
echo "A. Test DELETE API manually:\n";
echo "   http://localhost/chamcongv2/test_delete.php?id=FINGERPRINT_ID\n\n";
echo "B. Check if Arduino is calling the RIGHT URL:\n";
echo "   Arduino should call: GET /api/delete.php?id=FINGERPRINT_ID\n";
echo "   NOT: POST to delete.php with employee database ID\n\n";
echo "C. Enable logging in Arduino code:\n";
echo "   Serial.println(\"Calling: \" + url);\n";
echo "   Serial.println(\"Response: \" + response);\n\n";
echo "D. Check network connectivity:\n";
echo "   Can Arduino ping the server?\n";
echo "   Is the server IP correct in Arduino code?\n\n";

echo "=== END DEBUG ===\n";
?>
