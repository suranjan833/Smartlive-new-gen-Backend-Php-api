<?php

/**
 * /Admin/gifts.php   (Authorization: Bearer <admin token>)
 *   GET    ?page=&limit=&search=&status=1|0 (active filter)  -> list
 *   GET    ?id=5                                             -> detail
 *   POST   body: { name, category?, coins, price?, emoji?, image_url? }
 *          OR multipart/form-data with an "image" file upload
 *   PUT    ?id=5  body: any subset (multipart accepted too)
 *   PATCH  ?id=5  body: any subset
 *   DELETE ?id=5
 */

require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/crud.php';

require_once __DIR__ . '/gifts_def.php';

$conn = db();
$account = require_admin($conn);
require_permission($account, 'gifts', $_SERVER['REQUEST_METHOD'] === 'GET' ? 'view' : 'edit');

// ---- multipart image upload -> image_url -------------------------------
$uploaded = handle_upload('image', 'gifts');
if ($uploaded !== null) {
    request_set('image_url', $uploaded);       // inject into the body before CRUD
}

$method = $_SERVER['REQUEST_METHOD'];

// ---- POST: gifts must be active on create unless stated otherwise ------
if ($method === 'POST' && field('active') === null) {
    request_set('active', 1);
}

crud_handle(gifts_definition(), '', '', 'gifts');
