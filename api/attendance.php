<?php

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$pdo = db();

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    json_response(['error' => 'Method not allowed'], 405);
}

$name = isset($_GET['name']) ? '%' . sanitize_string($_GET['name']) . '%' : null;
$date = isset($_GET['date']) ? sanitize_string($_GET['date']) : null;
$export = isset($_GET['export']);

$query = [
    'SELECT e.full_name,
            e.department,
            a.date,
            a.check_in,
            a.check_out,
            a.status
    FROM attendance a
    JOIN employees e ON e.fingerprint_id = a.fingerprint_id
    WHERE 1 = 1',
];
$params = [];

if ($name) {
    $query[] = 'AND e.full_name LIKE :name';
    $params['name'] = $name;
}

if ($date) {
    $query[] = 'AND a.date = :date';
    $params['date'] = $date;
}

$query[] = 'ORDER BY a.date DESC, a.check_in DESC';
$sql = implode(' ', $query);

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

if ($export) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=attendance.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Tên', 'Phòng ban', 'Ngày', 'Giờ vào', 'Giờ ra', 'Trạng thái']);
    foreach ($rows as $row) {
        fputcsv($output, [
            $row['full_name'],
            $row['department'],
            $row['date'],
            $row['check_in'],
            $row['check_out'],
            $row['status'],
        ]);
    }
    fclose($output);
    exit;
}

json_response($rows);





