<?php

/**
 * POST /App/change_password.php   (Authorization: Bearer <token>)
 * Body: { "old_password": "...", "new_password": "..." }
 */

require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/validation.php';
require_once __DIR__ . '/../utils/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed, use POST', 405);
}

$conn = db();
$user = require_auth($conn, 'user');

$oldPassword = (string) field('old_password', '');
$newPassword = (string) field('new_password', '');

if ($oldPassword === '' || $newPassword === '') {
    json_error('old_password and new_password are required', 422);
}
if (!is_valid_password($newPassword)) {
    json_error('New password must be at least 6 characters', 422);
}
if (!password_verify($oldPassword, $user['password_hash'] ?? '')) {
    // require_auth unsets the hash, so re-read it
    $stmt = $conn->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || !password_verify($oldPassword, $row['password_hash'])) {
        json_error('Current password is incorrect', 401);
    }
}

$hash = password_hash($newPassword, PASSWORD_DEFAULT);
$stmt = $conn->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
$stmt->bind_param('si', $hash, $user['id']);
$stmt->execute();
$stmt->close();

// Security: force re-login everywhere
$stmt = $conn->prepare("DELETE FROM sessions WHERE user_type = 'user' AND user_id = ?");
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$stmt->close();

json_success(null, 'Password changed successfully, please login again');
