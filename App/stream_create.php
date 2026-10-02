<?php

/**
 * POST /App/stream_create.php   (Authorization: Bearer <token>)
 * Body: { "title": "...", "description": "...", "cover": "url-or-filename" (optional) }
 * Returns the stream row incl. channel name for the realtime socket later.
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

$title       = (string) field('title', '');
$description = (string) field('description', '');
$cover       = (string) field('cover', '');

if (trim($title) === '') {
    json_error('Title is required', 422);
}
if (mb_strlen($title) > 120) {
    json_error('Title must be 120 characters or fewer', 422);
}

// A user can host only one live stream at a time
$stmt = $conn->prepare("SELECT id FROM streams WHERE host_id = ? AND status = 'live' LIMIT 1");
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$alreadyLive = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($alreadyLive) {
    json_error('You already have a live stream. End it first.', 409);
}

$channel = 'stream_' . (int) $user['id'] . '_' . time();

$stmt = $conn->prepare('INSERT INTO streams (host_id, channel, title, description, cover) VALUES (?, ?, ?, ?, ?)');
$stmt->bind_param('issss', $user['id'], $channel, $title, $description, $cover);
$stmt->execute();
$streamId = $conn->insert_id;
$stmt->close();

// Host is their own first viewer row (left_at NULL)
$stmt = $conn->prepare('INSERT INTO stream_viewers (stream_id, user_id) VALUES (?, ?)');
$stmt->bind_param('ii', $streamId, $user['id']);
$stmt->execute();
$stmt->close();

$stmt = $conn->prepare('SELECT * FROM streams WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $streamId);
$stmt->execute();
$stream = $stmt->get_result()->fetch_assoc();
$stmt->close();

json_success(['stream' => $stream], 'You are live! Stream created', 201);
