<?php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../csrf.php';
require_once __DIR__ . '/../../includes/project_lifecycle.php';

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$csrfToken = $input['csrf_token'] ?? '';
if (!validateCSRFToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$taskId = isset($input['task_id']) ? (int)$input['task_id'] : 0;
$userId = (int)$_SESSION['user_id'];

if ($taskId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$taskStmt = $conn->prepare("
    SELECT
        t.id,
        t.project_id
    FROM tasks t

    INNER JOIN projects p
        ON p.id = t.project_id

    INNER JOIN project_members pm
        ON pm.project_id = t.project_id
        AND pm.user_id = ?

    INNER JOIN task_assignees ta
        ON ta.task_id = t.id
        AND ta.user_id = ?

    WHERE
        t.id = ?
        AND p.deleted_at IS NULL

    LIMIT 1
");
$taskStmt->bind_param(
    "iii",
    $userId,
    $userId,
    $taskId
);
$taskStmt->execute();
$task = $taskStmt->get_result()->fetch_assoc();
$taskStmt->close();

if (!$task) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Only the assigned user can delete this task.']);
    exit;
}

try {
    $conn->begin_transaction();

    $delete = $conn->prepare("DELETE FROM tasks WHERE id = ?");
    $delete->bind_param("i", $taskId);
    if (!$delete->execute()) {
        throw new Exception('Failed to delete task');
    }
    $delete->close();

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Task deleted successfully',
        'task_id' => $taskId,
        'project_id' => (int)$task['project_id']
    ]);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>