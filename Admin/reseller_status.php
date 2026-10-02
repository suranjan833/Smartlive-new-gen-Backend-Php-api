<?php

/**
 * PATCH|POST /Admin/reseller_status.php   (Authorization: Bearer <admin token>)
 * Body: { "id": 5, "status": "active" | "inactive" | "banned" }
 */

require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/crud.php';

require_once __DIR__ . '/resellers_def.php';

crud_set_status(resellers_definition(), ['active', 'inactive', 'banned'], 'status', 'resellers');
