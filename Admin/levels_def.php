<?php

/**
 * Shared definition for the Levels admin module.
 */

require_once __DIR__ . '/../utils/crud.php';

function levels_definition(): array
{
    $def = crud_definition();
    $def['table']       = 'levels';
    $def['fields']      = [
        'level'       => 'i',
        'title'       => 's',
        'xp_required' => 'i',
        'reward'      => 'i',
        'color'       => 's',
    ];
    $def['required']    = ['level', 'title'];
    $def['search_cols'] = ['title'];
    $def['hidden_cols'] = [];
    $def['order']       = 'level ASC';
    return $def;
}
