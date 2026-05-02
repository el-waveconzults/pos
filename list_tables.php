<?php
require_once 'config/config.php';
$conn = getDB();

echo "<h3>Database Tables</h3>";
$r = $conn->query('SHOW TABLES');
while ($t = $r->fetch_array()) {
    echo "<p>" . $t[0] . "</p>";
}
