<?php

/**
 * GET /App/gifts.php
 * Gift catalog with coin prices (used to render the gift tray in the app).
 * Only ACTIVE gifts are returned; ?all=1 also includes inactive ones.
 */

require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed, use GET', 405);
}

$conn = db();

$sql    = 'SELECT id, name, category, icon, emoji, coins, price, image_url FROM gifts';
$active = empty($_GET['all']);

if ($active) {
    $result = $conn->prepare($sql . ' WHERE active = 1 ORDER BY coins ASC');
    $result->execute();
    $rows = $result->get_result()->fetch_all(MYSQLI_ASSOC);
    $result->close();
} else {
    $rows = $conn->query($sql . ' ORDER BY coins ASC')->fetch_all(MYSQLI_ASSOC);
}

json_success(['gifts' => $rows], 'Gifts loaded');
