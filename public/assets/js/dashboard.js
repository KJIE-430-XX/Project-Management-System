document.addEventListener('DOMContentLoaded', () => {
    // Initialize drag and drop
    initDragAndDrop();

    // Default select "Uncategorized" (null workspace)
    selectWorkspace('null');
    
    // Initialize User Dropdown
    initUserDropdown();

    // Initialize Filters
    const searchInput = document.getElementById('projectSearchInput');
    const dueFrom = document.getElementById('projectDueFrom');
    const dueTo = document.getElementById('projectDueTo');

    if (searchInput) searchInput.addEventListener('input', filterProjects);
    if (dueFrom) dueFrom.addEventListener('change', filterProjects);
    if (dueTo) dueTo.addEventListener('change', filterProjects);

    const toggleFiltersBtn = document.getElementById('toggleDashboardFiltersBtn');
    const dashboardFilters = document.getElementById('dashboardFilters');
    const clearFiltersBtn = document.getElementById('clearDashboardFiltersBtn');

    if (toggleFiltersBtn && dashboardFilters) {
        toggleFiltersBtn.addEventListener('click', () => {
            dashboardFilters.style.display = dashboardFilters.style.display === 'none' ? 'flex' : 'none';
        });
    }

    if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener('click', () => {
            if (searchInput) searchInput.value = '';
            if (dueFrom) dueFrom.value = '';
            if (dueTo) dueTo.value = '';
            filterProjects();
        });
    }
});

let currentWorkspaceId = 'null';

function selectWorkspace(workspaceId) {
    currentWorkspaceId = workspaceId;

    // Update active class on sidebar
    document.querySelectorAll('.workspace-item').forEach(el => {
        el.classList.remove('active');
    });

    // Find the clicked item
    const clickedItem = document.querySelector(`.workspace-item[data-id="${workspaceId}"]`);
    const headerTitle = document.getElementById('current-workspace-title');
    const headerCount = document.getElementById('current-workspace-count');
    const headerProgress = document.getElementById('workspace-header-progress');
    const headerProgressFill = document.getElementById('workspace-header-progress-bar-fill');
    const headerProgressText = document.getElementById('workspace-header-progress-text');

    if (clickedItem) {
        clickedItem.classList.add('active');
        const nameEl = clickedItem.querySelector('.name-text');
        const nameText = nameEl ? nameEl.innerText.trim() : "Workspace";
        headerTitle.innerText = nameText;

        const projCount = clickedItem.getAttribute('data-project-count') || 0;
        const totalTasks = parseInt(clickedItem.getAttribute('data-total-tasks') || 0);
        const compTasks = parseInt(clickedItem.getAttribute('data-completed-tasks') || 0);
        const percent = parseFloat(clickedItem.getAttribute('data-progress-percent') || 0);

        headerCount.innerText = `(${projCount} project${projCount == 1 ? '' : 's'})`;

        // Show progress bar in main content
        headerProgress.style.display = 'flex';
        headerProgressFill.style.width = `${percent}%`;
        headerProgressText.innerText = `${percent}% (${compTasks}/${totalTasks})`;
    } else {
        headerTitle.innerText = "Uncategorized";
        headerCount.innerText = "";
        headerProgress.style.display = 'none';
    }

    // Filter projects
    filterProjects();
}

function filterProjects() {
    const projects = document.querySelectorAll('.project-card');
    
    const searchInput = document.getElementById('projectSearchInput');
    const dueFromInput = document.getElementById('projectDueFrom');
    const dueToInput = document.getElementById('projectDueTo');
    
    const searchTerm = searchInput ? searchInput.value.toLowerCase().trim() : '';
    const dueFrom = dueFromInput && dueFromInput.value ? new Date(dueFromInput.value) : null;
    const dueTo = dueToInput && dueToInput.value ? new Date(dueToInput.value) : null;
    
    let visibleCount = 0;

    projects.forEach(project => {
        const projectWorkspaceId = project.getAttribute('data-workspace-id');
        const name = (project.getAttribute('data-name') || '').toLowerCase();
        const description = (project.getAttribute('data-description') || '').toLowerCase();
        const dueDateStr = project.getAttribute('data-due-date') || '';
        
        let matchWorkspace = (currentWorkspaceId === 'all') || (String(projectWorkspaceId) === String(currentWorkspaceId));
        let matchSearch = searchTerm === '' || name.includes(searchTerm) || description.includes(searchTerm);
        
        let matchDate = true;
        if (dueDateStr) {
            const dueDate = new Date(dueDateStr);
            if (dueFrom && dueDate < dueFrom) matchDate = false;
            // Set dueTo time to end of day to include the date selected
            if (dueTo) {
                const dueToDate = new Date(dueToInput.value);
                dueToDate.setHours(23, 59, 59, 999);
                if (dueDate > dueToDate) matchDate = false;
            }
        } else if (dueFrom || dueTo) {
            matchDate = false; // If a date filter is applied but project has no date, hide it
        }

        if (matchWorkspace && matchSearch && matchDate) {
            project.style.display = 'flex';
            visibleCount++;
        } else {
            project.style.display = 'none';
        }
    });

    const emptyState = document.getElementById('empty-state');
    const projectsGrid = document.getElementById('projects-grid');

    if (visibleCount === 0 && projects.length > 0) {
        if (projectsGrid) projectsGrid.style.display = 'none';
        if (emptyState) emptyState.style.display = 'block';
    } else {
        if (projectsGrid) projectsGrid.style.display = 'grid';
        if (emptyState) emptyState.style.display = 'none';
    }
}

// ----------------------------------------------------
// Drag and Drop
// ----------------------------------------------------
function initDragAndDrop() {
    const draggables = document.querySelectorAll('.project-card.draggable');
    const dropzones = document.querySelectorAll('.dropzone');

    draggables.forEach(draggable => {
        draggable.addEventListener('dragstart', (e) => {
            e.dataTransfer?.setData('text/plain', draggable.getAttribute('data-id') || '');
            draggable.classList.add('dragging');
        });

        draggable.addEventListener('dragend', () => {
            draggable.classList.remove('dragging');
        });
    });

    dropzones.forEach(dropzone => {
        dropzone.addEventListener('dragover', e => {
            e.preventDefault();
            // Add drag-over styling; server-side API validates whether the drop is permitted.
            dropzone.classList.add('drag-over');
        });

        dropzone.addEventListener('dragleave', () => {
            dropzone.classList.remove('drag-over');
        });

        dropzone.addEventListener('drop', async e => {
            e.preventDefault();
            dropzone.classList.remove('drag-over');

            const draggable = document.querySelector('.dragging');
            if (!draggable) return;

            const projectId = draggable.getAttribute('data-id');
            const targetWorkspaceId = dropzone.getAttribute('data-id');
            const currentProjWorkspaceId = draggable.getAttribute('data-workspace-id');

            if (targetWorkspaceId === currentProjWorkspaceId) {
                return; // No change
            }

            try {
                const response = await fetch('api/project/move_workspace.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        project_id: projectId,
                        workspace_id: targetWorkspaceId === 'null' ? null : targetWorkspaceId
                    })
                });

                const result = await response.json();

                if (result.success) {
                    showToast('Project moved successfully');
                    draggable.setAttribute('data-workspace-id', targetWorkspaceId);
                    filterProjects(); // Hide it from current view if it was filtered
                    setTimeout(() => location.reload(), 1000); // Reload to update workspace stats
                } else {
                    showToast(result.message || 'Error moving project', true);
                }
            } catch (error) {
                showToast('Network error while moving project', true);
            }
        });
    });
}

// ----------------------------------------------------
// Modals and API Calls
// ----------------------------------------------------
const modal = document.getElementById('workspaceModal');
const nameInput = document.getElementById('workspace_name_input');
const idInput = document.getElementById('workspace_id_input');
const title = document.getElementById('modal-title');

function openWorkspaceModal() {
    title.innerText = 'New Workspace';
    idInput.value = '';
    nameInput.value = '';
    modal.style.display = 'flex';
    nameInput.focus();
}

function closeWorkspaceModal() {
    modal.style.display = 'none';
}

function editWorkspace(e, id, name) {
    e.stopPropagation(); // Prevent selectWorkspace
    title.innerText = 'Rename Workspace';
    idInput.value = id;
    nameInput.value = name;
    modal.style.display = 'flex';
    nameInput.focus();
}

async function saveWorkspace() {
    const id = idInput.value;
    const name = nameInput.value.trim();

    if (!name) {
        showToast('Workspace name cannot be empty', true);
        return;
    }

    const isEdit = id !== '';
    const url = isEdit ? 'api/workspace/rename.php' : 'api/workspace/create.php';
    const method = isEdit ? 'PUT' : 'POST';
    const body = isEdit ? { id, name } : { name };

    try {
        const response = await fetch(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });

        const result = await response.json();

        if (result.success) {
            showToast(isEdit ? 'Workspace renamed' : 'Workspace created');
            setTimeout(() => location.reload(), 1000); // Simple reload to update UI
        } else {
            showToast(result.message, true);
        }
    } catch (error) {
        showToast('Network error', true);
    }
}

async function deleteWorkspace(e, id) {
    e.stopPropagation(); // Prevent selectWorkspace

    if (!confirm('Are you sure you want to delete this workspace? Projects will be moved to Uncategorized.')) {
        return;
    }

    try {
        const response = await fetch(`api/workspace/delete.php?id=${id}`, {
            method: 'DELETE'
        });

        const result = await response.json();

        if (result.success) {
            showToast('Workspace deleted');
            setTimeout(() => location.reload(), 1000); // Reload to reflect changes
        } else {
            showToast(result.message, true);
        }
    } catch (error) {
        showToast('Network error', true);
    }
}

function showToast(message, isError = false) {
    const toast = document.getElementById('toast');
    toast.innerText = message;
    toast.style.backgroundColor = isError ? '#EF4444' : '#10B981';
    toast.style.opacity = '1';
    toast.style.bottom = '20px';

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.bottom = '-50px';
    }, 3000);
}

// ----------------------------------------------------
// User Dropdown Menu
// ----------------------------------------------------
function initUserDropdown() {
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
}
