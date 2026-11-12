<?php

/**
 * Image upload & storage helper for SmartLive.
 * Supports multipart/form-data $_FILES and Base64 data strings.
 */

require_once __DIR__ . '/response.php';

function get_upload_base_url(): string {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
             || (($_SERVER['SERVER_PORT'] ?? 80) == 443)
             ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');

    $baseDir = '';
    if (preg_match('#^(.*?)/(?:App|Admin|utils)/#i', $scriptPath, $m)) {
        $baseDir = rtrim($m[1], '/');
    } else {
        $baseDir = rtrim(dirname($scriptPath), '/');
    }
    return $protocol . $host . $baseDir;
}

/**
 * Handle multipart/form-data file upload.
 *
 * @param array  $fileInfo  $_FILES['avatar'] or similar
 * @param string $subDir    Subdirectory inside uploads/ (default 'avatars')
 * @param string $prefix    Filename prefix (default 'avatar')
 * @return string Public URL of the uploaded image
 */
function handle_image_upload(array $fileInfo, string $subDir = 'avatars', string $prefix = 'avatar'): string {
    if (!isset($fileInfo['error']) || $fileInfo['error'] !== UPLOAD_ERR_OK) {
        $errMap = [
            UPLOAD_ERR_INI_SIZE   => 'Image exceeds server upload_max_filesize limit',
            UPLOAD_ERR_FORM_SIZE  => 'Image exceeds form MAX_FILE_SIZE limit',
            UPLOAD_ERR_PARTIAL    => 'Image was only partially uploaded',
            UPLOAD_ERR_NO_FILE    => 'No image file uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder on server',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write image file to disk',
            UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload',
        ];
        $msg = $errMap[$fileInfo['error'] ?? -1] ?? 'Unknown file upload error';
        json_error($msg, 422);
    }

    // 8 MB max
    if ($fileInfo['size'] > 8 * 1024 * 1024) {
        json_error('Image size must be 8MB or less', 422);
    }

    $ext = strtolower(pathinfo($fileInfo['name'], PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($ext, $allowedExts, true)) {
        json_error('Allowed image formats: JPG, PNG, WEBP, GIF', 422);
    }

    $uploadDir = __DIR__ . '/../uploads/' . trim($subDir, '/');
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = sprintf('%s_%s_%s.%s', $prefix, time(), bin2hex(random_bytes(6)), $ext);
    $targetPath = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($fileInfo['tmp_name'], $targetPath)) {
        json_error('Failed to save uploaded image file', 500);
    }

    return get_upload_base_url() . '/uploads/' . trim($subDir, '/') . '/' . $filename;
}

/**
 * Handle base64 image data (e.g. data:image/png;base64,....)
 *
 * @param string $dataUri
 * @param string $subDir
 * @param string $prefix
 * @return string Public URL of the saved image
 */
function handle_base64_image(string $dataUri, string $subDir = 'avatars', string $prefix = 'avatar'): string {
    $ext = 'jpg';
    if (preg_match('#^data:image/(\w+);base64,#i', $dataUri, $m)) {
        $ext = strtolower($m[1]);
        if ($ext === 'jpeg') $ext = 'jpg';
        $dataUri = substr($dataUri, strpos($dataUri, ',') + 1);
    }

    $binary = base64_decode($dataUri, true);
    if ($binary === false || strlen($binary) === 0) {
        json_error('Invalid base64 image data', 422);
    }

    if (strlen($binary) > 8 * 1024 * 1024) {
        json_error('Image size must be 8MB or less', 422);
    }

    $uploadDir = __DIR__ . '/../uploads/' . trim($subDir, '/');
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = sprintf('%s_%s_%s.%s', $prefix, time(), bin2hex(random_bytes(6)), $ext);
    $targetPath = $uploadDir . '/' . $filename;

    if (file_put_contents($targetPath, $binary) === false) {
        json_error('Failed to save base64 image file', 500);
    }

    return get_upload_base_url() . '/uploads/' . trim($subDir, '/') . '/' . $filename;
}

