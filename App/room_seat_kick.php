<?php

/**
 * POST /App/room_seat_kick.php   (Authorization: Bearer <token>)
 * Host kick speaker from seat back to audience.
 * 
 * Body:
 * {
 *   "room_id": 105,
 *   "seat_index": 3
 * }
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

$roomId    = (int) (field('room_id') ?? field('id') ?? 0);
$seatIndex = (int) (field('seat_index') ?? 0);
$userId    = (int) $user['id'];

if ($roomId <= 0 || $seatIndex <= 0) {
    json_error('room_id and seat_index are required', 422);
}

// Check room & host permission
$stmt = $conn->prepare("SELECT host_id FROM audio_rooms WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $roomId);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$room) {
    json_error('Audio room not found', 404);
}

if ((int)$room['host_id'] !== $userId) {
    json_error('Permission denied: only host can kick speakers from seat', 403);
}

// Get kicked user info
$stmt = $conn->prepare("SELECT user_id FROM room_seats WHERE room_id = ? AND seat_index = ? LIMIT 1");
$stmt->bind_param('ii', $roomId, $seatIndex);
$stmt->execute();
$seat = $stmt->get_result()->fetch_assoc();
$stmt->close();

$kickedUserId = $seat ? (int) $seat['user_id'] : 0;

$stmt = $conn->prepare("DELETE FROM room_seats WHERE room_id = ? AND seat_index = ?");
$stmt->bind_param('ii', $roomId, $seatIndex);
$stmt->execute();
$stmt->close();

json_success([
    'room_id'        => $roomId,
    'seat_index'     => $seatIndex,
    'kicked_user_id' => $kickedUserId
], 'Speaker removed from seat');
