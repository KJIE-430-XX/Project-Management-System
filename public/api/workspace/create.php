<?php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) { $data = []; }
$name = trim($data['name'] ?? ($_POST['name'] ?? ''));

if (empty($name)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Workspace name is required']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO workspaces (name, user_id) VALUES (?, ?)");
$stmt->bind_param("si", $name, $user_id);

if ($stmt->execute()) {
    $workspace_id = $conn->insert_id;
    echo json_encode(['success' => true, 'workspace_id' => $workspace_id, 'name' => htmlspecialchars($name)]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?>
