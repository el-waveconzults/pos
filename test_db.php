<?php
require_once 'config/config.php';
$conn = getDB();

// Add default settings if not exist
$settings_to_add = [
    ['plan_free_days', '7'],
    ['plan_basic_price', '5000'],
    ['plan_premium_price', '15000'],
    ['currency', '₦'],
    ['tax_rate', '7.5']
];

foreach ($settings_to_add as $setting) {
    $conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('$setting[0]', '$setting[1]')");
}

echo "Settings added!<br>";

// Now check
$settings = getSettings();
echo "plan_free_days: " . ($settings['plan_free_days'] ?? 'NOT SET') . "<br>";
echo "plan_basic_price: " . ($settings['plan_basic_price'] ?? 'NOT SET') . "<br>";
echo "plan_premium_price: " . ($settings['plan_premium_price'] ?? 'NOT SET') . "<br>";

echo "<br>=== Check Users ===<br>";
$result = $conn->query("SELECT id, name, email, role FROM users");
while ($row = $result->fetch_assoc()) {
    echo $row['id'] . ": " . $row['name'] . " (" . $row['email'] . ") - " . $row['role'] . "<br>";
}
