<?php
require_once 'config/config.php';
$conn = getDB();

echo "<h3>Fixing empty barcode issue...</h3>";

// Set all empty/null barcodes to NULL (for unique constraint to work)
$conn->query("UPDATE products SET barcode = NULL WHERE barcode = '' OR barcode IS NULL");
echo "<p style='color:green'>✓ Set empty barcodes to NULL</p>";

// Drop the constraint if exists
try {
    $conn->query("ALTER TABLE products DROP INDEX unique_company_barcode");
} catch (Exception $e) {
    // Ignore if doesn't exist
}

// Add new constraint that allows NULL
$conn->query("ALTER TABLE products ADD UNIQUE KEY unique_company_barcode (company_id, barcode)");
echo "<p style='color:green'>✓ Added composite unique constraint on (company_id, barcode)</p>";

echo "<h3>Done!</h3>";
