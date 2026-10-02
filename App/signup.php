<?php

/**
 * POST /App/signup.php
 * Body: { "name": "...", "email": "...", "password": "...", "phone": "..." (optional) }
 */

require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/validation.php';
require_once __DIR__ . '/../utils/wallet.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed, use POST', 405);
}

$name     = (string) field('name', '');
$email    = strtolower((string) field('email', ''));
$password = (string) field('password', '');
$phone    = field('phone');

if (!is_valid_name($name)) {
    json_error('Name must be between 2 and 60 characters', 422);
}
if (!is_valid_email($email)) {
    json_error('Please provide a valid email address', 422);
}
if (!is_valid_password($password)) {
    json_error('Password must be at least 6 characters', 422);
}

$conn = db();

// Email must be unique
$stmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
if ($stmt->get_result()->fetch_assoc()) {
    json_error('This email is already registered', 409);
}
$stmt->close();

$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare('INSERT INTO users (name, email, password_hash, phone) VALUES (?, ?, ?, ?)');
$stmt->bind_param('ssss', $name, $email, $hash, $phone);
$stmt->execute();
$userId = $conn->insert_id;
$stmt->close();

// Every user gets a wallet (0 coins) on signup
ensure_wallet($conn, $userId);

json_success(['id' => $userId, 'name' => $name, 'email' => $email], 'Account created successfully', 201);
