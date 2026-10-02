<?php

/**
 * POST /App/stream_leave.php   (Authorization: Bearer <token>)
 * Body: { "stream_id": 5 }
 * Marks the viewer as left and decrements viewer_count.
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

$streamId = (int) field('stream_id', 0);
if ($streamId <= 0) {
    json_error('stream_id is required', 422);
}

$stmt = $conn->prepare(
    'SELECT id FROM stream_viewers WHERE stream_id = ? AND user_id = ? AND left_at IS NULL LIMIT 1'
);
$stmt->bind_param('ii', $streamId, $user['id']);
$stmt->execute();
$watching = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$watching) {
    json_error('You are not watching this stream', 409);
}

$stmt = $conn->prepare('UPDATE stream_viewers SET left_at = NOW() WHERE id = ?');
$stmt->bind_param('i', $watching['id']);
$stmt->execute();
$stmt->close();

$stmt = $conn->prepare('UPDATE streams SET viewer_count = GREATEST(viewer_count - 1, 0) WHERE id = ?');
$stmt->bind_param('i', $streamId);
$stmt->execute();
$stmt->close();

$stmt = $conn->prepare('SELECT viewer_count FROM streams WHERE id = ?');
$stmt->bind_param('i', $streamId);
$stmt->execute();
$counts = $stmt->get_result()->fetch_assoc();
$stmt->close();

json_success(['stream_id' => $streamId, 'counts' => $counts], 'Left the stream');
