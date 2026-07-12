<?php
require_once __DIR__ . '/../public/db.php';

echo "Starting task comment notification migration...\n";

$sql = "CREATE TABLE IF NOT EXISTS task_comment_notifications (
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    unread_count INT NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (task_id, user_id),
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    echo "✓ task_comment_notifications table created (or already exists).\n";
} else {
    echo "✗ Error creating task_comment_notifications table: " . $conn->error . "\n";
}

echo "Migration complete.\n";
$conn->close();
?>
