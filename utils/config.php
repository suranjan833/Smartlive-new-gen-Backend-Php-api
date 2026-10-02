<?php
/**
 * Central configuration for the whole API project.
 * Change DB credentials here if your local MySQL differs.
 */

// ---- Database (XAMPP defaults) ----
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');               // XAMPP default: empty password
define('DB_NAME', 'live_streaming');

// ---- Auth ----
define('TOKEN_EXPIRY_DAYS', 7);      // session token lifetime
