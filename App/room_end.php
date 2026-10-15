<?php

/**
 * POST /App/room_end.php   (Authorization: Bearer <token>)
 * Body: { "room_id": 5 }
 * Terminates the party audio room (Host only).
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

$roomId = (int) field('room_id', 0);
if ($roomId <= 0) {
    json_error('room_id is required', 422);
}

$stmt = $conn->prepare('SELECT id, host_id, status FROM audio_rooms WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $roomId);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$room) {
    json_error('Audio room not found', 404);
}
if ((int) $room['host_id'] !== (int) $user['id']) {
    json_error('Only the room host can end this room', 403);
}

$conn->begin_transaction();
try {
    $stmt = $conn->prepare("UPDATE audio_rooms SET status = 'ended', ended_at = NOW(), listeners = 0 WHERE id = ?");
    $stmt->bind_param('i', $roomId);
    $stmt->execute();
    $stmt->close();

    // Clear all seats
    $stmt = $conn->prepare('DELETE FROM room_seats WHERE room_id = ?');
    $stmt->bind_param('i', $roomId);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    json_error('Failed to end room: ' . $e->getMessage(), 500);
}

json_success(['room_id' => $roomId], 'Party room ended');

