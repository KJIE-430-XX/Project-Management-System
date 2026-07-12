<?php

function ensureTaskCommentNotificationsTable(mysqli $conn): void
{
    static $tableEnsured = false;

    if ($tableEnsured) {
        return;
    }

    $sql = "
        CREATE TABLE IF NOT EXISTS task_comment_notifications (
            task_id INT NOT NULL,
            user_id INT NOT NULL,
            unread_count INT NOT NULL DEFAULT 0,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (task_id, user_id),
            FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ";

    $conn->query($sql);
    $tableEnsured = true;
}

function getTaskCommentConversationContext(mysqli $conn, int $taskId, int $userId): ?array
{
    ensureTaskCommentNotificationsTable($conn);

    $sql = "
        SELECT
            t.id AS task_id,
            t.project_id,
            p.owner_id,
            task_assignee.user_id AS assignee_id,
            CASE WHEN p.owner_id = ? THEN 1 ELSE 0 END AS is_owner,
            CASE WHEN task_assignee.user_id = ? THEN 1 ELSE 0 END AS is_assignee
        FROM tasks t
        INNER JOIN projects p
            ON p.id = t.project_id
        LEFT JOIN (
            SELECT ta.task_id, MIN(ta.user_id) AS user_id
            FROM task_assignees ta
            GROUP BY ta.task_id
        ) task_assignee
            ON task_assignee.task_id = t.id
        WHERE t.id = ?
          AND p.deleted_at IS NULL
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $userId, $userId, $taskId);
    $stmt->execute();

    $context = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();

    if (!$context) {
        return null;
    }

    $context['is_owner'] = (int)($context['is_owner'] ?? 0);
    $context['is_assignee'] = (int)($context['is_assignee'] ?? 0);
    $context['owner_id'] = (int)($context['owner_id'] ?? 0);
    $context['assignee_id'] = (int)($context['assignee_id'] ?? 0);
    $context['is_participant'] = ($context['is_owner'] === 1 || $context['is_assignee'] === 1);

    return $context;
}

function resolveTaskCommentRecipientId(array $context, int $commenterUserId): ?int
{
    $ownerId = (int)($context['owner_id'] ?? 0);
    $assigneeId = (int)($context['assignee_id'] ?? 0);

    if ((int)($context['is_owner'] ?? 0) === 1) {
        return ($assigneeId > 0 && $assigneeId !== $commenterUserId) ? $assigneeId : null;
    }

    if ((int)($context['is_assignee'] ?? 0) === 1) {
        return ($ownerId > 0 && $ownerId !== $commenterUserId) ? $ownerId : null;
    }

    return null;
}

function recordUnreadTaskCommentNotification(mysqli $conn, int $taskId, ?int $recipientUserId): bool
{
    ensureTaskCommentNotificationsTable($conn);

    if ($taskId <= 0 || !$recipientUserId) {
        return true;
    }

    $sql = "
        INSERT INTO task_comment_notifications (task_id, user_id, unread_count)
        VALUES (?, ?, 1)
        ON DUPLICATE KEY UPDATE
            unread_count = unread_count + 1,
            updated_at = CURRENT_TIMESTAMP
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $taskId, $recipientUserId);
    $success = $stmt->execute();
    $stmt->close();

    return $success;
}

function markTaskCommentsAsRead(mysqli $conn, int $taskId, int $userId): bool
{
    ensureTaskCommentNotificationsTable($conn);

    if ($taskId <= 0 || $userId <= 0) {
        return false;
    }

    $stmt = $conn->prepare("
        UPDATE task_comment_notifications
        SET unread_count = 0,
            updated_at = CURRENT_TIMESTAMP
        WHERE task_id = ?
          AND user_id = ?
          AND unread_count > 0
    ");
    $stmt->bind_param("ii", $taskId, $userId);
    $success = $stmt->execute();
    $stmt->close();

    return $success;
}
?>
