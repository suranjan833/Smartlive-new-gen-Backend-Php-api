<?php

/**
 * Shared definition for the Sub-admins admin module.
 */

require_once __DIR__ . '/../utils/crud.php';

function sub_admins_definition(): array
{
    $def = crud_definition();
    $def['table']       = 'sub_admins';
    $def['fields']      = [
        'name'        => 's',
        'email'       => 's',
        'password'    => 's',
        'role'        => 's',
        'role_id'     => 'i',
        'permissions' => 's',
        'status'      => 's',
    ];
    $def['required']    = ['name', 'email', 'password'];
    $def['search_cols'] = ['name', 'email'];
    $def['status_col']  = 'status';
    $def['status_map']  = ['active', 'blocked'];
    $def['json_fields'] = ['permissions'];
    $def['hash_password'] = 'password';
    $def['unique_cols']   = ['email'];
    $def['zero_as_null']  = ['role_id'];
    return $def;
}
