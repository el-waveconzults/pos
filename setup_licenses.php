<?php
require_once 'config/config.php';

$conn = getDB();
$message = '';
$error = '';

// Only allow setup if table doesn't exist
$tableExists = $conn->query("SHOW TABLES LIKE 'licenses'");
$hasTable = $tableExists && $tableExists->num_rows > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup'])) {
    if ($hasTable) {
        $error = 'Licenses table already exists. No setup needed.';
    } else {
        // Create licenses table
        $sql = "CREATE TABLE IF NOT EXISTS licenses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            license_key VARCHAR(50) UNIQUE NOT NULL,
            company_id INT DEFAULT NULL,
            tier VARCHAR(20) DEFAULT 'starter' COMMENT 'starter, professional, enterprise',
            max_users INT DEFAULT 5,
            max_branches INT DEFAULT 1,
            activated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NULL,
            last_verified_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            status VARCHAR(20) DEFAULT 'active' COMMENT 'active, suspended, expired',
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_license_key (license_key),
            INDEX idx_company_id (company_id),
            INDEX idx_status (status),
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL
        )";

        if ($conn->query($sql)) {
            $message = '✓ Licenses table created successfully';
        } else {
            $error = 'Error creating licenses table: ' . $conn->error;
        }

        // Create audit log table
        $audit_sql = "CREATE TABLE IF NOT EXISTS license_audit_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            license_id INT,
            action VARCHAR(50),
            old_value TEXT,
            new_value TEXT,
            user_id INT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_license_id (license_id),
            INDEX idx_action (action),
            FOREIGN KEY (license_id) REFERENCES licenses(id) ON DELETE CASCADE
        )";

        if ($conn->query($audit_sql)) {
            $message .= '<br>✓ License audit log table created successfully';
        } else {
            $error = 'Error creating audit log table: ' . $conn->error;
        }
    }
}

// Refresh table status
$tableExists = $conn->query("SHOW TABLES LIKE 'licenses'");
$hasTable = $tableExists && $tableExists->num_rows > 0;
?>
<!DOCTYPE html>
<html>

<head>
    <title>License Manager Setup</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <style>
        body {
            background: #f5f5f5;
            padding: 40px 20px;
        }

        .setup-card {
            background: white;
            border-radius: 8px;
            padding: 40px;
            max-width: 500px;
            margin: 0 auto;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .success {
            color: #28a745;
        }

        .error {
            color: #dc3545;
        }
    </style>
</head>

<body>
    <div class="setup-card">
        <h1>🔐 License Manager Setup</h1>

        <?php if ($message): ?>
            <div class="alert alert-success mt-3">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger mt-3">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="mt-4">
            <h5>Database Status</h5>
            <p>
                Licenses table:
                <span class="badge <?php echo $hasTable ? 'bg-success' : 'bg-warning'; ?>">
                    <?php echo $hasTable ? '✓ Created' : '⚠ Missing'; ?>
                </span>
            </p>
        </div>

        <?php if (!$hasTable): ?>
            <form method="POST" class="mt-4">
                <p class="text-muted">The licenses table needs to be created to use the license manager.</p>
                <button type="submit" name="setup" class="btn btn-primary w-100">
                    Create License Tables
                </button>
            </form>
        <?php else: ?>
            <div class="alert alert-success mt-4">
                <strong>✓ Setup Complete!</strong>
                The license system is ready to use.
                <br><br>
                <a href="index.php?page=admin_licenses" class="btn btn-primary btn-sm">
                    Go to License Manager
                </a>
            </div>
        <?php endif; ?>

        <hr class="my-4">
        <h6>Next Steps</h6>
        <ol class="small">
            <li>License tables will be created automatically when you click the button above</li>
            <li>Go to License Administration (Super Admin only)</li>
            <li>Generate a license for your company</li>
            <li>Activate it in License Manager</li>
        </ol>
    </div>
</body>

</html>