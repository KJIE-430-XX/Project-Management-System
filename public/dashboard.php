<?php
session_start();

// Auth guard - verify user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'db.php';
$user_id = $_SESSION['user_id'];
$projects = [];
$project_stats = [];

// Fetch user's projects (where they are a member or owner)
$projects_sql = "
    SELECT p.id, p.name, p.description, p.owner_id, p.due_date, p.created_at, p.workspace_id
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

// Fetch workspaces (owned by user OR containing projects the user is a member of)
$workspaces_sql = "
    SELECT DISTINCT w.id, w.name, w.user_id as owner_id
    FROM workspaces w
    LEFT JOIN projects p ON p.workspace_id = w.id
    LEFT JOIN project_members pm ON p.id = pm.project_id
    WHERE w.user_id = ? OR pm.user_id = ?
    ORDER BY w.name ASC
";
$stmt = $conn->prepare($workspaces_sql);
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$workspaces_result = $stmt->get_result();
$workspaces = $workspaces_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch task counts and member counts for each project
$project_stats = [];
foreach ($projects as $project) {
    // Task count (total and completed)
    $task_sql = "SELECT COUNT(*) as task_count, SUM(CASE WHEN status_id = 1 THEN 1 ELSE 0 END) as completed_count FROM tasks WHERE project_id = ?";
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
        'completed_count' => $task_data['completed_count'] ?? 0,
        'member_count' => $member_data['member_count']
    ];
}

// Aggregate workspace statistics
$workspace_stats = [];
$workspace_stats['null'] = [
    'project_count' => 0,
    'total_tasks' => 0,
    'completed_tasks' => 0
];
foreach ($workspaces as $workspace) {
    $workspace_stats[$workspace['id']] = [
        'project_count' => 0,
        'total_tasks' => 0,
        'completed_tasks' => 0
    ];
}
foreach ($projects as $project) {
    $w_id = $project['workspace_id'] ?: 'null';
    if (!isset($workspace_stats[$w_id])) {
        $workspace_stats[$w_id] = [
            'project_count' => 0,
            'total_tasks' => 0,
            'completed_tasks' => 0
        ];
    }
    $p_stats = $project_stats[$project['id']];
    $workspace_stats[$w_id]['project_count']++;
    $workspace_stats[$w_id]['total_tasks'] += $p_stats['task_count'];
    $workspace_stats[$w_id]['completed_tasks'] += $p_stats['completed_count'];
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

                <a href="profile.php" class="profile-btn">
                    👤 My Profile
                </a>

                <a href="project_create.php" class="create-btn">
                    + Create Project
                </a>

                <a href="logout.php" class="logout-btn">
                    Logout
                </a>

            </div>

        </div>

        <!-- Dashboard Layout -->
        <div class="dashboard-layout">
            
            <!-- Sidebar (Workspaces) -->
            <div class="sidebar">
                <div class="sidebar-header">
                    <h2>Workspaces</h2>
                    <button class="new-workspace-btn" onclick="openWorkspaceModal()">+ New</button>
                </div>
                
                <ul class="workspace-list" id="workspace-list">
                    <!-- Dynamic Workspace List -->
                    <?php foreach ($workspaces as $workspace): ?>
                        <?php 
                        $w_id = $workspace['id'];
                        $w_stats = $workspace_stats[$w_id] ?? ['project_count' => 0, 'total_tasks' => 0, 'completed_tasks' => 0];
                        $total_t = $w_stats['total_tasks'];
                        $comp_t = $w_stats['completed_tasks'];
                        $percent = $total_t > 0 ? round(($comp_t / $total_t) * 100, 1) : 0;
                        ?>
                        <li class="workspace-item dropzone" 
                            data-id="<?php echo $w_id; ?>" 
                            data-owner="<?php echo $workspace['owner_id']; ?>"
                            data-project-count="<?php echo $w_stats['project_count']; ?>"
                            data-total-tasks="<?php echo $total_t; ?>"
                            data-completed-tasks="<?php echo $comp_t; ?>"
                            data-progress-percent="<?php echo $percent; ?>"
                            onclick="selectWorkspace(<?php echo $w_id; ?>)">
                            <div class="workspace-item-content">
                                <div class="workspace-main-row">
                                    <div class="workspace-name">
                                        📁 <span class="name-text"><?php echo htmlspecialchars($workspace['name']); ?></span>
                                        <span class="project-count-tag"><?php echo $w_stats['project_count']; ?> projects</span>
                                    </div>
                                    <?php if ($workspace['owner_id'] == $user_id): ?>
                                        <div class="workspace-actions">
                                            <button class="action-btn" onclick="editWorkspace(event, <?php echo (int)$workspace['id']; ?>, <?php echo htmlspecialchars(json_encode($workspace['name']), ENT_QUOTES, 'UTF-8'); ?>)">✎</button>
                                            <button class="action-btn delete-btn" onclick="deleteWorkspace(event, <?php echo $workspace['id']; ?>)">🗑</button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="workspace-progress-container">
                                    <div class="workspace-progress-bar-wrapper">
                                        <div class="workspace-progress-bar-fill" style="width: <?php echo $percent; ?>%;"></div>
                                    </div>
                                    <div class="workspace-progress-text">
                                        Progress: <?php echo $percent; ?>% (<?php echo $comp_t; ?>/<?php echo $total_t; ?>)
                                    </div>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
                
                <hr class="sidebar-divider">
                
                <?php 
                $w_stats = $workspace_stats['null'];
                $total_t = $w_stats['total_tasks'];
                $comp_t = $w_stats['completed_tasks'];
                $percent = $total_t > 0 ? round(($comp_t / $total_t) * 100, 1) : 0;
                ?>
                <div class="workspace-item dropzone active" 
                     data-id="null" 
                     data-project-count="<?php echo $w_stats['project_count']; ?>"
                     data-total-tasks="<?php echo $total_t; ?>"
                     data-completed-tasks="<?php echo $comp_t; ?>"
                     data-progress-percent="<?php echo $percent; ?>"
                     onclick="selectWorkspace('null')">
                    <div class="workspace-item-content">
                        <div class="workspace-main-row">
                            <div class="workspace-name">
                                📁 <span class="name-text">Uncategorized</span>
                                <span class="project-count-tag"><?php echo $w_stats['project_count']; ?> projects</span>
                            </div>
                        </div>
                        <div class="workspace-progress-container">
                            <div class="workspace-progress-bar-wrapper">
                                <div class="workspace-progress-bar-fill" style="width: <?php echo $percent; ?>%;"></div>
                            </div>
                            <div class="workspace-progress-text">
                                Progress: <?php echo $percent; ?>% (<?php echo $comp_t; ?>/<?php echo $total_t; ?>)
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content (Projects) -->
            <div class="main-content">
                <div class="main-header">
                    <div class="main-header-left">
                        <h2 id="current-workspace-title">Uncategorized</h2>
                        <span id="current-workspace-count" class="workspace-header-count"></span>
                    </div>
                    <!-- Workspace Header Progress Bar -->
                    <div id="workspace-header-progress" class="workspace-header-progress-container" style="display: none;">
                        <div class="workspace-header-progress-bar-wrapper">
                            <div id="workspace-header-progress-bar-fill" class="workspace-header-progress-bar-fill"></div>
                        </div>
                        <div id="workspace-header-progress-text" class="workspace-header-progress-text"></div>
                    </div>
                </div>

                <div class="project-dashboard">
                    <?php if (count($projects) > 0): ?>
                        <div class="projects-grid" id="projects-grid">
                            <?php foreach ($projects as $project): ?>
                                <?php $is_owner = ($project['owner_id'] == $user_id); ?>
                                <a href="project_view.php?project_id=<?php echo $project['id']; ?>" 
                                   class="project-card <?php echo $is_owner ? 'draggable' : ''; ?>"
                                   data-id="<?php echo $project['id']; ?>"
                                   data-workspace-id="<?php echo $project['workspace_id'] ?: 'null'; ?>"
                                   <?php echo $is_owner ? 'draggable="true"' : ''; ?>
                                >
                                    <div class="project-header">
                                        <h3><?php echo htmlspecialchars($project['name']); ?></h3>
                                        <span class="project-role"><?php echo $is_owner ? 'Owner' : 'Member'; ?></span>
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
                        <div id="empty-state" class="no-projects" style="display: none;">
                            <p>No projects in this workspace.</p>
                        </div>
                    <?php else: ?>
                        <div class="no-projects">
                            <p>No projects yet. <a href="project_create.php">Create your first project</a></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

    <!-- Modals & Toasts -->
    <div id="workspaceModal" class="modal">
        <div class="modal-content">
            <h3 id="modal-title">New Workspace</h3>
            <input type="hidden" id="workspace_id_input" value="">
            <input type="text" id="workspace_name_input" placeholder="Workspace Name" class="workspace-input">
            <div class="modal-actions">
                <button class="btn-cancel" onclick="closeWorkspaceModal()">Cancel</button>
                <button class="btn-save" onclick="saveWorkspace()">Save</button>
            </div>
        </div>
    </div>

    <div id="toast" class="toast"></div>

    <script src="assets/js/dashboard.js"></script>
</body>

</html>