<?php

/**
 * POST /App/update_profile.php   (Authorization: Bearer <token>)
 * Body (all optional, at least one required):
 *   { "name": "...", "phone": "...", "bio": "...", "avatar": "url-or-filename" }
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

$name   = field('name');
$phone  = field('phone');
$bio    = field('bio');
$avatar = field('avatar');

$updates = [];
$params  = [];
$types   = '';

if ($name !== null && $name !== '') {
    if (!is_valid_name((string) $name)) {
        json_error('Name must be between 2 and 60 characters', 422);
    }
    $updates[] = 'name = ?';
    $params[]  = $name;
    $types    .= 's';
}
if ($phone !== null) {
    $updates[] = 'phone = ?';
    $params[]  = $phone;
    $types    .= 's';
}
if ($bio !== null) {
    if (mb_strlen((string) $bio) > 255) {
        json_error('Bio must be 255 characters or fewer', 422);
    }
    $updates[] = 'bio = ?';
    $params[]  = $bio;
    $types    .= 's';
}
if ($avatar !== null) {
    $updates[] = 'avatar = ?';
    $params[]  = $avatar;
    $types    .= 's';
}

if (empty($updates)) {
    json_error('Send at least one field to update (name, phone, bio, avatar)', 422);
}

$sql  = 'UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?';
$stmt = $conn->prepare($sql);
$params[] = $user['id'];
$types   .= 'i';
$stmt->bind_param($types, ...$params);
$stmt->execute();
$stmt->close();

// Fresh profile
$stmt = $conn->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$fresh = public_account($stmt->get_result()->fetch_assoc());
$stmt->close();

json_success(['user' => $fresh], 'Profile updated successfully');
