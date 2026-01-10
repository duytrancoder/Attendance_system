<?php
// Clear delete log
$logFile = __DIR__ . '/delete_requests.log';
if (file_exists($logFile)) {
    unlink($logFile);
    echo json_encode(['status' => 'ok', 'message' => 'Log cleared']);
} else {
    echo json_encode(['status' => 'ok', 'message' => 'No log to clear']);
}
?>
