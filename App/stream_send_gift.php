<?php

/**
 * POST /App/stream_send_gift.php   (Authorization: Bearer <token>)
 * Body: { "stream_id": 5, "gift_id": 2, "quantity": 1 }
 * Atomically moves coins sender -> host and records the gift.
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

$streamId = (int) field('stream_id', 0);
$giftId   = (int) field('gift_id', 0);
$quantity = max(1, (int) field('quantity', 1));

if ($streamId <= 0 || $giftId <= 0) {
    json_error('stream_id and gift_id are required', 422);
}
if ($quantity > 100) {
    json_error('quantity cannot exceed 100 per request', 422);
}

// Stream must be live
$stmt = $conn->prepare("SELECT id, host_id FROM streams WHERE id = ? AND status = 'live' LIMIT 1");
$stmt->bind_param('i', $streamId);
$stmt->execute();
$stream = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$stream) {
    json_error('Stream not found or not live', 404);
}

$hostId = (int) $stream['host_id'];
if ($hostId === (int) $user['id']) {
    json_error('You cannot send a gift to your own stream', 422);
}

// Gift catalog lookup
$stmt = $conn->prepare('SELECT id, name, coins FROM gifts WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $giftId);
$stmt->execute();
$gift = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$gift) {
    json_error('Gift not found', 404);
}

$totalCoins = (int) $gift['coins'] * $quantity;

$conn->begin_transaction();
try {
    // 1. Debit the sender
    $senderBalance = wallet_adjust(
        $conn,
        (int) $user['id'],
        $totalCoins,
        'gift_sent',
        'Sent ' . $quantity . 'x ' . $gift['name'],
        $streamId
    );

    // 2. Credit the host
    $hostBalance = wallet_adjust(
        $conn,
        $hostId,
        $totalCoins,
        'gift_received',
        'Received ' . $quantity . 'x ' . $gift['name'],
        $streamId
    );

    // 3. Record the gift + bump stream coin stats
    $stmt = $conn->prepare(
        'INSERT INTO stream_gifts (stream_id, gift_id, sender_id, receiver_id, quantity, coins)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('iiiiii', $streamId, $giftId, $user['id'], $hostId, $quantity, $totalCoins);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare('UPDATE streams SET coins_earned = coins_earned + ? WHERE id = ?');
    $stmt->bind_param('ii', $totalCoins, $streamId);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    json_error($e->getMessage(), 400);
}

json_success([
    'stream_id'     => $streamId,
    'gift'          => $gift['name'],
    'quantity'      => $quantity,
    'coins_spent'   => $totalCoins,
    'your_balance'  => $senderBalance,
], 'Gift sent successfully');
