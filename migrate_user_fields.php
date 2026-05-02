<?php
require_once 'config/config.php';
$conn = getDB();

// Add phone column to users table if it doesn't exist
$result = $conn->query("SHOW COLUMNS FROM users LIKE 'phone'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN phone VARCHAR(20) DEFAULT NULL");
    echo "✓ Added phone column to users table\n";
} else {
    echo "✓ Phone column already exists\n";
}

// Add branch_id column to users table if it doesn't exist
$result = $conn->query("SHOW COLUMNS FROM users LIKE 'branch_id'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN branch_id INT DEFAULT NULL");
    echo "✓ Added branch_id column to users table\n";
} else {
    echo "✓ branch_id column already exists\n";
}

echo "✓ Migration complete!";
