<?php

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $stmt = $pdo->query('SELECT id, shift_name, start_time, end_time FROM shifts ORDER BY id ASC');
        json_response($stmt->fetchAll());
        break;

    case 'POST':
        $payload = $_POST ?: read_json_body();
        require_fields($payload, ['shift_name', 'start_time', 'end_time']);

        $stmt = $pdo->prepare(
            'INSERT INTO shifts (shift_name, start_time, end_time)
             VALUES (:name, :start, :end)'
        );
        $stmt->execute([
            'name' => sanitize_string($payload['shift_name']),
            'start' => $payload['start_time'],
            'end' => $payload['end_time'],
        ]);

        json_response(['message' => 'Đã thêm ca làm việc.']);
        break;

    case 'PUT':
        $payload = read_json_body();
        require_fields($payload, ['id', 'shift_name', 'start_time', 'end_time']);

        $stmt = $pdo->prepare(
            'UPDATE shifts
            SET shift_name = :name,
                start_time = :start,
                end_time = :end
            WHERE id = :id'
        );
        $stmt->execute([
            'name' => sanitize_string($payload['shift_name']),
            'start' => $payload['start_time'],
            'end' => $payload['end_time'],
            'id' => (int) $payload['id'],
        ]);

        json_response(['message' => 'Đã cập nhật ca.']);
        break;

    case 'DELETE':
        // Hỗ trợ payload form-data, x-www-form-urlencoded hoặc JSON
        $payload = $_POST ?: read_json_body();
        if (!$payload) {
            parse_str(file_get_contents('php://input'), $payload);
        }

        require_fields($payload, ['id']);

        $stmt = $pdo->prepare('DELETE FROM shifts WHERE id = :id');
        $stmt->execute(['id' => (int) $payload['id']]);

        json_response(['message' => 'Đã xóa ca.']);
        break;

    default:
        json_response(['error' => 'Method not allowed'], 405);
}




