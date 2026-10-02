<?php

/**
 * POST /Admin/user_create.php   (Authorization: Bearer <admin token>)
 * Body: { "name": "...", "email": "...", "password": "...", "phone"?, "country"?,
 *         "status"?: active|blocked, "vip"?: bool, "level"?: int, "coins"?: int }
 * Creates an app user (wallet auto-created; optional starting coins).
 */

require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/validation.php';
require_once __DIR__ . '/../utils/auth.php';
require_once __DIR__ . '/../utils/wallet.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed, use POST', 405);
}

$conn = db();
$account = require_admin($conn);
require_permission($account, 'users', 'edit');

$name     = (string) field('name', '');
$email    = strtolower((string) field('email', ''));
$password = (string) field('password', '');
$phone    = (string) field('phone', '');
$country  = (string) field('country', '');
$status   = strtolower((string) field('status', 'active'));
$vip      = field('vip', false);
$vip      = is_bool($vip) ? $vip : in_array(strtolower((string) $vip), ['1', 'true', 'yes', 'on'], true);
$level    = max(1, (int) field('level', 1));
$coins    = max(0, (int) field('coins', 0));

if (!is_valid_name($name)) {
    json_error('name must be 2-60 characters', 422);
}
if (!is_valid_email($email)) {
    json_error('A valid email is required', 422);
}
if (!is_valid_password($password)) {
    json_error('password must be at least 6 characters', 422);
}
if ($status !== 'active' && $status !== 'blocked') {
    json_error('status must be "active" or "blocked"', 422);
}

// Unique email
$stmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
if ($stmt->get_result()->fetch_assoc()) {
    json_error('Email is already registered', 422);
}
$stmt->close();

$stmt = $conn->prepare(
    'INSERT INTO users (name, email, password_hash, phone, country, vip, level, status)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
);
$vipInt = $vip ? 1 : 0;
$stmt->bind_param('sssssiss', $name, $email, $password, $phone, $country, $vipInt, $level, $status);
$stmt->execute();
$userId = $stmt->insert_id;
$stmt->close();

// Wallet (with optional starting coins)
$conn->begin_transaction();
try {
    if ($coins > 0) {
        wallet_adjust($conn, $userId, $coins, 'admin_credit', 'Starting balance set by admin');
    } else {
        ensure_wallet($conn, $userId);
    }
    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    json_error($e->getMessage(), 400);
}

$stmt = $conn->prepare(
    'SELECT id, name, email, phone, country, avatar, bio, vip, level, status, created_at
     FROM users WHERE id = ? LIMIT 1'
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

json_success(['user' => $user, 'coins' => $coins], 'User created', 201);
