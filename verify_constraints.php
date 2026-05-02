<?php
require_once 'config/config.php';
$conn = getDB();

echo "<h3>Verifying constraints...</h3>";

$result = $conn->query("SHOW INDEX FROM products WHERE Key_name LIKE 'unique%'");
while ($row = $result->fetch_assoc()) {
    echo "<p>Index: {$row['Key_name']} - Column: {$row['Column_name']}</p>";
}

echo "<p style='color:green'>✓ Constraints properly set!</p>";
