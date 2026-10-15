<?php
/**
 * Central configuration for the whole API project.
 * Automatically switches between Local XAMPP and Live Hosting (cPanel).
 */

// Detect if running on live server or local environment
$isLiveServer = !in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1'], true)
             && !in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', 'localhost:8000'], true)
             && (php_sapi_name() !== 'cli' || getenv('APP_ENV') === 'production');

if ($isLiveServer) {
    // ---- Live Hosting (cPanel) ----
    define('DB_HOST', 'localhost');
    define('DB_USER', 'saptahi1_smartlive');
    define('DB_PASS', 'saptahi1_smartlive');
    define('DB_NAME', 'saptahi1_smartlive');
} else {
    // ---- Local Environment (XAMPP) ----
    define('DB_HOST', '127.0.0.1');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'live_streaming');
}

// ---- Auth ----
define('TOKEN_EXPIRY_DAYS', 7);      // session token lifetime

