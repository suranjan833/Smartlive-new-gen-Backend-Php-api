<?php

/**
 * POST /App/google_auth.php
 * Handles both "Sign in with Google" and "Sign up with Google" seamlessly.
 *
 * Payload:
 * {
 *   "google_id": "109832019283019283019",   // required (or id_token)
 *   "email": "user@gmail.com",               // required
 *   "name": "Alex Kumar",                    // optional (retrieved from Google)
 *   "avatar": "https://lh3.googleusercontent.com/a/...", // optional (Google photo URL)
 *   "country_code": "IN",                    // optional
 *   "country": "India",                      // optional
 *   "id_token": "..."                        // optional Google ID token
 * }
 */

require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/validation.php';
require_once __DIR__ . '/../utils/wallet.php';
require_once __DIR__ . '/../utils/auth.php';
require_once __DIR__ . '/../utils/sequence.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed, use POST', 405);
}

$googleId    = trim((string) (field('google_id') ?? field('googleId') ?? field('sub') ?? ''));
$email       = strtolower(trim((string) field('email', '')));
$name        = trim((string) (field('name') ?? field('displayName') ?? ''));
$avatar      = trim((string) (field('avatar') ?? field('photo_url') ?? field('photoUrl') ?? field('picture') ?? ''));
$countryCode = field('country_code') ?? field('countryCode');
$country     = field('country');
$idToken     = field('id_token') ?? field('idToken');

// If id_token is provided and email or google_id is missing, attempt to extract payload from JWT
if (($email === '' || $googleId === '') && is_string($idToken) && substr_count($idToken, '.') === 2) {
    $parts = explode('.', $idToken);
    $payloadJson = base64_decode(strtr($parts[1], '-_', '+/'));
    if ($payloadJson) {
        $jwtPayload = json_decode($payloadJson, true);
        if (is_array($jwtPayload)) {
            if ($email === '' && !empty($jwtPayload['email'])) {
                $email = strtolower(trim($jwtPayload['email']));
            }
            if ($googleId === '' && !empty($jwtPayload['sub'])) {
                $googleId = trim($jwtPayload['sub']);
            }
            if ($name === '' && !empty($jwtPayload['name'])) {
                $name = trim($jwtPayload['name']);
            }
            if ($avatar === '' && !empty($jwtPayload['picture'])) {
                $avatar = trim($jwtPayload['picture']);
            }
        }
    }
}

if (!is_valid_email($email)) {
    json_error('A valid Google email is required', 422);
}

if ($googleId === '') {
    json_error('google_id is required for Google authentication', 422);
}

$conn = db();

// Check if user exists by google_id OR by email
$stmt = $conn->prepare('SELECT * FROM users WHERE google_id = ? OR email = ? LIMIT 1');
$stmt->bind_param('ss', $googleId, $email);
$stmt->execute();
$existingUser = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existingUser) {
    // ---- EXISTING USER: SIGN IN ----
    if (($existingUser['status'] ?? '') === 'blocked') {
        json_error('Your account has been suspended. Please contact support.', 403);
    }

    $updates = [];
    $updateParams = [];
    $updateTypes = '';

    // If google_id was not linked yet, link it now
    if (empty($existingUser['google_id'])) {
        $updates[] = 'google_id = ?';
        $updateParams[] = $googleId;
        $updateTypes .= 's';
    }

    // If avatar was empty and Google provides one, update avatar
    if (empty($existingUser['avatar']) && $avatar !== '') {
        $updates[] = 'avatar = ?';
        $updateParams[] = $avatar;
        $updateTypes .= 's';
    }

    if (!empty($updates)) {
        $updateParams[] = $existingUser['id'];
        $updateTypes .= 'i';
        $updateSql = 'UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?';
        $upStmt = $conn->prepare($updateSql);
        $upStmt->bind_param($updateTypes, ...$updateParams);
        $upStmt->execute();
        $upStmt->close();
    }

    // Ensure wallet exists
    ensure_wallet($conn, (int) $existingUser['id']);

    // Create session token
    $token = create_session($conn, 'user', (int) $existingUser['id']);

    // Refresh user record
    $stmt = $conn->prepare('SELECT u.*, w.coins FROM users u LEFT JOIN wallets w ON w.user_id = u.id WHERE u.id = ? LIMIT 1');
    $stmt->bind_param('i', $existingUser['id']);
    $stmt->execute();
    $freshUser = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    json_success([
        'token'       => $token,
        'is_new_user' => false,
        'user'        => public_account($freshUser),
    ], 'Login successful with Google');
} else {
    // ---- NEW USER: SIGN UP ----
    // 1. Generate formatted sequential user ID (e.g. smart-lv-000001)
    $userCustomId = generate_user_id($conn);

    // 2. Generate secure random password hash
    $randomHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

    // 3. Name fallback
    if ($name === '') {
        $parts = explode('@', $email);
        $name = ucfirst($parts[0]);
    }
    $name = mb_substr($name, 0, 60);

    $cleanAvatar = $avatar !== '' ? $avatar : null;
    $countryCode = $countryCode !== null && $countryCode !== '' ? trim((string) $countryCode) : null;
    $country     = $country !== null && $country !== '' ? trim((string) $country) : null;

    $stmt = $conn->prepare(
        'INSERT INTO users (user_id, name, email, google_id, password_hash, avatar, country_code, country, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'active\')'
    );
    $stmt->bind_param('ssssssss', $userCustomId, $name, $email, $googleId, $randomHash, $cleanAvatar, $countryCode, $country);
    $stmt->execute();
    $newUserId = (int) $conn->insert_id;
    $stmt->close();

    // Initialize wallet with 0 coins
    ensure_wallet($conn, $newUserId);

    // Create session token
    $token = create_session($conn, 'user', $newUserId);

    // Fetch complete user record
    $stmt = $conn->prepare('SELECT u.*, w.coins FROM users u LEFT JOIN wallets w ON w.user_id = u.id WHERE u.id = ? LIMIT 1');
    $stmt->bind_param('i', $newUserId);
    $stmt->execute();
    $newUser = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    json_success([
        'token'       => $token,
        'is_new_user' => true,
        'user'        => public_account($newUser),
    ], 'Signup successful with Google');
}

