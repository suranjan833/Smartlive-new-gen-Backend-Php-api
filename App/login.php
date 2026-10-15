<?php

/**
 * POST /App/login.php
 * Body: { "email": "...", "password": "..." }
 * Returns: { token, user }
 */

require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/validation.php';
require_once __DIR__ . '/../utils/auth.php';
require_once __DIR__ . '/../utils/sequence.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed, use POST', 405);
}

// Accepts email or custom user_id (e.g. smart-lv-000001) in "email" or "user_id" field
$loginInput = trim((string) (field('user_id') ?? field('email', '')));
$password   = (string) field('password', '');

if ($loginInput === '' || $password === '') {
    json_error('Email or User ID and password are required', 422);
}

$conn = db();

$stmt = $conn->prepare('SELECT * FROM users WHERE email = ? OR user_id = ? LIMIT 1');
$stmt->bind_param('ss', $loginInput, $loginInput);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user || !password_verify($password, $user['password_hash'])) {
    json_error('Invalid credentials', 401);
}
if ($user['status'] === 'blocked') {
    json_error('Your account has been blocked', 403);
}

// Ensure the user has an auto-increment custom ID assigned
ensure_user_has_custom_id($conn, $user);

$token = create_session($conn, 'user', (int) $user['id']);

json_success([
    'token'   => $token,
    'user_id' => $user['user_id'],
    'user'    => public_account($user),
], 'Login successful');

