<?php
require_once 'config/config.php';
$conn = getDB();

echo "<h3>Products Table Structure</h3>";
$r = $conn->query("SHOW CREATE TABLE products");
$row = $r->fetch_assoc();
echo "<pre>" . htmlspecialchars($row['Create Table']) . "</pre>";

echo "<h3>Current SKUs in company 1</h3>";
$products = $conn->query("SELECT id, name, sku FROM products WHERE company_id = 1 AND sku LIKE 'SM%'");
while ($p = $products->fetch_assoc()) {
    echo "<p>ID: {$p['id']} - SKU: {$p['sku']} - Name: {$p['name']}</p>";
}
