<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$pdo = db();
header('Content-Type: application/json');

// Get filter parameters
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); // Default: First day of current month
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d'); // Default: Today
$department = isset($_GET['department']) ? sanitize_string($_GET['department']) : null;
$name = isset($_GET['name']) ? '%' . sanitize_string($_GET['name']) . '%' : null;
$topType = isset($_GET['top']) ? $_GET['top'] : null; // 'late' or 'early'

// === SPECIAL: TOP LISTS ===
if ($topType === 'late') {
    // Get employees with most late arrivals
    $query = "SELECT e.id, e.full_name, e.department, 
                     COUNT(*) as late_count
              FROM attendance a
              JOIN employees e ON e.fingerprint_id = a.fingerprint_id
              WHERE a.date BETWEEN :start AND :end
                AND a.status LIKE '%muộn%'";
    
    $params = ['start' => $startDate, 'end' => $endDate];
    
    if ($department) {
        $query .= " AND e.department = :dept";
        $params['dept'] = $department;
    }
    
    $query .= " GROUP BY e.id ORDER BY late_count DESC LIMIT 10";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    json_response($stmt->fetchAll());
    exit;
}

if ($topType === 'early') {
    // Get employees with most early leaves
    $query = "SELECT e.id, e.full_name, e.department, 
                     COUNT(*) as early_count
              FROM attendance a
              JOIN employees e ON e.fingerprint_id = a.fingerprint_id
              WHERE a.date BETWEEN :start AND :end
                AND a.status LIKE '%sớm%'";
    
    $params = ['start' => $startDate, 'end' => $endDate];
    
    if ($department) {
        $query .= " AND e.department = :dept";
        $params['dept'] = $department;
    }
    
    $query .= " GROUP BY e.id ORDER BY early_count DESC LIMIT 10";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    json_response($stmt->fetchAll());
    exit;
}

// === OVERVIEW STATS ===
$overview = [];

// Total shifts created
$query = "SELECT COUNT(*) FROM attendance WHERE date BETWEEN :start AND :end";
$params = ['start' => $startDate, 'end' => $endDate];
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$overview['total_shifts'] = (int)$stmt->fetchColumn();

// Total late arrivals
$query = "SELECT COUNT(*) FROM attendance WHERE date BETWEEN :start AND :end AND status LIKE '%muộn%'";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$overview['total_late'] = (int)$stmt->fetchColumn();

// Total early leaves
$query = "SELECT COUNT(*) FROM attendance WHERE date BETWEEN :start AND :end AND status LIKE '%sớm%'";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$overview['total_early'] = (int)$stmt->fetchColumn();

// Most punctual employee (no late, no early leave)
$query = "SELECT e.full_name, e.department, COUNT(*) as perfect_days
          FROM attendance a
          JOIN employees e ON e.fingerprint_id = a.fingerprint_id
          WHERE a.date BETWEEN :start AND :end
            AND a.status NOT LIKE '%muộn%'
            AND a.status NOT LIKE '%sớm%'
            AND a.check_out IS NOT NULL
          GROUP BY e.id
          ORDER BY perfect_days DESC
          LIMIT 1";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$punctual = $stmt->fetch();
$overview['most_punctual'] = $punctual ? $punctual['full_name'] . ' (' . $punctual['perfect_days'] . ' ngày)' : 'Chưa có';

// === DETAILED EMPLOYEE SUMMARY ===
$query = "SELECT 
            e.id,
            e.fingerprint_id as employee_code,
            e.full_name,
            e.department,
            COUNT(DISTINCT a.date) as total_days,
            SUM(CASE WHEN a.status LIKE '%muộn%' THEN 1 ELSE 0 END) as late_count,
            SUM(CASE WHEN a.status LIKE '%sớm%' THEN 1 ELSE 0 END) as early_count,
            SUM(TIMESTAMPDIFF(MINUTE, 
                CONCAT(a.date, ' ', a.check_in), 
                CONCAT(a.date, ' ', COALESCE(a.check_out, a.check_in))
            )) as total_minutes,
            SUM(CASE 
                WHEN a.status LIKE '%muộn%' THEN 
                    TIMESTAMPDIFF(MINUTE, 
                        CONCAT(a.date, ' ', s.start_time), 
                        CONCAT(a.date, ' ', a.check_in)
                    )
                ELSE 0 
            END) as late_minutes
          FROM attendance a
          INNER JOIN employees e ON a.fingerprint_id = e.fingerprint_id
          LEFT JOIN shifts s ON s.id = a.shift_id
          WHERE a.date BETWEEN :start AND :end";

$params = ['start' => $startDate, 'end' => $endDate];

if ($department) {
    $query .= " AND e.department = :dept";
    $params['dept'] = $department;
}

if ($name) {
    $query .= " AND e.full_name LIKE :name";
    $params['name'] = $name;
}

$query .= " GROUP BY e.id ORDER BY e.full_name";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$employees = $stmt->fetchAll();

// Format the data
$summary = array_map(function($emp) {
    $totalHours = round($emp['total_minutes'] / 60, 1);
    return [
        'id' => $emp['id'],
        'employee_code' => $emp['employee_code'],
        'full_name' => $emp['full_name'],
        'department' => $emp['department'],
        'total_days' => $emp['total_days'],
        'total_hours' => $totalHours,
        'late_count' => $emp['late_count'],
        'late_minutes' => $emp['late_minutes'] ?? 0,
        'early_count' => $emp['early_count'],
        'action' => '' // For user input
    ];
}, $employees);

// Return combined response
json_response([
    'overview' => $overview,
    'summary' => $summary
]);
?>
