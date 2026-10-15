<?php

/**
 * GET /App/followers.php
 * Query params: ?user_id=5&type=followers|following&page=1&limit=20
 * Returns followers or following user list.
 */

require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/sequence.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed, use GET', 405);
}

$conn = db();

$rawUser = $_GET['user_id'] ?? '';
$type    = strtolower($_GET['type'] ?? 'followers');
$page    = max(1, (int) ($_GET['page'] ?? 1));
$limit   = min(50, max(1, (int) ($_GET['limit'] ?? 20)));
$offset  = ($page - 1) * $limit;

if ($rawUser === '') {
    json_error('user_id is required (?user_id=)', 422);
}

$target = find_user_by_id_or_code($conn, $rawUser);
if (!$target) {
    json_error('User not found', 404);
}
$targetId = (int) $target['id'];

if ($type === 'following') {
    // Users that $targetId follows
    $sql = "
        SELECT u.id, u.user_id, u.name, u.avatar, u.bio, u.level, u.vip, uf.created_at AS followed_at
        FROM user_follows uf
        JOIN users u ON u.id = uf.following_id
        WHERE uf.follower_id = ?
        ORDER BY uf.id DESC
        LIMIT ? OFFSET ?
    ";
    $countSql = "SELECT COUNT(*) AS total FROM user_follows WHERE follower_id = ?";
} else {
    // Users following $targetId
    $sql = "
        SELECT u.id, u.user_id, u.name, u.avatar, u.bio, u.level, u.vip, uf.created_at AS followed_at
        FROM user_follows uf
        JOIN users u ON u.id = uf.follower_id
        WHERE uf.following_id = ?
        ORDER BY uf.id DESC
        LIMIT ? OFFSET ?
    ";
    $countSql = "SELECT COUNT(*) AS total FROM user_follows WHERE following_id = ?";
}

$stmt = $conn->prepare($countSql);
$stmt->bind_param('i', $targetId);
$stmt->execute();
$total = (int) $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare($sql);
$stmt->bind_param('iii', $targetId, $limit, $offset);
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

json_success([
    'type'       => $type,
    'users'      => $users,
    'pagination' => [
        'page'        => $page,
        'limit'       => $limit,
        'total'       => $total,
        'total_pages' => (int) ceil($total / $limit),
    ],
], ucfirst($type) . ' loaded');

