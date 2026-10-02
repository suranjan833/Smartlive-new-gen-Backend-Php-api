<?php

/**
 * POST /Admin/user_recharge.php   (Authorization: Bearer <admin token>)
 * Body: { "user_id": 5, "coins": 1000, "note"?: "..." }
 * Credits coins to a user's wallet (typed 'admin_recharge' in the ledger)
 * and records a row in `recharges` for the recharge module.
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
$account = require_admin($conn);
require_permission($account, 'users', 'edit');

$userId = (int) field('user_id', 0);
$coins  = (int) field('coins', 0);
$note   = (string) field('note', '');
$amount = (float) field('amount', 0.0);
$currency = strtoupper((string) field('currency', 'USD'));

if ($userId <= 0) {
    json_error('user_id is required', 422);
}
if ($coins <= 0) {
    json_error('coins must be a positive number', 422);
}

$stmt = $conn->prepare('SELECT id, name FROM users WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $userId);
$stmt->execute();
$target = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$target) {
    json_error('User not found', 404);
}

$description = $note !== '' ? 'Admin recharge: ' . $note : 'Admin recharge by ' . $account['name'];

$conn->begin_transaction();
try {
    $balance = wallet_adjust($conn, $userId, $coins, 'admin_recharge', $description);

    // recharges.admin_id references admins(id); sub-admins are recorded as NULL
    // (their name still appears in the wallet ledger description)
    $adminId = ($account['_type'] ?? '') === 'admin' ? (int) $account['id'] : null;
    $stmt = $conn->prepare(
        'INSERT INTO recharges (user_id, admin_id, amount, currency, type, coins, note)
         VALUES (?, ?, ?, ?, \'coins\', ?, ?)'
    );
    $stmt->bind_param('iisdss', $userId, $adminId, $amount, $currency, $coins, $note);
    $stmt->execute();
    $rechargeId = $stmt->insert_id;
    $stmt->close();

    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    json_error($e->getMessage(), 400);
}

json_success([
    'recharge_id' => $rechargeId,
    'user_id'     => $userId,
    'coins_added' => $coins,
    'balance'     => $balance,
], 'Recharge successful');
