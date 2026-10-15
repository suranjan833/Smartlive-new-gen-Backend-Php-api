<?php

/**
 * GET /App/store_items.php
 * Query params: ?type=frame|entry|ride|badge|bubble
 * Returns store catalog with coin prices and duration.
 */

require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed, use GET', 405);
}

$conn = db();
$type = trim($_GET['type'] ?? '');

$sql = 'SELECT id, name, type, coins, days, image_url, preview_url, active FROM store_items WHERE active = 1';
$params = [];
$types  = '';

if ($type !== '') {
    $sql .= ' AND type = ?';
    $params[] = $type;
    $types   .= 's';
}

$sql .= ' ORDER BY type ASC, coins ASC';

$stmt = $conn->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

json_success(['items' => $items], 'Store items loaded');

