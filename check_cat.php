<?php
$conn = mysqli_connect('localhost', 'root', '', 'pos_db');
$result = mysqli_query($conn, "SELECT id, category_id FROM products LIMIT 5");
while ($row = mysqli_fetch_assoc($result)) {
    echo $row['id'] . " - " . ($row['category_id'] ?? 'NULL') . "<br>";
}
mysqli_close($conn);
