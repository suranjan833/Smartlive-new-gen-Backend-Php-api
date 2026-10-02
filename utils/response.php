<?php

/**
 * Every API answers with the same JSON envelope:
 * { "success": true|false, "message": "...", "data": {...} }
 */

function json_response(bool $success, $data, string $message, int $code): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data'    => $data,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_success($data = null, string $message = 'Success', int $code = 200): void
{
    json_response(true, $data, $message, $code);
}

function json_error(string $message = 'Something went wrong', int $code = 400, $data = null): void
{
    json_response(false, $data, $message, $code);
}
