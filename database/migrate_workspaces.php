<?php
require_once __DIR__ . '/../public/db.php';

echo "Starting database migration...\n";

// 1. Create workspaces table
$sql1 = "CREATE TABLE IF NOT EXISTS workspaces (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

if ($conn->query($sql1) === TRUE) {
    echo "✓ workspaces table created (or already exists).\n";
} else {
    echo "✗ Error creating workspaces table: " . $conn->error . "\n";
}

// 2. Add workspace_id column to projects
$sql2 = "SHOW COLUMNS FROM projects LIKE 'workspace_id'";
$result = $conn->query($sql2);

if ($result && $result->num_rows == 0) {
    $sql3 = "ALTER TABLE projects ADD COLUMN workspace_id INT NULL DEFAULT NULL";
    if ($conn->query($sql3) === TRUE) {
        echo "✓ workspace_id column added to projects.\n";
        
        // Add foreign key
        $sql4 = "ALTER TABLE projects ADD CONSTRAINT fk_project_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE SET NULL";
        if ($conn->query($sql4) === TRUE) {
            echo "✓ Foreign key for workspace_id added.\n";
        } else {
            echo "✗ Error adding foreign key: " . $conn->error . "\n";
        }
    } else {
        echo "✗ Error adding workspace_id column: " . $conn->error . "\n";
    }
} else {
    echo "✓ workspace_id column already exists in projects.\n";
}

echo "Migration complete.\n";
$conn->close();
?>
