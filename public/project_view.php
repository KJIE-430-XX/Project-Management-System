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

// 🔥 SECURITY FIX: Verify user is a member of the project before loading it
$p_stmt = $conn->prepare("
    SELECT p.* 
    FROM projects p 
    JOIN project_members pm ON p.id = pm.project_id 
    WHERE p.id = ? AND pm.user_id = ?
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
    SELECT t.*, u.name AS creator_name 
    FROM tasks t 
    LEFT JOIN users u ON t.created_by = u.id
    WHERE t.project_id = ? 
    ORDER BY t.created_at DESC
");
$t_stmt->bind_param("i", $project_id);
$t_stmt->execute();
$tasks = $t_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$t_stmt->close();

// Count tasks by status
$total_tasks = count($tasks);
$completed_count = 0;
$todo_count = 0;
$pending_count = 0;
foreach ($tasks as $t) {
    $sid = (int)($t['status_id'] ?? 2);
    if ($sid === 1) $completed_count++;
    elseif ($sid === 2) $todo_count++;
    elseif ($sid === 3) $pending_count++;
}

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
</head>
<body>
  <div class="pv-container">

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
        <a href="index.php" class="btn btn-back">← Dashboard</a>
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
        <div id="gridView" class="pv-tasks-grid">
          <?php foreach ($tasks as $task):
            $sid = (int)($task['status_id'] ?? 2);
            $pid = (int)($task['priority_id'] ?? 3);
            $priorities = [1 => ['label'=>'High','class'=>'high'], 2 => ['label'=>'Medium','class'=>'medium'], 3 => ['label'=>'Low','class'=>'low']];
            $statuses   = [1 => ['label'=>'Completed','class'=>'completed'], 2 => ['label'=>'To Do','class'=>'todo'], 3 => ['label'=>'Pending','class'=>'pending']];
            $priInfo = $priorities[$pid] ?? ['label'=>'Low','class'=>'low'];
            $stInfo  = $statuses[$sid]   ?? ['label'=>'To Do','class'=>'todo'];
            $isCompleted = ($sid === 1);
            $isTodo      = ($sid === 2);
            $isPending   = ($sid === 3);
          ?>
            <div class="pv-task-card <?php echo $isCompleted ? 'completed' : ''; ?>" data-task-id="<?php echo $task['id']; ?>" data-status="<?php echo $sid; ?>">
              <div class="pv-task-top">
                <?php if ($isTodo): ?>
                  <button class="pv-circle-check" title="Mark as completed"
                          onclick="updateStatus(<?php echo $task['id']; ?>, 1, this)"></button>
                <?php elseif ($isCompleted): ?>
                  <button class="pv-circle-check checked" title="Completed" disabled></button>
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
                <?php if ($isPending): ?>
                  <button class="pv-btn-activate" title="Move to To Do"
                          onclick="updateStatus(<?php echo $task['id']; ?>, 2, this)">▶ Activate</button>
                <?php endif; ?>
                <?php if ($task['due_date']): ?>
                  <span class="pv-due-date <?php echo (strtotime($task['due_date']) < time() && !$isCompleted) ? 'overdue' : ''; ?>">
                    📅 <?php echo date('M d', strtotime($task['due_date'])); ?>
                  </span>
                <?php endif; ?>
                <span class="pv-creator">by <?php echo htmlspecialchars($task['creator_name'] ?? 'System'); ?></span>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- ===== LIST VIEW ===== -->
        <div id="listView" class="pv-tasks-list hidden">
          <?php foreach ($tasks as $task):
            $sid = (int)($task['status_id'] ?? 2);
            $pid = (int)($task['priority_id'] ?? 3);
            $priorities = [1 => ['label'=>'High','class'=>'high'], 2 => ['label'=>'Medium','class'=>'medium'], 3 => ['label'=>'Low','class'=>'low']];
            $statuses   = [1 => ['label'=>'Completed','class'=>'completed'], 2 => ['label'=>'To Do','class'=>'todo'], 3 => ['label'=>'Pending','class'=>'pending']];
            $priInfo = $priorities[$pid] ?? ['label'=>'Low','class'=>'low'];
            $stInfo  = $statuses[$sid]   ?? ['label'=>'To Do','class'=>'todo'];
            $isCompleted = ($sid === 1);
            $isTodo      = ($sid === 2);
            $isPending   = ($sid === 3);
          ?>
            <div class="pv-task-row <?php echo $isCompleted ? 'completed' : ''; ?>" data-task-id="<?php echo $task['id']; ?>" data-status="<?php echo $sid; ?>">
              <?php if ($isTodo): ?>
                <button class="pv-circle-check" title="Mark as completed"
                        onclick="updateStatus(<?php echo $task['id']; ?>, 1, this)"></button>
              <?php elseif ($isCompleted): ?>
                <button class="pv-circle-check checked" title="Completed" disabled></button>
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

              <span class="pv-task-meta-cell">
                <?php if ($isPending): ?>
                  <button class="pv-btn-activate" onclick="updateStatus(<?php echo $task['id']; ?>, 2, this)">▶ Activate</button>
                <?php elseif ($task['due_date']): ?>
                  <span class="pv-due-date <?php echo (strtotime($task['due_date']) < time() && !$isCompleted) ? 'overdue' : ''; ?>">
                    📅 <?php echo date('M d', strtotime($task['due_date'])); ?>
                  </span>
                <?php else: ?>
                  –
                <?php endif; ?>
              </span>

              <span class="pv-task-meta-cell pv-creator">
                <?php echo htmlspecialchars($task['creator_name'] ?? 'System'); ?>
              </span>
            </div>
          <?php endforeach; ?>
        </div>

      <?php else: ?>
        <div class="pv-no-tasks">
          <div class="pv-no-tasks-icon">📋</div>
          <p>No tasks in this project yet.</p>
          <a href="task_create.php?project_id=<?php echo $project_id; ?>" class="btn btn-primary">＋ Create First Task</a>
        </div>
      <?php endif; ?>
    </div>

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

    // Restore saved preference
    const savedView = localStorage.getItem('pv_view') || 'grid';
    if (savedView === 'list') switchView('list');

    btnGrid.addEventListener('click', () => switchView('grid'));
    btnList.addEventListener('click', () => switchView('list'));

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

        if (newStatusId === 1) {
          // Completed
          card.classList.add('completed');
          if (circleBtn) {
            circleBtn.className = 'pv-circle-check checked';
            circleBtn.disabled = true;
            circleBtn.title = 'Completed';
            circleBtn.onclick = null;
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
      });

      // Update stat counters
      updateStatCounters();
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
    }
  })();
  </script>
</body>
</html>