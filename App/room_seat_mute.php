<?php

/**
 * POST /App/room_seat_mute.php   (Authorization: Bearer <token>)
 * Mute / Unmute a seat or self mic.
 * Host can mute any seat; Speaker can mute self.
 * 
 * Body:
 * {
 *   "room_id": 105,
 *   "seat_index": 3,
 *   "is_muted": true | false
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
$isMuted   = field('is_muted') ? 1 : 0;
$userId    = (int) $user['id'];

if ($roomId <= 0 || $seatIndex <= 0) {
    json_error('room_id and seat_index are required', 422);
}

// 1. Check room & permissions
$stmt = $conn->prepare("SELECT host_id FROM audio_rooms WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $roomId);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$room) {
    json_error('Audio room not found', 404);
}

$isHost = ((int) $room['host_id'] === $userId);

// 2. Fetch current seat holder
$stmt = $conn->prepare("SELECT user_id FROM room_seats WHERE room_id = ? AND seat_index = ? LIMIT 1");
$stmt->bind_param('ii', $roomId, $seatIndex);
$stmt->execute();
$seat = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$seat || empty($seat['user_id'])) {
    json_error('Seat is not occupied', 404);
}

// Only host or the sitting user can mute this seat
if (!$isHost && (int)$seat['user_id'] !== $userId) {
    json_error('Permission denied: only host or the speaker can mute this mic', 403);
}

$stmt = $conn->prepare("UPDATE room_seats SET is_muted = ? WHERE room_id = ? AND seat_index = ?");
$stmt->bind_param('iii', $isMuted, $roomId, $seatIndex);
$stmt->execute();
$stmt->close();

json_success([
    'room_id'    => $roomId,
    'seat_index' => $seatIndex,
    'is_muted'   => (bool) $isMuted,
    'muted_by'   => $isHost ? 'host' : 'self'
], $isMuted ? 'Microphone muted' : 'Microphone unmuted');
