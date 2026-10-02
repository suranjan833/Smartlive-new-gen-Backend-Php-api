<?php

/**
 * Shared definition for the Notifications admin module.
 */

require_once __DIR__ . '/../utils/crud.php';

function notifications_definition(): array
{
    $def = crud_definition();
    $def['table']       = 'notifications';
    $def['fields']      = [
        'title'    => 's',
        'message'  => 's',
        'type'     => 's',
        'audience' => 's',
        'status'   => 's',
    ];
    $def['required']    = ['title', 'message'];
    $def['search_cols'] = ['title', 'message'];
    $def['status_col']  = 'status';
    $def['status_map']  = ['draft', 'sent'];
    $def['hidden_cols'] = [];
    return $def;
}
