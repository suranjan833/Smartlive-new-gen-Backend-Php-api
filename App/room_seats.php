<?php

/**
 * GET /App/room_seats.php?room_id=105
 * 
 * Returns the current seat occupancy matrix for an audio party room (12 seats standard).
 */

require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed, use GET', 405);
}

$roomId = (int) ($_GET['room_id'] ?? $_GET['id'] ?? 0);
if ($roomId <= 0) {
    json_error('Valid room_id is required (?room_id=)', 422);
}

$conn = db();

// Check room
$stmt = $conn->prepare('SELECT id, host_id, channel, status FROM audio_rooms WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $roomId);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$room) {
    json_error('Audio room not found', 404);
}

// Fetch seats
$stmt = $conn->prepare('
    SELECT rs.seat_index, rs.is_muted, rs.joined_at,
           u.id AS user_id, u.user_id AS custom_user_id, u.name, u.avatar, u.level
    FROM room_seats rs
    LEFT JOIN users u ON u.id = rs.user_id
    WHERE rs.room_id = ?
    ORDER BY rs.seat_index ASC
');
$stmt->bind_param('i', $roomId);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$occupied = [];
foreach ($rows as $r) {
    $occupied[(int) $r['seat_index']] = [
        'seat_index' => (int) $r['seat_index'],
        'user_id'    => $r['user_id'] ? (int) $r['user_id'] : null,
        'custom_id'  => $r['custom_user_id'],
        'name'       => $r['name'],
        'avatar'     => $r['avatar'],
        'level'      => $r['level'] ? (int) $r['level'] : 1,
        'is_muted'   => (bool) $r['is_muted'],
        'joined_at'  => $r['joined_at'],
    ];
}

// Build standardized 12-seat layout (1 to 12)
$seats = [];
for ($i = 1; $i <= 12; $i++) {
    $seats[] = $occupied[$i] ?? [
        'seat_index' => $i,
        'user_id'    => null,
        'custom_id'  => null,
        'name'       => null,
        'avatar'     => null,
        'level'      => 0,
        'is_muted'   => false,
        'joined_at'  => null,
    ];
}

json_success([
    'room_id' => $roomId,
    'host_id' => (int) $room['host_id'],
    'seats'   => $seats
], 'Seats loaded successfully');
