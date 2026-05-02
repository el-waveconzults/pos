<?php
$conn = mysqli_connect('localhost', 'root', '', 'pos_db');
$result = mysqli_query($conn, "DESCRIBE products");
while ($row = mysqli_fetch_assoc($result)) {
    echo $row['Field'] . " - " . $row['Type'] . "<br>";
}
mysqli_close($conn);
