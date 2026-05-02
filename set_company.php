<?php
require_once 'config/config.php';
$conn = getDB();

// Set company name
$conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('company_name', 'ELWAVE-POS') ON DUPLICATE KEY UPDATE setting_value = 'ELWAVE-POS'");

// Verify
$settings = getSettings();
var_dump($settings['company_name'] ?? 'NOT SET');
