<?php

/**
 * POST /App/room_join.php   (Authorization: Bearer <token>)
 * Body: { "room_id": 5, "seat_index"?: 2 }
 * Increments room listener count and optionally claims a microphone seat.
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

$roomId    = (int) field('room_id', 0);
$seatIndex = field('seat_index') !== null ? (int) field('seat_index') : null;

if ($roomId <= 0) {
    json_error('room_id is required', 422);
}

$stmt = $conn->prepare("SELECT id, status FROM audio_rooms WHERE id = ? AND status = 'live' LIMIT 1");
$stmt->bind_param('i', $roomId);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$room) {
    json_error('Party room is not active', 404);
}

// Bump listener count
$stmt = $conn->prepare('
    UPDATE audio_rooms
    SET listeners = listeners + 1,
        peak_listeners = GREATEST(peak_listeners, listeners)
    WHERE id = ?
');
$stmt->bind_param('i', $roomId);
$stmt->execute();
$stmt->close();

// If user requests a seat (1 to 8)
if ($seatIndex !== null && $seatIndex >= 1 && $seatIndex <= 8) {
    // Check if seat already taken
    $stmt = $conn->prepare('SELECT id FROM room_seats WHERE room_id = ? AND seat_index = ? AND user_id IS NOT NULL LIMIT 1');
    $stmt->bind_param('ii', $roomId, $seatIndex);
    $stmt->execute();
    $taken = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($taken) {
        json_error("Seat #{$seatIndex} is already occupied", 409);
    }

    $stmt = $conn->prepare('
        INSERT INTO room_seats (room_id, seat_index, user_id, is_muted)
        VALUES (?, ?, ?, 0)
        ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)
    ');
    $stmt->bind_param('iii', $roomId, $seatIndex, $user['id']);
    $stmt->execute();
    $stmt->close();
}

json_success(['room_id' => $roomId, 'seat_index' => $seatIndex], 'Joined party room');

