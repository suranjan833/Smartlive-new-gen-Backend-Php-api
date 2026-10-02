<?php

/**
 * GET /App/wallet_transactions.php   (Authorization: Bearer <token>)
 * Query params: ?type=recharge|gift_sent|gift_received|admin_credit|admin_debit&page=1&limit=20
 * Returns the user's coin ledger (newest first) + current balance.
 */

require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/auth.php';
require_once __DIR__ . '/../utils/wallet.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed, use GET', 405);
}

$conn = db();
$user = require_auth($conn, 'user');
$userId = (int) $user['id'];

$type  = trim($_GET['type'] ?? '');
$page  = max(1, (int) ($_GET['page'] ?? 1));
$limit = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;

$where  = 'WHERE user_id = ?';
$params = [$userId];
$types  = 'i';

if ($type !== '') {
    $allowed = array_merge(WALLET_CREDIT_TYPES, WALLET_DEBIT_TYPES);
    if (!in_array($type, $allowed, true)) {
        json_error('Unknown transaction type filter', 422);
    }
    $where   .= ' AND type = ?';
    $params[] = $type;
    $types   .= 's';
}

$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM wallet_transactions {$where}");
$stmt->bind_param($types, ...$params);
$stmt->execute();
$total = (int) $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare(
    "SELECT id, type, coins, balance_after, stream_id, description, created_at
     FROM wallet_transactions {$where}
     ORDER BY id DESC
     LIMIT ? OFFSET ?"
);
$bindTypes = $types . 'ii';
$bindParams = array_merge($params, [$limit, $offset]);
$stmt->bind_param($bindTypes, ...$bindParams);
$stmt->execute();
$transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$wallet = ensure_wallet($conn, $userId);

json_success([
    'balance'      => (int) $wallet['coins'],
    'transactions' => $transactions,
    'pagination'   => [
        'page'        => $page,
        'limit'       => $limit,
        'total'       => $total,
        'total_pages' => (int) ceil($total / $limit),
    ],
], 'Transactions loaded');
