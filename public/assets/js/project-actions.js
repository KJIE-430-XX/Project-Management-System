document.addEventListener('DOMContentLoaded', () => {
    bindInlineProjectEditors();
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
    event.stopPropagation();

    const label = document.getElementById(`project-name-label-${projectId}`);
    const input = document.getElementById(`project-name-input-${projectId}`);
    if (!label || !input) {
        return;
    }

    label.style.display = 'none';
    input.style.display = 'block';
    input.dataset.editing = 'true';
    input.focus();
    input.select();
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
