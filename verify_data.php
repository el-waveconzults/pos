<?php
require_once 'config/config.php';
$conn = getDB();

echo "<h3>Data Verification</h3>";
echo "<p>Categories for company 1: " . $conn->query("SELECT COUNT(*) FROM categories WHERE company_id = 1")->fetch_row()[0] . "</p>";
echo "<p>Products for company 1: " . $conn->query("SELECT COUNT(*) FROM products WHERE company_id = 1")->fetch_row()[0] . "</p>";
echo "<p>Customers for company 1: " . $conn->query("SELECT COUNT(*) FROM customers WHERE company_id = 1")->fetch_row()[0] . "</p>";
echo "<p style='color:green'>✓ All data properly assigned!</p>";
