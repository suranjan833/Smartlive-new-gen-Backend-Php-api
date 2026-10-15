<?php

/**
 * POST /App/follow.php   (Authorization: Bearer <token>)
 * Body: { "user_id": 5, "action"?: "follow|unfollow" }
 * Follows or unfollows a streamer/user.
 */

require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/validation.php';
require_once __DIR__ . '/../utils/auth.php';
require_once __DIR__ . '/../utils/sequence.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed, use POST', 405);
}

$conn = db();
$user = require_auth($conn, 'user');

$targetInput = field('user_id');
$action      = strtolower((string) field('action', 'follow'));

if ($targetInput === null || $targetInput === '') {
    json_error('user_id is required', 422);
}

$target = find_user_by_id_or_code($conn, $targetInput);
if (!$target) {
    json_error('User to follow not found', 404);
}

$targetId = (int) $target['id'];
$myId     = (int) $user['id'];

if ($targetId === $myId) {
    json_error('You cannot follow yourself', 422);
}

if ($action === 'unfollow') {
    $stmt = $conn->prepare('DELETE FROM user_follows WHERE follower_id = ? AND following_id = ?');
    $stmt->bind_param('ii', $myId, $targetId);
    $stmt->execute();
    $stmt->close();

    json_success(['following' => false, 'user_id' => $target['user_id']], 'Unfollowed successfully');
} else {
    $stmt = $conn->prepare('
        INSERT INTO user_follows (follower_id, following_id)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE id = id
    ');
    $stmt->bind_param('ii', $myId, $targetId);
    $stmt->execute();
    $stmt->close();

    json_success(['following' => true, 'user_id' => $target['user_id']], 'Followed successfully');
}

