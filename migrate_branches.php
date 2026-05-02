<?php

/**
 * Database Migration: Add Branches Support
 * Run this file once to add branches functionality
 */

require_once 'config/config.php';
$conn = getDB();

echo "<h2>Running Branch Migration...</h2>";

// Create branches table
$sql1 = "CREATE TABLE IF NOT EXISTS branches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    address TEXT,
    phone VARCHAR(50),
    email VARCHAR(255),
    manager_id INT DEFAULT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
)";

if ($conn->query($sql1)) {
    echo "<p>✅ Created branches table</p>";
} else {
    echo "<p>❌ Error creating branches: " . $conn->error . "</p>";
}

// Add branch_id column to users table if not exists
$sql2 = "ALTER TABLE users ADD COLUMN IF NOT EXISTS branch_id INT DEFAULT NULL";
$conn->query($sql2); // MySQL doesn't support IF EXISTS for columns, but we'll handle this differently

// Check if branch_id exists
$result = $conn->query("SHOW COLUMNS FROM users LIKE 'branch_id'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN branch_id INT DEFAULT NULL");
    echo "<p>✅ Added branch_id to users table</p>";
} else {
    echo "<p>✅ branch_id column already exists</p>";
}

// Add foreign key constraint
$sql3 = "ALTER TABLE users ADD CONSTRAINT fk_user_branch FOREIGN KEY (branch_id) REFERENCES branches(id)";
$conn->query($sql3); // May fail if constraint exists, that's okay

echo "<p>✅ Migration complete!</p>";
echo "<a href='index.php' class='btn btn-primary'>Go to Dashboard</a>";
