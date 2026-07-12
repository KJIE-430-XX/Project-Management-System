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

$userId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $taskId = isset($_GET['task_id']) ? (int)$_GET['task_id'] : 0;
    $markRead = isset($_GET['mark_read']) && $_GET['mark_read'] === '1';

    if ($taskId <= 0) {
        http_response_code(400);
        echo json_encode(['success'=>false,'message'=>'Invalid task']);
        exit;
    }
    $context = getTaskCommentConversationContext($conn, $taskId, $userId);

    if (!$context || empty($context['is_participant'])) {

        http_response_code(403);

        echo json_encode([
            'success' => false,
            'message' => 'Access denied'
        ]);

        exit;
    }
    $stmt = $conn->prepare("
        SELECT
            tc.id,
            tc.comment,
            tc.created_at,
            u.id AS user_id,
            u.name
        FROM task_comments tc
        JOIN users u
            ON u.id = tc.user_id
        WHERE tc.task_id = ?
        ORDER BY tc.created_at ASC
    ");

    $stmt->bind_param("i", $taskId);
    $stmt->execute();

    $comments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if ($markRead) {
        markTaskCommentsAsRead($conn, $taskId, $userId);
    }

    echo json_encode([
        'success' => true,
        'comments' => $comments,
        'unread_cleared' => $markRead
    ]);

    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $input = json_decode(file_get_contents('php://input'), true) ?: [];

    $csrfToken = $input['csrf_token'] ?? '';

    if (!validateCSRFToken($csrfToken)) {

        http_response_code(403);

        echo json_encode([
            'success' => false,
            'message' => 'Invalid CSRF token'
        ]);

        exit;
    }

    $taskId = (int)($input['task_id'] ?? 0);

    $comment = trim($input['comment'] ?? '');

    if ($taskId <= 0 || $comment === '') {

        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Invalid request'
        ]);

        exit;
    }
    $context = getTaskCommentConversationContext($conn, $taskId, $userId);

    if (!$context || empty($context['is_participant'])) {

        http_response_code(403);

        echo json_encode([
            'success' => false,
            'message' => 'Access denied'
        ]);

        exit;
    }
    $recipientUserId = resolveTaskCommentRecipientId($context, $userId);

    $conn->begin_transaction();

    $stmt = $conn->prepare("
        INSERT INTO task_comments
        (
            task_id,
            user_id,
            comment
        )
        VALUES
        (?, ?, ?)
    ");

    $stmt->bind_param(
        "iis",
        $taskId,
        $userId,
        $comment
    );

    if (!$stmt->execute()) {
        $stmt->close();
        $conn->rollback();

        http_response_code(500);

        echo json_encode([
            'success' => false,
            'message' => 'Unable to save comment'
        ]);

        exit;
    }
    $stmt->close();

    if (!recordUnreadTaskCommentNotification($conn, $taskId, $recipientUserId)) {
        $conn->rollback();

        http_response_code(500);

        echo json_encode([
            'success' => false,
            'message' => 'Unable to update notification badge'
        ]);

        exit;
    }

    $conn->commit();

    echo json_encode([
        'success' => true
    ]);

    exit;
}

http_response_code(405);

echo json_encode([
    'success' => false,
    'message' => 'Method not allowed'
]);
