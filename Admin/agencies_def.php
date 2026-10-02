<?php

/**
 * Shared definition for the Agencies admin module.
 */

require_once __DIR__ . '/../utils/crud.php';

function agencies_definition(): array
{
    $def = crud_definition();
    $def['table']       = 'agencies';
    $def['fields']      = [
        'name'       => 's',
        'owner_name' => 's',
        'email'      => 's',
        'phone'      => 's',
        'rating'     => 'd',
        'revenue'    => 'd',
        'status'     => 's',
    ];
    $def['required']    = ['name', 'owner_name'];
    $def['search_cols'] = ['name', 'email', 'owner_name'];
    $def['status_col']  = 'status';
    $def['status_map']  = ['active', 'inactive', 'banned'];
    return $def;
}
