<?php

/**
 * POST /Admin/login.php
 * Body: { "email": "...", "password": "..." }
 * Default seeded admin: admin@livestream.com / Admin@123
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

$stmt = $conn->prepare('SELECT * FROM admins WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$admin || !password_verify($password, $admin['password_hash'])) {
    json_error('Invalid email or password', 401);
}

$token = create_session($conn, 'admin', (int) $admin['id']);

json_success(['token' => $token, 'admin' => public_account($admin)], 'Login successful');
