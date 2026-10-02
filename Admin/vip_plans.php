<?php

/**
 * /Admin/vip_plans.php   (Authorization: Bearer <admin token>)
 *   GET    ?page=&limit=&search=          -> list
 *   GET    ?id=5                          -> detail
 *   POST   body: { name, price, currency?: USD, duration_days?: 30, bonus_coins?, active? }
 *   PUT    ?id=5  body: any subset
 *   PATCH  ?id=5  body: any subset
 *   DELETE ?id=5
 */

require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/crud.php';

require_once __DIR__ . '/vip_plans_def.php';

// active by default on create
if ($_SERVER['REQUEST_METHOD'] === 'POST' && field('active') === null) {
    request_set('active', 1);
}

crud_handle(vip_plans_definition(), '', '', 'vip_plans');
