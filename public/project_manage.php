<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include 'db.php';
include 'csrf.php';

$project_id = (int)($_GET['project_id'] ?? 0);
$user_id = $_SESSION['user_id'];

if ($project_id === 0) {
    die("Project workspace selection required.");
}

// 🔥 SECURITY FIX: Verify current user is the project owner before allowing management
$p_stmt = $conn->prepare("
    SELECT p.* 
    FROM projects p 
    WHERE p.id = ? AND p.owner_id = ?
");
$p_stmt->bind_param("ii", $project_id, $user_id);
$p_stmt->execute();
$project = $p_stmt->get_result()->fetch_assoc();
$p_stmt->close();

if (!$project) {
    die("Project not found or you do not have permission to manage this workspace.");
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_member'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf_token)) {
        $error = "Security validation failed.";
    } else {
        $new_user_id = (int)$_POST['user_id'];
        
        $check = $conn->prepare("SELECT project_id FROM project_members WHERE project_id = ? AND user_id = ?");
        $check->bind_param("ii", $project_id, $new_user_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = "User is already a member of this project workspace.";
        } else {
            $ins = $conn->prepare("INSERT INTO project_members (project_id, user_id, role) VALUES (?, ?, 'member')");
            $ins->bind_param("ii", $project_id, $new_user_id);
            if ($ins->execute()) {
                $success = "Team member added successfully!";
            } else {
                $error = "Failed to add member to workspace: " . $ins->error;
            }
            $ins->close();
        }
        $check->close();
    }
}

// Fetch current project members
$mem_query = $conn->prepare("
    SELECT users.id, users.name, users.email, project_members.role 
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

    <!-- Add Member Card -->
    <div class="pm-card">
        <h2>Invite a Team Member</h2>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
            <div class="form-group">
                <select name="user_id" class="form-select" required>
                    <option value="">-- Select User --</option>
                    <?php foreach ($available_users as $u): ?>
                        <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['name']); ?> (<?php echo htmlspecialchars($u['email']); ?>)</option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" name="add_member" class="btn btn-primary">Add to Project</button>
            </div>
        </form>
    </div>

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
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
</body>
</html>