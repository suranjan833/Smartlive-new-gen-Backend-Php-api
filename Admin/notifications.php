<?php

/**
 * /Admin/notifications.php   (Authorization: Bearer <admin token>)
 *   GET    ?page=&limit=&search=&status=draft|sent  -> list
 *   GET    ?id=5                                    -> detail
 *   POST   body: { title, message, type?: info|promo|alert,
 *                  audience?: all_users|vip_users|hosts|active_users, status?: draft }
 *   PUT    ?id=5  body: any subset
 *   PATCH  ?id=5  body: any subset
 *   DELETE ?id=5
 * Sent notifications can no longer be edited (PUT/PATCH return 422).
 */

require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/crud.php';

require_once __DIR__ . '/notifications_def.php';

$conn = db();
$account = require_admin($conn);
require_permission($account, 'notifications', $_SERVER['REQUEST_METHOD'] === 'GET' ? 'view' : 'edit');

// Sent notifications are immutable
if (in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'PATCH'], true) && !empty($_GET['id'])) {
    $stmt = $conn->prepare("SELECT status FROM notifications WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $_GET['id']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row && $row['status'] === 'sent') {
        json_error('A sent notification cannot be edited', 422);
    }
}

crud_handle(notifications_definition());
