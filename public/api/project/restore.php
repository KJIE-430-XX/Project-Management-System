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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$projectId = isset($data['project_id']) ? (int)$data['project_id'] : 0;
$csrfToken = $data['csrf_token'] ?? '';
$userId = (int)$_SESSION['user_id'];

if ($projectId <= 0 || !validateCSRFToken($csrfToken)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$project = fetchProjectForOwner($conn, $projectId, $userId, true);
if (!$project) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Project not found or unauthorized']);
    exit;
}

$stmt = $conn->prepare("UPDATE projects SET deleted_at = NULL, deleted_by = NULL WHERE id = ? AND owner_id = ? AND deleted_at IS NOT NULL");
$stmt->bind_param("ii", $projectId, $userId);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    echo json_encode(['success' => true, 'message' => 'Project restored']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to restore project']);
}

$stmt->close();
$conn->close();
?>