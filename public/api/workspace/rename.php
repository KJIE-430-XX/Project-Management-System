<?php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

$workspace_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($data['id']) ? intval($data['id']) : 0);
$name = trim($data['name'] ?? ($_POST['name'] ?? ''));

if ($workspace_id <= 0 || empty($name)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Workspace ID and new name are required']);
    exit;
}

// Ensure the workspace exists and belongs to the logged in user
$check_stmt = $conn->prepare("SELECT id FROM workspaces WHERE id = ? AND user_id = ?");
$check_stmt->bind_param("ii", $workspace_id, $user_id);
$check_stmt->execute();
$check_stmt->store_result();

if ($check_stmt->num_rows === 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Workspace not found or unauthorized']);
    $check_stmt->close();
    $conn->close();
    exit;
}
$check_stmt->close();

// Update the workspace name
$stmt = $conn->prepare("UPDATE workspaces SET name = ? WHERE id = ? AND user_id = ?");
$stmt->bind_param("sii", $name, $workspace_id, $user_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Workspace renamed']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to rename workspace']);
}

$stmt->close();
$conn->close();
?>
