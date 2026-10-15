<?php

/**
 * GET /App/level.php   (Authorization: Bearer <token>)
 * Returns the logged-in user's level, XP, next milestone, and all level rewards.
 */

require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed, use GET', 405);
}

$conn = db();
$user = require_auth($conn, 'user');

$myLevel = (int) ($user['level'] ?? 1);
$myXP    = (int) ($user['xp'] ?? 0);

// Fetch all levels
$stmt = $conn->query('SELECT level, title, xp_required, reward, color FROM levels ORDER BY level ASC');
$ladder = $stmt->fetch_all(MYSQLI_ASSOC);

// Calculate next level
$nextLevel = null;
foreach ($ladder as $lv) {
    if ((int) $lv['level'] > $myLevel) {
        $nextLevel = $lv;
        break;
    }
}

$xpNeeded = $nextLevel ? max(0, (int) $nextLevel['xp_required'] - $myXP) : 0;
$progressPercent = ($nextLevel && (int) $nextLevel['xp_required'] > 0)
    ? min(100, round(($myXP / (int) $nextLevel['xp_required']) * 100, 1))
    : 100;

json_success([
    'current_level'    => $myLevel,
    'current_xp'       => $myXP,
    'next_level'       => $nextLevel ? (int) $nextLevel['level'] : null,
    'xp_needed'        => $xpNeeded,
    'progress_percent' => $progressPercent,
    'ladder'           => $ladder,
], 'Level progress loaded');

