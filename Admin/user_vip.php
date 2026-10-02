<?php

/**
 * POST /Admin/user_vip.php   (Authorization: Bearer <admin token>)
 * Body: { "user_id": 5, "vip": true, "days": 30 }   (days ignored when vip=false)
 * Grants or revokes VIP on a user account.
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
require_permission($account, 'users', 'edit');

$userId = (int) field('user_id', 0);
$vip    = field('vip', null);
$days   = max(1, (int) field('days', 30));

if ($userId <= 0) {
    json_error('user_id is required', 422);
}
if ($vip === null) {
    json_error('vip must be true or false', 422);
}
$vip = is_bool($vip) ? $vip : in_array(strtolower((string) $vip), ['1', 'true', 'yes', 'on'], true);

$stmt = $conn->prepare('SELECT id, name FROM users WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    json_error('User not found', 404);
}

if ($vip) {
    $stmt = $conn->prepare(
        'UPDATE users SET vip = 1,
                vip_expires_at = GREATEST(COALESCE(vip_expires_at, NOW()), DATE_ADD(NOW(), INTERVAL ? DAY))
         WHERE id = ?'
    );
    $stmt->bind_param('ii', $days, $userId);
    $stmt->execute();
    $stmt->close();
    $message = "VIP granted for {$days} days";
} else {
    $stmt = $conn->prepare('UPDATE users SET vip = 0, vip_expires_at = NULL WHERE id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();
    $message = 'VIP revoked';
}

$stmt = $conn->prepare('SELECT id, name, vip, vip_expires_at FROM users WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $userId);
$stmt->execute();
$updated = $stmt->get_result()->fetch_assoc();
$stmt->close();

json_success(['user' => $updated], $message);
