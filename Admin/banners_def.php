<?php

/**
 * Shared definition for the Banners admin module.
 */

require_once __DIR__ . '/../utils/crud.php';

function banners_definition(): array
{
    $def = crud_definition();
    $def['table']       = 'banners';
    $def['fields']      = [
        'name'      => 's',
        'category'  => 's',
        'price'     => 'd',
        'coins'     => 'i',
        'image_url' => 's',
        'active'    => 'i',
    ];
    $def['required']    = ['name', 'image_url'];
    $def['search_cols'] = ['name', 'category'];
    $def['bool_fields'] = ['active'];
    $def['hidden_cols'] = [];
    return $def;
}
