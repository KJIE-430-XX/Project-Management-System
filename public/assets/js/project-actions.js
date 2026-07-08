document.addEventListener('DOMContentLoaded', () => {
    bindInlineProjectEditors();

    // Close project edit modal when clicking the backdrop
    const projectEditModal = document.getElementById('projectEditModal');
    if (projectEditModal) {
        projectEditModal.addEventListener('click', (e) => {
            if (e.target === projectEditModal) closeProjectEditModal();
        });
    }

    // Close project edit modal on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const modal = document.getElementById('projectEditModal');
            if (modal && modal.style.display === 'flex') closeProjectEditModal();
        }
    });
});

function projectActionToast(message, isError = false) {
    if (typeof showToast === 'function') {
        showToast(message, isError);
        return;
    }

    window.alert(message);
}

function openProject(projectId) {
    window.location.href = `project_view.php?project_id=${projectId}`;
}

function bindInlineProjectEditors() {
    document.querySelectorAll('.project-name-input').forEach(input => {
        input.addEventListener('click', event => {
            event.stopPropagation();
        });

        input.addEventListener('keydown', event => {
            if (event.key === 'Enter') {
                event.preventDefault();
                saveProjectRename(input.dataset.projectId);
            }
            if (event.key === 'Escape') {
                event.preventDefault();
                cancelProjectRename(input.dataset.projectId);
            }
        });

        input.addEventListener('blur', () => {
            if (input.dataset.editing === 'true') {
                saveProjectRename(input.dataset.projectId);
            }
        });
    });
}

function toggleProjectRename(event, projectId) {
    // Delegate to the modal-based editor
    openProjectEditModal(event, projectId);
}

function openProjectEditModal(event, projectId) {
    event.stopPropagation();

    const card = document.querySelector(`.project-card[data-id="${projectId}"]`);
    if (!card) return;

    const name        = card.dataset.name        || '';
    const description = card.dataset.description || '';
    const dueDate     = card.dataset.dueDate      || '';

    document.getElementById('edit_project_id').value          = projectId;
    document.getElementById('edit_project_name').value        = name;
    document.getElementById('edit_project_description').value = description;
    document.getElementById('edit_project_due_date').value    = dueDate;

    const modal = document.getElementById('projectEditModal');
    modal.style.display = 'flex';
    document.getElementById('edit_project_name').focus();
}

function closeProjectEditModal() {
    const modal = document.getElementById('projectEditModal');
    if (modal) modal.style.display = 'none';
}

async function saveProjectEdit() {
    const projectId   = document.getElementById('edit_project_id').value;
    const name        = document.getElementById('edit_project_name').value.trim();
    const description = document.getElementById('edit_project_description').value.trim();
    const dueDate     = document.getElementById('edit_project_due_date').value;

    if (!name) {
        projectActionToast('Project name cannot be empty', true);
        document.getElementById('edit_project_name').focus();
        return;
    }

    const saveBtn = document.getElementById('saveProjectEditBtn');
    saveBtn.disabled = true;
    saveBtn.textContent = 'Saving...';

    try {
        const response = await fetch('api/project/update.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                project_id:  projectId,
                name:        name,
                description: description,
                due_date:    dueDate,
                csrf_token:  window.PROMANAGE_CSRF_TOKEN || ''
            })
        });

        const result = await response.json();

        if (result.success) {
            // Update the card's data attributes and visible labels
            const card = document.querySelector(`.project-card[data-id="${projectId}"]`);
            if (card) {
                card.dataset.name        = result.name;
                card.dataset.description = result.description;
                card.dataset.dueDate     = result.due_date || '';

                const nameLabel = card.querySelector('.project-name-label');
                if (nameLabel) {
                    nameLabel.textContent = result.name;
                    nameLabel.dataset.originalName = result.name;
                }
                const nameInput = card.querySelector('.project-name-input');
                if (nameInput) nameInput.value = result.name;

                const descEl = card.querySelector('.project-description');
                if (descEl) {
                    const desc = result.description || '';
                    descEl.textContent = desc.length > 100 ? desc.substring(0, 100) + '...' : desc;
                }

                const dueEl = card.querySelector('.project-due');
                if (result.due_date) {
                    const formatted = new Date(result.due_date + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
                    if (dueEl) {
                        dueEl.textContent = 'Due: ' + formatted;
                    } else {
                        // Create a due date element if it didn't exist before
                        const statsEl = card.querySelector('.project-stats');
                        if (statsEl) {
                            const newDue = document.createElement('div');
                            newDue.className = 'project-due';
                            newDue.textContent = 'Due: ' + formatted;
                            statsEl.insertAdjacentElement('afterend', newDue);
                        }
                    }
                } else if (dueEl) {
                    dueEl.remove();
                }
            }

            closeProjectEditModal();
            projectActionToast('Project updated successfully');
        } else {
            projectActionToast(result.message || 'Unable to update project', true);
        }
    } catch (error) {
        projectActionToast('Network error while updating project', true);
    } finally {
        saveBtn.disabled = false;
        saveBtn.textContent = 'Save Changes';
    }
}

function cancelProjectRename(projectId) {
    const label = document.getElementById(`project-name-label-${projectId}`);
    const input = document.getElementById(`project-name-input-${projectId}`);
    if (!label || !input) {
        return;
    }

    input.value = label.dataset.originalName || input.value;
    input.dataset.editing = 'false';
    input.style.display = 'none';
    label.style.display = 'block';
}

async function saveProjectRename(projectId) {
    const label = document.getElementById(`project-name-label-${projectId}`);
    const input = document.getElementById(`project-name-input-${projectId}`);
    if (!label || !input) {
        return;
    }

    const newName = input.value.trim();
    const originalName = label.dataset.originalName || label.textContent.trim();

    if (!newName) {
        input.value = originalName;
        cancelProjectRename(projectId);
        projectActionToast('Project name cannot be empty', true);
        return;
    }

    if (newName === originalName) {
        cancelProjectRename(projectId);
        return;
    }

    try {
        const response = await fetch('api/project/rename.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                project_id: projectId,
                name: newName,
                csrf_token: window.PROMANAGE_CSRF_TOKEN || ''
            })
        });

        const result = await response.json();

        if (result.success) {
            label.textContent = result.name;
            label.dataset.originalName = result.name;
            input.value = result.name;
            cancelProjectRename(projectId);
            projectActionToast('Project renamed');
        } else {
            input.value = originalName;
            cancelProjectRename(projectId);
            projectActionToast(result.message || 'Unable to rename project', true);
        }
    } catch (error) {
        input.value = originalName;
        cancelProjectRename(projectId);
        projectActionToast('Network error while renaming project', true);
    }
}

async function confirmProjectTrash(event, projectId, projectName) {
    event.stopPropagation();

    if (!confirm(`Move "${projectName}" to Trash? It can be restored for 30 days.`)) {
        return;
    }

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'project_trash.php';
    form.style.display = 'none';

    const projectInput = document.createElement('input');
    projectInput.type = 'hidden';
    projectInput.name = 'project_id';
    projectInput.value = String(projectId);
    form.appendChild(projectInput);

    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = 'csrf_token';
    csrfInput.value = window.PROMANAGE_CSRF_TOKEN || '';
    form.appendChild(csrfInput);

    document.body.appendChild(form);
    form.submit();
}

async function restoreProject(event, projectId) {
    event.stopPropagation();

    try {
        const response = await fetch('api/project/restore.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                project_id: projectId,
                csrf_token: window.PROMANAGE_CSRF_TOKEN || ''
            })
        });

        const result = await response.json();
        if (result.success) {
            projectActionToast('Project restored');
            setTimeout(() => location.reload(), 700);
        } else {
            projectActionToast(result.message || 'Unable to restore project', true);
        }
    } catch (error) {
        projectActionToast('Network error while restoring project', true);
    }
}

async function permanentlyDeleteProject(event, projectId, projectName) {
    event.stopPropagation();

    if (!confirm(`Permanently delete "${projectName}"? This cannot be undone.`)) {
        return;
    }

    try {
        const response = await fetch('api/project/delete.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                project_id: projectId,
                csrf_token: window.PROMANAGE_CSRF_TOKEN || ''
            })
        });

        const result = await response.json();
        if (result.success) {
            projectActionToast('Project permanently deleted');
            setTimeout(() => location.reload(), 700);
        } else {
            projectActionToast(result.message || 'Unable to delete project', true);
        }
    } catch (error) {
        projectActionToast('Network error while deleting project', true);
    }
}
