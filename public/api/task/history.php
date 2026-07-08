<?php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit;
}

require_once __DIR__ . '/../../db.php';

$userId = (int)$_SESSION['user_id'];

$taskId = isset($_GET['task_id'])
    ? (int)$_GET['task_id']
    : 0;

if ($taskId <= 0) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid task'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Check permission
|--------------------------------------------------------------------------
*/

$check = $conn->prepare("
    SELECT t.id
    FROM tasks t
    JOIN projects p
        ON p.id = t.project_id
    JOIN project_members pm
        ON pm.project_id = p.id
    WHERE
        t.id = ?
        AND pm.user_id = ?
        AND p.deleted_at IS NULL
    LIMIT 1
");

$check->bind_param(
    "ii",
    $taskId,
    $userId
);

$check->execute();

if ($check->get_result()->num_rows === 0) {

    http_response_code(403);

    echo json_encode([
        'success' => false,
        'message' => 'Access denied'
    ]);

    exit;
}

$check->close();

/*
|--------------------------------------------------------------------------
| Load history
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
SELECT
    h.id,
    h.created_at,

    oldStatus.name AS old_status,

    newStatus.name AS new_status,

    u.name AS changed_by

FROM task_status_history h

LEFT JOIN status oldStatus
    ON oldStatus.id = h.old_status_id

LEFT JOIN status newStatus
    ON newStatus.id = h.new_status_id

LEFT JOIN users u
    ON u.id = h.changed_by

WHERE h.task_id = ?

ORDER BY h.created_at DESC
");

$stmt->bind_param(
    "i",
    $taskId
);

$stmt->execute();

$history = $stmt
    ->get_result()
    ->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    'success' => true,
    'history' => $history
]);