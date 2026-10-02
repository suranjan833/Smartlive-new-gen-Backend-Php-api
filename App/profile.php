<?php

/**
 * GET /App/profile.php   (Authorization: Bearer <token>)
 * Returns the logged-in user's profile.
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

json_success(['user' => $user], 'Profile loaded');
