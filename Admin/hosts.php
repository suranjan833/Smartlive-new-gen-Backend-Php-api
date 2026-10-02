<?php

/**
 * /Admin/hosts.php   (Authorization: Bearer <admin token>)
 *   GET    ?page=&limit=&search=&status=online|offline|banned  -> list (with agency name)
 *   GET    ?id=5                                               -> detail
 *   POST   body: { name, agency_id?, email?, phone?, followers?, level?, earnings?, status? }
 *   PUT    ?id=5  body: any subset of the POST fields
 *   PATCH  ?id=5  body: any subset (partial update)
 *   DELETE ?id=5
 */

require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/crud.php';

require_once __DIR__ . '/hosts_def.php';

crud_handle(
    hosts_definition(),
    ', a.name AS agency_name',
    'LEFT JOIN agencies a ON a.id = t.agency_id',
    'hosts'
);
