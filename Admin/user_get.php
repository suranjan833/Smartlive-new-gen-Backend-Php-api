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

$rawId = trim((string) ($_GET['id'] ?? ''));
if ($rawId === '') {
    json_error('Valid user id is required (?id=)', 422);
}

if (is_numeric($rawId)) {
    $stmt = $conn->prepare(
        'SELECT id, user_id, name, email, phone, country_code, country, avatar, bio, vip, level, xp, status, created_at, updated_at
         FROM users WHERE id = ? LIMIT 1'
    );
    $intId = (int) $rawId;
    $stmt->bind_param('i', $intId);
} else {
    $stmt = $conn->prepare(
        'SELECT id, user_id, name, email, phone, country_code, country, avatar, bio, vip, level, xp, status, created_at, updated_at
         FROM users WHERE user_id = ? LIMIT 1'
    );
    $stmt->bind_param('s', $rawId);
}
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    json_error('User not found', 404);
}


json_success(['user' => $user], 'User loaded');
