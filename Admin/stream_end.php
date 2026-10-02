<?php

/**
 * POST /Admin/stream_end.php   (Authorization: Bearer <admin token>)
 * Body: { "stream_id": 5 }
 * Force-ends any live stream (moderation action).
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
$account = require_admin($conn);
require_permission($account, 'streams', 'edit');

$streamId = (int) field('stream_id', 0);
if ($streamId <= 0) {
    json_error('stream_id is required', 422);
}

$stmt = $conn->prepare('SELECT id, status, host_id FROM streams WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $streamId);
$stmt->execute();
$stream = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$stream) {
    json_error('Stream not found', 404);
}
if ($stream['status'] !== 'live') {
    json_error('Stream is already ended', 409);
}

$conn->begin_transaction();
try {
    $stmt = $conn->prepare(
        "UPDATE stream_viewers SET left_at = NOW() WHERE stream_id = ? AND left_at IS NULL"
    );
    $stmt->bind_param('i', $streamId);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare(
        "UPDATE streams SET status = 'ended', viewer_count = 0, ended_at = NOW() WHERE id = ?"
    );
    $stmt->bind_param('i', $streamId);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    json_error($e->getMessage(), 400);
}

json_success(['stream_id' => $streamId], 'Stream force-ended');
