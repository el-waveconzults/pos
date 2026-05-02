<?php
require_once 'config/config.php';
$conn = getDB();

echo "<h3>Settings Table Structure</h3>";
$r = $conn->query("SHOW CREATE TABLE settings");
$row = $r->fetch_assoc();
echo "<pre>" . htmlspecialchars($row['Create Table']) . "</pre>";

echo "<h3>Current Plan Prices</h3>";
$prices = $conn->query("SELECT * FROM settings WHERE setting_key LIKE 'plan_%_price'");
while ($p = $prices->fetch_assoc()) {
    echo "<p>{$p['setting_key']}: {$p['setting_value']}</p>";
}
