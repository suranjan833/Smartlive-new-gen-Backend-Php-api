<?php

/**
 * PUT|PATCH|POST /Admin/user_update.php?id=5   (Authorization: Bearer <admin token>)
 * Full or partial update of any user field.
 * Body (any subset): { "name", "email", "password", "phone", "country",
 *                      "avatar", "bio", "status": active|blocked, "vip": bool, "level": int }
 * A POST with "user_id" in the body is also accepted (legacy clients).
 */

require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/validation.php';
require_once __DIR__ . '/../utils/auth.php';

if (!in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'PATCH', 'POST'], true)) {
    json_error('Method not allowed, use PUT, PATCH or POST', 405);
}

$conn = db();
$account = require_admin($conn);
require_permission($account, 'users', 'edit');

// Legacy POST style: /Admin/user_update.php with user_id in the body
$userId = (int) ($_GET['id'] ?? field('user_id', 0));
if ($userId <= 0) {
    json_error('Valid user id is required (?id= or user_id)', 422);
}

$stmt = $conn->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    json_error('User not found', 404);
}

$sets  = [];
$types = '';
$vals  = [];

// name
if (field('name') !== null) {
    $name = (string) field('name');
    if (!is_valid_name($name)) {
        json_error('name must be 2-60 characters', 422);
    }
    $sets[] = 'name = ?';
    $types .= 's';
    $vals[] = $name;
}

// email (unique)
if (field('email') !== null) {
    $email = strtolower((string) field('email'));
    if (!is_valid_email($email)) {
        json_error('A valid email is required', 422);
    }
    $stmt = $conn->prepare('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
    $stmt->bind_param('si', $email, $userId);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        json_error('Email is already in use', 422);
    }
    $stmt->close();

    $sets[] = 'email = ?';
    $types .= 's';
    $vals[] = $email;
}

// password
if (field('password') !== null) {
    $password = (string) field('password');
    if (!is_valid_password($password)) {
        json_error('password must be at least 6 characters', 422);
    }
    $hash   = password_hash($password, PASSWORD_DEFAULT);
    $sets[] = 'password_hash = ?';
    $types .= 's';
    $vals[] = $hash;
}

// simple string columns
foreach (['phone', 'country', 'avatar', 'bio'] as $col) {
    if (field($col) !== null) {
        $sets[] = "{$col} = ?";
        $types .= 's';
        $vals[] = (string) field($col);
    }
}

// status (blocked also kills the user's sessions)
if (field('status') !== null) {
    $status = strtolower((string) field('status'));
    if ($status !== 'active' && $status !== 'blocked') {
        json_error('status must be "active" or "blocked"', 422);
    }
    $sets[] = 'status = ?';
    $types .= 's';
    $vals[] = $status;
}

// vip flag
if (field('vip') !== null) {
    $vip    = field('vip');
    $vipInt = (is_bool($vip) ? $vip : in_array(strtolower((string) $vip), ['1', 'true', 'yes', 'on'], true)) ? 1 : 0;
    $sets[] = 'vip = ?';
    $types .= 'i';
    $vals[] = $vipInt;
}

// level
if (field('level') !== null) {
    $levelRaw = field('level');
    if (!is_numeric($levelRaw)) {
        json_error('level must be a number', 422);
    }
    $sets[] = 'level = ?';
    $types .= 'i';
    $vals[] = max(1, (int) $levelRaw);
}

if (empty($sets)) {
    json_error('No fields to update', 422);
}

$sql  = 'UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?';
$stmt = $conn->prepare($sql);
$vals[] = $userId;
$types .= 'i';
$stmt->bind_param($types, ...$vals);
$stmt->execute();
$stmt->close();

// Blocked users lose all their sessions immediately
if ((string) field('status') === 'blocked') {
    $stmt = $conn->prepare("DELETE FROM sessions WHERE user_type = 'user' AND user_id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();
}

$stmt = $conn->prepare(
    'SELECT id, name, email, phone, country, avatar, bio, vip, level, status, created_at, updated_at
     FROM users WHERE id = ? LIMIT 1'
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$updated = $stmt->get_result()->fetch_assoc();
$stmt->close();

json_success(['user' => $updated], 'User updated');
