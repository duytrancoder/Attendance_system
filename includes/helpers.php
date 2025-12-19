<?php

function json_response($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function read_json_body(): array
{
    $input = file_get_contents('php://input');
    $decoded = json_decode($input, true);
    return is_array($decoded) ? $decoded : [];
}

function sanitize_string(?string $value): string
{
    return trim($value ?? '');
}

function require_fields(array $payload, array $required): void
{
    foreach ($required as $field) {
        if (!isset($payload[$field]) || $payload[$field] === '') {
            json_response(['error' => "Missing field: {$field}"], 422);
        }
    }
}






