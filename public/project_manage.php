<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include 'db.php';
include 'csrf.php';
require_once __DIR__ . '/includes/project_lifecycle.php';

$project_id = (int)($_GET['project_id'] ?? 0);
$user_id = $_SESSION['user_id'];

if ($project_id === 0) {
    die("Project workspace selection required.");
}

// 🔥 SECURITY FIX: Verify current user is a project member before allowing view
$p_stmt = $conn->prepare("
    SELECT p.*, pm.role as current_user_role 
    FROM projects p 
    JOIN project_members pm ON p.id = pm.project_id
    WHERE p.id = ? AND pm.user_id = ? AND p.deleted_at IS NULL
");
$p_stmt->bind_param("ii", $project_id, $user_id);
$p_stmt->execute();
$project = $p_stmt->get_result()->fetch_assoc();
$p_stmt->close();

if (!$project) {
    die("Project not found or you do not have permission to view this workspace.");
}

$is_owner = ($project['current_user_role'] === 'owner');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $is_owner) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf_token)) {
        $error = "Security validation failed.";
    } elseif (isset($_POST['remove_user_id'])) {
        $remove_user_id = (int)$_POST['remove_user_id'];
        
        $conn->begin_transaction();
        try {
            // Delete from task_assignees
            $del_tasks = $conn->prepare("DELETE FROM task_assignees WHERE project_id = ? AND user_id = ?");
            $del_tasks->bind_param("ii", $project_id, $remove_user_id);
            $del_tasks->execute();
            $del_tasks->close();

            // Delete from project_members
            $del_mem = $conn->prepare("DELETE FROM project_members WHERE project_id = ? AND user_id = ? AND role != 'owner'");
            $del_mem->bind_param("ii", $project_id, $remove_user_id);
            $del_mem->execute();
            $del_mem->close();

            $conn->commit();
            $success = "Team member removed successfully!";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Failed to remove member: " . $e->getMessage();
        }
    } elseif (isset($_POST['add_member'])) {
        $new_user_ids = $_POST['user_ids'] ?? [];
        if (!is_array($new_user_ids)) {
            $new_user_ids = [$new_user_ids];
        }
        
        $added = 0;
        foreach ($new_user_ids as $new_user_id) {
            $new_user_id = (int)$new_user_id;
            if ($new_user_id <= 0) continue;
            
            $check = $conn->prepare("SELECT project_id FROM project_members WHERE project_id = ? AND user_id = ?");
            $check->bind_param("ii", $project_id, $new_user_id);
            $check->execute();
            if ($check->get_result()->num_rows === 0) {
                $ins = $conn->prepare("INSERT INTO project_members (project_id, user_id, role) VALUES (?, ?, 'member')");
                $ins->bind_param("ii", $project_id, $new_user_id);
                if ($ins->execute()) {
                    $added++;
                }
                $ins->close();
            }
            $check->close();
        }
        if ($added > 0) {
            $success = "$added team member(s) added successfully!";
        } elseif (empty($error)) {
            $error = "Please select at least one user to add into the project.";
        }
    }
}

// Fetch current project members
$mem_query = $conn->prepare("
    SELECT users.id, users.name, users.email, project_members.role,
           (SELECT COUNT(*) FROM task_assignees ta WHERE ta.user_id = users.id AND ta.project_id = project_members.project_id) as assigned_task_count
    FROM project_members 
    JOIN users ON project_members.user_id = users.id 
    WHERE project_members.project_id = ?
    ORDER BY CASE WHEN project_members.role = 'owner' THEN 0 ELSE 1 END, users.name ASC
");
$mem_query->bind_param("i", $project_id);
$mem_query->execute();
$members = $mem_query->get_result()->fetch_all(MYSQLI_ASSOC);
$mem_query->close();

// Fetch users not already in the project to show in dropdown
$member_ids = array_column($members, 'id');
$placeholders = implode(',', array_fill(0, count($member_ids), '?'));

$non_members_query = "SELECT id, name, email FROM users WHERE id NOT IN ($placeholders) ORDER BY name ASC";
$non_members_stmt = $conn->prepare($non_members_query);
$non_members_stmt->bind_param(str_repeat('i', count($member_ids)), ...$member_ids);
$non_members_stmt->execute();
$available_users = $non_members_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$non_members_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Project Team – <?php echo htmlspecialchars($project['name']); ?></title>
    <link rel="stylesheet" href="assets/css/project-manage.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>
<body>
<div class="pm-container">
    <div class="pm-header">
        <h1>
            <span class="accent">👥</span> 
            Manage Team: <?php echo htmlspecialchars($project['name']); ?>
        </h1>
        <div class="pm-header-buttons">
            <a href="project_view.php?project_id=<?php echo $project_id; ?>" class="btn btn-back">← Project Board</a>
        </div>
    </div>

    <?php if(!empty($error)): ?>
        <div class="pm-alert pm-alert-error">⚠️ <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if(!empty($success)): ?>
        <div class="pm-alert pm-alert-success">✅ <?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if ($is_owner): ?>
    <!-- Add Member Card -->
    <div class="pm-card">
        <h2>Add a Team Member</h2>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
            <div class="form-group custom-select-group">
                <div class="user-checkbox-list">
                    <?php if (empty($available_users)): ?>
                        <div class="no-users">No more users available to invite.</div>
                    <?php else: ?>
                        <?php foreach ($available_users as $u): ?>
                            <label class="user-checkbox-item">
                                <input type="checkbox" name="user_ids[]" value="<?php echo $u['id']; ?>">
                                <div class="user-details">
                                    <span class="u-name"><?php echo htmlspecialchars($u['name']); ?></span>
                                    <span class="u-email"><?php echo htmlspecialchars($u['email']); ?></span>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <button type="submit" name="add_member" class="btn btn-primary" <?php echo empty($available_users) ? 'disabled' : ''; ?>>Add to Project</button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- Current Members List -->
    <div class="pm-card">
        <h2>Current Team Members (<?php echo count($members); ?>)</h2>
        <div class="member-list">
            <?php foreach ($members as $m): ?>
                <div class="member-item">
                    <div class="member-info">
                        <span class="member-name">
                            <?php echo htmlspecialchars($m['name']); ?>
                            <span class="role-badge role-<?php echo htmlspecialchars($m['role']); ?>">
                                <?php echo htmlspecialchars(ucfirst($m['role'] ?? 'member')); ?>
                            </span>
                        </span>
                        <span class="member-email"><?php echo htmlspecialchars($m['email']); ?></span>
                        <span class="member-tasks" style="font-size: 12px; color: #9CA3AF; margin-top: 4px; display: block;">Tasks Assigned: <?php echo (int)$m['assigned_task_count']; ?></span>
                    </div>
                    <?php if ($is_owner && $m['id'] !== $user_id): ?>
                        <button type="button" class="btn-remove-member" 
                                onclick="confirmRemoveMember(<?php echo $m['id']; ?>, '<?php echo htmlspecialchars(addslashes($m['name'])); ?>', <?php echo (int)$m['assigned_task_count']; ?>)" 
                                title="Remove Member">
                            <i class="fa fa-trash" aria-hidden="true"></i>
                        </button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

    <!-- Hidden form for removing member -->
    <form method="POST" id="remove-member-form" style="display:none;">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
        <input type="hidden" name="remove_user_id" id="remove_user_id_input" value="">
    </form>

<script>
function confirmRemoveMember(userId, userName, taskCount) {
    let msg = "Are you sure you want to remove " + userName + " from the project?";
    if (taskCount > 0) {
        msg += "\n\nWARNING: They are currently assigned to " + taskCount + " task(s). If removed, these tasks will not have any assignee.";
    }
    if (confirm(msg)) {
        document.getElementById('remove_user_id_input').value = userId;
        document.getElementById('remove-member-form').submit();
    }
}
</script>
</body>
</html>