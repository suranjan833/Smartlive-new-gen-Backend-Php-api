<?php

/**
 * PUT|POST /Admin/sub_admin_permissions.php?id=5   (Authorization: Bearer <admin token>)
 * Body: { "permissions": ["users.view", "users.edit", "hosts.view"] }
 * Replaces the sub-admin's whole permission list.
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
require_permission($account, 'sub_admins', 'edit');

$id = (int) ($_GET['id'] ?? field('id', 0));
if ($id <= 0) {
    json_error('Valid id is required (?id=)', 422);
}

$permissions = field('permissions', null);
if (!is_array($permissions)) {
    json_error('permissions must be an array like ["users.view","hosts.view"]', 422);
}

$clean = [];
foreach ($permissions as $permission) {
    if (is_string($permission) && preg_match('/^[a-z0-9_]+(\.[a-z0-9_]+)*$/', trim($permission))) {
        $clean[] = trim($permission);
    }
}

$stmt = $conn->prepare('SELECT id FROM sub_admins WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
if (!$stmt->get_result()->fetch_assoc()) {
    json_error('Sub-admin not found', 404);
}
$stmt->close();

$json = json_encode(array_values(array_unique($clean)), JSON_UNESCAPED_UNICODE);
$stmt = $conn->prepare('UPDATE sub_admins SET permissions = ? WHERE id = ?');
$stmt->bind_param('si', $json, $id);
$stmt->execute();
$stmt->close();

json_success(['id' => $id, 'permissions' => array_values(array_unique($clean))], 'Permissions updated');
