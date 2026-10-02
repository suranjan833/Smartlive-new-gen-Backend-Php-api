<?php

/**
 * POST /App/wallet_recharge.php   (Authorization: Bearer <token>)
 * Body: { "coins": 100, "reference": "optional-payment-ref" }
 *
 * LOCAL MODE: credits coins immediately without a payment gateway.
 * When a real gateway (Razorpay/Stripe/etc.) is added, verify the payment
 * first and keep the same wallet_adjust('recharge') call for the ledger.
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

$coins     = (int) field('coins', 0);
$reference = (string) field('reference', '');

if ($coins <= 0) {
    json_error('coins must be a positive number', 422);
}
if ($coins > 1000000) {
    json_error('coins exceeds the maximum recharge of 1,000,000', 422);
}

try {
    $conn->begin_transaction();

    $balance = wallet_adjust(
        $conn,
        (int) $user['id'],
        $coins,
        'recharge',
        $reference !== '' ? 'Recharge (ref: ' . $reference . ')' : 'Local recharge'
    );

    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    json_error($e->getMessage(), 400);
}

json_success(['coins_added' => $coins, 'balance' => $balance], 'Recharge successful');
