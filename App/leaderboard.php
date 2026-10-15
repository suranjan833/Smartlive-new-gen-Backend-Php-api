<?php

/**
 * GET /App/leaderboard.php
 * Query params: ?type=gifters|stars&period=daily|weekly|monthly|all&limit=20
 * Returns top ranking gifters and streamers.
 */

require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed, use GET', 405);
}

$conn = db();

$type   = strtolower($_GET['type'] ?? 'stars');    // 'stars' or 'gifters'
$period = strtolower($_GET['period'] ?? 'daily');  // 'daily', 'weekly', 'monthly', 'all'
$limit  = min(50, max(1, (int) ($_GET['limit'] ?? 20)));

$dateFilter = '';
if ($period === 'daily') {
    $dateFilter = 'AND sg.created_at >= CURDATE()';
} elseif ($period === 'weekly') {
    $dateFilter = 'AND sg.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
} elseif ($period === 'monthly') {
    $dateFilter = 'AND sg.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
}

if ($type === 'gifters') {
    // Top gifters (sender_id)
    $sql = "
        SELECT u.id AS user_id, u.user_id AS custom_user_id, u.name, u.avatar, u.level, u.vip,
               SUM(sg.coins) AS total_coins
        FROM stream_gifts sg
        JOIN users u ON u.id = sg.sender_id
        WHERE 1=1 {$dateFilter}
        GROUP BY u.id
        ORDER BY total_coins DESC
        LIMIT ?
    ";
} else {
    // Top stars / streamers (receiver_id)
    $sql = "
        SELECT u.id AS user_id, u.user_id AS custom_user_id, u.name, u.avatar, u.level, u.vip,
               SUM(sg.coins) AS total_coins
        FROM stream_gifts sg
        JOIN users u ON u.id = sg.receiver_id
        WHERE 1=1 {$dateFilter}
        GROUP BY u.id
        ORDER BY total_coins DESC
        LIMIT ?
    ";
}

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $limit);
$stmt->execute();
$rankings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Assign rank 1, 2, 3...
foreach ($rankings as $idx => &$row) {
    $row['rank'] = $idx + 1;
}
unset($row);

json_success([
    'type'     => $type,
    'period'   => $period,
    'rankings' => $rankings,
], 'Leaderboard loaded');

