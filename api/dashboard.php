<?php

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$pdo = db();

// Today's date for attendance snapshot.
$today = (new DateTime('today'))->format('Y-m-d');

// Totals
$totalEmployees = (int) $pdo->query('SELECT COUNT(*) FROM employees')->fetchColumn();

$stmtPresent = $pdo->prepare('SELECT COUNT(DISTINCT fingerprint_id) FROM attendance WHERE date = :d');
$stmtPresent->execute(['d' => $today]);
$present = (int) $stmtPresent->fetchColumn();

$stmtLate = $pdo->prepare(
    'SELECT COUNT(a.id)
    FROM attendance a
    JOIN shifts s ON s.id = a.shift_id
    WHERE a.date = :d AND a.check_in IS NOT NULL AND a.check_in > s.start_time'
);
$stmtLate->execute(['d' => $today]);
$late = (int) $stmtLate->fetchColumn();

$absent = max(0, $totalEmployees - $present);

$stmt = $pdo->prepare(
    'SELECT a.id,
            e.full_name,
            e.department,
            a.date,
            a.check_in,
            a.check_out,
            a.status,
            s.shift_name
    FROM attendance a
    JOIN employees e ON e.fingerprint_id = a.fingerprint_id
    LEFT JOIN shifts s ON s.id = a.shift_id
    WHERE a.date = :d
    ORDER BY a.check_in ASC'
);
$stmt->execute(['d' => $today]);
$todayLogs = $stmt->fetchAll();

json_response([
    'cards' => [
        'totalEmployees' => $totalEmployees,
        'present' => $present,
        'late' => $late,
        'absent' => $absent,
    ],
    'todayLogs' => $todayLogs,
]);




