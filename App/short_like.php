<?php

/**
 * POST /App/short_like.php   (Authorization: Bearer <token>)
 * Body: { "short_id": 5, "action"?: "like|unlike" }
 * Likes or un-likes a short video.
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

$shortId = (int) field('short_id', 0);
$action  = strtolower((string) field('action', 'like'));

if ($shortId <= 0) {
    json_error('short_id is required', 422);
}

$stmt = $conn->prepare('SELECT id, likes FROM shorts WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $shortId);
$stmt->execute();
$short = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$short) {
    json_error('Short video not found', 404);
}

if ($action === 'unlike') {
    $stmt = $conn->prepare('DELETE FROM short_likes WHERE short_id = ? AND user_id = ?');
    $stmt->bind_param('ii', $shortId, $user['id']);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected > 0) {
        $stmt = $conn->prepare('UPDATE shorts SET likes = GREATEST(0, CAST(likes AS SIGNED) - 1) WHERE id = ?');
        $stmt->bind_param('i', $shortId);
        $stmt->execute();
        $stmt->close();
    }

    json_success(['short_id' => $shortId, 'is_liked' => false], 'Unliked');
} else {
    $stmt = $conn->prepare('INSERT IGNORE INTO short_likes (short_id, user_id) VALUES (?, ?)');
    $stmt->bind_param('ii', $shortId, $user['id']);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected > 0) {
        $stmt = $conn->prepare('UPDATE shorts SET likes = likes + 1 WHERE id = ?');
        $stmt->bind_param('i', $shortId);
        $stmt->execute();
        $stmt->close();
    }

    json_success(['short_id' => $shortId, 'is_liked' => true], 'Liked');
}

