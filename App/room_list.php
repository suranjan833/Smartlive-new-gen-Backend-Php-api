<?php

/**
 * GET /App/room_list.php
 * Query params: ?category=Music|Chill|Poetry|Gaming|Motivation&status=live&page=1&limit=20
 * Returns party audio rooms with listener count and active speaker badges.
 */

require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed, use GET', 405);
}

$conn = db();

$status   = $_GET['status'] ?? 'live';
$category = trim($_GET['category'] ?? '');
$search   = trim($_GET['search'] ?? '');
$page     = max(1, (int) ($_GET['page'] ?? 1));
$limit    = min(50, max(1, (int) ($_GET['limit'] ?? 20)));
$offset   = ($page - 1) * $limit;

$where  = [];
$params = [];
$types  = '';

if ($status === 'live' || $status === 'ended') {
    $where[]  = 'r.status = ?';
    $params[] = $status;
    $types   .= 's';
}
if ($category !== '' && strtolower($category) !== 'all') {
    $where[]  = 'r.category = ?';
    $params[] = $category;
    $types   .= 's';
}
if ($search !== '') {
    $where[]  = '(r.title LIKE ? OR u.name LIKE ?)';
    $like     = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $types   .= 'ss';
}

$whereSql = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

// Total count
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM audio_rooms r JOIN users u ON u.id = r.host_id {$whereSql}");
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total = (int) $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Fetch rooms
$sql = "SELECT r.id, r.host_id, r.channel, r.title, r.category, r.cover, r.status,
               r.listeners, r.peak_listeners, r.coins_earned, r.started_at,
               u.name AS host_name, u.avatar AS host_avatar, u.user_id AS host_user_id
        FROM audio_rooms r
        JOIN users u ON u.id = r.host_id
        {$whereSql}
        ORDER BY r.listeners DESC, r.id DESC
        LIMIT ? OFFSET ?";

$bindTypes  = $types . 'ii';
$bindParams = array_merge($params, [$limit, $offset]);
$stmt = $conn->prepare($sql);
$stmt->bind_param($bindTypes, ...$bindParams);
$stmt->execute();
$rooms = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Attach active speakers (seats) for each room
if (!empty($rooms)) {
    $roomIds = array_column($rooms, 'id');
    $placeholders = implode(',', array_fill(0, count($roomIds), '?'));
    $typesIn = str_repeat('i', count($roomIds));

    $seatStmt = $conn->prepare("
        SELECT rs.room_id, rs.seat_index, rs.is_muted, u.id AS user_id, u.name, u.avatar, u.user_id AS custom_user_id
        FROM room_seats rs
        JOIN users u ON u.id = rs.user_id
        WHERE rs.room_id IN ({$placeholders})
        ORDER BY rs.seat_index ASC
    ");
    $seatStmt->bind_param($typesIn, ...$roomIds);
    $seatStmt->execute();
    $allSeats = $seatStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $seatStmt->close();

    $speakersByRoom = [];
    foreach ($allSeats as $seat) {
        $speakersByRoom[$seat['room_id']][] = [
            'seat_index' => (int) $seat['seat_index'],
            'user_id'    => (int) $seat['user_id'],
            'custom_id'  => $seat['custom_user_id'],
            'name'       => $seat['name'],
            'avatar'     => $seat['avatar'],
            'is_muted'   => (bool) $seat['is_muted'],
        ];
    }

    foreach ($rooms as &$room) {
        $room['speakers'] = $speakersByRoom[$room['id']] ?? [];
    }
    unset($room);
}

json_success([
    'rooms'      => $rooms,
    'pagination' => [
        'page'        => $page,
        'limit'       => $limit,
        'total'       => $total,
        'total_pages' => (int) ceil($total / $limit),
    ],
], 'Audio rooms loaded');

