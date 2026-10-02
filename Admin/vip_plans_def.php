<?php

/**
 * Shared definition for the VIP plans admin module.
 */

require_once __DIR__ . '/../utils/crud.php';

function vip_plans_definition(): array
{
    $def = crud_definition();
    $def['table']       = 'vip_plans';
    $def['fields']      = [
        'name'          => 's',
        'price'         => 'd',
        'currency'      => 's',
        'duration_days' => 'i',
        'bonus_coins'   => 'i',
        'active'        => 'i',
    ];
    $def['required']    = ['name', 'price'];
    $def['search_cols'] = ['name'];
    $def['bool_fields'] = ['active'];
    $def['hidden_cols'] = [];
    return $def;
}
