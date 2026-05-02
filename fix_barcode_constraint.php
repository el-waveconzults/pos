<?php
require_once 'config/config.php';
$conn = getDB();

echo "<h2>Fixing Barcode Unique Constraint...</h2>";

// Check if barcode column has unique constraint
$result = $conn->query("SHOW INDEX FROM products WHERE Key_name = 'barcode'");
if ($result->num_rows > 0) {
    try {
        $conn->query("ALTER TABLE products DROP INDEX barcode");
        echo "<p style='color:green'>✓ Dropped old unique constraint on barcode</p>";
    } catch (Exception $e) {
        echo "<p style='color:orange'>⚠ " . $e->getMessage() . "</p>";
    }
}

// Add composite unique key on (company_id, barcode) - only if barcode is not null
try {
    $conn->query("ALTER TABLE products ADD UNIQUE KEY unique_company_barcode (company_id, barcode)");
    echo "<p style='color:green'>✓ Added composite unique constraint on (company_id, barcode)</p>";
} catch (Exception $e) {
    echo "<p style='color:orange'>⚠ " . $e->getMessage() . "</p>";
}

echo "<h3>Done! Barcodes are now unique per company.</h3>";
