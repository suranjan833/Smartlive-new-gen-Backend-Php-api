<?php

require_once __DIR__ . '/db.php';

/**
 * Shared wallet helpers used by App wallet APIs and the gift API.
 *
 * wallet_adjust() expects the CALLER to have started a transaction
 * ($conn->begin_transaction()) and to commit/rollback.
 */

const WALLET_CREDIT_TYPES = ['recharge', 'gift_received', 'admin_credit', 'admin_recharge'];
const WALLET_DEBIT_TYPES  = ['gift_sent', 'admin_debit'];

/** Return the wallet row for a user, creating it (0 coins) if missing. */
function ensure_wallet(mysqli $conn, int $userId): array
{
    $stmt = $conn->prepare('SELECT * FROM wallets WHERE user_id = ? LIMIT 1');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $wallet = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($wallet) {
        return $wallet;
    }

    $stmt = $conn->prepare('INSERT INTO wallets (user_id) VALUES (?)');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();

    return [
        'user_id' => $userId,
        'coins'   => 0,
    ];
}

/**
 * Move coins in or out of a wallet and write a ledger row.
 * Throws Exception (caller should rollback + show the message).
 * Returns the new balance.
 */
function wallet_adjust(
    mysqli $conn,
    int $userId,
    int $coins,
    string $type,
    string $description = '',
    ?int $streamId = null
): int {
    if ($coins <= 0) {
        throw new Exception('Coin amount must be positive');
    }

    $isDebit = in_array($type, WALLET_DEBIT_TYPES, true);
    if (!$isDebit && !in_array($type, WALLET_CREDIT_TYPES, true)) {
        throw new Exception('Unknown transaction type');
    }

    ensure_wallet($conn, $userId);

    // Lock the row for the duration of the transaction
    $stmt = $conn->prepare('SELECT coins FROM wallets WHERE user_id = ? FOR UPDATE');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        throw new Exception('Wallet not found');
    }

    $newBalance = (int) $row['coins'] + ($isDebit ? -$coins : $coins);
    if ($newBalance < 0) {
        throw new Exception('Insufficient wallet balance');
    }

    $stmt = $conn->prepare('UPDATE wallets SET coins = ? WHERE user_id = ?');
    $stmt->bind_param('ii', $newBalance, $userId);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare(
        'INSERT INTO wallet_transactions (user_id, type, coins, balance_after, stream_id, description)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('isiiss', $userId, $type, $coins, $newBalance, $streamId, $description);
    $stmt->execute();
    $stmt->close();

    return $newBalance;
}
