<?php

/**
 * POST /App/room_seat_leave.php   (Authorization: Bearer <token>)
 * Leave a microphone seat in an Audio Party Room.
 * 
 * Body:
 * {
 *   "room_id": 105,
 *   "seat_index": 3            // optional, auto-detects user seat if omitted
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

if ($roomId <= 0) {
    json_error('Valid room_id is required', 422);
}

if ($seatIndex > 0) {
    $stmt = $conn->prepare("DELETE FROM room_seats WHERE room_id = ? AND seat_index = ? AND user_id = ?");
    $stmt->bind_param('iii', $roomId, $seatIndex, $userId);
} else {
    $stmt = $conn->prepare("DELETE FROM room_seats WHERE room_id = ? AND user_id = ?");
    $stmt->bind_param('ii', $roomId, $userId);
}

$stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();

json_success([
    'room_id'    => $roomId,
    'seat_index' => $seatIndex,
    'vacated'    => $affected > 0
], 'Seat vacated successfully');
