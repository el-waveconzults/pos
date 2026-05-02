<?php
require_once 'config/config.php';
$conn = getDB();

echo "<h3>Checking data status...</h3>";

// Check how many records have company_id = 0
$cats = $conn->query("SELECT COUNT(*) as cnt FROM categories WHERE company_id = 0")->fetch_assoc()['cnt'];
$prods = $conn->query("SELECT COUNT(*) as cnt FROM products WHERE company_id = 0")->fetch_assoc()['cnt'];
$custs = $conn->query("SELECT COUNT(*) as cnt FROM customers WHERE company_id = 0")->fetch_assoc()['cnt'];

echo "<p>Categories with company_id=0: $cats</p>";
echo "<p>Products with company_id=0: $prods</p>";
echo "<p>Customers with company_id=0: $custs</p>";

// Get first company
$company = $conn->query("SELECT id, name FROM companies LIMIT 1");
if ($row = $company->fetch_assoc()) {
    $companyId = $row['id'];
    echo "<h3>Assigning existing records to company: {$row['name']} (ID: $companyId)</h3>";

    // Assign all records to first company
    $conn->query("UPDATE categories SET company_id = $companyId WHERE company_id = 0");
    $conn->query("UPDATE products SET company_id = $companyId WHERE company_id = 0");
    $conn->query("UPDATE customers SET company_id = $companyId WHERE company_id = 0");
    $conn->query("UPDATE invoices SET company_id = $companyId WHERE company_id = 0");
    $conn->query("UPDATE expenses SET company_id = $companyId WHERE company_id = 0");

    echo "<p style='color:green'>✓ All existing records assigned to company ID $companyId</p>";
} else {
    echo "<p style='color:red'>No companies found! Please create a company first.</p>";
}

echo "<h3>Done!</h3>";
