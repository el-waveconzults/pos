<?php
require_once 'config/config.php';
$conn = getDB();

echo "<h3>Checking duplicate barcodes...</h3>";

// Find duplicate barcodes
$dupes = $conn->query("SELECT barcode, COUNT(*) as cnt FROM products WHERE company_id = 1 AND barcode IS NOT NULL AND barcode != '' GROUP BY barcode HAVING cnt > 1");
while ($d = $dupes->fetch_assoc()) {
    echo "<p>Duplicate barcode: {$d['barcode']} ({$d['cnt']} times)</p>";

    // Clear duplicate barcodes (set to empty)
    $conn->query("UPDATE products SET barcode = '' WHERE company_id = 1 AND barcode = '{$d['barcode']}'");
    echo "<p style='color:orange'>⚠ Cleared duplicate barcode</p>";
}

echo "<p style='color:green'>✓ Barcode duplicates fixed!</p>";
