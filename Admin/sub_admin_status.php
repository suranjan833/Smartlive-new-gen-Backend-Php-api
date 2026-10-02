<?php

/**
 * PATCH|POST /Admin/sub_admin_status.php   (Authorization: Bearer <admin token>)
 * Body: { "id": 5, "status": "active" | "blocked" }
 * Blocking a sub-admin immediately kills their sessions.
 */

require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/crud.php';
require_once __DIR__ . '/../utils/db.php';

require_once __DIR__ . '/sub_admins_def.php';

crud_set_status(sub_admins_definition(), ['active', 'blocked'], 'status', 'sub_admins');

// crud_set_status exits with a JSON response, so this only runs after a change
if (field('status') === 'blocked') {
    $id = (int) ($_GET['id'] ?? 0);
    $stmt = $conn->prepare("DELETE FROM sessions WHERE user_type = 'sub_admin' AND user_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
}
