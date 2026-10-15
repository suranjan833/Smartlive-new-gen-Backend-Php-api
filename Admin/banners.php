<?php

/**
 * /Admin/banners.php   (Authorization: Bearer <admin token>)
 *   GET    ?page=&limit=&search=&status=1|0  -> list
 *   GET    ?id=5                             -> detail
 *   POST   body: { name, image_url, category?, price?, coins? }
 *          OR multipart/form-data with an "image" file upload
 *   PUT    ?id=5  body: any subset (multipart accepted too)
 *   PATCH  ?id=5  body: any subset
 *   DELETE ?id=5
 */

require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/crud.php';

require_once __DIR__ . '/banners_def.php';

$conn = db();
$account = require_admin($conn);
$method  = $_SERVER['REQUEST_METHOD'];
$ability = $method === 'GET' ? 'view' : ($method === 'DELETE' ? 'delete' : 'edit');
require_permission($account, 'banners', $ability);


// multipart image upload -> image_url
$uploaded = handle_upload('image', 'banners');
if ($uploaded !== null) {
    request_set('image_url', $uploaded);
}

// banners are active on create unless stated otherwise
if ($_SERVER['REQUEST_METHOD'] === 'POST' && field('active') === null) {
    request_set('active', 1);
}

crud_handle(banners_definition(), '', '', 'banners');
