<?php

/**
 * POST /App/room_leave.php   (Authorization: Bearer <token>)
 * Body: { "room_id": 5 }
 * Leaves room and vacates microphone seat if occupied.
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

// Vacate seat if user is seated
$stmt = $conn->prepare('DELETE FROM room_seats WHERE room_id = ? AND user_id = ?');
$stmt->bind_param('ii', $roomId, $user['id']);
$stmt->execute();
$stmt->close();

// Decrement listeners count (min 0)
$stmt = $conn->prepare('UPDATE audio_rooms SET listeners = GREATEST(0, CAST(listeners AS SIGNED) - 1) WHERE id = ?');
$stmt->bind_param('i', $roomId);
$stmt->execute();
$stmt->close();

json_success(['room_id' => $roomId], 'Left party room');

