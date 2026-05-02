<?php
// Import database schema - statement by statement
$conn = mysqli_connect('localhost', 'root', '', 'pos_db');

$statements = [
    "CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    "CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category_id INT,
        name VARCHAR(200) NOT NULL,
        sku VARCHAR(50) UNIQUE,
        barcode VARCHAR(100),
        description TEXT,
        cost_price DECIMAL(12,2) DEFAULT 0,
        sell_price DECIMAL(12,2) DEFAULT 0,
        quantity INT DEFAULT 0,
        min_quantity INT DEFAULT 10,
        image VARCHAR(255),
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
    )",

    "CREATE TABLE IF NOT EXISTS customers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(200) NOT NULL,
        email VARCHAR(100),
        phone VARCHAR(20),
        address TEXT,
        company_name VARCHAR(200),
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    "CREATE TABLE IF NOT EXISTS sales (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_no VARCHAR(50) UNIQUE,
        customer_id INT,
        subtotal DECIMAL(12,2) DEFAULT 0,
        tax_amount DECIMAL(12,2) DEFAULT 0,
        discount_amount DECIMAL(12,2) DEFAULT 0,
        total_amount DECIMAL(12,2) DEFAULT 0,
        payment_method ENUM('cash', 'card', 'mobile_money', 'credit') DEFAULT 'cash',
        amount_paid DECIMAL(12,2) DEFAULT 0,
        amount_change DECIMAL(12,2) DEFAULT 0,
        status ENUM('completed', 'pending', 'cancelled') DEFAULT 'completed',
        notes TEXT,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
    )",

    "CREATE TABLE IF NOT EXISTS sale_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sale_id INT NOT NULL,
        product_id INT NOT NULL,
        quantity INT NOT NULL,
        unit_price DECIMAL(12,2) NOT NULL,
        total_price DECIMAL(12,2) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    )",

    "CREATE TABLE IF NOT EXISTS invoices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_no VARCHAR(50) UNIQUE,
        customer_id INT,
        sale_id INT,
        due_date DATE,
        subtotal DECIMAL(12,2) DEFAULT 0,
        tax_amount DECIMAL(12,2) DEFAULT 0,
        discount_amount DECIMAL(12,2) DEFAULT 0,
        total_amount DECIMAL(12,2) DEFAULT 0,
        amount_paid DECIMAL(12,2) DEFAULT 0,
        status ENUM('paid', 'partial', 'overdue', 'cancelled') DEFAULT 'partial',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
        FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE SET NULL
    )",

    "CREATE TABLE IF NOT EXISTS expenses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category VARCHAR(100),
        description TEXT NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        expense_date DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(200) NOT NULL,
        email VARCHAR(100) UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'manager', 'cashier') DEFAULT 'cashier',
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    "CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE,
        setting_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )"
];

foreach ($statements as $sql) {
    if (!mysqli_query($conn, $sql)) {
        echo "Error: " . mysqli_error($conn) . "<br>";
    } else {
        echo "Table created<br>";
    }
}

// Insert default data
$defaults = [
    "INSERT INTO categories (name, description) VALUES ('Electronics', 'Electronic devices and accessories')",
    "INSERT INTO categories (name, description) VALUES ('Food & Beverages', 'Food items and drinks')",
    "INSERT INTO categories (name, description) VALUES ('Clothing', 'Apparel and fashion items')",
    "INSERT INTO categories (name, description) VALUES ('Office Supplies', 'Office and stationery items')",
    "INSERT INTO categories (name, description) VALUES ('Other', 'Miscellaneous products')",

    "INSERT INTO users (name, email, password, role) VALUES ('Admin User', 'admin@pos.com', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin')",
    "INSERT INTO users (name, email, password, role) VALUES ('Manager', 'manager@pos.com', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager')",
    "INSERT INTO users (name, email, password, role) VALUES ('Cashier', 'cashier@pos.com', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cashier')",

    "INSERT INTO settings (setting_key, setting_value) VALUES ('company_name', 'My POS Business')",
    "INSERT INTO settings (setting_key, setting_value) VALUES ('company_address', '123 Business Street, City')",
    "INSERT INTO settings (setting_key, setting_value) VALUES ('company_phone', '+1234567890')",
    "INSERT INTO settings (setting_key, setting_value) VALUES ('company_email', 'info@mypostbusiness.com')",
    "INSERT INTO settings (setting_key, setting_value) VALUES ('tax_rate', '10')",
    "INSERT INTO settings (setting_key, setting_value) VALUES ('currency', 'USD')",
    "INSERT INTO settings (setting_key, setting_value) VALUES ('invoice_prefix', 'INV')",
    "INSERT INTO settings (setting_key, setting_value) VALUES ('low_stock_alert', '10')",

    "INSERT INTO products (category_id, name, sku, cost_price, sell_price, quantity) VALUES (1, 'Wireless Mouse', 'WM001', 15.00, 25.00, 50)",
    "INSERT INTO products (category_id, name, sku, cost_price, sell_price, quantity) VALUES (1, 'USB Keyboard', 'UK001', 20.00, 35.00, 30)",
    "INSERT INTO products (category_id, name, sku, cost_price, sell_price, quantity) VALUES (1, 'Headphones', 'HP001', 25.00, 45.00, 25)",
    "INSERT INTO products (category_id, name, sku, cost_price, sell_price, quantity) VALUES (2, 'Bottled Water (Pack)', 'BW001', 5.00, 10.00, 100)",
    "INSERT INTO products (category_id, name, sku, cost_price, sell_price, quantity) VALUES (2, 'Snacks Pack', 'SN001', 3.00, 7.00, 75)",
    "INSERT INTO products (category_id, name, sku, cost_price, sell_price, quantity) VALUES (3, 'T-Shirt (Basic)', 'TS001', 8.00, 15.00, 50)",
    "INSERT INTO products (category_id, name, sku, cost_price, sell_price, quantity) VALUES (3, 'Jeans Pants', 'JP001', 20.00, 40.00, 20)",
    "INSERT INTO products (category_id, name, sku, cost_price, sell_price, quantity) VALUES (4, 'Notebook A4', 'NB001', 1.00, 2.50, 200)",
    "INSERT INTO products (category_id, name, sku, cost_price, sell_price, quantity) VALUES (4, 'Pen Set (5pcs)', 'PN001', 2.00, 5.00, 100)"
];

foreach ($defaults as $sql) {
    mysqli_query($conn, $sql);
}

echo "Database setup complete!";

mysqli_close($conn);

mysqli_close($conn);
