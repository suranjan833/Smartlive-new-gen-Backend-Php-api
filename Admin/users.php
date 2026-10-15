<?php

/**
 * GET /Admin/users.php   (Authorization: Bearer <admin token>)
 * Query params: ?search=name-or-email&page=1&limit=20&status=active|blocked
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
require_permission($account, 'users', 'view');

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$page   = max(1, (int) ($_GET['page'] ?? 1));
$limit  = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;

$where  = [];
$params = [];
$types  = '';

if ($search !== '') {
    $where[]  = '(name LIKE ? OR email LIKE ? OR user_id LIKE ?)';
    $like     = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types   .= 'sss';
}
if ($status === 'active' || $status === 'blocked') {
    $where[]  = 'status = ?';
    $params[] = $status;
    $types   .= 's';
}

$whereSql = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

// Total count for pagination
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM users {$whereSql}");
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total = (int) $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Page of users
$stmt = $conn->prepare(
    "SELECT id, user_id, name, email, phone, country_code, country, avatar, bio, vip, level, status, created_at, updated_at
     FROM users {$whereSql}
     ORDER BY id DESC
     LIMIT ? OFFSET ?"
);
if ($types !== '') {
    $bindTypes = $types . 'ii';
    $bindParams = array_merge($params, [$limit, $offset]);
    $stmt->bind_param($bindTypes, ...$bindParams);
} else {
    $stmt->bind_param('ii', $limit, $offset);
}
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

json_success([
    'users'      => $users,
    'pagination' => [
        'page'        => $page,
        'limit'       => $limit,
        'total'       => $total,
        'total_pages' => (int) ceil($total / $limit),
    ],
], 'Users loaded');
