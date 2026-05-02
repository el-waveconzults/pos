<?php

/**
 * Migration: Add subscription and support features
 * Run this to add: plan, subscription_status, expiry_date to companies
 * and create support_tickets table
 */

require_once 'config/config.php';
$conn = getDB();

echo "<h2>Adding Subscription & Support Features...</h2>";

// 1. Add subscription fields to companies table
$fields = [
    'plan' => "ALTER TABLE companies ADD COLUMN plan VARCHAR(50) DEFAULT 'free' AFTER status",
    'subscription_status' => "ALTER TABLE companies ADD COLUMN subscription_status VARCHAR(20) DEFAULT 'trial' AFTER plan",
    'expiry_date' => "ALTER TABLE companies ADD COLUMN expiry_date DATE AFTER subscription_status",
    'trial_start' => "ALTER TABLE companies ADD COLUMN trial_start DATE AFTER expiry_date",
    'payment_method' => "ALTER TABLE companies ADD COLUMN payment_method VARCHAR(50) AFTER payment_details"
];

foreach ($fields as $name => $sql) {
    try {
        $conn->query($sql);
        echo "<p style='color:green'>✓ Added $name to companies</p>";
    } catch (Exception $e) {
        echo "<p style='color:blue'>○ $name: " . $e->getMessage() . "</p>";
    }
}

// 2. Create support_tickets table
$createTickets = "CREATE TABLE IF NOT EXISTS support_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    subject VARCHAR(200) NOT NULL,
    description TEXT,
    status VARCHAR(20) DEFAULT 'open',
    priority VARCHAR(20) DEFAULT 'normal',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
)";

try {
    $conn->query($createTickets);
    echo "<p style='color:green'>✓ Created support_tickets table</p>";
} catch (Exception $e) {
    echo "<p style='color:blue'>○ support_tickets: " . $e->getMessage() . "</p>";
}

// 3. Set trial dates for existing companies (7 days from now)
$trialEnd = date('Y-m-d', strtotime('+7 days'));
$conn->query("UPDATE companies SET trial_start = CURDATE(), expiry_date = '$trialEnd', subscription_status = 'trial', plan = 'free' WHERE trial_start IS NULL");

echo "<p style='color:green'>✓ Set trial dates for existing companies</p>";

echo "<h3>Done! Subscription and support features added.</h3>";
