<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/response.php';

/**
 * Shared mysqli connection (one per request).
 * Usage: $conn = db();
 */
function db(): mysqli
{
    static $conn = null;

    if ($conn === null) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        try {
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            $conn->set_charset('utf8mb4');
        } catch (mysqli_sql_exception $e) {
            json_error('Database connection failed: ' . $e->getMessage(), 500);
        }
    }

    return $conn;
}
