<?php
require_once 'config/config.php';
$conn = getDB();

echo "<h2>Fixing SKU Unique Constraint...</h2>";

// Drop the old unique key on sku
try {
    $conn->query("ALTER TABLE products DROP INDEX sku");
    echo "<p style='color:green'>✓ Dropped old unique constraint on sku</p>";
} catch (Exception $e) {
    echo "<p style='color:orange'>⚠ " . $e->getMessage() . "</p>";
}

// Add composite unique key on (company_id, sku)
try {
    $conn->query("ALTER TABLE products ADD UNIQUE KEY unique_company_sku (company_id, sku)");
    echo "<p style='color:green'>✓ Added composite unique constraint on (company_id, sku)</p>";
} catch (Exception $e) {
    echo "<p style='color:orange'>⚠ " . $e->getMessage() . "</p>";
}

echo "<h3>Done! SKUs are now unique per company.</h3>";
