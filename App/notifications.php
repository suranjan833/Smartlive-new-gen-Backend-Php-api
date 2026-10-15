<?php

/**
 * GET /App/notifications.php
 * Query params: ?page=1&limit=20
 * Returns platform announcements and messages for the Message tab.
 */

require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed, use GET', 405);
}

$conn = db();

$page   = max(1, (int) ($_GET['page'] ?? 1));
$limit  = min(50, max(1, (int) ($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;

// Public or sent notifications
$sql = "
    SELECT id, title, message, type, audience, sent_at, created_at
    FROM notifications
    WHERE status = 'sent'
    ORDER BY id DESC
    LIMIT ? OFFSET ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $limit, $offset);
$stmt->execute();
$notifs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

json_success(['notifications' => $notifs], 'Notifications loaded');

