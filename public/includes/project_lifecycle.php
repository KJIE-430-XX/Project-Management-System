<?php

function purgeExpiredTrashedProjects(mysqli $conn)
{
    $stmt = $conn->prepare("DELETE FROM projects WHERE deleted_at IS NOT NULL AND deleted_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    if ($stmt) {
        $stmt->execute();
        $stmt->close();
    }
}

function fetchProjectForMember(mysqli $conn, int $projectId, int $userId, bool $includeTrash = false)
{
    $sql = "
        SELECT p.*
        FROM projects p
        INNER JOIN project_members pm ON p.id = pm.project_id
        WHERE p.id = ? AND pm.user_id = ?
    ";

    $sql .= $includeTrash ? " AND p.deleted_at IS NOT NULL" : " AND p.deleted_at IS NULL";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $projectId, $userId);
    $stmt->execute();
    $project = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $project;
}

function fetchProjectForOwner(mysqli $conn, int $projectId, int $userId, bool $includeTrash = false)
{
    $sql = "SELECT p.* FROM projects p WHERE p.id = ? AND p.owner_id = ?";
    $sql .= $includeTrash ? " AND p.deleted_at IS NOT NULL" : " AND p.deleted_at IS NULL";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $projectId, $userId);
    $stmt->execute();
    $project = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $project;
}

?>