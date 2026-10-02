<?php

/**
 * PATCH|PUT|POST /Admin/setting_update.php   (Authorization: Bearer <admin token>)
 * Body: { "key": "maintenanceMode", "value": true }
 * Upserts one setting (api.md PATCH /settings/:key maps to key= in the body,
 * or ?key=maintenanceMode in the query string).
 */

require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/validation.php';
require_once __DIR__ . '/../utils/auth.php';

if (!in_array($_SERVER['REQUEST_METHOD'], ['PATCH', 'PUT', 'POST'], true)) {
    json_error('Method not allowed, use PATCH, PUT or POST', 405);
}

$conn = db();
$account = require_admin($conn);
require_permission($account, 'settings', 'edit');

$key   = trim((string) ($_GET['key'] ?? field('key', '')));
$value = field('value', null);

if ($key === '' || $value === null) {
    json_error('key and value are required', 422);
}

if (is_bool($value)) {
    $value = $value ? '1' : '0';
} elseif (is_array($value)) {
    $value = json_encode($value, JSON_UNESCAPED_UNICODE);
} else {
    $value = (string) $value;
}

$stmt = $conn->prepare(
    'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
     ON DUPLICATE KEY UPDATE setting_value = VALUES (setting_value)'
);
$stmt->bind_param('ss', $key, $value);
$stmt->execute();
$stmt->close();

json_success(['key' => $key, 'value' => $value], 'Setting saved');
