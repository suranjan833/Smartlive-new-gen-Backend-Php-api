<?php

/**
 * Shared definition for the Gifts admin module.
 */

require_once __DIR__ . '/../utils/crud.php';

function gifts_definition(): array
{
    $def = crud_definition();
    $def['table']       = 'gifts';
    $def['fields']      = [
        'name'      => 's',
        'category'  => 's',
        'icon'      => 's',
        'emoji'     => 's',
        'coins'     => 'i',
        'price'     => 'd',
        'image_url' => 's',
        'active'    => 'i',
    ];
    $def['required']    = ['name', 'coins'];
    $def['search_cols'] = ['name', 'category'];
    $def['json_fields'] = [];
    $def['bool_fields'] = ['active'];
    $def['unique_cols'] = ['name'];
    $def['hidden_cols'] = [];
    $def['order']       = 'coins ASC';
    return $def;
}
