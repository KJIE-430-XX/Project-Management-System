<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

include 'db.php';
include 'csrf.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$csrf_token = $input['csrf_token'] ?? '';
if (!validateCSRFToken($csrf_token)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$task_id = (int)($input['task_id'] ?? 0);
$new_status_id = (int)($input['new_status_id'] ?? 0);
$user_id = $_SESSION['user_id'];

// Validate status_id (1=Completed, 2=To Do, 3=Pending)
if (!in_array($new_status_id, [1, 2, 3])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid status']);
    exit;
}

// Verify the task exists and user has access to the project
$check = $conn->prepare("
    SELECT t.id, t.status_id, t.project_id 
    FROM tasks t 
    JOIN project_members pm ON t.project_id = pm.project_id 
    WHERE t.id = ? AND pm.user_id = ?
");
$check->bind_param("ii", $task_id, $user_id);
$check->execute();
$task = $check->get_result()->fetch_assoc();
$check->close();

if (!$task) {
    http_response_code(404);
    echo json_encode(['error' => 'Task not found or access denied']);
    exit;
}

$old_status_id = (int)$task['status_id'];

try {
    $conn->begin_transaction();

    // Update task status
    $update = $conn->prepare("UPDATE tasks SET status_id = ? WHERE id = ?");
    $update->bind_param("ii", $new_status_id, $task_id);
    if (!$update->execute()) {
        throw new Exception("Failed to update task status");
    }
    $update->close();

    // Log status history
    $history = $conn->prepare("INSERT INTO task_status_history (task_id, old_status_id, new_status_id, changed_by) VALUES (?, ?, ?, ?)");
    $history->bind_param("iiii", $task_id, $old_status_id, $new_status_id, $user_id);
    if (!$history->execute()) {
        throw new Exception("Failed to log status change");
    }
    $history->close();

    $conn->commit();

    $status_labels = [1 => 'Completed', 2 => 'To Do', 3 => 'Pending'];
    echo json_encode([
        'success' => true,
        'task_id' => $task_id,
        'new_status_id' => $new_status_id,
        'new_status_label' => $status_labels[$new_status_id] ?? 'Unknown'
    ]);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
