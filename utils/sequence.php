<?php

require_once __DIR__ . '/db.php';

/**
 * Handles persistent, monotonic sequence generation for formatted IDs.
 * Deleted records NEVER reuse previous sequence IDs.
 */

const USER_ID_PREFIX = 'smart-lv-';
const USER_ID_PAD_LENGTH = 6;

/**
 * Ensure the sequence table exists.
 */
function ensure_sequences_table(mysqli $conn): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $conn->query("
        CREATE TABLE IF NOT EXISTS app_sequences (
            seq_name VARCHAR(50) PRIMARY KEY,
            last_value INT UNSIGNED NOT NULL DEFAULT 0
        ) ENGINE = InnoDB
    ");

    $ensured = true;
}

/**
 * Generate the next auto-increment user ID string (e.g. smart-lv-000001).
 * Monotonically increasing: even if a user is deleted, their ID is NEVER reused.
 */
function generate_user_id(mysqli $conn): string
{
    ensure_sequences_table($conn);

    do {
        $conn->query("
            INSERT INTO app_sequences (seq_name, last_value)
            VALUES ('user_id', LAST_INSERT_ID(1))
            ON DUPLICATE KEY UPDATE last_value = LAST_INSERT_ID(last_value + 1)
        ");
        $seq = (int) $conn->insert_id;
        $userCode = USER_ID_PREFIX . str_pad((string) $seq, USER_ID_PAD_LENGTH, '0', STR_PAD_LEFT);

        // Ensure no collision if an ID was manually inserted earlier
        $stmt = $conn->prepare('SELECT id FROM users WHERE user_id = ? LIMIT 1');
        $stmt->bind_param('s', $userCode);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    } while ($exists);

    return $userCode;
}

/**
 * If an existing user row lacks user_id, generate and save one.
 */
function ensure_user_has_custom_id(mysqli $conn, array &$user): string
{
    if (!empty($user['user_id'])) {
        return $user['user_id'];
    }

    $newCode = generate_user_id($conn);
    $stmt = $conn->prepare('UPDATE users SET user_id = ? WHERE id = ?');
    $stmt->bind_param('si', $newCode, $user['id']);
    $stmt->execute();
    $stmt->close();

    $user['user_id'] = $newCode;
    return $newCode;
}

/**
 * Resolve a user identifier (numeric integer ID or custom 'smart-lv-XXXXXX' string) to user record.
 */
function find_user_by_id_or_code(mysqli $conn, $identifier): ?array
{
    if ($identifier === null || $identifier === '') {
        return null;
    }

    $str = trim((string) $identifier);

    if (is_numeric($str)) {
        $intId = (int) $str;
        $stmt = $conn->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $intId);
    } else {
        $stmt = $conn->prepare('SELECT * FROM users WHERE user_id = ? LIMIT 1');
        $stmt->bind_param('s', $str);
    }

    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

