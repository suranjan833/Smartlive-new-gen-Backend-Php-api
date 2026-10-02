<?php

/**
 * POST /Admin/user_delete.php   (Authorization: Bearer <admin token>)
 * Body: { "user_id": 5 }
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
$account = require_admin($conn);
require_permission($account, 'users', 'delete');

$userId = (int) field('user_id', 0);
if ($userId <= 0) {
    json_error('user_id is required', 422);
}

$stmt = $conn->prepare('SELECT id, name, email FROM users WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    json_error('User not found', 404);
}

// Permanently remove the user (wallet, ledger, streams, recharges cascade)
$stmt = $conn->prepare('DELETE FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$stmt->close();

$stmt = $conn->prepare("DELETE FROM sessions WHERE user_type = 'user' AND user_id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$stmt->close();

json_success(['user_id' => $userId], 'User deleted successfully');
