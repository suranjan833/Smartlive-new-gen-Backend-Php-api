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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed, use POST', 405);
}

$email    = strtolower((string) field('email', ''));
$password = (string) field('password', '');

if (!is_valid_email($email) || $password === '') {
    json_error('Email and password are required', 422);
}

$conn = db();

$stmt = $conn->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user || !password_verify($password, $user['password_hash'])) {
    json_error('Invalid email or password', 401);
}
if ($user['status'] === 'blocked') {
    json_error('Your account has been blocked', 403);
}

$token = create_session($conn, 'user', (int) $user['id']);

json_success(['token' => $token, 'user' => public_account($user)], 'Login successful');
