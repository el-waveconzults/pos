<?php
// Create database script
$conn = @mysqli_connect('localhost', 'root', '');
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Create database
if (mysqli_query($conn, "CREATE DATABASE IF NOT EXISTS pos_db")) {
    echo "Database created successfully!<br>";
} else {
    echo "Error: " . mysqli_error($conn) . "<br>";
}

mysqli_close($conn);
echo "Done!";
