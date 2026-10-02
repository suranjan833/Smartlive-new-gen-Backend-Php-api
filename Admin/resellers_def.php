<?php

/**
 * Shared definition for the Resellers admin module.
 */

require_once __DIR__ . '/../utils/crud.php';

function resellers_definition(): array
{
    $def = crud_definition();
    $def['table']       = 'resellers';
    $def['fields']      = [
        'name'         => 's',
        'owner_name'   => 's',
        'email'        => 's',
        'phone'        => 's',
        'users_served' => 'i',
        'packages'     => 'i',
        'revenue'      => 'd',
        'status'       => 's',
    ];
    $def['required']    = ['name', 'owner_name'];
    $def['search_cols'] = ['name', 'email', 'owner_name'];
    $def['status_col']  = 'status';
    $def['status_map']  = ['active', 'inactive', 'banned'];
    return $def;
}
