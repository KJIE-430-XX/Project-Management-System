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

// Ensure the workspace belongs to the logged in user
$stmt = $conn->prepare("UPDATE workspaces SET name = ? WHERE id = ? AND user_id = ?");
$stmt->bind_param("sii", $name, $workspace_id, $user_id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode(['success' => true, 'message' => 'Workspace renamed']);
} else {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Workspace not found or unauthorized']);
}

$stmt->close();
$conn->close();
?>
