<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'db.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/includes/project_lifecycle.php';

$user_id = (int)$_SESSION['user_id'];
purgeExpiredTrashedProjects($conn);

$trash_sql = "
    SELECT p.id, p.name, p.description, p.due_date, p.deleted_at, p.created_at, p.updated_at
    FROM projects p
    WHERE p.owner_id = ? AND p.deleted_at IS NOT NULL
    ORDER BY p.deleted_at DESC
";

$stmt = $conn->prepare($trash_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$trashed_projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trash – ProManage</title>
    <link rel="stylesheet" href="assets/css/trash.css">
</head>
<body>
    <script>
        window.PROMANAGE_CSRF_TOKEN = <?php echo json_encode($csrf_token); ?>;
    </script>
    <div class="trash-container">
        <div class="trash-header">
            <div>
                <h1>Trash</h1>
                <p>Projects stay here for 30 days before automatic permanent deletion.</p>
            </div>
            <div class="trash-header-actions">
                <a href="dashboard.php" class="btn btn-secondary">← Dashboard</a>
            </div>
        </div>

        <?php if (count($trashed_projects) > 0): ?>
            <div class="trash-grid">
                <?php foreach ($trashed_projects as $project):
                    $deleted_at = strtotime($project['deleted_at']);
                    $days_left = max(0, 30 - (int)ceil((time() - $deleted_at) / 86400));
                ?>
                    <div class="trash-card">
                        <div class="trash-card-header">
                            <div>
                                <h2><?php echo htmlspecialchars($project['name']); ?></h2>
                                <span class="trash-badge">Auto delete in <?php echo $days_left; ?> day<?php echo $days_left === 1 ? '' : 's'; ?></span>
                            </div>
                        </div>
                        <p class="trash-description">
                            <?php echo htmlspecialchars($project['description'] ?: 'No description provided.'); ?>
                        </p>
                        <div class="trash-meta">
                            <span>Deleted: <?php echo date('M d, Y', $deleted_at); ?></span>
                            <?php if (!empty($project['due_date'])): ?>
                                <span>Due: <?php echo date('M d, Y', strtotime($project['due_date'])); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="trash-actions">
                            <button class="btn btn-primary" onclick="restoreProject(event, <?php echo (int)$project['id']; ?>)">Restore</button>
                            <button class="btn btn-danger" onclick="permanentlyDeleteProject(event, <?php echo (int)$project['id']; ?>, <?php echo htmlspecialchars(json_encode($project['name']), ENT_QUOTES, 'UTF-8'); ?>)">Delete Permanently</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="trash-empty">
                <h2>Trash is empty</h2>
                <p>Soft-deleted projects will appear here for 30 days.</p>
            </div>
        <?php endif; ?>
    </div>

    <script src="assets/js/project-actions.js"></script>
</body>
</html>