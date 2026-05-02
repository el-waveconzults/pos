<?php
require_once 'config/config.php';
$conn = getDB();

// Force set currency to Naira
$conn->query("DELETE FROM settings WHERE setting_key = 'currency'");
$conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('currency', '₦')");

// Also set tax rate to default
$conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('tax_rate', '7.5') ON DUPLICATE KEY UPDATE setting_value = '7.5'");

echo "Currency set to ₦ and tax rate to 7.5%!";
echo "<br><a href='index.php'>Go to Dashboard</a>";
