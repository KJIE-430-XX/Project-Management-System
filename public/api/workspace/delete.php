<?php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user_id = $_SESSION['user_id'];
$workspace_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($workspace_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Workspace ID is required']);
    exit;
}

// Due to ON DELETE SET NULL constraint on projects table, 
// deleting the workspace will automatically set workspace_id = NULL for all its projects
$stmt = $conn->prepare("DELETE FROM workspaces WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $workspace_id, $user_id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode(['success' => true, 'message' => 'Workspace deleted']);
} else {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Workspace not found or unauthorized']);
}

$stmt->close();
$conn->close();
?>
