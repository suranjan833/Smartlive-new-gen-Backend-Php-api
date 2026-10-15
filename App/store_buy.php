<?php

/**
 * POST /App/store_buy.php   (Authorization: Bearer <token>)
 * Body: { "item_id": 2 }
 * Purchases an item from the store using coins and adds it to the user's bag.
 */

require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/validation.php';
require_once __DIR__ . '/../utils/auth.php';
require_once __DIR__ . '/../utils/wallet.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed, use POST', 405);
}

$conn = db();
$user = require_auth($conn, 'user');

$itemId = (int) field('item_id', 0);
if ($itemId <= 0) {
    json_error('item_id is required', 422);
}

$stmt = $conn->prepare('SELECT id, name, type, coins, days FROM store_items WHERE id = ? AND active = 1 LIMIT 1');
$stmt->bind_param('i', $itemId);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$item) {
    json_error('Store item not found or unavailable', 404);
}

$priceCoins = (int) $item['coins'];
$days       = max(1, (int) $item['days']);

$conn->begin_transaction();
try {
    // 1. Debit coins from wallet (with row-level lock)
    if ($priceCoins > 0) {
        $balance = wallet_adjust(
            $conn,
            (int) $user['id'],
            $priceCoins,
            'admin_debit', // transaction ledger debit
            'Purchased store item: ' . $item['name']
        );
    } else {
        $balance = (int) ensure_wallet($conn, (int) $user['id'])['coins'];
    }

    // 2. Add to user_bag with calculated expiry
    // If user already owns this active item, extend the expiry date!
    $stmt = $conn->prepare('
        SELECT id, expires_at FROM user_bag
        WHERE user_id = ? AND item_id = ? AND expires_at > NOW()
        LIMIT 1
    ');
    $stmt->bind_param('ii', $user['id'], $itemId);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        // Extend existing
        $newExpiry = date('Y-m-d H:i:s', strtotime($existing['expires_at']) + ($days * 86400));
        $stmt = $conn->prepare('UPDATE user_bag SET expires_at = ? WHERE id = ?');
        $stmt->bind_param('si', $newExpiry, $existing['id']);
        $stmt->execute();
        $bagId = (int) $existing['id'];
        $stmt->close();
    } else {
        // New purchase
        $newExpiry = date('Y-m-d H:i:s', time() + ($days * 86400));
        $stmt = $conn->prepare('
            INSERT INTO user_bag (user_id, item_id, is_equipped, expires_at)
            VALUES (?, ?, 0, ?)
        ');
        $stmt->bind_param('iis', $user['id'], $itemId, $newExpiry);
        $stmt->execute();
        $bagId = (int) $conn->insert_id;
        $stmt->close();
    }

    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    json_error($e->getMessage(), 400);
}

json_success([
    'bag_id'         => $bagId,
    'item'           => $item,
    'expires_at'     => $newExpiry,
    'wallet_balance' => $balance,
], 'Item purchased successfully', 201);

