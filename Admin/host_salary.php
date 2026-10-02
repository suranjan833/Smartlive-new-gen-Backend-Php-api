<?php

/**
 * POST /Admin/host_salary.php   (Authorization: Bearer <admin token>)
 * Body: { "host_id": 5, "amount": 500.00, "month"?: "2026-09", "note"?: "..." }
 * Records a salary payout for a host (month defaults to the current one).
 */

require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/validation.php';
require_once __DIR__ . '/../utils/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed, use POST', 405);
}

$conn = db();
$account = require_admin($conn);
require_permission($account, 'hosts', 'edit');

$hostId = (int) field('host_id', 0);
$amount = (float) field('amount', 0);
$month  = (string) field('month', date('Y-m'));
$note   = (string) field('note', '');

if ($hostId <= 0) {
    json_error('host_id is required', 422);
}
if ($amount <= 0) {
    json_error('amount must be a positive number', 422);
}
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    json_error('month must look like 2026-09', 422);
}

$stmt = $conn->prepare('SELECT id, name FROM hosts WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $hostId);
$stmt->execute();
$host = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$host) {
    json_error('Host not found', 404);
}

$stmt = $conn->prepare(
    'INSERT INTO host_salaries (host_id, amount, month, note, paid_at)
     VALUES (?, ?, ?, ?, NOW())'
);
$stmt->bind_param('idss', $hostId, $amount, $month, $note);
$stmt->execute();
$salaryId = $stmt->insert_id;
$stmt->close();

json_success(['salary_id' => $salaryId, 'host_id' => $hostId, 'amount' => $amount, 'month' => $month], 'Salary recorded');
