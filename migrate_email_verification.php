<?php
require_once 'config/config.php';
$conn = getDB();

// Add email verification columns to users table
$sql = "ALTER TABLE users ADD COLUMN IF NOT EXISTS email_verified TINYINT(1) DEFAULT 0";
$conn->query($sql);

$sql = "ALTER TABLE users ADD COLUMN IF NOT EXISTS verification_token VARCHAR(64) DEFAULT NULL";
$conn->query($sql);

$sql = "ALTER TABLE users ADD COLUMN IF NOT EXISTS verified_at DATETIME DEFAULT NULL";
$conn->query($sql);

echo "Email verification columns added to users table!<br>";

// Check current structure
$result = $conn->query("DESCRIBE users");
echo "<br>Users table columns:<br>";
while ($row = $result->fetch_assoc()) {
    if (in_array($row['Field'], ['email_verified', 'verification_token', 'verified_at'])) {
        echo "✓ " . $row['Field'] . " - " . $row['Type'] . "<br>";
    }
}
