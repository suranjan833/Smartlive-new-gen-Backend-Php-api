<?php

/**
 * POST /App/room_send_gift.php   (Authorization: Bearer <token>)
 * 
 * Sends a gift inside an Audio Party Room.
 * Deducts coins from sender wallet, credits host / seat receiver,
 * records room coins, and prepares payload for Node.js socket broadcast.
 * 
 * Body:
 * {
 *   "room_id": 105,
 *   "gift_id": 2,
 *   "receiver_id": 12,        // optional: receiver user_id. Defaults to room host
 *   "seat_index": 3,          // optional: seat index where receiver is sitting
 *   "quantity": 1             // default 1
 * }
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

$roomId     = (int) (field('room_id') ?? field('id') ?? 0);
$giftId     = (int) field('gift_id', 0);
$receiverId = (int) (field('receiver_id') ?? field('receiver_user_id') ?? 0);
$seatIndex  = (int) field('seat_index', 0);
$quantity   = max(1, (int) field('quantity', 1));

if ($roomId <= 0 || $giftId <= 0) {
    json_error('room_id and gift_id are required', 422);
}
if ($quantity > 100) {
    json_error('quantity cannot exceed 100 per request', 422);
}

// 1. Fetch Room details
$stmt = $conn->prepare("SELECT id, host_id, channel, status FROM audio_rooms WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $roomId);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$room) {
    json_error('Audio room not found', 404);
}
if ($room['status'] === 'ended') {
    json_error('This room has ended', 410);
}

// 2. Determine Receiver (Defaults to Host if not specified)
$hostId = (int) $room['host_id'];
if ($receiverId <= 0) {
    if ($seatIndex > 0) {
        $stmt = $conn->prepare('SELECT user_id FROM room_seats WHERE room_id = ? AND seat_index = ? LIMIT 1');
        $stmt->bind_param('ii', $roomId, $seatIndex);
        $stmt->execute();
        $seat = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($seat && !empty($seat['user_id'])) {
            $receiverId = (int) $seat['user_id'];
        }
    }
}

if ($receiverId <= 0) {
    $receiverId = $hostId;
}

if ($receiverId === (int) $user['id']) {
    json_error('You cannot send a gift to yourself', 422);
}

// 3. Fetch Gift Details
$stmt = $conn->prepare('SELECT id, name, category, coins, icon, image_url, animation_url FROM gifts WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $giftId);
$stmt->execute();
$gift = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$gift) {
    json_error('Gift not found', 404);
}

// 4. Fetch Receiver details
$stmt = $conn->prepare('SELECT id, name, user_id AS custom_user_id, avatar FROM users WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $receiverId);
$stmt->execute();
$receiverUser = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$receiverUser) {
    json_error('Gift receiver user not found', 404);
}

$totalCoins = (int) $gift['coins'] * $quantity;

$conn->begin_transaction();
try {
    // 1. Debit the sender wallet
    $senderBalance = wallet_adjust(
        $conn,
        (int) $user['id'],
        $totalCoins,
        'room_gift_sent',
        "Sent {$quantity}x {$gift['name']} in Audio Room #{$roomId}",
        $roomId
    );

    // 2. Credit the receiver wallet (or host)
    $receiverBalance = wallet_adjust(
        $conn,
        $receiverId,
        $totalCoins,
        'room_gift_received',
        "Received {$quantity}x {$gift['name']} in Audio Room #{$roomId}",
        $roomId
    );

    // 3. Bump room coins_earned
    $stmt = $conn->prepare('UPDATE audio_rooms SET coins_earned = coins_earned + ? WHERE id = ?');
    $stmt->bind_param('ii', $totalCoins, $roomId);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    json_error('Transaction failed: ' . $e->getMessage(), 422);
}

$broadcastPayload = [
    'room_id'         => $roomId,
    'channel'         => $room['channel'],
    'sender'          => [
        'id'          => (int) $user['id'],
        'user_id'     => $user['user_id'] ?? null,
        'name'        => $user['name'],
        'avatar'      => $user['avatar'] ?? null,
    ],
    'receiver'        => [
        'id'          => $receiverId,
        'user_id'     => $receiverUser['custom_user_id'] ?? null,
        'name'        => $receiverUser['name'],
        'avatar'      => $receiverUser['avatar'] ?? null,
    ],
    'gift'            => [
        'id'          => (int) $gift['id'],
        'name'        => $gift['name'],
        'icon'        => $gift['icon'] ?? $gift['image_url'] ?? null,
        'anim_type'   => !empty($gift['animation_url']) ? 'svga' : 'css',
        'anim_url'    => $gift['animation_url'] ?? null,
        'coins'       => (int) $gift['coins'],
        'quantity'    => $quantity,
        'total_coins' => $totalCoins,
    ],
    'seat_index'      => $seatIndex > 0 ? $seatIndex : null,
    'timestamp'       => time(),
];

json_success([
    'remaining_coins' => $senderBalance,
    'total_coins'     => $totalCoins,
    'gift'            => $gift['name'],
    'broadcast_data'  => $broadcastPayload
], 'Gift sent successfully');
