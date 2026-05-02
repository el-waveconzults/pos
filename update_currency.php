<?php
$conn = mysqli_connect('localhost', 'root', '', 'pos_db');

// Update currency to Naira
mysqli_query($conn, "UPDATE settings SET setting_value='NGN' WHERE setting_key='currency'");
mysqli_query($conn, "UPDATE settings SET setting_value='₦' WHERE setting_key='company_name'");

// Also update tax rate for Nigeria (7.5% VAT)
mysqli_query($conn, "UPDATE settings SET setting_value='7.5' WHERE setting_key='tax_rate'");

echo "Currency updated to Nigeria Naira (₦)!";
mysqli_close($conn);
