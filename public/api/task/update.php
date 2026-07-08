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

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'PUT') {
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
$title = trim($input['title'] ?? '');
$description = trim($input['description'] ?? '');
$statusId = isset($input['status_id']) ? (int)$input['status_id'] : 0;
$priorityId = isset($input['priority_id']) ? (int)$input['priority_id'] : 0;
$dueDate = trim($input['due_date'] ?? '');
$assigneeId = isset($input['assignee_id']) && $input['assignee_id'] !== '' ? (int)$input['assignee_id'] : null;
$userId = (int)$_SESSION['user_id'];

if ($taskId <= 0 || $title === '' || !in_array($statusId, [1, 2, 3], true) || !in_array($priorityId, [1, 2, 3], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$taskStmt = $conn->prepare("
    SELECT
        t.id,
        t.project_id,
        t.status_id,
        p.due_date AS project_due_date
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
    echo json_encode(['success' => false, 'message' => 'Only the assigned user can edit this task.']);
    exit;
}

if ($dueDate === '') {
    $dueDate = null;
} elseif ($task['project_due_date'] !== null && strtotime($dueDate) > strtotime($task['project_due_date'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Task due date cannot be later than the project deadline']);
    exit;
}

$memberIds = [];
$membersStmt = $conn->prepare("SELECT user_id FROM project_members WHERE project_id = ?");
$membersStmt->bind_param("i", $task['project_id']);
$membersStmt->execute();
$memberResult = $membersStmt->get_result();
while ($row = $memberResult->fetch_assoc()) {
    $memberIds[] = (int)$row['user_id'];
}
$membersStmt->close();

if ($assigneeId !== null && !in_array($assigneeId, $memberIds, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid assignee selected']);
    exit;
}

try {
    $conn->begin_transaction();

    $update = $conn->prepare("UPDATE tasks SET title = ?, description = ?, status_id = ?, priority_id = ?, due_date = ? WHERE id = ?");
    $update->bind_param("ssiisi", $title, $description, $statusId, $priorityId, $dueDate, $taskId);
    if (!$update->execute()) {
        throw new Exception('Failed to update task');
    }
    $update->close();

    if ($assigneeId === null) {
        $clearAssignee = $conn->prepare("DELETE FROM task_assignees WHERE task_id = ?");
        $clearAssignee->bind_param("i", $taskId);
        if (!$clearAssignee->execute()) {
            throw new Exception('Failed to clear task assignee');
        }
        $clearAssignee->close();
    } else {
        $clearAssignee = $conn->prepare("DELETE FROM task_assignees WHERE task_id = ?");
        $clearAssignee->bind_param("i", $taskId);
        if (!$clearAssignee->execute()) {
            throw new Exception('Failed to reset task assignee');
        }
        $clearAssignee->close();

        $assign = $conn->prepare("INSERT INTO task_assignees (task_id, project_id, user_id) VALUES (?, ?, ?)");
        $assign->bind_param("iii", $taskId, $task['project_id'], $assigneeId);
        if (!$assign->execute()) {
            throw new Exception('Failed to assign task');
        }
        $assign->close();
    }

    if ((int)$task['status_id'] !== $statusId) {
        $history = $conn->prepare("INSERT INTO task_status_history (task_id, old_status_id, new_status_id, changed_by) VALUES (?, ?, ?, ?)");
        $history->bind_param("iiii", $taskId, $task['status_id'], $statusId, $userId);
        if (!$history->execute()) {
            throw new Exception('Failed to record status history');
        }
        $history->close();
    }

    $detailStmt = $conn->prepare("
        SELECT t.id, t.project_id, t.title, t.description, t.status_id, t.priority_id, t.due_date,
               u.name AS creator_name,
               au.id AS assignee_id, au.name AS assignee_name
        FROM tasks t
        LEFT JOIN users u ON u.id = t.created_by
        LEFT JOIN task_assignees ta ON ta.task_id = t.id
        LEFT JOIN users au ON au.id = ta.user_id
        WHERE t.id = ?
        LIMIT 1
    ");
    $detailStmt->bind_param("i", $taskId);
    $detailStmt->execute();
    $updatedTask = $detailStmt->get_result()->fetch_assoc();
    $detailStmt->close();

    $conn->commit();

    $statusLabels = [1 => 'Completed', 2 => 'To Do', 3 => 'Pending'];
    $priorityLabels = [1 => 'High', 2 => 'Medium', 3 => 'Low'];

    echo json_encode([
        'success' => true,
        'message' => 'Task updated successfully',
        'task' => [
            'id' => (int)$taskId,
            'project_id' => (int)$task['project_id'],
            'title' => $title,
            'description' => $description,
            'status_id' => $statusId,
            'status_label' => $statusLabels[$statusId] ?? 'Unknown',
            'priority_id' => $priorityId,
            'priority_label' => $priorityLabels[$priorityId] ?? 'Unknown',
            'due_date' => $dueDate,
            'creator_name' => $updatedTask['creator_name'] ?? 'System',
            'assignee_id' => $updatedTask['assignee_id'] ? (int)$updatedTask['assignee_id'] : null,
            'assignee_name' => $updatedTask['assignee_name'] ?? null,
        ]
    ]);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>