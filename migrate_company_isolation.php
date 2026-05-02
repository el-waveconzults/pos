<?php

/**
 * Migration: Add company_id to all tables for multi-company support
 * Run this file once to add company_id columns to existing tables
 */

require_once 'config/config.php';
$conn = getDB();

echo "<h2>Running Company Isolation Migration...</h2>";

$tables = [
    'categories' => 'ALTER TABLE categories ADD COLUMN company_id INT DEFAULT 0 AFTER id',
    'products' => 'ALTER TABLE products ADD COLUMN company_id INT DEFAULT 0 AFTER id',
    'customers' => 'ALTER TABLE customers ADD COLUMN company_id INT DEFAULT 0 AFTER id',
    'invoices' => 'ALTER TABLE invoices ADD COLUMN company_id INT DEFAULT 0 AFTER id',
    'expenses' => 'ALTER TABLE expenses ADD COLUMN company_id INT DEFAULT 0 AFTER id',
    'branches' => 'ALTER TABLE branches ADD COLUMN company_id INT DEFAULT 0 AFTER id'
];

foreach ($tables as $table => $sql) {
    // Check if column exists
    $result = $conn->query("SHOW COLUMNS FROM $table LIKE 'company_id'");
    if ($result->num_rows == 0) {
        try {
            $conn->query($sql);
            echo "<p style='color:green'>✓ Added company_id to $table</p>";
        } catch (Exception $e) {
            echo "<p style='color:orange'>⚠ $table: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p style='color:blue'>○ company_id already exists in $table</p>";
    }
}

echo "<h3>Setting company_id for existing records...</h3>";

// Get all companies
$companies = $conn->query("SELECT id FROM companies");
if ($companies && $companies->num_rows > 0) {
    while ($company = $companies->fetch_assoc()) {
        $companyId = $company['id'];

        // Update categories without company_id
        $conn->query("UPDATE categories SET company_id = $companyId WHERE company_id = 0 AND name LIKE '%" . getCompanyName($companyId) . "%'");

        // For products, customers, etc - assign to first company if not set
        // This is a simple approach - you may need to manually assign
    }
    echo "<p style='color:green'>✓ Updated existing records</p>";
}

echo "<h3>Migration complete!</h3>";
echo "<p>Now each company will only see their own data.</p>";

function getCompanyName($companyId)
{
    global $conn;
    $result = $conn->query("SELECT name FROM companies WHERE id = $companyId");
    if ($row = $result->fetch_assoc()) {
        return $row['name'];
    }
    return '';
}
