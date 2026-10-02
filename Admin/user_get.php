<?php

/**
 * GET /Admin/user_get.php?id=5   (Authorization: Bearer <admin token>)
 */

require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed, use GET', 405);
}

$conn = db();
$account = require_admin($conn);
require_permission($account, 'users', 'view');

$userId = (int) ($_GET['id'] ?? 0);
if ($userId <= 0) {
    json_error('Valid user id is required (?id=)', 422);
}

$stmt = $conn->prepare(
    'SELECT id, name, email, phone, avatar, bio, status, created_at, updated_at
     FROM users WHERE id = ? LIMIT 1'
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    json_error('User not found', 404);
}

json_success(['user' => $user], 'User loaded');
