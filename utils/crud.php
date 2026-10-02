<?php

/**
 * Generic CRUD engine for the Admin module APIs.
 *
 * Every admin module (users, hosts, agencies, ...) is one small file that
 * calls crud_handle() with a table definition — this file does the rest:
 *   GET           -> paginated list (with ?search= & ?status= filters)
 *   GET ?id=      -> single record
 *   POST          -> create
 *   PUT ?id=      -> full update (ignores missing fields)
 *   PATCH ?id=    -> partial update / status toggle
 *   DELETE ?id=   -> delete
 *
 * All endpoints require an admin Bearer token (see require_auth()).
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/response.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/auth.php';

/**
 * Uniform module definition passed to crud_handle().
 *
 * table        table name
 * fields       field => type  ('s' string, 'i' int, 'd' decimal/float, 'b' bool-int)
 * required     fields required on create
 * numeric_id   name of the ?id= query param (default 'id')
 * search_cols  columns matched against ?search= (LIKE %%)
 * status_col   column toggled by ?status= (optional)
 * status_map   allowed status values (optional; validated before update)
 * json_fields  columns stored as JSON (permissions, settings payload, ...)
 * bool_fields  incoming true/false stored as tinyint
 * hash_password body field holding a plain password to store as password_hash
 * unique_cols  columns checked for duplicates before insert/update (422)
 * hidden_cols  columns stripped from every response (password_hash, ...)
 * zero_as_null int FK columns where a posted 0 is stored as NULL
 * order        ORDER BY clause, default "id DESC"
 */
function crud_definition(): array
{
    return [
        'table'       => '',
        'fields'      => [],
        'required'    => [],
        'numeric_id'  => 'id',
        'search_cols' => [],
        'status_col'  => '',
        'status_map'  => [],
        'json_fields' => [],
        'bool_fields' => [],
        'hash_password' => '',
        'unique_cols' => [],
        'hidden_cols' => ['password_hash'],
        'zero_as_null' => [],
        'order'       => 'id DESC',
    ];
}

/** Read + validate the ?id= query param, or stop with a JSON error. */
function crud_id(array $def): int
{
    $id = (int) ($_GET[$def['numeric_id']] ?? 0);
    if ($id <= 0) {
        json_error('Valid ' . $def['numeric_id'] . ' is required (?' . $def['numeric_id'] . '=)', 422);
    }
    return $id;
}

/** Fetch one row or stop with 404. Joins mirror the list query so detail/create responses match. */
function crud_find(mysqli $conn, array $def, int $id, string $extraSelect = '', string $extraJoin = ''): array
{
    $stmt = $conn->prepare(
        "SELECT t.* {$extraSelect} FROM {$def['table']} t {$extraJoin} WHERE t.id = ? LIMIT 1"
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        json_error(ucfirst($def['table']) . ' not found', 404);
    }

    foreach ($def['json_fields'] as $col) {
        if (isset($row[$col]) && is_string($row[$col])) {
            $decoded    = json_decode($row[$col], true);
            $row[$col]  = $decoded !== null ? $decoded : $row[$col];
        }
    }

    foreach ($def['hidden_cols'] as $col) {
        unset($row[$col]);
    }

    return $row;
}

/** Collect + validate body fields present in the request. Returns [cols, types, values]. */
function crud_collect_fields(array $def, bool $creating): array
{
    $cols  = [];
    $types = '';
    $vals  = [];

    foreach ($def['fields'] as $field => $type) {
        if (!array_key_exists($field, request_data())) {
            continue;
        }

        $raw = field($field);

        if (in_array($field, $def['bool_fields'], true)) {
            $raw = is_bool($raw) ? $raw : in_array(strtolower((string) $raw), ['1', 'true', 'yes', 'on'], true);
            $raw = $raw ? 1 : 0;
        } elseif (in_array($field, $def['json_fields'], true)) {
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                $raw     = $decoded !== null ? $decoded : $raw;
            }
            $raw = json_encode($raw, JSON_UNESCAPED_UNICODE);
            $type = 's';
        } elseif ($type === 'i') {
            if ($raw === null || $raw === '') {
                $raw = in_array($field, $def['zero_as_null'], true) ? null : 0;
            } else {
                if (!is_numeric($raw)) {
                    json_error(ucfirst($field) . ' must be a number', 422);
                }
                $raw = (int) $raw;
                if ($raw === 0 && in_array($field, $def['zero_as_null'], true)) {
                    $raw = null;   // "no relation" for FK columns
                }
            }
        } elseif ($type === 'd') {
            if ($raw !== null && !is_numeric($raw)) {
                json_error(ucfirst($field) . ' must be a number', 422);
            }
            $raw = (float) $raw;
        } else {
            $raw = (string) $raw;
        }

        $cols[]  = $field;
        $types  .= $type;
        $vals[]  = $raw;
    }

    if ($creating) {
        foreach ($def['required'] as $req) {
            $v = field($req);
            if ($v === null || $v === '' || (is_array($v) && $v === [])) {
                json_error(ucfirst($req) . ' is required', 422);
            }
        }
    }

    if (empty($cols)) {
        json_error($creating ? 'No valid fields provided' : 'No fields to update', 422);
    }

    // Plain password field -> hashed password_hash column
    if ($def['hash_password'] !== '') {
        $idx = array_search($def['hash_password'], $cols, true);
        if ($idx !== false) {
            $cols[$idx] = 'password_hash';
            $vals[$idx] = password_hash((string) field($def['hash_password']), PASSWORD_DEFAULT);
        }
    }

    return [$cols, $types, $vals];
}

/** Stop with 422 when a unique column would get a duplicate value. */
function crud_check_unique(mysqli $conn, array $def, array $cols, array $vals, ?int $excludeId = null): void
{
    foreach ($def['unique_cols'] as $col) {
        $idx = array_search($col, $cols, true);
        if ($idx === false) {
            continue;
        }

        $sql = "SELECT id FROM {$def['table']} WHERE {$col} = ?";
        if ($excludeId !== null) {
            $sql .= ' AND id != ?';
        }
        $sql .= ' LIMIT 1';

        $stmt = $conn->prepare($sql);
        if ($excludeId !== null) {
            $stmt->bind_param('si', $vals[$idx], $excludeId);
        } else {
            $stmt->bind_param('s', $vals[$idx]);
        }
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($exists) {
            json_error(ucfirst($col) . " '{$vals[$idx]}' is already in use", 422);
        }
    }
}

/** Build the WHERE fragments for ?search= and ?status=. */
function crud_filters(array $def, array &$params, string &$types): string
{
    $where = [];

    $search = trim($_GET['search'] ?? '');
    if ($search !== '' && !empty($def['search_cols'])) {
        $parts = [];
        foreach ($def['search_cols'] as $col) {
            $parts[]   = "t.{$col} LIKE ?";
            $params[] = '%' . $search . '%';
            $types   .= 's';
        }
        $where[] = '(' . implode(' OR ', $parts) . ')';
    }

    $status = trim($_GET['status'] ?? '');
    if ($status !== '' && $def['status_col'] !== '') {
        if (!empty($def['status_map']) && !in_array($status, $def['status_map'], true)) {
            json_error('status must be one of: ' . implode(', ', $def['status_map']), 422);
        }
        $where[]  = "t.{$def['status_col']} = ?";
        $params[] = $status;
        $types   .= 's';
    }

    return empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);
}

/**
 * The whole module in one call.
 * Requires an admin token, routes on the HTTP verb and answers JSON.
 * $extra_select lets list/detail queries JOIN other tables (e.g. agency name).
 */
/**
 * The whole module in one call.
 *
 * $extraSelect/$extraJoin let list/detail queries JOIN other tables (agency name...).
 * $module is the permission module name (users, hosts, ...); when null it is
 * derived from the table name. Sub-admins need:
 *   GET    -> <module>.view
 *   POST   -> <module>.edit
 *   PUT    -> <module>.edit
 *   PATCH  -> <module>.edit
 *   DELETE -> <module>.delete
 * Super admins bypass the checks.
 */
function crud_handle(array $def, string $extraSelect = '', string $extraJoin = '', ?string $module = null): void
{
    $conn = db();
    $account = require_admin($conn);

    $module = $module ?? $def['table'];
    $abilityByMethod = [
        'GET'    => 'view',
        'POST'   => 'edit',
        'PUT'    => 'edit',
        'PATCH'  => 'edit',
        'DELETE' => 'delete',
    ];
    require_permission($account, $module, $abilityByMethod[$_SERVER['REQUEST_METHOD']] ?? 'view');

    crud_apply_aliases($def);

    $method = $_SERVER['REQUEST_METHOD'];
    $idParam = $def['numeric_id'];

    // ---- DELETE ?id= -----------------------------------------------------
    if ($method === 'DELETE') {
        $id = crud_id($def);
        crud_find($conn, $def, $id, '', $extraJoin); // 404 when missing

        $stmt = $conn->prepare("DELETE FROM {$def['table']} WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        json_success([$idParam => $id], 'Deleted successfully');
    }

    // ---- GET (list ?search= & ?status=, or single ?id=) ------------------
    if ($method === 'GET') {
        if (!empty($_GET[$idParam])) {
            $row = crud_find($conn, $def, (int) $_GET[$idParam], $extraSelect, $extraJoin);
            json_success(['record' => $row], 'Loaded');
        }

        $params = [];
        $types  = '';
        $where  = crud_filters($def, $params, $types);

        $page   = max(1, (int) ($_GET['page'] ?? 1));
        $limit  = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM {$def['table']} t {$extraJoin} {$where}");
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $total = (int) $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();

        $select = "SELECT t.* {$extraSelect} FROM {$def['table']} t {$extraJoin} ";
        $stmt   = $conn->prepare(
            $select . $where . " ORDER BY t.{$def['order']} LIMIT ? OFFSET ?"
        );
        $bindTypes  = $types . 'ii';
        $bindParams = array_merge($params, [$limit, $offset]);
        $stmt->bind_param($bindTypes, ...$bindParams);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($rows as &$row) {
            foreach ($def['json_fields'] as $col) {
                if (isset($row[$col]) && is_string($row[$col])) {
                    $decoded   = json_decode($row[$col], true);
                    $row[$col] = $decoded !== null ? $decoded : $row[$col];
                }
            }
            foreach ($def['hidden_cols'] as $col) {
                unset($row[$col]);
            }
        }
        unset($row);

        json_success([
            strtolower(rtrim($def['table'], 's')) . 's' => $rows,
            'pagination' => [
                'page'        => $page,
                'limit'       => $limit,
                'total'       => $total,
                'total_pages' => (int) ceil($total / $limit),
            ],
        ], 'Loaded');
    }

    // ---- POST (create) ---------------------------------------------------
    if ($method === 'POST') {
        [$cols, $types, $vals] = crud_collect_fields($def, true);
        crud_check_unique($conn, $def, $cols, $vals);

        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
        $collist      = implode(', ', $cols);

        $stmt = $conn->prepare(
            "INSERT INTO {$def['table']} ({$collist}) VALUES ({$placeholders})"
        );
        $stmt->bind_param($types, ...$vals);
        $stmt->execute();
        $newId = $stmt->insert_id;
        $stmt->close();

        json_success([$idParam => $newId, 'record' => crud_find($conn, $def, $newId, $extraSelect, $extraJoin)], 'Created successfully', 201);
    }

    // ---- PUT / PATCH (update) -------------------------------------------
    if ($method === 'PUT' || $method === 'PATCH') {
        $id = crud_id($def);
        crud_find($conn, $def, $id); // 404 when missing

        [$cols, $types, $vals] = crud_collect_fields($def, false);
        crud_check_unique($conn, $def, $cols, $vals, $id);

        $set = implode(' = ?, ', $cols) . ' = ?';

        $stmt = $conn->prepare("UPDATE {$def['table']} SET {$set} WHERE id = ?");
        $stmt->bind_param($types . 'i', ...array_merge($vals, [$id]));
        $stmt->execute();
        $stmt->close();

        json_success([$idParam => $id, 'record' => crud_find($conn, $def, $id, $extraSelect, $extraJoin)], 'Updated successfully');
    }

    json_error('Method not allowed', 405);
}

/** Toggle a single status-ish column (PATCH /xxx/:id/status endpoints). Needs <module>.edit. */
function crud_set_status(array $def, array $allowed, string $column = 'status', ?string $module = null): void
{
    $conn = db();
    $account = require_admin($conn);

    $module = $module ?? $def['table'];
    require_permission($account, $module, 'edit');

    $key    = $def['numeric_id'];
    $id     = (int) ($_GET[$key] ?? field($key, 0));   // ?id= in query or id in body
    $status = strtolower(trim((string) field('status', '')));

    if ($id <= 0) {
        json_error('Valid ' . $key . ' is required', 422);
    }

    if ($status === '' || !in_array($status, $allowed, true)) {
        json_error('status must be one of: ' . implode(', ', $allowed), 422);
    }

    crud_find($conn, $def, $id); // 404 when missing

    $stmt = $conn->prepare("UPDATE {$def['table']} SET {$column} = ? WHERE id = ?");
    $stmt->bind_param('si', $status, $id);
    $stmt->execute();
    $stmt->close();

    json_success([$def['numeric_id'] => $id, 'status' => $status], 'Status updated');
}

/** Map camelCase body keys (agencyId, ownerName, imageUrl, ...) to snake_case columns. */
function crud_apply_aliases(array $def): void
{
    foreach (request_data() as $key => $value) {
        if (!is_string($key)) {
            continue;
        }
        $alias = strtolower((string) preg_replace('/([a-z])([A-Z])/', '$1_$2', $key));
        if ($alias !== $key && array_key_exists($alias, $def['fields'])) {
            request_set($alias, $value);
        }
    }
}

/** Move an uploaded file ($_FILES[...]) into /uploads/<folder>/ and return its public path. */
function handle_upload(string $inputName, string $folder = 'misc'): ?string
{
    if (empty($_FILES[$inputName]) || !is_uploaded_file($_FILES[$inputName]['tmp_name'] ?? '')) {
        return null;
    }

    $file = $_FILES[$inputName];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        json_error('File upload failed (code ' . $file['error'] . ')', 400);
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        json_error('File too large (max 5 MB)', 422);
    }

    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $mime    = mime_content_type($file['tmp_name']);
    if (!in_array($mime, $allowed, true)) {
        json_error('Only JPG, PNG, GIF or WEBP images are allowed', 422);
    }

    $dir = __DIR__ . '/../uploads/' . preg_replace('/[^a-z0-9_-]/', '', strtolower($folder));
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $ext  = image_type_to_extension((int) array_search($mime, [
        'image/jpeg' => IMAGETYPE_JPEG,
        'image/png'  => IMAGETYPE_PNG,
        'image/gif'  => IMAGETYPE_GIF,
        'image/webp' => IMAGETYPE_WEBP,
    ], true));
    $name = bin2hex(random_bytes(8)) . $ext;
    $dest = $dir . '/' . $name;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        json_error('Could not store uploaded file', 500);
    }

    return 'uploads/' . basename($dir) . '/' . $name;
}

/** GET ?page= & ?limit= pair shared by list endpoints outside crud_handle(). */
function list_page_params(int $maxLimit = 100, int $defaultLimit = 20): array
{
    $page  = max(1, (int) ($_GET['page'] ?? 1));
    $limit = min($maxLimit, max(1, (int) ($_GET['limit'] ?? $defaultLimit)));
    return [$page, $limit, ($page - 1) * $limit];
}

/** Standard pagination payload. */
function pagination_payload(int $page, int $limit, int $total): array
{
    return [
        'page'        => $page,
        'limit'       => $limit,
        'total'       => $total,
        'total_pages' => (int) ceil($total / $limit),
    ];
}
