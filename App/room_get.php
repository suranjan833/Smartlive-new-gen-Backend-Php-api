<?php

/**
 * GET /App/room_get.php?id=5
 * Returns complete party room details with host info and all 8 seats.
 */

require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed, use GET', 405);
}

$roomId = (int) ($_GET['id'] ?? 0);
if ($roomId <= 0) {
    json_error('Valid room id is required (?id=)', 422);
}

$conn = db();

$stmt = $conn->prepare('
    SELECT r.id, r.host_id, r.channel, r.title, r.category, r.cover, r.status,
           r.listeners, r.peak_listeners, r.coins_earned, r.started_at, r.ended_at,
           u.name AS host_name, u.avatar AS host_avatar, u.user_id AS host_user_id
    FROM audio_rooms r
    JOIN users u ON u.id = r.host_id
    WHERE r.id = ?
    LIMIT 1
');
$stmt->bind_param('i', $roomId);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$room) {
    json_error('Audio room not found', 404);
}

// Fetch seats (up to 8 seats)
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
$occupied = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$occupiedByIndex = [];
foreach ($occupied as $row) {
    $occupiedByIndex[(int) $row['seat_index']] = [
        'seat_index' => (int) $row['seat_index'],
        'user_id'    => $row['user_id'] ? (int) $row['user_id'] : null,
        'custom_id'  => $row['custom_user_id'],
        'name'       => $row['name'],
        'avatar'     => $row['avatar'],
        'level'      => $row['level'] ? (int) $row['level'] : 1,
        'is_muted'   => (bool) $row['is_muted'],
    ];
}

// Build standardized 8 seats array
$seats = [];
for ($i = 1; $i <= 8; $i++) {
    $seats[] = $occupiedByIndex[$i] ?? [
        'seat_index' => $i,
        'user_id'    => null,
        'custom_id'  => null,
        'name'       => null,
        'avatar'     => null,
        'level'      => 0,
        'is_muted'   => false,
    ];
}

$room['seats'] = $seats;

json_success(['room' => $room], 'Audio room loaded');

