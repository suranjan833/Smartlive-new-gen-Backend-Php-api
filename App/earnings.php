<?php

/**
 * GET /App/earnings.php   (Authorization: Bearer <token>)
 * Returns host earnings, gift stats, and salary records.
 */

require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed, use GET', 405);
}

$conn = db();
$user = require_auth($conn, 'user');
$userId = (int) $user['id'];

// Total gifts received & coins earned
$stmt = $conn->prepare('
    SELECT COALESCE(SUM(coins), 0) AS total_beans,
           COALESCE(SUM(quantity), 0) AS total_gifts_count
    FROM stream_gifts
    WHERE receiver_id = ?
');
$stmt->bind_param('i', $userId);
$stmt->execute();
$allTime = $stmt->get_result()->fetch_assoc();
$stmt->close();

// This month's earnings
$stmt = $conn->prepare('
    SELECT COALESCE(SUM(coins), 0) AS month_beans
    FROM stream_gifts
    WHERE receiver_id = ? AND created_at >= DATE_FORMAT(NOW(), "%Y-%m-01")
');
$stmt->bind_param('i', $userId);
$stmt->execute();
$thisMonth = (int) $stmt->get_result()->fetch_assoc()['month_beans'];
$stmt->close();

// Check if user is linked to a registered Host record
$stmt = $conn->prepare('
    SELECT h.id AS host_id, h.name, h.followers, h.earnings AS host_earnings, h.status,
           a.name AS agency_name
    FROM hosts h
    LEFT JOIN agencies a ON a.id = h.agency_id
    WHERE h.user_id = ?
    LIMIT 1
');
$stmt->bind_param('i', $userId);
$stmt->execute();
$hostRecord = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Salary payouts if linked to a host
$salaries = [];
if ($hostRecord) {
    $stmt = $conn->prepare('
        SELECT id, amount, month, note, paid_at, created_at
        FROM host_salaries
        WHERE host_id = ?
        ORDER BY month DESC
    ');
    $stmt->bind_param('i', $hostRecord['host_id']);
    $stmt->execute();
    $salaries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

json_success([
    'total_beans'       => (int) $allTime['total_beans'],
    'this_month_beans'  => $thisMonth,
    'total_gifts_count' => (int) $allTime['total_gifts_count'],
    'host_profile'      => $hostRecord,
    'salary_history'    => $salaries,
], 'Earnings loaded');

