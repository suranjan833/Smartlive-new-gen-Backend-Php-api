<?php

/**
 * POST /Admin/wallet_adjust.php   (Authorization: Bearer <admin token>)
 * Body: { "user_id": 5, "coins": 100, "action": "credit" | "debit" }
 * Manual wallet adjustment by admin (support/refunds/moderation).
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

$rawUser = field('user_id');
$coins   = (int) field('coins', 0);
$action  = strtolower((string) field('action', ''));

if ($rawUser === null || $rawUser === '') {
    json_error('user_id is required', 422);
}
if ($coins <= 0) {
    json_error('coins must be a positive number', 422);
}
if ($action !== 'credit' && $action !== 'debit') {
    json_error('action must be "credit" or "debit"', 422);
}

$target = find_user_by_id_or_code($conn, $rawUser);
if (!$target) {
    json_error('User not found', 404);
}
$userId = (int) $target['id'];

$type = $action === 'credit' ? 'admin_credit' : 'admin_debit';

$description = 'Admin adjust by ' . $account['name'];

$conn->begin_transaction();
try {
    $balance = wallet_adjust($conn, $userId, $coins, $type, $description);
    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    json_error($e->getMessage(), 400);
}

json_success(['user_id' => $userId, 'balance' => $balance], 'Wallet ' . $action . ' successful');
