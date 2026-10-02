<?php

/**
 * GET /Admin/streams.php   (Authorization: Bearer <admin token>)
 * Query params: ?status=live|ended|all&search=title-or-host&page=1&limit=20
 */

require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed, use GET', 405);
}

$conn = db();
$account = require_admin($conn);
require_permission($account, 'streams', 'view');

$status = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$page   = max(1, (int) ($_GET['page'] ?? 1));
$limit  = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;

$where  = [];
$params = [];
$types  = '';

if ($status === 'live' || $status === 'ended') {
    $where[]  = 's.status = ?';
    $params[] = $status;
    $types   .= 's';
}
if ($search !== '') {
    $where[]  = '(s.title LIKE ? OR u.name LIKE ?)';
    $like     = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $types   .= 'ss';
}

$whereSql = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

$stmt = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM streams s
     JOIN users u ON u.id = s.host_id
     {$whereSql}"
);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total = (int) $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare(
    "SELECT s.id, s.host_id, s.channel, s.title, s.status,
            s.viewer_count, s.peak_viewers, s.total_views, s.coins_earned,
            s.started_at, s.ended_at, u.name AS host_name, u.email AS host_email
     FROM streams s
     JOIN users u ON u.id = s.host_id
     {$whereSql}
     ORDER BY s.id DESC
     LIMIT ? OFFSET ?"
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
