<?php

/**
 * License Database Migration
 * Run this once to create the licenses table
 */

$conn = getDB();

$sql = "CREATE TABLE IF NOT EXISTS licenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    license_key VARCHAR(50) UNIQUE NOT NULL,
    company_id INT DEFAULT NULL,
    tier VARCHAR(20) DEFAULT 'starter' COMMENT 'starter, professional, enterprise',
    max_users INT DEFAULT 5,
    max_branches INT DEFAULT 1,
    activated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    last_verified_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    status VARCHAR(20) DEFAULT 'active' COMMENT 'active, suspended, expired',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_license_key (license_key),
    INDEX idx_company_id (company_id),
    INDEX idx_status (status),
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL
)";

if ($conn->query($sql)) {
    echo "✓ Licenses table created successfully\n";
} else {
    echo "✗ Error creating licenses table: " . $conn->error . "\n";
}

// Create audit log table for license changes
$audit_sql = "CREATE TABLE IF NOT EXISTS license_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    license_id INT,
    action VARCHAR(50),
    old_value TEXT,
    new_value TEXT,
    user_id INT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_license_id (license_id),
    INDEX idx_action (action),
    FOREIGN KEY (license_id) REFERENCES licenses(id) ON DELETE CASCADE
)";

if ($conn->query($audit_sql)) {
    echo "✓ License audit log table created successfully\n";
} else {
    echo "✗ Error creating license audit log table: " . $conn->error . "\n";
}

echo "\n✓ License database migration complete\n";
