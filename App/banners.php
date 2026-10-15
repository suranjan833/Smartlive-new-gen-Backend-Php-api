<?php

/**
 * GET /App/banners.php
 * Query params: ?category=event|home (optional)
 * Returns active banners for carousel and event promotions.
 */

require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed, use GET', 405);
}

$conn = db();
$category = trim($_GET['category'] ?? '');

$sql = 'SELECT id, name, category, price, coins, image_url, active, created_at FROM banners WHERE active = 1';
$params = [];
$types  = '';

if ($category !== '') {
    $sql .= ' AND category = ?';
    $params[] = $category;
    $types   .= 's';
}

$sql .= ' ORDER BY id DESC LIMIT 20';

$stmt = $conn->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$banners = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

json_success(['banners' => $banners], 'Banners loaded');

