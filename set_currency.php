<?php
require_once 'config/config.php';
$conn = getDB();

// Set default currency to Naira
$conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('currency', '₦') ON DUPLICATE KEY UPDATE setting_value = '₦'");

echo "Currency set to ₦ (Naira)!";
echo "<br><a href='index.php'>Go to Dashboard</a>";
