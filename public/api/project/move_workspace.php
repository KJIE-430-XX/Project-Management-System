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

$project_id = isset($data['project_id']) ? intval($data['project_id']) : 0;
// workspace_id can be null or empty, which means uncategorized
$workspace_id = isset($data['workspace_id']) && $data['workspace_id'] !== "" ? intval($data['workspace_id']) : null;

if ($project_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Project ID is required']);
    exit;
}

// 1. Verify user is owner of the project
$stmt = $conn->prepare("SELECT owner_id FROM projects WHERE id = ?");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Project not found']);
    exit;
}
$project = $result->fetch_assoc();
if ($project['owner_id'] != $user_id) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only the project owner can move it']);
    exit;
}
$stmt->close();

// 2. If workspace_id is provided, verify it belongs to the user
if ($workspace_id !== null) {
    $stmt = $conn->prepare("SELECT id FROM workspaces WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $workspace_id, $user_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Workspace not found or unauthorized']);
        exit;
    }
    $stmt->close();
}

// 3. Update the project
$update_sql = "UPDATE projects SET workspace_id = ? WHERE id = ?";
$stmt = $conn->prepare($update_sql);
$stmt->bind_param("ii", $workspace_id, $project_id);
$stmt->execute();

if ($stmt->error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
} else {
    echo json_encode(['success' => true, 'message' => 'Project moved successfully']);
}

$stmt->close();
$conn->close();
?>
