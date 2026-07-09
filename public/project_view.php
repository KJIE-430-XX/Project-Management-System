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

// Fetch current user details
$user_stmt = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$current_user = $user_stmt->get_result()->fetch_assoc();
$user_stmt->close();

// 🔥 SECURITY FIX: Verify user is a member of the project before loading it
$p_stmt = $conn->prepare("
    SELECT p.* 
    FROM projects p 
    JOIN project_members pm ON p.id = pm.project_id 
    WHERE p.id = ? AND pm.user_id = ? AND p.deleted_at IS NULL
");
$p_stmt->bind_param("ii", $project_id, $user_id);
$p_stmt->execute();
$project = $p_stmt->get_result()->fetch_assoc();
$p_stmt->close();

if (!$project) {
    die("Workspace project channel not found or you do not have permission to access it.");
}

// 🔥 FIXED: Adjusted query selection to explicitly read priority_id and status_id columns
$t_stmt = $conn->prepare("
  SELECT t.*, u.name AS creator_name, au.id AS assignee_id, au.name AS assignee_name 
    FROM tasks t 
    LEFT JOIN users u ON t.created_by = u.id
  LEFT JOIN (
    SELECT ta.task_id, ta.user_id
    FROM task_assignees ta
    INNER JOIN (
      SELECT task_id, MIN(user_id) AS user_id
      FROM task_assignees
      GROUP BY task_id
    ) chosen ON chosen.task_id = ta.task_id AND chosen.user_id = ta.user_id
  ) task_assignee ON task_assignee.task_id = t.id
  LEFT JOIN users au ON au.id = task_assignee.user_id
    WHERE t.project_id = ? AND (t.created_by = ? OR EXISTS (SELECT 1 FROM task_assignees ta2 WHERE ta2.task_id = t.id AND ta2.user_id = ?))
    ORDER BY t.created_at DESC
");
$t_stmt->bind_param("iii", $project_id, $user_id, $user_id);
$t_stmt->execute();
$tasks = $t_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$t_stmt->close();

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

$priorities = [1 => ['label' => 'High', 'class' => 'high'], 2 => ['label' => 'Medium', 'class' => 'medium'], 3 => ['label' => 'Low', 'class' => 'low']];
$statuses   = [1 => ['label' => 'Completed', 'class' => 'completed'], 2 => ['label' => 'To Do', 'class' => 'todo'], 3 => ['label' => 'Pending', 'class' => 'pending']];

// Count tasks by status
$total_tasks = count($tasks);
$completed_count = 0;
$todo_count = 0;
$pending_count = 0;
$active_tasks = [];
$completed_tasks = [];
foreach ($tasks as $t) {
    $sid = (int)($t['status_id'] ?? 2);
  if ($sid === 1) {
    $completed_count++;
    $completed_tasks[] = $t;
  } else {
    $active_tasks[] = $t;
    if ($sid === 2) $todo_count++;
    elseif ($sid === 3) $pending_count++;
  }
}
$active_count = count($active_tasks);

// Generate CSRF token for AJAX calls
$csrf_token = generateCSRFToken();

// Check for flash success message
$success_msg = '';
if (isset($_SESSION['success'])) {
    $success_msg = $_SESSION['success'];
    unset($_SESSION['success']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($project['name']); ?> – ProManage</title>
  <link rel="stylesheet" href="assets/css/project-view.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>
<body>
  <div class="pv-container">

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
      <a href="dashboard.php" style="text-decoration: none; font-size: 24px; font-weight: bold;">
          <span style="color: #A855F7;">Pro</span><span style="color: #FFFFFF;">Manage</span>
      </a>
      <div class="user-dropdown-container">
          <div class="user-dropdown-toggle">
              <div style="display: flex; flex-direction: column; text-align: left; justify-content: center;">
                  <span class="user-name" style="font-weight: 600; font-size: 14px; line-height: 1.2;"><?php echo htmlspecialchars($current_user['name'] ?? 'Unknown User'); ?></span>
                  <span class="user-email" style="font-size: 12px; opacity: 0.7; line-height: 1.2;"><?php echo htmlspecialchars($current_user['email'] ?? ''); ?></span>
              </div>
              <span style="font-size: 10px; margin-left: 4px; color: #94A3B8;">▼</span>
          </div>
          <div class="user-dropdown-menu">
              <a href="profile.php"><i class="fa fa-user" aria-hidden="true"></i> My Profile</a>
              <a href="logout.php"><i class="fa fa-sign-out" aria-hidden="true"></i> Logout</a>
          </div>
      </div>
    </div>

    <!-- Header -->
    <div class="pv-header">
      <div class="pv-header-left">
        <h1>
          <span class="accent">📁</span>
          <?php echo htmlspecialchars($project['name']); ?>
        </h1>
      </div>
      <div class="pv-header-buttons">
        <a href="project_manage.php?project_id=<?php echo $project_id; ?>" class="btn btn-secondary">👥 Team</a>
        <a href="task_create.php?project_id=<?php echo $project_id; ?>" class="btn btn-primary">＋ Add Task</a>
        <a href="dashboard.php" class="btn btn-back">← Dashboard</a>
      </div>
    </div>

    <!-- Success flash -->
    <?php if (!empty($success_msg)): ?>
      <div class="pv-alert pv-alert-success">
        ✅ <?php echo htmlspecialchars($success_msg); ?>
      </div>
    <?php endif; ?>

    <!-- Description -->
    <div class="pv-description">
      <strong>Description:</strong> <?php echo htmlspecialchars($project['description'] ?: 'No description provided.'); ?>
      <?php if (!empty($project['due_date'])): ?>
        <span style="float:right; font-size:12px;">📅 Due: <?php echo date('M d, Y', strtotime($project['due_date'])); ?></span>
      <?php endif; ?>
    </div>

    <!-- Stats -->
    <div class="pv-stats">
      <div class="pv-stat-card">
        <div class="pv-stat-value"><?php echo $total_tasks; ?></div>
        <div class="pv-stat-label">Total Tasks</div>
      </div>
      <div class="pv-stat-card">
        <div class="pv-stat-value" style="color: #4ADE80;"><?php echo $completed_count; ?></div>
        <div class="pv-stat-label">Completed</div>
      </div>
      <div class="pv-stat-card">
        <div class="pv-stat-value" style="color: #818CF8;"><?php echo $todo_count; ?></div>
        <div class="pv-stat-label">To Do</div>
      </div>
      <div class="pv-stat-card">
        <div class="pv-stat-value" style="color: #FBBF24;"><?php echo $pending_count; ?></div>
        <div class="pv-stat-label">Pending</div>
      </div>
    </div>

    <!-- Tasks Section -->
    <div class="pv-tasks-section">
      <div class="pv-tasks-toolbar">
        <h2>Tasks</h2>
        <div class="pv-view-toggle">
          <button class="pv-view-btn active" data-view="grid" id="btnGridView">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><rect x="1" y="1" width="6" height="6" rx="1"/><rect x="9" y="1" width="6" height="6" rx="1"/><rect x="1" y="9" width="6" height="6" rx="1"/><rect x="9" y="9" width="6" height="6" rx="1"/></svg>
            Grid
          </button>
          <button class="pv-view-btn" data-view="list" id="btnListView">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><rect x="1" y="2" width="14" height="3" rx="1"/><rect x="1" y="7" width="14" height="3" rx="1"/><rect x="1" y="12" width="14" height="3" rx="1"/></svg>
            List
          </button>
        </div>
      </div>

      <?php if ($total_tasks > 0): ?>

        <!-- ===== GRID VIEW ===== -->
        <div id="gridView" class="pv-task-view">
          <div class="pv-task-group">
            <div class="pv-section-heading">
              <h3>Active Tasks</h3>
              <span class="pv-section-count" data-count="active-grid"><?php echo $active_count; ?></span>
            </div>
            <div id="gridActiveTasks" class="pv-tasks-grid">
              <div class="pv-empty-state" data-empty-state="grid-active">No active tasks right now.</div>
              <?php foreach ($active_tasks as $task):
                $sid = (int)($task['status_id'] ?? 2);
                $pid = (int)($task['priority_id'] ?? 3);
                $priInfo = $priorities[$pid] ?? ['label'=>'Low','class'=>'low'];
                $stInfo  = $statuses[$sid]   ?? ['label'=>'To Do','class'=>'todo'];
                $isTodo      = ($sid === 2);
                $isPending   = ($sid === 3);
                $assigneeName = $task['assignee_name'] ?? '';
              ?>
                <div class="pv-task-card pv-task-openable" data-task-id="<?php echo $task['id']; ?>" data-status="<?php echo $sid; ?>" data-task-title="<?php echo htmlspecialchars($task['title'], ENT_QUOTES); ?>" data-task-description="<?php echo htmlspecialchars($task['description'] ?? '', ENT_QUOTES); ?>" data-task-due-date="<?php echo htmlspecialchars($task['due_date'] ?? '', ENT_QUOTES); ?>" data-task-priority-id="<?php echo $pid; ?>" data-task-assignee-id="<?php echo (int)($task['assignee_id'] ?? 0); ?>" data-task-assignee-name="<?php echo htmlspecialchars($assigneeName, ENT_QUOTES); ?>">
                  <div class="pv-task-top">
                    <?php if ($isTodo): ?>
                      <button class="pv-circle-check" title="Mark as completed"
                              onclick="updateStatus(<?php echo $task['id']; ?>, 1, this)"></button>
                    <?php else: ?>
                      <button class="pv-circle-check disabled" title="Pending – activate first" disabled></button>
                    <?php endif; ?>
                    <span class="pv-task-title"><?php echo htmlspecialchars($task['title']); ?></span>
                  </div>

                  <?php if (!empty($task['description'])): ?>
                    <div class="pv-task-desc">
                      <?php echo htmlspecialchars(substr($task['description'], 0, 100)); ?><?php echo strlen($task['description']) > 100 ? '…' : ''; ?>
                    </div>
                  <?php endif; ?>

                  <div class="pv-task-meta">
                    <span class="pv-badge pv-badge-<?php echo $stInfo['class']; ?>">
                      <?php echo $stInfo['label']; ?>
                    </span>
                    <span class="pv-priority pv-priority-<?php echo $priInfo['class']; ?>">
                      <?php echo $priInfo['label']; ?>
                    </span>
                    <span class="pv-task-action-cell">
                      <?php if ($isPending): ?>
                        <button class="pv-btn-activate" title="Move to To Do"
                                onclick="updateStatus(<?php echo $task['id']; ?>, 2, this)">▶ Activate</button>
                      <?php elseif ($task['due_date']): ?>
                        <span class="pv-due-date <?php echo (strtotime($task['due_date']) < time() && $sid !== 1) ? 'overdue' : ''; ?>" data-task-due-date-text>
                          📅 <?php echo date('M d', strtotime($task['due_date'])); ?>
                        </span>
                      <?php endif; ?>
                    </span>
                    <span class="pv-task-assignee" data-task-assignee-text>
                      👤 <?php echo htmlspecialchars($assigneeName ?: 'Unassigned'); ?>
                    </span>
                    <span class="pv-creator">by <?php echo htmlspecialchars($task['creator_name'] ?? 'System'); ?></span>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <details class="pv-completed-panel" id="gridCompletedPanel">
            <summary>
              <span>Completed Tasks</span>
              <span class="pv-section-count" data-count="completed-grid"><?php echo $completed_count; ?></span>
            </summary>
            <div id="gridCompletedTasks" class="pv-tasks-grid">
              <div class="pv-empty-state" data-empty-state="grid-completed">No completed tasks yet.</div>
              <?php foreach ($completed_tasks as $task):
                $sid = (int)($task['status_id'] ?? 2);
                $pid = (int)($task['priority_id'] ?? 3);
                $priInfo = $priorities[$pid] ?? ['label'=>'Low','class'=>'low'];
                $stInfo  = $statuses[$sid]   ?? ['label'=>'Completed','class'=>'completed'];
                $assigneeName = $task['assignee_name'] ?? '';
              ?>
                <div class="pv-task-card pv-task-openable completed" data-task-id="<?php echo $task['id']; ?>" data-status="<?php echo $sid; ?>" data-task-title="<?php echo htmlspecialchars($task['title'], ENT_QUOTES); ?>" data-task-description="<?php echo htmlspecialchars($task['description'] ?? '', ENT_QUOTES); ?>" data-task-due-date="<?php echo htmlspecialchars($task['due_date'] ?? '', ENT_QUOTES); ?>" data-task-priority-id="<?php echo $pid; ?>" data-task-assignee-id="<?php echo (int)($task['assignee_id'] ?? 0); ?>" data-task-assignee-name="<?php echo htmlspecialchars($assigneeName, ENT_QUOTES); ?>">
                  <div class="pv-task-top">
                    <button class="pv-circle-check checked" title="Move to To Do"
                            onclick="updateStatus(<?php echo $task['id']; ?>, 2, this)"></button>
                    <span class="pv-task-title"><?php echo htmlspecialchars($task['title']); ?></span>
                  </div>

                  <?php if (!empty($task['description'])): ?>
                    <div class="pv-task-desc">
                      <?php echo htmlspecialchars(substr($task['description'], 0, 100)); ?><?php echo strlen($task['description']) > 100 ? '…' : ''; ?>
                    </div>
                  <?php endif; ?>

                  <div class="pv-task-meta">
                    <span class="pv-badge pv-badge-<?php echo $stInfo['class']; ?>">
                      <?php echo $stInfo['label']; ?>
                    </span>
                    <span class="pv-priority pv-priority-<?php echo $priInfo['class']; ?>">
                      <?php echo $priInfo['label']; ?>
                    </span>
                    <span class="pv-task-action-cell">
                      <?php if ($task['due_date']): ?>
                        <span class="pv-due-date" data-task-due-date-text>
                          📅 <?php echo date('M d', strtotime($task['due_date'])); ?>
                        </span>
                      <?php endif; ?>
                    </span>
                    <span class="pv-task-assignee" data-task-assignee-text>
                      👤 <?php echo htmlspecialchars($assigneeName ?: 'Unassigned'); ?>
                    </span>
                    <span class="pv-creator">by <?php echo htmlspecialchars($task['creator_name'] ?? 'System'); ?></span>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </details>
        </div>

        <!-- ===== LIST VIEW ===== -->
        <div id="listView" class="pv-task-view hidden">
          <div class="pv-task-group">
            <div class="pv-section-heading">
              <h3>Active Tasks</h3>
              <span class="pv-section-count" data-count="active-list"><?php echo $active_count; ?></span>
            </div>
            <div id="listActiveTasks" class="pv-tasks-list">
              <div class="pv-empty-state" data-empty-state="list-active">No active tasks right now.</div>
              <?php foreach ($active_tasks as $task):
                $sid = (int)($task['status_id'] ?? 2);
                $pid = (int)($task['priority_id'] ?? 3);
                $priInfo = $priorities[$pid] ?? ['label'=>'Low','class'=>'low'];
                $stInfo  = $statuses[$sid]   ?? ['label'=>'To Do','class'=>'todo'];
                $isTodo      = ($sid === 2);
                $isPending   = ($sid === 3);
                $assigneeName = $task['assignee_name'] ?? '';
              ?>
                <div class="pv-task-row pv-task-openable" data-task-id="<?php echo $task['id']; ?>" data-status="<?php echo $sid; ?>" data-task-title="<?php echo htmlspecialchars($task['title'], ENT_QUOTES); ?>" data-task-description="<?php echo htmlspecialchars($task['description'] ?? '', ENT_QUOTES); ?>" data-task-due-date="<?php echo htmlspecialchars($task['due_date'] ?? '', ENT_QUOTES); ?>" data-task-priority-id="<?php echo $pid; ?>" data-task-assignee-id="<?php echo (int)($task['assignee_id'] ?? 0); ?>" data-task-assignee-name="<?php echo htmlspecialchars($assigneeName, ENT_QUOTES); ?>">
                  <?php if ($isTodo): ?>
                    <button class="pv-circle-check" title="Mark as completed"
                            onclick="updateStatus(<?php echo $task['id']; ?>, 1, this)"></button>
                  <?php else: ?>
                    <button class="pv-circle-check disabled" title="Pending – activate first" disabled></button>
                  <?php endif; ?>

                  <span class="pv-task-title"><?php echo htmlspecialchars($task['title']); ?></span>

                  <span class="pv-task-meta-cell">
                    <span class="pv-badge pv-badge-<?php echo $stInfo['class']; ?>"><?php echo $stInfo['label']; ?></span>
                  </span>

                  <span class="pv-task-meta-cell">
                    <span class="pv-priority pv-priority-<?php echo $priInfo['class']; ?>"><?php echo $priInfo['label']; ?></span>
                  </span>

                  <span class="pv-task-meta-cell pv-task-action-cell">
                    <?php if ($isPending): ?>
                      <button class="pv-btn-activate" onclick="updateStatus(<?php echo $task['id']; ?>, 2, this)">▶ Activate</button>
                    <?php elseif ($task['due_date']): ?>
                      <span class="pv-due-date <?php echo (strtotime($task['due_date']) < time() && $sid !== 1) ? 'overdue' : ''; ?>">
                        📅 <?php echo date('M d', strtotime($task['due_date'])); ?>
                      </span>
                    <?php else: ?>
                      –
                    <?php endif; ?>
                  </span>

                  <span class="pv-task-meta-cell pv-creator">
                    <?php echo htmlspecialchars($task['creator_name'] ?? 'System'); ?>
                  </span>

                  <span class="pv-task-meta-cell pv-task-assignee" data-task-assignee-text>
                    👤 <?php echo htmlspecialchars($assigneeName ?: 'Unassigned'); ?>
                  </span>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <details class="pv-completed-panel" id="listCompletedPanel">
            <summary>
              <span>Completed Tasks</span>
              <span class="pv-section-count" data-count="completed-list"><?php echo $completed_count; ?></span>
            </summary>
            <div id="listCompletedTasks" class="pv-tasks-list">
              <div class="pv-empty-state" data-empty-state="list-completed">No completed tasks yet.</div>
              <?php foreach ($completed_tasks as $task):
                $sid = (int)($task['status_id'] ?? 2);
                $pid = (int)($task['priority_id'] ?? 3);
                $priInfo = $priorities[$pid] ?? ['label'=>'Low','class'=>'low'];
                $stInfo  = $statuses[$sid]   ?? ['label'=>'Completed','class'=>'completed'];
                $assigneeName = $task['assignee_name'] ?? '';
              ?>
                <div class="pv-task-row pv-task-openable completed" data-task-id="<?php echo $task['id']; ?>" data-status="<?php echo $sid; ?>" data-task-title="<?php echo htmlspecialchars($task['title'], ENT_QUOTES); ?>" data-task-description="<?php echo htmlspecialchars($task['description'] ?? '', ENT_QUOTES); ?>" data-task-due-date="<?php echo htmlspecialchars($task['due_date'] ?? '', ENT_QUOTES); ?>" data-task-priority-id="<?php echo $pid; ?>" data-task-assignee-id="<?php echo (int)($task['assignee_id'] ?? 0); ?>" data-task-assignee-name="<?php echo htmlspecialchars($assigneeName, ENT_QUOTES); ?>">
                  <button class="pv-circle-check checked" title="Move to To Do"
                          onclick="updateStatus(<?php echo $task['id']; ?>, 2, this)"></button>

                  <span class="pv-task-title"><?php echo htmlspecialchars($task['title']); ?></span>

                  <span class="pv-task-meta-cell">
                    <span class="pv-badge pv-badge-<?php echo $stInfo['class']; ?>"><?php echo $stInfo['label']; ?></span>
                  </span>

                  <span class="pv-task-meta-cell">
                    <span class="pv-priority pv-priority-<?php echo $priInfo['class']; ?>"><?php echo $priInfo['label']; ?></span>
                  </span>

                  <span class="pv-task-meta-cell pv-task-action-cell">
                    <?php if ($task['due_date']): ?>
                      <span class="pv-due-date">
                        📅 <?php echo date('M d', strtotime($task['due_date'])); ?>
                      </span>
                    <?php else: ?>
                      –
                    <?php endif; ?>
                  </span>

                  <span class="pv-task-meta-cell pv-creator">
                    <?php echo htmlspecialchars($task['creator_name'] ?? 'System'); ?>
                  </span>

                  <span class="pv-task-meta-cell pv-task-assignee" data-task-assignee-text>
                    👤 <?php echo htmlspecialchars($assigneeName ?: 'Unassigned'); ?>
                  </span>
                </div>
              <?php endforeach; ?>
            </div>
          </details>
        </div>

      <?php else: ?>
        <div class="pv-no-tasks">
          <div class="pv-no-tasks-icon">📋</div>
          <p>No tasks in this project yet.</p>
          <a href="task_create.php?project_id=<?php echo $project_id; ?>" class="btn btn-primary">＋ Create First Task</a>
        </div>
      <?php endif; ?>
    </div>

    <div class="pv-task-drawer-overlay" id="pvTaskDrawerOverlay"></div>
    <aside class="pv-task-drawer" id="pvTaskDrawer" aria-hidden="true">
      <div class="pv-task-drawer-header">
        <div>
          <p class="pv-task-drawer-kicker">Task Details</p>
          <h3 id="pvDrawerTitle">Select a task</h3>
        </div>
        <div class="pv-task-drawer-actions">
          <button type="button" class="pv-icon-btn danger" id="pvDeleteTaskBtn" title="Delete task">🗑</button>
          <button type="button" class="pv-icon-btn" id="pvCloseTaskDrawerBtn" title="Close">✕</button>
        </div>
      </div>

      <form class="pv-task-drawer-form" id="pvTaskDrawerForm">
        <input type="hidden" id="pvTaskId" name="task_id">

        <label class="pv-field">
          <span>Task Title</span>
          <input type="text" id="pvTaskTitle" name="title" maxlength="255" required>
        </label>

        <label class="pv-field">
          <span>Description</span>
          <textarea id="pvTaskDescription" name="description" rows="5" maxlength="1000"></textarea>
        </label>

        <div class="pv-field-grid">
          <label class="pv-field">
            <span>Status</span>
            <select id="pvTaskStatus" name="status_id">
              <?php foreach ($statuses as $id => $info): ?>
                <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($info['label']); ?></option>
              <?php endforeach; ?>
            </select>
          </label>

          <label class="pv-field">
            <span>Priority</span>
            <select id="pvTaskPriority" name="priority_id">
              <?php foreach ($priorities as $id => $info): ?>
                <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($info['label']); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>

        <div class="pv-field-grid">
          <label class="pv-field">
            <span>Due Date</span>
            <input type="date" id="pvTaskDueDate" name="due_date">
          </label>

          <label class="pv-field">
            <span>Assignee</span>
            <select id="pvTaskAssignee" name="assignee_id">
              <option value="">-- Unassigned --</option>
              <?php foreach ($project_members as $member): ?>
                <option value="<?php echo $member['id']; ?>"><?php echo htmlspecialchars($member['name'] . ' (' . $member['email'] . ')'); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>

        <div class="pv-task-drawer-footer">
          <button type="submit" class="btn btn-primary" id="pvSaveTaskBtn">Save Changes</button>
        </div>
      </form>
    </aside>

  </div>

  <!-- Toast notification -->
  <div class="pv-toast" id="pvToast"></div>

  <script>
  (function() {
    /* ===== View Toggle ===== */
    const gridView = document.getElementById('gridView');
    const listView = document.getElementById('listView');
    const btnGrid  = document.getElementById('btnGridView');
    const btnList  = document.getElementById('btnListView');
    const gridActiveTasks = document.getElementById('gridActiveTasks');
    const gridCompletedTasks = document.getElementById('gridCompletedTasks');
    const listActiveTasks = document.getElementById('listActiveTasks');
    const listCompletedTasks = document.getElementById('listCompletedTasks');
    const gridCompletedPanel = document.getElementById('gridCompletedPanel');
    const listCompletedPanel = document.getElementById('listCompletedPanel');
    const taskDrawer = document.getElementById('pvTaskDrawer');
    const taskDrawerOverlay = document.getElementById('pvTaskDrawerOverlay');
    const taskDrawerForm = document.getElementById('pvTaskDrawerForm');
    const taskDrawerTitle = document.getElementById('pvDrawerTitle');
    const taskIdInput = document.getElementById('pvTaskId');
    const taskTitleInput = document.getElementById('pvTaskTitle');
    const taskDescriptionInput = document.getElementById('pvTaskDescription');
    const taskStatusInput = document.getElementById('pvTaskStatus');
    const taskPriorityInput = document.getElementById('pvTaskPriority');
    const taskDueDateInput = document.getElementById('pvTaskDueDate');
    const taskAssigneeInput = document.getElementById('pvTaskAssignee');
    const deleteTaskBtn = document.getElementById('pvDeleteTaskBtn');
    const closeTaskDrawerBtn = document.getElementById('pvCloseTaskDrawerBtn');
    let selectedTaskId = null;

    // Restore saved preference
    const savedView = localStorage.getItem('pv_view') || 'grid';
    if (savedView === 'list') switchView('list');

    btnGrid.addEventListener('click', () => switchView('grid'));
    btnList.addEventListener('click', () => switchView('list'));

    // User Dropdown logic
    const toggle = document.querySelector('.user-dropdown-toggle');
    const menu = document.querySelector('.user-dropdown-menu');
    if (toggle && menu) {
        toggle.addEventListener('click', (e) => {
            e.stopPropagation();
            menu.classList.toggle('show');
        });
        document.addEventListener('click', (e) => {
            if (!toggle.contains(e.target) && !menu.contains(e.target)) {
                menu.classList.remove('show');
            }
        });
    }

    document.querySelectorAll('.pv-task-openable').forEach(taskEl => {
      taskEl.addEventListener('click', event => {
        if (event.target.closest('button, a, input, select, textarea, label, summary')) {
          return;
        }

        openTaskDrawer(taskEl);
      });
    });

    if (taskDrawerOverlay) {
      taskDrawerOverlay.addEventListener('click', closeTaskDrawer);
    }

    if (closeTaskDrawerBtn) {
      closeTaskDrawerBtn.addEventListener('click', closeTaskDrawer);
    }

    if (deleteTaskBtn) {
      deleteTaskBtn.addEventListener('click', deleteSelectedTask);
    }

    if (taskDrawerForm) {
      taskDrawerForm.addEventListener('submit', saveTaskFromDrawer);
    }

    document.addEventListener('keydown', event => {
      if (event.key === 'Escape') {
        closeTaskDrawer();
      }
    });

    function switchView(view) {
      if (view === 'grid') {
        gridView && gridView.classList.remove('hidden');
        listView && listView.classList.add('hidden');
        btnGrid.classList.add('active');
        btnList.classList.remove('active');
      } else {
        gridView && gridView.classList.add('hidden');
        listView && listView.classList.remove('hidden');
        btnList.classList.add('active');
        btnGrid.classList.remove('active');
      }
      localStorage.setItem('pv_view', view);
    }

    /* ===== Toast ===== */
    const toastEl = document.getElementById('pvToast');
    let toastTimer = null;

    function showToast(message, type) {
      type = type || 'success';
      toastEl.textContent = (type === 'success' ? '✅ ' : '⚠️ ') + message;
      toastEl.className = 'pv-toast ' + type + ' show';
      clearTimeout(toastTimer);
      toastTimer = setTimeout(() => { toastEl.classList.remove('show'); }, 3000);
    }

    function openTaskDrawer(taskEl) {
      if (!taskEl || !taskDrawer) {
        return;
      }

      selectedTaskId = taskEl.getAttribute('data-task-id');
      taskIdInput.value = selectedTaskId || '';
      taskTitleInput.value = taskEl.getAttribute('data-task-title') || '';
      taskDescriptionInput.value = taskEl.getAttribute('data-task-description') || '';
      taskStatusInput.value = taskEl.getAttribute('data-status') || '2';
      taskPriorityInput.value = taskEl.getAttribute('data-task-priority-id') || '3';
      taskDueDateInput.value = taskEl.getAttribute('data-task-due-date') || '';
      taskAssigneeInput.value = taskEl.getAttribute('data-task-assignee-id') || '';
      taskDrawerTitle.textContent = taskEl.getAttribute('data-task-title') || 'Task Details';

      taskDrawer.classList.add('open');
      taskDrawerOverlay && taskDrawerOverlay.classList.add('open');
      taskDrawer.setAttribute('aria-hidden', 'false');
      document.body.classList.add('pv-drawer-open');
      taskTitleInput.focus();
      taskTitleInput.select();
    }

    function closeTaskDrawer() {
      if (!taskDrawer) {
        return;
      }

      taskDrawer.classList.remove('open');
      taskDrawerOverlay && taskDrawerOverlay.classList.remove('open');
      taskDrawer.setAttribute('aria-hidden', 'true');
      document.body.classList.remove('pv-drawer-open');
      selectedTaskId = null;
    }

    function taskMatchesOpenDrawer(taskId) {
      return selectedTaskId && String(selectedTaskId) === String(taskId);
    }

    function updateTaskNodeDetails(node, taskData) {
      node.setAttribute('data-status', taskData.status_id);
      node.setAttribute('data-task-title', taskData.title || '');
      node.setAttribute('data-task-description', taskData.description || '');
      node.setAttribute('data-task-due-date', taskData.due_date || '');
      node.setAttribute('data-task-priority-id', taskData.priority_id || '3');
      node.setAttribute('data-task-assignee-id', taskData.assignee_id || '');
      node.setAttribute('data-task-assignee-name', taskData.assignee_name || '');

      const titleEl = node.querySelector('.pv-task-title');
      if (titleEl) {
        titleEl.textContent = taskData.title || '';
      }

      const badge = node.querySelector('.pv-badge');
      if (badge) {
        const statusMeta = statusMetaFor(taskData.status_id);
        badge.className = 'pv-badge pv-badge-' + statusMeta.class;
        badge.textContent = statusMeta.label;
      }

      const priorityBadge = node.querySelector('.pv-priority');
      if (priorityBadge) {
        const priorityMeta = priorityMetaFor(taskData.priority_id);
        priorityBadge.className = 'pv-priority pv-priority-' + priorityMeta.class;
        priorityBadge.textContent = priorityMeta.label;
      }

      const assigneeEl = node.querySelector('[data-task-assignee-text]');
      if (assigneeEl) {
        assigneeEl.textContent = '👤 ' + (taskData.assignee_name || 'Unassigned');
      }

      const dueDateEl = node.querySelector('[data-task-due-date-text]');
      if (taskData.due_date && taskData.status_id !== 3) {
        const formattedDate = formatDisplayDate(taskData.due_date);
        if (dueDateEl) {
          dueDateEl.textContent = '📅 ' + formattedDate;
          dueDateEl.style.display = '';
          dueDateEl.classList.toggle('overdue', taskData.status_id !== 1 && isPastDate(taskData.due_date));
        } else if (node.classList.contains('pv-task-card')) {
          const meta = node.querySelector('.pv-task-meta');
          if (meta) {
            const dueSpan = document.createElement('span');
            dueSpan.className = 'pv-due-date';
            dueSpan.setAttribute('data-task-due-date-text', '');
            dueSpan.textContent = '📅 ' + formattedDate;
            if (taskData.status_id !== 1 && isPastDate(taskData.due_date)) {
              dueSpan.classList.add('overdue');
            }
            meta.insertBefore(dueSpan, meta.querySelector('.pv-creator'));
          }
        }
      } else if (dueDateEl) {
        dueDateEl.remove();
      }

      const descEl = node.querySelector('.pv-task-desc');
      if (node.classList.contains('pv-task-card')) {
        if (taskData.description) {
          if (descEl) {
            descEl.textContent = taskData.description;
          } else {
            const topEl = node.querySelector('.pv-task-top');
            if (topEl) {
              const descriptionNode = document.createElement('div');
              descriptionNode.className = 'pv-task-desc';
              descriptionNode.textContent = taskData.description;
              topEl.insertAdjacentElement('afterend', descriptionNode);
            }
          }
        } else if (descEl) {
          descEl.remove();
        }
      }

      const circleBtn = node.querySelector('.pv-circle-check');
      if (circleBtn) {
        if (taskData.status_id === 1) {
          circleBtn.className = 'pv-circle-check checked';
          circleBtn.disabled = false;
          circleBtn.title = 'Move to To Do';
          circleBtn.onclick = function() { updateStatus(taskData.id, 2, this); };
        } else if (taskData.status_id === 2) {
          circleBtn.className = 'pv-circle-check';
          circleBtn.disabled = false;
          circleBtn.title = 'Mark as completed';
          circleBtn.onclick = function() { updateStatus(taskData.id, 1, this); };
        } else {
          circleBtn.className = 'pv-circle-check disabled';
          circleBtn.disabled = true;
          circleBtn.title = 'Pending – activate first';
          circleBtn.onclick = null;
        }
      }

      const actionCell = node.querySelector('.pv-task-action-cell');
      const activateBtn = node.querySelector('.pv-btn-activate');
      if (taskData.status_id === 3) {
        if (!activateBtn && actionCell) {
          const newActivateBtn = document.createElement('button');
          newActivateBtn.className = 'pv-btn-activate';
          newActivateBtn.textContent = '▶ Activate';
          newActivateBtn.addEventListener('click', event => {
            event.stopPropagation();
            updateStatus(taskData.id, 2, newActivateBtn);
          });
          actionCell.innerHTML = '';
          actionCell.appendChild(newActivateBtn);
        }
      } else if (activateBtn) {
        activateBtn.remove();
        if (actionCell) {
          if (taskData.due_date) {
            const dueSpan = document.createElement('span');
            dueSpan.className = 'pv-due-date';
            dueSpan.setAttribute('data-task-due-date-text', '');
            dueSpan.textContent = '📅 ' + formatDisplayDate(taskData.due_date);
            if (taskData.status_id !== 1 && isPastDate(taskData.due_date)) {
              dueSpan.classList.add('overdue');
            }
            actionCell.textContent = '';
            actionCell.appendChild(dueSpan);
          } else {
            actionCell.textContent = '–';
          }
        }
      }

      if (taskMatchesOpenDrawer(taskData.id)) {
        taskDrawerTitle.textContent = taskData.title || 'Task Details';
        taskIdInput.value = String(taskData.id);
        taskTitleInput.value = taskData.title || '';
        taskDescriptionInput.value = taskData.description || '';
        taskStatusInput.value = String(taskData.status_id || 2);
        taskPriorityInput.value = String(taskData.priority_id || 3);
        taskDueDateInput.value = taskData.due_date || '';
        taskAssigneeInput.value = taskData.assignee_id || '';
      }
    }

    function formatDisplayDate(value) {
      const date = new Date(value + 'T00:00:00');
      if (Number.isNaN(date.getTime())) {
        return value;
      }

      return date.toLocaleDateString(undefined, { month: 'short', day: '2-digit' });
    }

    function isPastDate(value) {
      const date = new Date(value + 'T23:59:59');
      return !Number.isNaN(date.getTime()) && date.getTime() < Date.now();
    }

    function priorityMetaFor(priorityId) {
      if (priorityId === 1) return { label: 'High', class: 'high' };
      if (priorityId === 2) return { label: 'Medium', class: 'medium' };
      return { label: 'Low', class: 'low' };
    }

    function statusMetaFor(statusId) {
      if (statusId === 1) return { label: 'Completed', class: 'completed' };
      if (statusId === 3) return { label: 'Pending', class: 'pending' };
      return { label: 'To Do', class: 'todo' };
    }

    function buildTaskDataFromNode(node, statusId) {
      return {
        id: parseInt(node.getAttribute('data-task-id'), 10),
        title: node.getAttribute('data-task-title') || '',
        description: node.getAttribute('data-task-description') || '',
        status_id: parseInt(statusId, 10),
        priority_id: parseInt(node.getAttribute('data-task-priority-id') || '3', 10),
        due_date: node.getAttribute('data-task-due-date') || '',
        assignee_id: node.getAttribute('data-task-assignee-id') || '',
        assignee_name: node.getAttribute('data-task-assignee-name') || ''
      };
    }

    async function saveTaskFromDrawer(event) {
      event.preventDefault();

      const taskId = parseInt(taskIdInput.value, 10);
      if (!taskId) {
        return;
      }

      const payload = {
        task_id: taskId,
        title: taskTitleInput.value.trim(),
        description: taskDescriptionInput.value.trim(),
        status_id: parseInt(taskStatusInput.value, 10),
        priority_id: parseInt(taskPriorityInput.value, 10),
        due_date: taskDueDateInput.value,
        assignee_id: taskAssigneeInput.value ? parseInt(taskAssigneeInput.value, 10) : null,
        csrf_token: csrfToken
      };

      if (!payload.title) {
        showToast('Task title is required', 'error');
        return;
      }

      const saveBtn = document.getElementById('pvSaveTaskBtn');
      if (saveBtn) {
        saveBtn.disabled = true;
      }

      try {
        const response = await fetch('api/task/update.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });

        const data = await response.json();

        if (data.success && data.task) {
          applyTaskDataToViews(data.task);
          closeTaskDrawer();
          showToast('Task updated successfully', 'success');
        } else {
          showToast(data.message || 'Unable to update task', 'error');
        }
      } catch (error) {
        showToast('Network error while updating task', 'error');
      } finally {
        if (saveBtn) {
          saveBtn.disabled = false;
        }
      }
    }

    function applyTaskDataToViews(taskData) {
      const nodes = document.querySelectorAll('[data-task-id="' + taskData.id + '"]');
      nodes.forEach(node => updateTaskNodeDetails(node, taskData));

      updateTaskUI(taskData.id, taskData.status_id);
      syncEmptyStates();
      updateStatCounters();

      if (taskMatchesOpenDrawer(taskData.id)) {
        taskDrawerTitle.textContent = taskData.title || 'Task Details';
        taskTitleInput.value = taskData.title || '';
        taskDescriptionInput.value = taskData.description || '';
        taskStatusInput.value = String(taskData.status_id || 2);
        taskPriorityInput.value = String(taskData.priority_id || 3);
        taskDueDateInput.value = taskData.due_date || '';
        taskAssigneeInput.value = taskData.assignee_id || '';
      }
    }

    async function deleteSelectedTask() {
      const taskId = parseInt(taskIdInput.value, 10);
      if (!taskId) {
        return;
      }

      const taskName = taskTitleInput.value || 'this task';
      if (!confirm(`Delete "${taskName}"? This cannot be undone.`)) {
        return;
      }

      try {
        const response = await fetch('api/task/delete.php', {
          method: 'DELETE',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ task_id: taskId, csrf_token: csrfToken })
        });

        const data = await response.json();
        if (data.success) {
          document.querySelectorAll('[data-task-id="' + taskId + '"]').forEach(node => node.remove());
          syncEmptyStates();
          updateStatCounters();
          closeTaskDrawer();
          showToast('Task deleted successfully', 'success');
        } else {
          showToast(data.message || 'Unable to delete task', 'error');
        }
      } catch (error) {
        showToast('Network error while deleting task', 'error');
      }
    }

    /* ===== Status Update ===== */
    const csrfToken = '<?php echo $csrf_token; ?>';

    window.updateStatus = function(taskId, newStatusId, btnEl) {
      btnEl.disabled = true;

      fetch('update_task_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          task_id: taskId,
          new_status_id: newStatusId,
          csrf_token: csrfToken
        })
      })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          showToast(data.new_status_label === 'Completed'
            ? 'Task marked as completed!'
            : 'Task moved to To Do!', 'success');

          // Update both views in the DOM
          updateTaskUI(taskId, newStatusId);
          if (taskMatchesOpenDrawer(taskId)) {
            taskStatusInput.value = String(newStatusId);
          }
        } else {
          showToast(data.error || 'Something went wrong', 'error');
          btnEl.disabled = false;
        }
      })
      .catch(() => {
        showToast('Network error – please try again', 'error');
        btnEl.disabled = false;
      });
    };

    function updateTaskUI(taskId, newStatusId) {
      const cards = document.querySelectorAll('[data-task-id="' + taskId + '"]');
      cards.forEach(card => {
        card.setAttribute('data-status', newStatusId);

        const circleBtn = card.querySelector('.pv-circle-check');
        const isRow = card.classList.contains('pv-task-row');
        const targetContainer = isRow
          ? (newStatusId === 1 ? listCompletedTasks : listActiveTasks)
          : (newStatusId === 1 ? gridCompletedTasks : gridActiveTasks);

        if (targetContainer && card.parentElement !== targetContainer) {
          targetContainer.appendChild(card);
        }

        if (newStatusId === 1) {
          // Completed
          card.classList.add('completed');
          if (circleBtn) {
            circleBtn.className = 'pv-circle-check checked';
            circleBtn.disabled = false;
            circleBtn.title = 'Move to To Do';
            circleBtn.onclick = function() { updateStatus(taskId, 2, this); };
          }
          // Update badge
          const badge = card.querySelector('.pv-badge');
          if (badge) {
            badge.className = 'pv-badge pv-badge-completed';
            badge.textContent = 'Completed';
          }
          // Remove activate button if present
          const actBtn = card.querySelector('.pv-btn-activate');
          if (actBtn) actBtn.remove();

        } else if (newStatusId === 2) {
          // To Do – make checkable
          card.classList.remove('completed');
          if (circleBtn) {
            circleBtn.className = 'pv-circle-check';
            circleBtn.disabled = false;
            circleBtn.title = 'Mark as completed';
            circleBtn.onclick = function() { updateStatus(taskId, 1, this); };
          }
          const badge = card.querySelector('.pv-badge');
          if (badge) {
            badge.className = 'pv-badge pv-badge-todo';
            badge.textContent = 'To Do';
          }
          // Remove activate button if present
          const actBtn = card.querySelector('.pv-btn-activate');
          if (actBtn) actBtn.remove();
        }

        updateTaskNodeDetails(card, buildTaskDataFromNode(card, newStatusId));
      });

      if (newStatusId === 1) {
        if (gridCompletedPanel) gridCompletedPanel.open = true;
        if (listCompletedPanel) listCompletedPanel.open = true;
      }

      syncEmptyStates();
      // Update stat counters
      updateStatCounters();
    }

    function syncEmptyStates() {
      const sections = [
        { container: gridActiveTasks, empty: document.querySelector('[data-empty-state="grid-active"]') },
        { container: gridCompletedTasks, empty: document.querySelector('[data-empty-state="grid-completed"]') },
        { container: listActiveTasks, empty: document.querySelector('[data-empty-state="list-active"]') },
        { container: listCompletedTasks, empty: document.querySelector('[data-empty-state="list-completed"]') }
      ];

      sections.forEach(section => {
        if (!section.container || !section.empty) return;
        const hasTasks = section.container.querySelector('[data-task-id]') !== null;
        section.empty.classList.toggle('is-visible', !hasTasks);
      });
    }

    function updateStatCounters() {
      // Recount from the grid view data attributes
      const allCards = document.querySelectorAll('#gridView .pv-task-card');
      let comp = 0, todo = 0, pend = 0;
      allCards.forEach(c => {
        const s = parseInt(c.getAttribute('data-status'));
        if (s === 1) comp++;
        else if (s === 2) todo++;
        else if (s === 3) pend++;
      });

      const statValues = document.querySelectorAll('.pv-stat-value');
      if (statValues.length >= 4) {
        statValues[0].textContent = allCards.length;
        statValues[1].textContent = comp;
        statValues[2].textContent = todo;
        statValues[3].textContent = pend;
      }

      const activeCount = todo + pend;
      const completedCount = comp;
      document.querySelectorAll('[data-count="active-grid"], [data-count="active-list"]').forEach(el => {
        el.textContent = activeCount;
      });
      document.querySelectorAll('[data-count="completed-grid"], [data-count="completed-list"]').forEach(el => {
        el.textContent = completedCount;
      });
    }

    syncEmptyStates();
  })();
  </script>
</body>
</html>