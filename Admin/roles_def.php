<?php

/**
 * Shared definition for the Roles admin module.
 */

require_once __DIR__ . '/../utils/crud.php';

function roles_definition(): array
{
    $def = crud_definition();
    $def['table']       = 'roles';
    $def['fields']      = [
        'name'        => 's',
        'description' => 's',
        'permissions' => 's',
    ];
    $def['required']    = ['name'];
    $def['search_cols'] = ['name'];
    $def['json_fields'] = ['permissions'];
    return $def;
}
