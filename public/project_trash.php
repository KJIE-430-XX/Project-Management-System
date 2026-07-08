<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/includes/project_lifecycle.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$project_id = (int)($_POST['project_id'] ?? 0);
$csrf_token = $_POST['csrf_token'] ?? '';

if ($project_id <= 0 || !validateCSRFToken($csrf_token)) {
    $_SESSION['success'] = 'Unable to move project to Trash.';
    header("Location: dashboard.php");
    exit;
}

$project = fetchProjectForOwner($conn, $project_id, $user_id, false);
if (!$project) {
    $_SESSION['success'] = 'Project not found or unauthorized.';
    header("Location: dashboard.php");
    exit;
}

$stmt = $conn->prepare("UPDATE projects SET deleted_at = NOW(), deleted_by = ? WHERE id = ? AND owner_id = ? AND deleted_at IS NULL");
if (!$stmt) {
    $_SESSION['success'] = 'Unable to move project to Trash.';
    header("Location: dashboard.php");
    exit;
}

$stmt->bind_param("iii", $user_id, $project_id, $user_id);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    $_SESSION['success'] = 'Project moved to Trash.';
} else {
    $_SESSION['success'] = 'Unable to move project to Trash.';
}

$stmt->close();
$conn->close();

header("Location: dashboard.php");
exit;
?>