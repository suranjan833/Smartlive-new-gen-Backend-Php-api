<?php

/**
 * POST /App/equip_item.php   (Authorization: Bearer <token>)
 * Body: { "item_id": 2, "equip": true|false }
 * Equips or un-equips an item from the user's bag.
 */

require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/validation.php';
require_once __DIR__ . '/../utils/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed, use POST', 405);
}

$conn = db();
$user = require_auth($conn, 'user');

$itemId = (int) field('item_id', 0);
$equip  = field('equip', true);
$equip  = is_bool($equip) ? $equip : in_array(strtolower((string) $equip), ['1', 'true', 'yes'], true);

if ($itemId <= 0) {
    json_error('item_id is required', 422);
}

// Find item in user bag and check if expired
$stmt = $conn->prepare('
    SELECT ub.id, ub.expires_at, si.type
    FROM user_bag ub
    JOIN store_items si ON si.id = ub.item_id
    WHERE ub.user_id = ? AND ub.item_id = ?
    LIMIT 1
');
$stmt->bind_param('ii', $user['id'], $itemId);
$stmt->execute();
$bag = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$bag) {
    json_error('You do not own this item', 404);
}
if (strtotime($bag['expires_at']) <= time()) {
    json_error('This item has expired. Please renew it in the store', 422);
}

$conn->begin_transaction();
try {
    if ($equip) {
        // Unequip any other item of the same type (e.g. frame or ride)
        $stmt = $conn->prepare('
            UPDATE user_bag ub
            JOIN store_items si ON si.id = ub.item_id
            SET ub.is_equipped = 0
            WHERE ub.user_id = ? AND si.type = ?
        ');
        $stmt->bind_param('is', $user['id'], $bag['type']);
        $stmt->execute();
        $stmt->close();

        // Equip this item
        $stmt = $conn->prepare('UPDATE user_bag SET is_equipped = 1 WHERE id = ?');
        $stmt->bind_param('i', $bag['id']);
        $stmt->execute();
        $stmt->close();
    } else {
        // Unequip this item
        $stmt = $conn->prepare('UPDATE user_bag SET is_equipped = 0 WHERE id = ?');
        $stmt->bind_param('i', $bag['id']);
        $stmt->execute();
        $stmt->close();
    }

    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    json_error($e->getMessage(), 500);
}

json_success(['item_id' => $itemId, 'is_equipped' => $equip], $equip ? 'Item equipped' : 'Item unequipped');

