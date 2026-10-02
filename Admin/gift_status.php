<?php

/**
 * PATCH|POST /Admin/gift_status.php   (Authorization: Bearer <admin token>)
 * Body: { "id": 5, "active": true|false }   (also accepts status=1/0)
 * Toggles a gift active/inactive.
 */

require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/validation.php';
require_once __DIR__ . '/../utils/auth.php';

if (!in_array($_SERVER['REQUEST_METHOD'], ['PATCH', 'POST'], true)) {
    json_error('Method not allowed, use PATCH or POST', 405);
}

$conn = db();
$account = require_admin($conn);
require_permission($account, 'gifts', 'edit');

$id = (int) ($_GET['id'] ?? field('id', 0));
if ($id <= 0) {
    json_error('Valid id is required (?id=)', 422);
}

$raw  = field('active', field('status', null));
if ($raw === null) {
    json_error('active (or status) must be true or false', 422);
}
$active = is_bool($raw) ? ($raw ? 1 : 0) : (in_array(strtolower((string) $raw), ['1', 'true', 'yes', 'on', 'active'], true) ? 1 : 0);

$stmt = $conn->prepare('SELECT id, name FROM gifts WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$gift = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$gift) {
    json_error('Gift not found', 404);
}

$stmt = $conn->prepare('UPDATE gifts SET active = ? WHERE id = ?');
$stmt->bind_param('ii', $active, $id);
$stmt->execute();
$stmt->close();

json_success(['id' => $id, 'name' => $gift['name'], 'active' => (bool) $active], 'Gift status updated');
