<?php

/**
 * GET /App/my_bag.php   (Authorization: Bearer <token>)
 * Returns user backpack / purchased items and whether they are equipped.
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

$stmt = $conn->prepare('
    SELECT ub.id AS bag_id, ub.item_id, ub.is_equipped, ub.expires_at, ub.created_at AS purchased_at,
           si.name, si.type, si.image_url, si.preview_url,
           (ub.expires_at > NOW()) AS is_active
    FROM user_bag ub
    JOIN store_items si ON si.id = ub.item_id
    WHERE ub.user_id = ?
    ORDER BY ub.is_equipped DESC, ub.id DESC
');
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

json_success(['bag' => $items], 'My bag items loaded');

