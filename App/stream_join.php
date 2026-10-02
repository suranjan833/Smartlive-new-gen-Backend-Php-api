<?php

/**
 * POST /App/stream_join.php   (Authorization: Bearer <token>)
 * Body: { "stream_id": 5 }
 * Adds the user as an active viewer and bumps viewer_count / total_views.
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

$conn->begin_transaction();
try {
    $stmt = $conn->prepare("SELECT * FROM streams WHERE id = ? AND status = 'live' FOR UPDATE");
    $stmt->bind_param('i', $streamId);
    $stmt->execute();
    $stream = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$stream) {
        throw new Exception('Stream not found or not live', 404);
    }

    $stmt = $conn->prepare(
        'SELECT id FROM stream_viewers WHERE stream_id = ? AND user_id = ? AND left_at IS NULL LIMIT 1'
    );
    $stmt->bind_param('ii', $streamId, $user['id']);
    $stmt->execute();
    $alreadyWatching = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($alreadyWatching) {
        $conn->rollback();
        json_success(['stream_id' => $streamId], 'Already watching this stream');
    }

    $stmt = $conn->prepare('INSERT INTO stream_viewers (stream_id, user_id) VALUES (?, ?)');
    $stmt->bind_param('ii', $streamId, $user['id']);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare(
        'UPDATE streams
         SET viewer_count = viewer_count + 1,
             total_views  = total_views + 1,
             peak_viewers = GREATEST(peak_viewers, viewer_count + 1)
         WHERE id = ?'
    );
    $stmt->bind_param('i', $streamId);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare('SELECT viewer_count, peak_viewers, total_views FROM streams WHERE id = ?');
    $stmt->bind_param('i', $streamId);
    $stmt->execute();
    $counts = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    $code = is_int($e->getCode()) && $e->getCode() >= 400 ? $e->getCode() : 400;
    json_error($e->getMessage(), $code);
}

json_success(['stream_id' => $streamId, 'counts' => $counts], 'Joined the stream');
