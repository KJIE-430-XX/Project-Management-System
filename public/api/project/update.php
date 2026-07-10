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

if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$projectId  = isset($data['project_id']) ? (int)$data['project_id'] : 0;
$name       = trim($data['name'] ?? '');
$description = trim($data['description'] ?? '');
$dueDate    = trim($data['due_date'] ?? '');
$csrfToken  = $data['csrf_token'] ?? '';
$userId     = (int)$_SESSION['user_id'];

if ($projectId <= 0 || $name === '' || !validateCSRFToken($csrfToken)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$project = fetchProjectForOwner($conn, $projectId, $userId, false);
if (!$project) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Project not found or unauthorized']);
    exit;
}

// Validate and sanitize due_date
$dueDateValue = null;
if ($dueDate !== '') {
    $d = DateTime::createFromFormat('Y-m-d', $dueDate);
    if ($d && $d->format('Y-m-d') === $dueDate) {
        $dueDateValue = $dueDate;
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid due date format']);
        exit;
    }
}

$stmt = $conn->prepare("UPDATE projects SET name = ?, description = ?, due_date = ?, updated_at = NOW() WHERE id = ? AND owner_id = ? AND deleted_at IS NULL");
$stmt->bind_param("sssii", $name, $description, $dueDateValue, $projectId, $userId);

if ($stmt->execute()) {
    echo json_encode([
        'success'     => true,
        'message'     => 'Project updated',
        'name'        => $name,
        'description' => $description,
        'due_date'    => $dueDateValue
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to update project']);
}

$stmt->close();
$conn->close();
?>
