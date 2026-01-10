<?php
// TEST SCRIPT: Kiểm tra API delete có hoạt động không
// Gọi: http://localhost/chamcongv2/test_delete.php?id=5

require_once __DIR__ . '/includes/db.php';
$pdo = db();

echo "=== TEST DELETE API ===\n\n";

$fingerprintId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$fingerprintId) {
    die("Usage: test_delete.php?id=FINGERPRINT_ID\n");
}

echo "Testing delete for fingerprint_id = $fingerprintId\n\n";

// Check if employee exists
$stmt = $pdo->prepare("SELECT * FROM employees WHERE fingerprint_id = ?");
$stmt->execute([$fingerprintId]);
$employee = $stmt->fetch();

if ($employee) {
    echo "✅ Employee found:\n";
    echo "   ID: {$employee['id']}\n";
    echo "   Name: {$employee['full_name']}\n";
    echo "   Department: {$employee['department']}\n\n";
} else {
    echo "❌ Employee with fingerprint_id = $fingerprintId NOT FOUND\n";
    die();
}

// Check attendance records
$stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE fingerprint_id = ?");
$stmt->execute([$fingerprintId]);
$attCount = $stmt->fetchColumn();
echo "📊 Attendance records: $attCount\n\n";

// Now test the DELETE
echo "🔥 Calling DELETE API...\n";
$url = "http://localhost/chamcongv2/api/delete.php?id=$fingerprintId";
echo "URL: $url\n\n";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n\n";

// Verify deletion
$stmt = $pdo->prepare("SELECT * FROM employees WHERE fingerprint_id = ?");
$stmt->execute([$fingerprintId]);
$stillExists = $stmt->fetch();

if (!$stillExists) {
    echo "✅ SUCCESS: Employee deleted from database!\n";
} else {
    echo "❌ FAIL: Employee still exists in database!\n";
    print_r($stillExists);
}

// Check attendance
$stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE fingerprint_id = ?");
$stmt->execute([$fingerprintId]);
$attCountAfter = $stmt->fetchColumn();
echo "📊 Attendance records after delete: $attCountAfter\n";

echo "\n=== TEST COMPLETE ===\n";
?>
