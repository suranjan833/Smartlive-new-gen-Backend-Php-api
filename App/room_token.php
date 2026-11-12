<?php

/**
 * POST /App/room_token.php   (Authorization: Bearer <token>)
 * 
 * Generates RTC authentication credentials / tokens for Agora / ZegoCloud voice channels.
 * 
 * Body:
 * {
 *   "room_id": 105,
 *   "role": "publisher" | "subscriber",     // default "subscriber"
 *   "provider": "zego" | "agora"            // default "zego"
 * }
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

$roomId   = (int) (field('room_id') ?? field('id') ?? 0);
$role     = strtolower(trim((string) (field('role') ?? 'subscriber')));
$provider = strtolower(trim((string) (field('provider') ?? 'zego')));

if ($roomId <= 0) {
    json_error('room_id is required', 422);
}

// 1. Fetch Room Details
$stmt = $conn->prepare('SELECT id, channel, host_id, status FROM audio_rooms WHERE id = ? LIMIT 1');
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

$channelName = $room['channel'] ?: ('room_' . $room['id']);
$userId      = (int) $user['id'];
$isHost      = ((int) $room['host_id'] === $userId);

// If user is host, force publisher role
if ($isHost) {
    $role = 'publisher';
} else if ($role === 'publisher') {
    // Check if user is on any seat in this room
    $stmt = $conn->prepare('SELECT seat_index FROM room_seats WHERE room_id = ? AND user_id = ? LIMIT 1');
    $stmt->bind_param('ii', $roomId, $userId);
    $stmt->execute();
    $hasSeat = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // If not sitting on a mic, publisher role is downgraded to subscriber
    if (!$hasSeat) {
        $role = 'subscriber';
    }
}

// Configuration defaults (can be customized via environment or database settings)
$agoraAppId      = defined('AGORA_APP_ID') ? AGORA_APP_ID : (getenv('AGORA_APP_ID') ?: 'YOUR_AGORA_APP_ID');
$agoraAppCert    = defined('AGORA_APP_CERTIFICATE') ? AGORA_APP_CERTIFICATE : (getenv('AGORA_APP_CERTIFICATE') ?: '');

// ZegoCloud AppID & AppSign
$zegoAppId       = defined('ZEGO_APP_ID') ? ZEGO_APP_ID : (int) (getenv('ZEGO_APP_ID') ?: 0);
$zegoAppSign     = defined('ZEGO_APP_SIGN') ? ZEGO_APP_SIGN : (getenv('ZEGO_APP_SIGN') ?: '');

$expireSeconds   = 3600 * 24; // 24 hours
$expireTimestamp = time() + $expireSeconds;

// Generate simulated / standard token payload
// In production with Agora RTC dynamic token generator, builder class can be invoked here.
// For ZegoCloud, UserID and RoomID are used with AppSign or ServerToken.
$token = hash_hmac('sha256', "{$channelName}:{$userId}:{$role}:{$expireTimestamp}", $agoraAppCert ?: 'smartlive_secret_key');

$data = [
    'provider'         => $provider,
    'room_id'          => $roomId,
    'channel_name'     => $channelName,
    'user_id'          => $userId,
    'custom_user_id'   => $user['user_id'] ?? ('smart-lv-' . str_pad($userId, 6, '0', STR_PAD_LEFT)),
    'user_name'        => $user['name'],
    'role'             => $role,            // publisher | subscriber
    'is_host'          => $isHost,
    'token'            => $token,
    'expires_in'       => $expireSeconds,
    'expires_at'       => date('Y-m-d H:i:s', $expireTimestamp),
    'agora'            => [
        'app_id'       => $agoraAppId,
        'channel'      => $channelName,
        'uid'          => $userId,
        'token'        => $token,
        'role'         => $role === 'publisher' ? 1 : 2, // 1: Broadcaster, 2: Audience
    ],
    'zego'             => [
        'app_id'       => $zegoAppId ?: 123456789,
        'room_id'      => $channelName,
        'user_id'      => (string) $userId,
        'user_name'    => $user['name'],
        'token'        => $token,
        'role'         => $role,
    ]
];

json_success($data, 'Voice RTC token generated successfully');
