<?php

/**
 * PATCH|POST /Admin/host_status.php   (Authorization: Bearer <admin token>)
 * Body: { "id": 5, "status": "online" | "offline" | "banned" }
 */

require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/crud.php';

require_once __DIR__ . '/hosts_def.php';

crud_set_status(hosts_definition(), ['online', 'offline', 'banned'], 'status', 'hosts');
