<?php

/**
 * POST /Admin/sub_admin_login.php
 * Body: { "email": "...", "password": "..." }
 *
 * Logs a sub-admin in and returns their token plus the EFFECTIVE permission
 * list (role permissions merged with their own), e.g.
 *   { "token": "...", "sub_admin": {...}, "permissions": ["users.view", "hosts.view"] }
 *
 * The token works on every /Admin/* endpoint, but each endpoint checks the
 * matching <module>.view / .edit / .delete permission first.
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

$stmt = $conn->prepare('SELECT * FROM sub_admins WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$subAdmin = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$subAdmin || !password_verify($password, $subAdmin['password_hash'])) {
    json_error('Invalid email or password', 401);
}
if (($subAdmin['status'] ?? '') === 'blocked') {
    json_error('This sub-admin account has been blocked', 403);
}

$token = create_session($conn, 'sub_admin', (int) $subAdmin['id']);

$permissions = effective_permissions($conn, $subAdmin);

unset($subAdmin['password_hash']);
$subAdmin['permissions'] = is_string($subAdmin['permissions'])
    ? (json_decode($subAdmin['permissions'], true) ?? [])
    : ($subAdmin['permissions'] ?? []);

json_success([
    'token'       => $token,
    'sub_admin'   => $subAdmin,
    'permissions' => $permissions,
], 'Sub-admin login successful');
