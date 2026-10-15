<?php

/**
 * GET /App/stream_list.php
 * Query params: ?status=live|ended|all&page=1&limit=20&search=title
 * Default shows live streams (newest first) with host info.
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
    $where[]  = 's.status = ?';
    $params[] = $status;
    $types   .= 's';
}
if ($category !== '' && strtolower($category) !== 'all') {
    $where[]  = 's.category = ?';
    $params[] = $category;
    $types   .= 's';
}
if ($search !== '') {
    $where[]  = '(s.title LIKE ? OR u.name LIKE ? OR u.user_id LIKE ?)';
    $like     = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types   .= 'sss';
}

$whereSql = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

$select = 'SELECT s.id, s.host_id, s.channel, s.title, s.category, s.tag, s.description, s.cover, s.status,
                  s.viewer_count, s.peak_viewers, s.total_views, s.coins_earned,
                  s.started_at, s.ended_at,
                  u.name AS host_name, u.avatar AS host_avatar, u.user_id AS host_user_id, u.level AS host_level
           FROM streams s
           JOIN users u ON u.id = s.host_id ';

$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM streams s JOIN users u ON u.id = s.host_id {$whereSql}");
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total = (int) $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare(
    $select . $whereSql . ' ORDER BY s.viewer_count DESC, s.id DESC LIMIT ? OFFSET ?'
);
$bindTypes  = $types . 'ii';
$bindParams = array_merge($params, [$limit, $offset]);
$stmt->bind_param($bindTypes, ...$bindParams);
$stmt->execute();
$streams = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();


json_success([
    'streams'    => $streams,
    'pagination' => [
        'page'        => $page,
        'limit'       => $limit,
        'total'       => $total,
        'total_pages' => (int) ceil($total / $limit),
    ],
], 'Streams loaded');
