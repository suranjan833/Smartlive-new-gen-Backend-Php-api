<?php

/**
 * Shared definition for the Hosts admin module.
 * snake_case body fields (agency_id, followers, ...) are accepted,
 * and camelCase aliases from api.md (agencyId, ...) are mapped too.
 */

require_once __DIR__ . '/../utils/crud.php';

function hosts_definition(): array
{
    $def = crud_definition();
    $def['table']       = 'hosts';
    $def['fields']      = [
        'user_id'   => 'i',
        'name'      => 's',
        'agency_id' => 'i',
        'email'     => 's',
        'phone'     => 's',
        'followers' => 'i',
        'level'     => 'i',
        'earnings'  => 'd',
        'status'    => 's',
    ];
    $def['required']    = ['name'];
    $def['search_cols'] = ['name', 'email'];
    $def['status_col']  = 'status';
    $def['status_map']  = ['online', 'offline', 'banned'];
    $def['zero_as_null'] = ['user_id', 'agency_id'];

    // camelCase aliases (api.md style) -> real columns
    foreach (request_data() as $key => $value) {
        $alias = strtolower((string) preg_replace('/([a-z])([A-Z])/', '$1_$2', $key));
        if ($alias !== $key && array_key_exists($alias, $def['fields'])) {
            request_set($alias, $value);
        }
    }

    return $def;
}
