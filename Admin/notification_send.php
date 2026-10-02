<?php

/**
 * POST /Admin/notification_send.php?id=5   (Authorization: Bearer <admin token>)
 * Marks a draft notification as sent and records the delivery time.
 * (Local mode: no push provider yet — a FCM/OneSignal call slots in here.)
 */

require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed, use POST', 405);
}

$conn = db();
$account = require_admin($conn);
require_permission($account, 'notifications', 'edit');

$id = (int) ($_GET['id'] ?? field('id', 0));
if ($id <= 0) {
    json_error('Valid id is required (?id=)', 422);
}

$stmt = $conn->prepare('SELECT * FROM notifications WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$notification = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$notification) {
    json_error('Notification not found', 404);
}
if ($notification['status'] === 'sent') {
    json_error('Notification was already sent', 422);
}

// Local mode: mark as sent. A real push provider (FCM/OneSignal) hooks in here,
// selecting users by $notification['audience'] and delivering the message.
$stmt = $conn->prepare("UPDATE notifications SET status = 'sent', sent_at = NOW() WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->close();

json_success([
    'id'       => $id,
    'status'   => 'sent',
    'sent_at'  => date('Y-m-d H:i:s'),
    'audience' => $notification['audience'],
], 'Notification sent');
