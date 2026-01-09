<?php

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Nếu ?pending=1 -> chỉ trả về các bản ghi do ESP32 tạo (department = 'Chờ cập nhật')
        $isPending = isset($_GET['pending']) && $_GET['pending'] === '1';

        if ($isPending) {
            $stmt = $pdo->prepare(
                'SELECT id, fingerprint_id, full_name, department, position, birth_year, created_at
                 FROM employees
                 WHERE department = :dept
                 ORDER BY created_at DESC'
            );
            $stmt->execute(['dept' => 'Chờ cập nhật']);
        } else {
            $deptFilter = isset($_GET['dept']) ? $_GET['dept'] : null;
            if ($deptFilter) {
                $stmt = $pdo->prepare(
                    'SELECT id, fingerprint_id, full_name, department, position, birth_year, created_at
                     FROM employees
                     WHERE department = :dept
                     ORDER BY created_at DESC'
                );
                $stmt->execute(['dept' => $deptFilter]);
            } else {
                $stmt = $pdo->query(
                    'SELECT id, fingerprint_id, full_name, department, position, birth_year, created_at
                     FROM employees
                     ORDER BY created_at DESC'
                );
            }
        }

        json_response($stmt->fetchAll());
        break;

    case 'POST':
        $payload = $_POST ?: read_json_body();
        require_fields($payload, ['fingerprint_id', 'full_name', 'department', 'position']);

        $fingerId = (int) $payload['fingerprint_id'];

        // fingerprint_id is UNIQUE; let DB enforce but return friendly message.
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO employees (fingerprint_id, full_name, department, position, birth_year)
                 VALUES (:fp, :name, :dept, :pos, :birth)'
            );
            $stmt->execute([
                'fp' => $fingerId,
                'name' => sanitize_string($payload['full_name']),
                'dept' => sanitize_string($payload['department']),
                'pos' => sanitize_string($payload['position']),
                'birth' => isset($payload['birth_year']) ? (int) $payload['birth_year'] : null,
            ]);
        } catch (PDOException $e) {
            if ((int) $e->errorInfo[1] === 1062) {
                json_response(['error' => 'Fingerprint ID đã tồn tại.'], 409);
            }
            throw $e;
        }

        json_response(['message' => 'Đã thêm nhân viên mới.']);
        break;

    case 'PUT':
        $payload = read_json_body();
        require_fields($payload, ['id', 'full_name', 'department', 'position']);

        $stmt = $pdo->prepare(
            'UPDATE employees
             SET full_name = :name,
                 department = :dept,
                 position = :pos,
                 birth_year = :birth
             WHERE id = :id'
        );
        $stmt->execute([
            'name' => sanitize_string($payload['full_name']),
            'dept' => sanitize_string($payload['department']),
            'pos' => sanitize_string($payload['position']),
            'birth' => isset($payload['birth_year']) ? (int) $payload['birth_year'] : null,
            'id' => (int) $payload['id'],
        ]);

        json_response(['message' => 'Đã cập nhật thông tin nhân viên.']);
        break;

    case 'DELETE':
        parse_str(file_get_contents('php://input'), $payload);
        require_fields($payload, ['id']);

        $stmt = $pdo->prepare('DELETE FROM employees WHERE id = :id');
        $stmt->execute(['id' => (int) $payload['id']]);

        json_response(['message' => 'Đã xóa nhân viên.']);
        break;

    default:
        json_response(['error' => 'Method not allowed'], 405);
}


