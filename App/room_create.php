<?php

/**
 * POST /App/room_create.php   (Authorization: Bearer <token>)
 * Body: { "title": "Bollywood Night 🎵", "category"?: "Music", "cover"?: "..." }
 * Creates an audio room and assigns the host to seat #1.
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

$title    = trim((string) field('title', ''));
$category = trim((string) field('category', 'Party'));
$cover    = field('cover');

if ($title === '' || mb_strlen($title) > 120) {
    json_error('Title is required (max 120 chars)', 422);
}

// Generate unique channel identifier for realtime layer
$channel = 'room_' . bin2hex(random_bytes(16));

$conn->begin_transaction();
try {
    $stmt = $conn->prepare(
        'INSERT INTO audio_rooms (host_id, channel, title, category, cover, listeners, peak_listeners)
         VALUES (?, ?, ?, ?, ?, 1, 1)'
    );
    $stmt->bind_param('issss', $user['id'], $channel, $title, $category, $cover);
    $stmt->execute();
    $roomId = (int) $conn->insert_id;
    $stmt->close();

    // Assign host to seat #1
    $stmt = $conn->prepare(
        'INSERT INTO room_seats (room_id, seat_index, user_id, is_muted) VALUES (?, 1, ?, 0)'
    );
    $stmt->bind_param('ii', $roomId, $user['id']);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    json_error('Failed to create audio room: ' . $e->getMessage(), 500);
}

// Fetch created room
$stmt = $conn->prepare('SELECT * FROM audio_rooms WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $roomId);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();
$stmt->close();

json_success(['room' => $room], 'Party room created successfully', 201);

