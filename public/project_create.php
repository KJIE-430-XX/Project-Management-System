<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include 'db.php';
include 'csrf.php';

$error = '';
$success = '';

// Fetch all users except the current logged-in user
$current_user_id = $_SESSION['user_id'];
$users_result = $conn->prepare("SELECT id, name, username FROM users WHERE id != ? ORDER BY name ASC");
$users_result->bind_param("i", $current_user_id);
$users_result->execute();
$available_users = $users_result->get_result()->fetch_all(MYSQLI_ASSOC);
$users_result->close();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf_token)) {
        $error = "Security validation failed.";
    } else {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        // 🔥 NEW: Capture project due date from the form
        $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
        $user_id = $_SESSION['user_id'];

        if (empty($name)) {
            $error = "Project name is required.";
        } else {
            // 🔥 UPDATED: Wrap project and owner membership in a transaction
            try {
                $conn->begin_transaction();
                
                $stmt = $conn->prepare("INSERT INTO projects (name, description, owner_id, due_date) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssis", $name, $description, $user_id, $due_date);
                
                if (!$stmt->execute()) {
                    throw new Exception("Error creating project: " . $stmt->error);
                }
                
                $project_id = $stmt->insert_id;
                $stmt->close();
                
                // Establish creator automatically with 'owner' privileges 
                $member_stmt = $conn->prepare("INSERT INTO project_members (project_id, user_id, role) VALUES (?, ?, 'owner')");
                $member_stmt->bind_param("ii", $project_id, $user_id);
                
                if (!$member_stmt->execute()) {
                    throw new Exception("Error adding owner to project members: " . $member_stmt->error);
                }
                
                $member_stmt->close();

                // Add selected members to the project
                if (!empty($_POST['members']) && is_array($_POST['members'])) {
                    $invite_stmt = $conn->prepare("INSERT INTO project_members (project_id, user_id, role) VALUES (?, ?, 'member')");
                    foreach ($_POST['members'] as $member_id) {
                        $member_id = (int)$member_id;
                        if ($member_id !== $user_id) { // Safety: don't duplicate owner
                            $invite_stmt->bind_param("ii", $project_id, $member_id);
                            if (!$invite_stmt->execute()) {
                                throw new Exception("Error inviting member: " . $invite_stmt->error);
                            }
                        }
                    }
                    $invite_stmt->close();
                }

                // Commit transaction
                $conn->commit();
                
                $_SESSION['success'] = "Project created successfully!";
                header("Location: project_view.php?project_id=" . $project_id);
                exit;
            } catch (Exception $e) {
                // Rollback on error
                $conn->rollback();
                $error = $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ProManage - Create Project</title>
    <link rel="stylesheet" href="assets/css/task-create.css">
</head>
<body>
    <div class="container">
        <div class="form-container">
            <div class="form-header">
                <h1><span class="pro-text">Pro</span><span class="manage-text">Manage</span></h1>
                <p class="form-subtitle">Create a new project</p>
            </div>

            <?php if(!empty($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                
                <div class="form-group">
                    <label for="name">Project Name <span class="required">*</span></label>
                    <input type="text" id="name" name="name" placeholder="Enter project name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" placeholder="Describe your project..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                </div>

                <div class="form-group">
                    <label for="due_date">Due Date</label>
                    <input type="date" id="due_date" name="due_date" value="<?php echo isset($_POST['due_date']) ? htmlspecialchars($_POST['due_date']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label>Invite Members</label>
                    <div class="dropdown-wrapper" id="memberDropdown">
                        <div class="dropdown-trigger" id="memberTrigger">
                            <span class="trigger-text" id="memberTriggerText">– Select –</span>
                            <span class="trigger-arrow">▼</span>
                        </div>
                        <div class="dropdown-menu-list" id="memberMenuList">
                            <?php if (count($available_users) > 0): ?>
                                <?php foreach ($available_users as $user): ?>
                                    <label class="dropdown-item" data-user-id="<?php echo $user['id']; ?>">
                                        <input type="checkbox" class="dropdown-item-checkbox" name="members[]" value="<?php echo $user['id']; ?>"
                                            <?php echo (isset($_POST['members']) && in_array($user['id'], $_POST['members'])) ? 'checked' : ''; ?>>
                                        <span class="dropdown-item-text"><?php echo htmlspecialchars($user['name']); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="dropdown-item" style="cursor: default; color: #64748B;">
                                    <span class="dropdown-item-text" style="color: #64748B;">No users available</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary">Create Project</button>
                    <a href="dashboard.php" class="btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
<script>
(function() {
    const wrapper = document.getElementById('memberDropdown');
    const trigger = document.getElementById('memberTrigger');
    const triggerText = document.getElementById('memberTriggerText');
    const checkboxes = wrapper.querySelectorAll('.dropdown-item-checkbox');

    // Toggle dropdown open/close
    trigger.addEventListener('click', function(e) {
        e.stopPropagation();
        wrapper.classList.toggle('open');
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!wrapper.contains(e.target)) {
            wrapper.classList.remove('open');
        }
    });

    // Update trigger text on checkbox change
    function updateTriggerText() {
        const checked = wrapper.querySelectorAll('.dropdown-item-checkbox:checked');
        if (checked.length === 0) {
            triggerText.textContent = '– Select –';
            triggerText.style.color = '#9CA3AF';
        } else {
            const names = Array.from(checked).map(cb => cb.closest('.dropdown-item').querySelector('.dropdown-item-text').textContent);
            triggerText.textContent = names.join(', ');
            triggerText.style.color = '#F8FAFC';
        }
    }

    checkboxes.forEach(cb => cb.addEventListener('change', updateTriggerText));

    // Initialize trigger text on load (for form re-submissions)
    updateTriggerText();
})();
</script>
</body>
</html>