<?php

/**
 * POST /App/upload_image.php   (Authorization: Bearer <token>)
 * Upload an image (avatar, stream cover, etc.)
 * Accepts:
 *   - multipart/form-data with file field 'image' or 'file' or 'avatar'
 *   - OR JSON body with base64 data: { "image": "data:image/png;base64,...", "type": "avatars|covers" }
 * Returns:
 *   { "success": true, "message": "Image uploaded", "data": { "url": "https://..." } }
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

$type = field('type') ?? 'avatars';
$allowedTypes = ['avatars', 'covers', 'banners', 'shorts'];
if (!in_array($type, $allowedTypes, true)) {
    $type = 'avatars';
}

$url = null;

// Check $_FILES
if (!empty($_FILES['image']['name'])) {
    $url = handle_image_upload($_FILES['image'], $type, $type . '_' . $user['id']);
} elseif (!empty($_FILES['file']['name'])) {
    $url = handle_image_upload($_FILES['file'], $type, $type . '_' . $user['id']);
} elseif (!empty($_FILES['avatar']['name'])) {
    $url = handle_image_upload($_FILES['avatar'], $type, 'avatar_' . $user['id']);
}

// Check Base64 string
if ($url === null) {
    $base64 = field('image') ?? field('file') ?? field('avatar');
    if (is_string($base64) && preg_match('#^data:image/#i', $base64)) {
        $url = handle_base64_image($base64, $type, $type . '_' . $user['id']);
    }
}

if ($url === null) {
    json_error('No valid image uploaded. Send multipart/form-data or base64 image data', 422);
}

json_success(['url' => $url], 'Image uploaded successfully');

