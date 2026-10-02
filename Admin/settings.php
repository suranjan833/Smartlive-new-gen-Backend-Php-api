<?php

/**
 * /Admin/settings.php   (Authorization: Bearer <admin token>)
 *   GET -> { "settings": { "audioEnabled": "1", ... } }
 *   PUT (or POST) with any subset of keys -> upsert each one
 *       body: { "audioEnabled": true, "maintenanceMode": false, ... }
 *       Values may be booleans, numbers or strings; they are stored as strings.
 */

require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/validation.php';
require_once __DIR__ . '/../utils/auth.php';

/** Known setting keys (new keys from api.md-style clients are still accepted). */
const SETTING_KEYS = [
    'audioEnabled', 'videoEnabled', 'maintenanceMode', 'registrationEnabled',
    'giftsEnabled', 'vipEnabled', 'agencyEnabled', 'resellerEnabled',
    'pushNotifications', 'emailNotifications',
];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $conn = db();
    $account = require_admin($conn);
    require_permission($account, 'settings', 'view');

    $rows  = $conn->query('SELECT setting_key, setting_value FROM settings')->fetch_all(MYSQLI_ASSOC);
    $pairs = [];
    foreach ($rows as $row) {
        $pairs[$row['setting_key']] = $row['setting_value'];
    }

    json_success(['settings' => $pairs], 'Settings loaded');
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = db();
    $account = require_admin($conn);
    require_permission($account, 'settings', 'edit');

    $body = request_data();
    if (empty($body)) {
        json_error('No settings provided', 422);
    }

    $stmt = $conn->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES (setting_value)'
    );

    $saved = [];
    foreach ($body as $key => $value) {
        $key = trim((string) $key);
        if ($key === '') {
            continue;
        }

        // normalize booleans to 1/0, everything else to its string form
        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        } elseif (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        } else {
            $value = (string) $value;
        }

        $stmt->bind_param('ss', $key, $value);
        $stmt->execute();
        $saved[$key] = $value;
    }
    $stmt->close();

    json_success(['settings' => $saved, 'known_keys' => SETTING_KEYS], 'Settings saved');
}

json_error('Method not allowed, use GET or PUT', 405);
