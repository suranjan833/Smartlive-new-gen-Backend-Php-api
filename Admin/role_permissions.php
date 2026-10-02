<?php

/**
 * PUT|POST /Admin/role_permissions.php?id=5   (Authorization: Bearer <admin token>)
 * Body: { "permissions": { "users": {"view": true, "edit": false}, "hosts": {"view": true} } }
 * Replaces the role's whole permission map.
 */

require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/validation.php';
require_once __DIR__ . '/../utils/auth.php';

if (!in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'POST'], true)) {
    json_error('Method not allowed, use PUT or POST', 405);
}

$conn = db();
$account = require_admin($conn);
require_permission($account, 'roles', 'edit');

$id = (int) ($_GET['id'] ?? field('id', 0));
if ($id <= 0) {
    json_error('Valid id is required (?id=)', 422);
}

$permissions = field('permissions', null);
if (!is_array($permissions)) {
    json_error('permissions must be an object like {"users":{"view":true}}', 422);
}

$stmt = $conn->prepare('SELECT id FROM roles WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
if (!$stmt->get_result()->fetch_assoc()) {
    json_error('Role not found', 404);
}
$stmt->close();

$json = json_encode($permissions, JSON_UNESCAPED_UNICODE);
$stmt = $conn->prepare('UPDATE roles SET permissions = ? WHERE id = ?');
$stmt->bind_param('si', $json, $id);
$stmt->execute();
$stmt->close();

json_success(['id' => $id, 'permissions' => $permissions], 'Role permissions updated');
