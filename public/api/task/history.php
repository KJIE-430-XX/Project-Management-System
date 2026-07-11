<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
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

$editHistoryTableCheck = $conn->query("SHOW TABLES LIKE 'task_edit_history'");
$hasEditHistoryTable = $editHistoryTableCheck && $editHistoryTableCheck->num_rows > 0;

if ($editHistoryTableCheck instanceof mysqli_result) {
    $editHistoryTableCheck->free();
}

if ($hasEditHistoryTable) {
    $stmt = $conn->prepare("
    SELECT *
    FROM (
        SELECT
            h.id,
            h.changed_at,
            'status' AS event_type,
            NULL AS field_name,
            NULL AS old_value,
            NULL AS new_value,
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

        UNION ALL

        SELECT
            eh.id,
            eh.changed_at,
            'field_edit' AS event_type,
            eh.field_name,
            eh.old_value,
            eh.new_value,
            NULL AS old_status,
            NULL AS new_status,
            u.name AS changed_by
        FROM task_edit_history eh
        LEFT JOIN users u
            ON u.id = eh.changed_by
        WHERE eh.task_id = ?
    ) history
    ORDER BY history.changed_at DESC, history.id DESC
    ");

    $stmt->bind_param(
        "ii",
        $taskId,
        $taskId
    );
} else {
    $stmt = $conn->prepare("
    SELECT
        h.id,
        h.changed_at,
        'status' AS event_type,
        NULL AS field_name,
        NULL AS old_value,
        NULL AS new_value,
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
    ORDER BY h.changed_at DESC, h.id DESC
    ");

    $stmt->bind_param(
        "i",
        $taskId
    );
}

$stmt->execute();

$history = $stmt
    ->get_result()
    ->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    'success' => true,
    'history' => $history
]);
