<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];
$dataFile = __DIR__ . '/departments.json';

// Helper to read/write JSON
function readDepartments() {
    global $dataFile;
    if (!file_exists($dataFile)) return [];
    $content = file_get_contents($dataFile);
    return json_decode($content, true) ?: [];
}

function saveDepartments($data) {
    global $dataFile;
    file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

switch ($method) {
    case 'GET':
        $depts = readDepartments();
        
        // Get employee counts for each department from DB
        // Use a single query for efficiency
        $stmt = $pdo->query("SELECT department, COUNT(*) as count FROM employees GROUP BY department");
        $counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // ['IT' => 5, 'Sales' => 2]

        // Merge counts
        foreach ($depts as &$d) {
            $d['employee_count'] = isset($counts[$d['name']]) ? (int)$counts[$d['name']] : 0;
        }
        unset($d); // break reference

        json_response($depts);
        break;

    case 'POST':
        $payload = $_POST ?: read_json_body();
        require_fields($payload, ['name', 'device_code']);

        $depts = readDepartments();
        $name = sanitize_string($payload['name']);
        
        // Check for duplicates
        foreach ($depts as $d) {
            if (strcasecmp($d['name'], $name) === 0) {
                json_response(['error' => 'Tên phòng ban đã tồn tại.'], 409);
            }
        }

        $newDept = [
            'id' => uniqid(),
            'name' => $name,
            'device_code' => sanitize_string($payload['device_code'])
        ];

        $depts[] = $newDept;
        saveDepartments($depts);

        json_response(['message' => 'Đã thêm phòng ban.', 'data' => $newDept]);
        break;

    case 'PUT':
        $payload = read_json_body();
        require_fields($payload, ['id', 'name', 'device_code']);

        $depts = readDepartments();
        $id = $payload['id'];
        $found = false;

        foreach ($depts as &$d) {
            if ($d['id'] === $id) {
                $d['name'] = sanitize_string($payload['name']);
                $d['device_code'] = sanitize_string($payload['device_code']);
                $found = true;
                break;
            }
        }

        if (!$found) {
            json_response(['error' => 'Không tìm thấy phòng ban.'], 404);
        }

        saveDepartments($depts);
        json_response(['message' => 'Đã cập nhật phòng ban.']);
        break;

    case 'DELETE':
        $payload = read_json_body_or_query(); 
        // read_json_body_or_query isn't standard in helpers, let's use logic compatible with existing code
        // Existing delete.php uses query params or json body? 
        // employees.php uses `parse_str(file_get_contents('php://input'), $payload);` for DELETE.
        // Let's stick to reading body for DELETE as is common in this project's style or check query manually.
        
        $payload = [];
        if (!empty($_GET['id'])) {
            $payload['id'] = $_GET['id'];
        } else {
            $input = file_get_contents('php://input');
            $json = json_decode($input, true);
            if (is_array($json)) {
                $payload = $json;
            } else {
                 parse_str($input, $payload);
            }
        }

        if (empty($payload['id'])) {
            json_response(['error' => 'Missing id'], 400);
        }

        $id = $payload['id'];
        $depts = readDepartments();
        
        // Find dept name to check constraints
        $deptName = null;
        $idxToRemove = -1;
        foreach ($depts as $i => $d) {
            if ($d['id'] === $id) {
                $deptName = $d['name'];
                $idxToRemove = $i;
                break;
            }
        }

        if ($idxToRemove === -1) {
            json_response(['error' => 'Không tìm thấy phòng ban.'], 404);
        }

        // Check if employees exist in this department
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE department = :dept");
        $stmt->execute(['dept' => $deptName]);
        $count = $stmt->fetchColumn();

        if ($count > 0) {
            json_response(['error' => "Không thể xóa. Có $count nhân viên trong phòng ban này."], 409);
        }

        array_splice($depts, $idxToRemove, 1);
        saveDepartments($depts);

        json_response(['message' => 'Đã xóa phòng ban.']);
        break;

    default:
        json_response(['error' => 'Method not allowed'], 405);
}
