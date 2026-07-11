<?php
require_once __DIR__ . '/../public/db.php';

echo "Starting task edit history migration...\n";

$sql = "CREATE TABLE IF NOT EXISTS task_edit_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    field_name VARCHAR(50) NOT NULL,
    old_value TEXT,
    new_value TEXT,
    changed_by INT NOT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id)
)";

if ($conn->query($sql) === TRUE) {
    echo "✓ task_edit_history table created (or already exists).\n";
} else {
    echo "✗ Error creating task_edit_history table: " . $conn->error . "\n";
}

echo "Migration complete.\n";
$conn->close();
?>
