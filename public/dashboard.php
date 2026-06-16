<?php
session_start();

// Auth guard - verify user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'db.php';
$user_id = $_SESSION['user_id'];

// Fetch user's projects (where they are a member or owner)
$projects_sql = "
    SELECT p.id, p.name, p.description, p.owner_id, p.due_date, p.created_at
    FROM projects p
    INNER JOIN project_members pm ON p.id = pm.project_id
    WHERE pm.user_id = ?
    ORDER BY p.updated_at DESC
";
$stmt = $conn->prepare($projects_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$projects_result = $stmt->get_result();
$projects = $projects_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch task counts and member counts for each project
$project_stats = [];
foreach ($projects as $project) {
    // Task count
    $task_sql = "SELECT COUNT(*) as task_count FROM tasks WHERE project_id = ?";
    $task_stmt = $conn->prepare($task_sql);
    $task_stmt->bind_param("i", $project['id']);
    $task_stmt->execute();
    $task_result = $task_stmt->get_result();
    $task_data = $task_result->fetch_assoc();
    $task_stmt->close();

    // Member count
    $member_sql = "SELECT COUNT(*) as member_count FROM project_members WHERE project_id = ?";
    $member_stmt = $conn->prepare($member_sql);
    $member_stmt->bind_param("i", $project['id']);
    $member_stmt->execute();
    $member_result = $member_stmt->get_result();
    $member_data = $member_result->fetch_assoc();
    $member_stmt->close();

    $project_stats[$project['id']] = [
        'task_count' => $task_data['task_count'],
        'member_count' => $member_data['member_count']
    ];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>ProManage - Organize, Assign, Deliver</title>

    <link rel="stylesheet" href="assets/css/dashboard.css">

</head>

<body>

    <div class="container">

        <!-- Header -->

        <div class="header">

            <h1><span class="pro-text">Pro</span><span class="manage-text">Manage</span></h1>

            <p class="dashboard-subtitle">
                Organize, Assign, Deliver
            </p>

            <div class="header-actions">

                <a href="index.php" class="index-btn">
                    ⌂ Home
                </a>

                <a href="project_create.php" class="create-btn">
                    + Create Project
                </a>

                <a href="logout.php" class="logout-btn">
                    Logout
                </a>

            </div>

        </div>

        <!-- Project Dashboard Section -->
<div class="project-dashboard">
    <h2>My Projects</h2>
    <?php if (count($projects) > 0): ?>
        <div class="projects-grid">
            <?php foreach ($projects as $project): ?>
                <a href="project_view.php?project_id=<?php echo $project['id']; ?>" class="project-card">
                    <div class="project-header">
                        <h3><?php echo htmlspecialchars($project['name']); ?></h3>
                        <span class="project-role"><?php echo ($project['owner_id'] == $user_id) ? 'Owner' : 'Member'; ?></span>
                    </div>
                    <p class="project-description"><?php echo htmlspecialchars(substr($project['description'], 0, 100)) . (strlen($project['description']) > 100 ? '...' : ''); ?></p>
                    <div class="project-stats">
                        <div class="stat">
                            <span class="stat-value"><?php echo $project_stats[$project['id']]['task_count']; ?></span>
                            <span class="stat-label">Tasks</span>
                        </div>
                        <div class="stat">
                            <span class="stat-value"><?php echo $project_stats[$project['id']]['member_count']; ?></span>
                            <span class="stat-label">Members</span>
                        </div>
                    </div>
                    <?php if ($project['due_date']): ?>
                        <div class="project-due">Due: <?php echo date('M d, Y', strtotime($project['due_date'])); ?></div>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="no-projects">
            <p>No projects yet. <a href="project_create.php">Create your first project</a></p>
        </div>
    <?php endif; ?>
</div>

    </div>

</body>

</html>