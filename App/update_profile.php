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
require_once __DIR__ . '/../utils/upload.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed, use POST', 405);
}

$conn = db();
$user = require_auth($conn, 'user');

$name        = field('name');
$phone       = field('phone');
$countryCode = field('country_code') ?? field('countryCode');
$country     = field('country');
$bio         = field('bio');
$avatar      = field('avatar') ?? field('image');
$dob         = field('dob') ?? field('birth_date') ?? field('birthdate') ?? field('date_of_birth');
$gender      = field('gender');

// 1. Check if a binary file was uploaded via multipart/form-data
if (!empty($_FILES['avatar']['name'])) {
    $avatar = handle_image_upload($_FILES['avatar'], 'avatars', 'avatar_' . $user['id']);
} elseif (!empty($_FILES['image']['name'])) {
    $avatar = handle_image_upload($_FILES['image'], 'avatars', 'avatar_' . $user['id']);
}
// 2. Check if a Base64 image data URI was provided in body
elseif (is_string($avatar) && preg_match('#^data:image/#i', $avatar)) {
    $avatar = handle_base64_image($avatar, 'avatars', 'avatar_' . $user['id']);
}

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
if ($countryCode !== null) {
    $updates[] = 'country_code = ?';
    $params[]  = trim((string) $countryCode);
    $types    .= 's';
}
if ($country !== null) {
    $updates[] = 'country = ?';
    $params[]  = trim((string) $country);
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
if ($dob !== null && $dob !== '') {
    $dobTimestamp = strtotime((string) $dob);
    if ($dobTimestamp === false) {
        json_error('Invalid date of birth format. Please use YYYY-MM-DD', 422);
    }
    if ($dobTimestamp > time()) {
        json_error('Date of birth cannot be in the future', 422);
    }
    $updates[] = 'dob = ?';
    $params[]  = date('Y-m-d', $dobTimestamp);
    $types    .= 's';
}
if ($gender !== null && $gender !== '') {
    $cleanGender = strtolower(trim((string) $gender));
    if (!in_array($cleanGender, ['male', 'female', 'other'], true)) {
        json_error('Gender must be male, female, or other', 422);
    }
    $updates[] = 'gender = ?';
    $params[]  = $cleanGender;
    $types    .= 's';
}

if (empty($updates)) {
    json_error('Send at least one field to update (name, phone, country_code, country, bio, avatar, dob, gender)', 422);
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
