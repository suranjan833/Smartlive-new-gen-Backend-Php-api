<?php

/**
 * /Admin/roles.php   (Authorization: Bearer <admin token>)
 *   GET    ?page=&limit=&search=  -> list
 *   GET    ?id=5                  -> detail
 *   POST   body: { name, description?, permissions?: {"users":{"view":true,...}, ...} }
 *   PUT    ?id=5  body: any subset
 *   PATCH  ?id=5  body: any subset
 *   DELETE ?id=5
 */

require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/crud.php';

require_once __DIR__ . '/roles_def.php';

crud_handle(roles_definition(), '', '', 'roles');
