<?php
require_once __DIR__ . '/../public/db.php';

echo "Starting project trash migration...\n";

$columns = [
    'deleted_at' => "ALTER TABLE projects ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER workspace_id",
    'deleted_by' => "ALTER TABLE projects ADD COLUMN deleted_by INT NULL DEFAULT NULL AFTER deleted_at"
];

foreach ($columns as $column => $sql) {
    $result = $conn->query("SHOW COLUMNS FROM projects LIKE '" . $conn->real_escape_string($column) . "'");
    if ($result && $result->num_rows === 0) {
        if ($conn->query($sql) === TRUE) {
            echo "✓ {$column} column added to projects.\n";
        } else {
            echo "✗ Error adding {$column}: " . $conn->error . "\n";
        }
    } else {
        echo "✓ {$column} column already exists in projects.\n";
    }
}

$fkCheck = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'deleted_by' AND REFERENCED_TABLE_NAME IS NOT NULL");
if ($fkCheck && $fkCheck->num_rows === 0) {
    if ($conn->query("ALTER TABLE projects ADD CONSTRAINT fk_project_deleted_by FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL") === TRUE) {
        echo "✓ deleted_by foreign key added.\n";
    } else {
        echo "✗ Error adding deleted_by foreign key: " . $conn->error . "\n";
    }
} else {
    echo "✓ deleted_by foreign key already exists.\n";
}

echo "Migration complete.\n";
$conn->close();
?>