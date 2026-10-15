<?php

/**
 * GET /App/shorts_list.php
 * Query params: ?page=1&limit=10
 * Returns short videos feed for the bottom bar Shorts tab.
 */

require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed, use GET', 405);
}

$conn = db();

// Optional auth token to show if current user liked each short
$token = get_request_token();
$currentUserId = null;
if ($token !== null) {
    $sStmt = $conn->prepare("SELECT user_id FROM sessions WHERE token = ? AND user_type = 'user' AND expires_at > NOW() LIMIT 1");
    $sStmt->bind_param('s', $token);
    $sStmt->execute();
    $sRow = $sStmt->get_result()->fetch_assoc();
    $sStmt->close();
    if ($sRow) {
        $currentUserId = (int) $sRow['user_id'];
    }
}

$page   = max(1, (int) ($_GET['page'] ?? 1));
$limit  = min(30, max(1, (int) ($_GET['limit'] ?? 10)));
$offset = ($page - 1) * $limit;

$stmt = $conn->query('SELECT COUNT(*) AS total FROM shorts');
$total = (int) $stmt->fetch_assoc()['total'];

$sql = '
    SELECT s.id, s.user_id, s.title, s.video_url, s.cover_url, s.views, s.likes, s.created_at,
           u.name AS creator_name, u.avatar AS creator_avatar, u.user_id AS creator_user_id
    FROM shorts s
    JOIN users u ON u.id = s.user_id
    ORDER BY s.id DESC
    LIMIT ? OFFSET ?
';
$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $limit, $offset);
$stmt->execute();
$shorts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if ($currentUserId !== null && !empty($shorts)) {
    $shortIds = array_column($shorts, 'id');
    $placeholders = implode(',', array_fill(0, count($shortIds), '?'));
    $typesIn = str_repeat('i', count($shortIds));

    $lStmt = $conn->prepare("SELECT short_id FROM short_likes WHERE user_id = ? AND short_id IN ({$placeholders})");
    $bindParams = array_merge([$currentUserId], $shortIds);
    $lStmt->bind_param('i' . $typesIn, ...$bindParams);
    $lStmt->execute();
    $likedRows = $lStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $lStmt->close();

    $likedSet = array_flip(array_column($likedRows, 'short_id'));
    foreach ($shorts as &$sh) {
        $sh['is_liked'] = isset($likedSet[$sh['id']]);
    }
    unset($sh);
} else {
    foreach ($shorts as &$sh) {
        $sh['is_liked'] = false;
    }
    unset($sh);
}

json_success([
    'shorts'     => $shorts,
    'pagination' => [
        'page'        => $page,
        'limit'       => $limit,
        'total'       => $total,
        'total_pages' => (int) ceil($total / $limit),
    ],
], 'Shorts loaded');

