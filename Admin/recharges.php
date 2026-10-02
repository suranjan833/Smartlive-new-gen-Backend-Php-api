<?php

/**
 * /Admin/recharges.php   (Authorization: Bearer <admin token>)
 *   GET  ?page=&limit=&user_id=&type=coins|balance  -> recharge history
 *   POST body: { user_id, coins, amount?, currency?: USD, note? }
 *        Credits the user's wallet and records a recharge row
 *        (same core logic as /Admin/user_recharge.php).
 */

require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/validation.php';
require_once __DIR__ . '/../utils/auth.php';
require_once __DIR__ . '/../utils/wallet.php';
require_once __DIR__ . '/../utils/crud.php';

$conn = db();
$account = require_admin($conn);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    require_permission($account, 'recharges', 'view');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_permission($account, 'users', 'edit');   // crediting a wallet is a user action
}

// ---- GET: recharge history -------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    [$page, $limit, $offset] = list_page_params();

    $where  = [];
    $params = [];
    $types  = '';

    if (!empty($_GET['user_id'])) {
        $where[]  = 'r.user_id = ?';
        $params[] = (int) $_GET['user_id'];
        $types   .= 'i';
    }
    if (!empty($_GET['type']) && in_array($_GET['type'], ['coins', 'balance'], true)) {
        $where[]  = 'r.type = ?';
        $params[] = $_GET['type'];
        $types   .= 's';
    }

    $whereSql = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM recharges r {$whereSql}");
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $total = (int) $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    $stmt = $conn->prepare(
        "SELECT r.*, u.name AS user_name, a.name AS admin_name
         FROM recharges r
         JOIN users u  ON u.id = r.user_id
         LEFT JOIN admins a ON a.id = r.admin_id
         {$whereSql}
         ORDER BY r.id DESC
         LIMIT ? OFFSET ?"
    );
    $bindTypes  = $types . 'ii';
    $bindParams = array_merge($params, [$limit, $offset]);
    $stmt->bind_param($bindTypes, ...$bindParams);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    json_success([
        'recharges'  => $rows,
        'pagination' => pagination_payload($page, $limit, $total),
    ], 'Recharges loaded');
}

// ---- POST: create a recharge ------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId   = (int) field('user_id', 0);
    $coins    = (int) field('coins', 0);
    $amount   = (float) field('amount', 0.0);
    $currency = strtoupper((string) field('currency', 'USD'));
    $note     = (string) field('note', '');

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
    ], 'Recharge successful', 201);
}

json_error('Method not allowed, use GET or POST', 405);
