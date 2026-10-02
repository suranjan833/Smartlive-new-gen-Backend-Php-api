<?php

/**
 * GET /App/wallet.php   (Authorization: Bearer <token>)
 * Returns the current user's coin balance.
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
$wallet = ensure_wallet($conn, (int) $user['id']);

json_success(['coins' => (int) $wallet['coins']], 'Wallet balance');
