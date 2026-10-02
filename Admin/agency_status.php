<?php

/**
 * PATCH|POST /Admin/agency_status.php   (Authorization: Bearer <admin token>)
 * Body: { "id": 5, "status": "active" | "inactive" | "banned" }
 */

require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/crud.php';

require_once __DIR__ . '/agencies_def.php';

crud_set_status(agencies_definition(), ['active', 'inactive', 'banned'], 'status', 'agencies');
