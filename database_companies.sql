-- Companies table for multi-tenant system
CREATE TABLE IF NOT EXISTS companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(50),
    address TEXT,
    logo VARCHAR(255),
    status ENUM('active', 'inactive', 'pending') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Modify users table to link to companies
ALTER TABLE users ADD COLUMN company_id INT DEFAULT NULL;
ALTER TABLE users ADD FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL;

-- Add index for company_id
ALTER TABLE users ADD INDEX (company_id);