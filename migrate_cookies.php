<?php
require_once 'config/config.php';

$conn = getDB();

// Create user_tokens table for remember me functionality
$sql = "CREATE TABLE IF NOT EXISTS user_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_token (token)
)";

if ($conn->query($sql)) {
    echo "✓ user_tokens table created successfully\n";
} else {
    echo "✗ Error: " . $conn->error . "\n";
}
