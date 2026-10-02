<?php

/**
 * GET /App/stream_get.php?id=5
 * Full details of a single stream incl. host info.
 */

require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed, use GET', 405);
}

$conn = db();

$streamId = (int) ($_GET['id'] ?? 0);
if ($streamId <= 0) {
    json_error('Valid stream id is required (?id=)', 422);
}

$stmt = $conn->prepare(
    'SELECT s.id, s.host_id, s.channel, s.title, s.description, s.cover, s.status,
            s.viewer_count, s.peak_viewers, s.total_views, s.coins_earned,
            s.started_at, s.ended_at,
            u.name AS host_name, u.avatar AS host_avatar, u.bio AS host_bio
     FROM streams s
     JOIN users u ON u.id = s.host_id
     WHERE s.id = ?
     LIMIT 1'
);
$stmt->bind_param('i', $streamId);
$stmt->execute();
$stream = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$stream) {
    json_error('Stream not found', 404);
}

json_success(['stream' => $stream], 'Stream loaded');
