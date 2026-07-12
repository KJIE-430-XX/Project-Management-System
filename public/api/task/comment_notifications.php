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
require_once __DIR__ . '/../../includes/task_comment_notifications.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$csrfToken = $input['csrf_token'] ?? '';
$taskId = (int)($input['task_id'] ?? 0);

if (!validateCSRFToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

if ($taskId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid task']);
    exit;
}

$context = getTaskCommentConversationContext($conn, $taskId, $userId);

if (!$context || empty($context['is_participant'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if (!markTaskCommentsAsRead($conn, $taskId, $userId)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to clear notification badge']);
    exit;
}

echo json_encode([
    'success' => true,
    'task_id' => $taskId
]);
?>
