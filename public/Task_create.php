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

// Catch the active project ID from the URL context string
$project_id = (int)($_GET['project_id'] ?? 0);

if ($project_id === 0) {
    die("Error: No project workspace context provided.");
}

// Fetch the current project's name and its due date to validate against
$user_id = $_SESSION['user_id'];
$proj_stmt = $conn->prepare("
    SELECT p.name, p.due_date 
    FROM projects p 
    JOIN project_members pm ON p.id = pm.project_id 
    WHERE p.id = ? AND pm.user_id = ?
");
$proj_stmt->bind_param("ii", $project_id, $user_id);
$proj_stmt->execute();
$project_data = $proj_stmt->get_result()->fetch_assoc();
$proj_stmt->close();

if (!$project_data) {
    die("Error: Project workspace not found or you do not have permission to access it.");
}

// Fetch project members to assign the task to
$members_stmt = $conn->prepare("
    SELECT u.id, u.name, u.email 
    FROM project_members pm 
    JOIN users u ON pm.user_id = u.id 
    WHERE pm.project_id = ? 
    ORDER BY u.name ASC
");
$members_stmt->bind_param("i", $project_id);
$members_stmt->execute();
$project_members = $members_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$members_stmt->close();

// Fetch valid statuses and priorities from database
$status_list = [];
$priority_list = [];

$status_stmt = $conn->prepare("SELECT id, name FROM status ORDER BY id");
$status_stmt->execute();
$status_result = $status_stmt->get_result();
while ($row = $status_result->fetch_assoc()) {
    $status_list[$row['id']] = $row['name'];
}
$status_stmt->close();

$priority_stmt = $conn->prepare("SELECT id, name FROM priority ORDER BY id");
$priority_stmt->execute();
$priority_result = $priority_stmt->get_result();
while ($row = $priority_result->fetch_assoc()) {
    $priority_list[$row['id']] = $row['name'];
}
$priority_stmt->close();

if (empty($status_list) || empty($priority_list)) {
    die("Error: Status or Priority table is empty. Please contact administrator.");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!validateCSRFToken($csrf_token)) {
        $error = "Security validation failed. Please try again.";
    } else {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $priority_id = (int)($_POST['priority_id'] ?? 0); 
        $status_id = (int)($_POST['status_id'] ?? 0);     
        $assignee_id = !empty($_POST['assignee_id']) ? (int)$_POST['assignee_id'] : null;
        $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;

        if (empty($title)) {
            $error = "Task title is required.";
        } elseif (!isset($status_list[$status_id])) { 
            $error = "Invalid status selected.";
        } elseif (!isset($priority_list[$priority_id])) {
            $error = "Invalid priority selected.";
        } 
        // Validate assignee is part of the project members
        elseif ($assignee_id !== null) {
            $is_member = false;
            foreach ($project_members as $m) {
                if ($m['id'] === $assignee_id) {
                    $is_member = true;
                    break;
                }
            }
            if (!$is_member) {
                $error = "Invalid assignee selected. User must be a member of the project workspace.";
            }
        }
        
        if (empty($error)) {
            // Check task due date against project deadline
            if ($due_date !== null && $project_data['due_date'] !== null && strtotime($due_date) > strtotime($project_data['due_date'])) {
                $error = "Task due date cannot be later than the project deadline (" . date('M d, Y', strtotime($project_data['due_date'])) . ").";
            } else {
                try {
                    $conn->begin_transaction();

                    $stmt = $conn->prepare("
                        INSERT INTO tasks 
                        (created_by, project_id, title, description, status_id, priority_id, due_date, created_at, updated_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ");

                    $stmt->bind_param(
                        "iissiis",
                        $user_id,
                        $project_id,
                        $title,
                        $description,
                        $status_id,
                        $priority_id,
                        $due_date
                    );

                    if ($stmt->execute()) {
                        $task_id = $stmt->insert_id;
                        $stmt->close();

                        // If assignee is specified, insert into task_assignees
                        if ($assignee_id !== null) {
                            $assign_stmt = $conn->prepare("
                                INSERT INTO task_assignees (task_id, project_id, user_id) 
                                VALUES (?, ?, ?)
                            ");
                            $assign_stmt->bind_param("iii", $task_id, $project_id, $assignee_id);
                            if (!$assign_stmt->execute()) {
                                throw new Exception("Failed to assign user to task: " . $assign_stmt->error);
                            }
                            $assign_stmt->close();
                        }

                        $conn->commit();
                        $_SESSION['success'] = "Task created successfully!";
                        header("Location: project_view.php?project_id=" . $project_id);
                        exit;
                    } else {
                        throw new Exception("Error creating task: " . $stmt->error);
                    }
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = $e->getMessage();
                }
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
    <title>Create Task – ProManage</title>
    <link rel="stylesheet" href="assets/css/task-create.css">
</head>
<body>
<div class="container">
    <div class="form-container">
        <div class="form-header">
            <h1>Create New Task</h1>
            <p class="form-subtitle">Add a task to the project workspace</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">⚠️ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
            <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">

            <div class="form-group">
                <label>Project Workspace</label>
                <div class="static-field">
                    <strong>📁 <?php echo htmlspecialchars($project_data['name']); ?></strong>
                    <?php if ($project_data['due_date']): ?>
                        <span style="float: right; font-size: 13px;">
                            Deadline: <?php echo date('M d, Y', strtotime($project_data['due_date'])); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-group">
                <label for="title">Task Title <span class="required">*</span></label>
                <input type="text" id="title" name="title" placeholder="Enter task title" maxlength="255" required value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" placeholder="Describe the task details..." maxlength="1000"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
            </div>

            <div class="form-group">
                <label for="assignee_id">Assignee</label>
                <select id="assignee_id" name="assignee_id">
                    <option value="">-- Unassigned --</option>
                    <?php foreach ($project_members as $member): ?>
                        <option value="<?php echo $member['id']; ?>" <?php echo (isset($_POST['assignee_id']) && (int)$_POST['assignee_id'] === $member['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($member['name']); ?> (<?php echo htmlspecialchars($member['email']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="status_id">Status</label>
                <select id="status_id" name="status_id">
                    <?php foreach ($status_list as $id => $name): ?>
                        <option value="<?php echo $id; ?>" <?php echo (isset($_POST['status_id']) ? (int)$_POST['status_id'] === $id : $id === 2) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="priority_id">Priority</label>
                <select id="priority_id" name="priority_id">
                    <?php foreach ($priority_list as $id => $name): ?>
                        <option value="<?php echo $id; ?>" <?php echo (isset($_POST['priority_id']) ? (int)$_POST['priority_id'] === $id : $id === 3) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="due_date">Due Date</label>
                <input type="date" id="due_date" name="due_date" 
                       value="<?php echo isset($_POST['due_date']) ? htmlspecialchars($_POST['due_date']) : ''; ?>"
                       <?php if ($project_data['due_date']): ?> max="<?php echo $project_data['due_date']; ?>" <?php endif; ?>>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-primary">Create Task</button>
                <a href="project_view.php?project_id=<?php echo $project_id; ?>" class="btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>