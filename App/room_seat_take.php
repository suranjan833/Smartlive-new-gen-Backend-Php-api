<?php

/**
 * POST /App/room_seat_take.php   (Authorization: Bearer <token>)
 * Take an empty seat in an Audio Party Room.
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

if ($roomId <= 0 || $seatIndex <= 0 || $seatIndex > 12) {
    json_error('Valid room_id and seat_index (1-12) are required', 422);
}

// 1. Check room
$stmt = $conn->prepare("SELECT id, host_id, status FROM audio_rooms WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $roomId);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$room || $room['status'] === 'ended') {
    json_error('Audio room not found or ended', 404);
}

$userId = (int) $user['id'];

// 2. Remove user from any other seat in this room first
$stmt = $conn->prepare("DELETE FROM room_seats WHERE room_id = ? AND user_id = ?");
$stmt->bind_param('ii', $roomId, $userId);
$stmt->execute();
$stmt->close();

// 3. Check if target seat is already occupied by someone else
$stmt = $conn->prepare("SELECT user_id FROM room_seats WHERE room_id = ? AND seat_index = ? AND user_id IS NOT NULL LIMIT 1");
$stmt->bind_param('ii', $roomId, $seatIndex);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existing && (int)$existing['user_id'] !== $userId) {
    json_error('This seat is already occupied', 409);
}

// 4. Assign seat
$stmt = $conn->prepare("
    INSERT INTO room_seats (room_id, seat_index, user_id, is_muted, joined_at)
    VALUES (?, ?, ?, 0, NOW())
    ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), is_muted = 0, joined_at = NOW()
");
$stmt->bind_param('iii', $roomId, $seatIndex, $userId);
$stmt->execute();
$stmt->close();

json_success([
    'room_id'    => $roomId,
    'seat_index' => $seatIndex,
    'user'       => [
        'id'        => $userId,
        'user_id'   => $user['user_id'] ?? null,
        'name'      => $user['name'],
        'avatar'    => $user['avatar'] ?? null,
        'level'     => (int) ($user['level'] ?? 1),
        'is_muted'  => false
    ]
], 'Seat occupied successfully');
