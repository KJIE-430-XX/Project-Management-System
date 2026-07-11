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

$userId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $taskId = isset($_GET['task_id']) ? (int)$_GET['task_id'] : 0;

    if ($taskId <= 0) {
        http_response_code(400);
        echo json_encode(['success'=>false,'message'=>'Invalid task']);
        exit;
    }
    $check = $conn->prepare("
        SELECT t.id
        FROM tasks t
        JOIN projects p
            ON p.id = t.project_id
        JOIN project_members pm
            ON pm.project_id = p.id
        WHERE
            t.id = ?
            AND pm.user_id = ?
            AND p.deleted_at IS NULL
        LIMIT 1
    ");

    $check->bind_param("ii", $taskId, $userId);
    $check->execute();

    if ($check->get_result()->num_rows === 0) {

        http_response_code(403);

        echo json_encode([
            'success' => false,
            'message' => 'Access denied'
        ]);

        exit;
    }

    $check->close();
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

    echo json_encode([
        'success' => true,
        'comments' => $comments
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

       $check = $conn->prepare("
        SELECT t.id
        FROM tasks t
        JOIN projects p
            ON p.id = t.project_id
        JOIN project_members pm
            ON pm.project_id = p.id
        WHERE
            t.id = ?
            AND pm.user_id = ?
            AND p.deleted_at IS NULL
        LIMIT 1
    ");

    $check->bind_param("ii", $taskId, $userId);
    $check->execute();

    if ($check->get_result()->num_rows === 0) {

        http_response_code(403);

        echo json_encode([
            'success' => false,
            'message' => 'Access denied'
        ]);

        exit;
    }

    $check->close();
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

        http_response_code(500);

        echo json_encode([
            'success' => false,
            'message' => 'Unable to save comment'
        ]);

        exit;
    }

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