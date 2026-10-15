<?php

/**
 * GET /App/search.php?q=smart-lv-000001
 * Search users by custom ID, name, email or search live streams.
 */

require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed, use GET', 405);
}

$q = trim($_GET['q'] ?? '');
if ($q === '') {
    json_error('Search query is required (?q=)', 422);
}

$conn = db();
$like = '%' . $q . '%';

// 1. Search Users
$stmt = $conn->prepare('
    SELECT id, user_id, name, avatar, bio, level, vip, status
    FROM users
    WHERE user_id LIKE ? OR name LIKE ? OR email LIKE ?
    LIMIT 20
');
$stmt->bind_param('sss', $like, $like, $like);
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 2. Search Live Streams
$stmt = $conn->prepare("
    SELECT s.id, s.host_id, s.channel, s.title, s.category, s.tag, s.cover, s.viewer_count,
           u.name AS host_name, u.avatar AS host_avatar, u.user_id AS host_user_id
    FROM streams s
    JOIN users u ON u.id = s.host_id
    WHERE s.status = 'live' AND (s.title LIKE ? OR u.name LIKE ? OR u.user_id LIKE ?)
    LIMIT 20
");
$stmt->bind_param('sss', $like, $like, $like);
$stmt->execute();
$streams = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 3. Search Audio Party Rooms
$stmt = $conn->prepare("
    SELECT r.id, r.host_id, r.channel, r.title, r.category, r.cover, r.listeners,
           u.name AS host_name, u.avatar AS host_avatar, u.user_id AS host_user_id
    FROM audio_rooms r
    JOIN users u ON u.id = r.host_id
    WHERE r.status = 'live' AND (r.title LIKE ? OR u.name LIKE ? OR u.user_id LIKE ?)
    LIMIT 20
");
$stmt->bind_param('sss', $like, $like, $like);
$stmt->execute();
$rooms = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

json_success([
    'query'   => $q,
    'users'   => $users,
    'streams' => $streams,
    'rooms'   => $rooms,
], 'Search results loaded');

