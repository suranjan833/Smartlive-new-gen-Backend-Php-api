<?php

/**
 * /Admin/levels.php   (Authorization: Bearer <admin token>)
 *   GET    ?page=&limit=&search=title        -> list (ascending by level)
 *   GET    ?id=5                             -> detail
 *   POST   body: { level, title, xp_required?, reward?, color? }
 *   PUT    ?id=5  body: any subset
 *   PATCH  ?id=5  body: any subset
 *   DELETE ?id=5
 */

require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/crud.php';

require_once __DIR__ . '/levels_def.php';

crud_handle(levels_definition(), '', '', 'levels');
