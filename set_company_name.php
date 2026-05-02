<?php
// Quick script to set company name - run from browser
require_once 'config/config.php';
$conn = getDB();

$conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('company_name', 'ELWAVE-POS') ON DUPLICATE KEY UPDATE setting_value = 'ELWAVE-POS'");

echo "Company name set to ELWAVE-POS!";
echo "<br><a href='index.php'>Go to Dashboard</a>";
