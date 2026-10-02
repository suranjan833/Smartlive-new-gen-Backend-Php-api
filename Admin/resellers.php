<?php

/**
 * /Admin/resellers.php   (Authorization: Bearer <admin token>)
 *   GET    ?page=&limit=&search=&status=active|inactive|banned  -> list
 *   GET    ?id=5                                                -> detail
 *   POST   body: { name, owner_name, email?, phone?, users_served?, packages?, revenue?, status? }
 *   PUT    ?id=5  body: any subset
 *   PATCH  ?id=5  body: any subset
 *   DELETE ?id=5
 */

require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/crud.php';

require_once __DIR__ . '/resellers_def.php';

crud_handle(resellers_definition(), '', '', 'resellers');
