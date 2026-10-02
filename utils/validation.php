<?php

/**
 * Input helpers — works with JSON bodies AND form-data / url-encoded posts.
 */

/** Internal shared body store (returned by reference so it can be mutated). */
function &request_store(): array
{
    static $data = [];
    return $data;
}

/**
 * Decoded request body (json_decode of php://input, fallback to $_POST).
 * Works for POST, PUT and PATCH — the admin CRUD APIs use all three verbs.
 */
function request_data(): array
{
    $data = &request_store();
    if ($data !== []) {
        return $data;
    }

    $raw  = file_get_contents('php://input');
    $json = is_string($raw) ? json_decode($raw, true) : null;

    if (is_array($json)) {
        $data = $json;
    } elseif (is_string($raw) && $raw !== '' && str_contains($raw, '=')) {
        parse_str($raw, $parsed);   // form-encoded PUT/PATCH bodies ($_POST only fills for POST)
        $data = is_array($parsed) ? $parsed : $_POST;
    } else {
        $data = $_POST;
    }

    return $data;
}

/**
 * Set/override a body field (uploads inject image_url, aliases get
 * normalized, defaults get filled). Modifies the shared body store.
 */
function request_set(string $key, $value): void
{
    $data = &request_store();
    if ($data === []) {
        request_data();          // make sure the body was parsed first
    }
    request_store()[$key] = $value;
}

/** True if the body is empty (no fields sent). */
function request_is_empty(): bool
{
    return request_data() === [];
}

/** Trimmed value of a body field, or $default when missing. */
function field(string $key, $default = null)
{
    $value = request_data()[$key] ?? $default;
    return is_string($value) ? trim($value) : $value;
}

function is_valid_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function is_valid_password(string $password): bool
{
    return strlen($password) >= 6;
}

function is_valid_name(string $name): bool
{
    $len = mb_strlen($name);
    return $len >= 2 && $len <= 60;
}
