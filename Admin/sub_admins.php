<?php

/**
 * /Admin/sub_admins.php   (Authorization: Bearer <admin token>)
 *   GET    ?page=&limit=&search=&status=active|blocked  -> list
 *   GET    ?id=5                                        -> detail
 *   POST   body: { name, email, password, role?, role_id?, permissions?: ["users.view", ...], status? }
 *   PUT    ?id=5  body: any subset
 *   PATCH  ?id=5  body: any subset
 *   DELETE ?id=5
 */

require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/crud.php';

require_once __DIR__ . '/sub_admins_def.php';

// Sub-admins manage their own kind; viewing requires sub_admins.view etc.
crud_handle(sub_admins_definition(), '', '', 'sub_admins');
