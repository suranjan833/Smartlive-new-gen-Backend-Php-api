<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/response.php';
require_once __DIR__ . '/sequence.php';

/**
 * Token-based authentication shared by App and Admin APIs.
 *
 * Client sends the token in one of these ways:
 *   Authorization: Bearer <token>
 *   X-Auth-Token: <token>
 *   ?token=<token>            (handy for quick browser testing)
 */

/** Read the token from the current request (or null). */
function get_request_token(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(\S+)/i', $header, $m)) {
        return $m[1];
    }

    if (!empty($_SERVER['HTTP_X_AUTH_TOKEN'])) {
        return $_SERVER['HTTP_X_AUTH_TOKEN'];
    }

    if (!empty($_GET['token'])) {
        return $_GET['token'];
    }

    return null;
}

/** Create a session row and return the plain token to give to the client. */
function create_session(mysqli $conn, string $userType, int $userId): string
{
    $token   = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + TOKEN_EXPIRY_DAYS * 86400);

    $stmt = $conn->prepare('INSERT INTO sessions (user_type, user_id, token, expires_at) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('siss', $userType, $userId, $token, $expires);
    $stmt->execute();
    $stmt->close();

    return $token;
}

/** Delete the current request's session (logout). */
function destroy_current_session(mysqli $conn): void
{
    $token = get_request_token();
    if ($token === null) {
        return;
    }

    $stmt = $conn->prepare('DELETE FROM sessions WHERE token = ?');
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $stmt->close();
}

/**
 * Guard for protected endpoints.
 * $userType: 'user' (App APIs) or 'admin' (Admin APIs).
 * Returns the account row (without password) or stops with a 401/403 JSON error.
 */
function require_auth(mysqli $conn, string $userType): array
{
    $token = get_request_token();
    if ($token === null) {
        json_error('Unauthorized: token missing', 401);
    }

    $table = $userType === 'admin' ? 'admins' : 'users';

    $stmt = $conn->prepare(
        "SELECT a.*, s.expires_at
         FROM sessions s
         JOIN {$table} a ON a.id = s.user_id
         WHERE s.token = ? AND s.user_type = ? AND s.expires_at > NOW()
         LIMIT 1"
    );
    $stmt->bind_param('ss', $token, $userType);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        json_error('Unauthorized: invalid or expired token', 401);
    }

    if ($userType === 'user' && ($row['status'] ?? '') === 'blocked') {
        json_error('Your account has been blocked', 403);
    }

    unset($row['password_hash'], $row['expires_at']);
    return $row;
}

/** Strip sensitive fields before returning a user/admin row to the client. */
function public_account(array $row): array
{
    unset($row['password_hash']);
    return $row;
}

/**
 * Admin-panel guard: accepts a SUPER ADMIN token or a SUB-ADMIN token.
 *
 * Returns the account row plus two synthetic keys:
 *   _type         'admin' | 'sub_admin'
 *   _permissions  flat string list like ["users.view", ...] (sub_admins only;
 *                 super admins get [] and bypass every permission check)
 */
function require_admin(mysqli $conn): array
{
    $token = get_request_token();
    if ($token === null) {
        json_error('Unauthorized: token missing', 401);
    }

    // Find the session (any admin-ish type), then load the account from its table
    $stmt = $conn->prepare(
        'SELECT user_type, user_id FROM sessions
         WHERE token = ? AND expires_at > NOW() AND user_type IN (\'admin\', \'sub_admin\')
         LIMIT 1'
    );
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $session = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$session) {
        json_error('Unauthorized: invalid or expired token', 401);
    }

    $userType = $session['user_type'];
    $table    = $userType === 'admin' ? 'admins' : 'sub_admins';

    $stmt = $conn->prepare("SELECT * FROM {$table} WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $session['user_id']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        json_error('Unauthorized: account no longer exists', 401);
    }

    if ($userType === 'sub_admin') {
        if (($row['status'] ?? '') === 'blocked') {
            json_error('This sub-admin account has been blocked', 403);
        }
        $row['_type']        = 'sub_admin';
        $row['_permissions'] = effective_permissions($conn, $row);
    } else {
        $row['_type']        = 'admin';
        $row['_permissions'] = [];
    }

    unset($row['password_hash']);
    return $row;
}

/**
 * Flatten a permissions payload into "module.ability" strings.
 * Accepts every format the admin panel might send:
 *   ["users.view", "hosts.view"]                       (flat list)
 *   {"users": {"view": true, "edit": false}}           (nested map)
 *   {"users.view": true}                               (flat map)
 *   ["*"]                                              (full access)
 */
function normalize_permissions($permissions): array
{
    if (!is_array($permissions)) {
        return [];
    }
    if (isset($permissions['permissions']) && is_array($permissions['permissions'])) {
        $permissions = $permissions['permissions'];   // unwrap accidental double nesting
    }

    $flat = [];
    foreach ($permissions as $key => $value) {
        if (is_int($key) && is_string($value)) {
            $flat[] = strtolower(trim($value));
        } elseif (is_string($key) && is_array($value)) {
            foreach ($value as $ability => $granted) {
                if ($granted === true || $granted === 1 || $granted === '1') {
                    $flat[] = strtolower(trim($key) . '.' . strtolower(trim((string) $ability)));
                }
            }
        } elseif (is_string($key) && ($value === true || $value === 1 || $value === '1')) {
            $flat[] = strtolower(trim($key));
        }
    }

    return array_values(array_unique(array_filter($flat)));
}

/**
 * Effective grants for a sub-admin = role permissions + own permissions (union).
 * The row needs `role_id` and `permissions` fields.
 */
function effective_permissions(mysqli $conn, array $subAdmin): array
{
    $own = [];
    if (!empty($subAdmin['permissions'])) {
        $decoded = is_array($subAdmin['permissions'])
            ? $subAdmin['permissions']
            : json_decode((string) $subAdmin['permissions'], true);
        if (is_array($decoded)) {
            $own = normalize_permissions($decoded);
        }
    }

    $rolePerms = [];
    if (!empty($subAdmin['role_id'])) {
        $roleId = (int) $subAdmin['role_id'];
        $stmt   = $conn->prepare('SELECT permissions FROM roles WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $roleId);
        $stmt->execute();
        $role = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($role && !empty($role['permissions'])) {
            $decoded = is_array($role['permissions'])
                ? $role['permissions']
                : json_decode((string) $role['permissions'], true);
            if (is_array($decoded)) {
                $rolePerms = normalize_permissions($decoded);
            }
        }
    }

    return array_values(array_unique(array_merge($rolePerms, $own)));
}

/** True when the account may do <module>.<ability>. Super admins always pass. */
function perm_allowed(array $account, string $module, string $ability): bool
{
    if (($account['_type'] ?? '') !== 'sub_admin') {
        return true;
    }

    $grants = $account['_permissions'] ?? [];
    if (in_array('*', $grants, true) || in_array('*.*', $grants, true)) {
        return true;
    }

    return in_array(strtolower($module) . '.' . strtolower($ability), $grants, true);
}

/** Stop with 403 unless the account holds <module>.<ability>. */
function require_permission(array $account, string $module, string $ability): void
{
    if (!perm_allowed($account, $module, $ability)) {
        json_error("Permission denied: {$module}.{$ability} is required", 403);
    }
}
