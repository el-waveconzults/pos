<?php
require_once(__DIR__ . '/../config/config.php');
require_once(__DIR__ . '/../config/LicenseManager.php');

$currentUser = getCurrentUser();

$licenseManager = new LicenseManager(getDB());
$message = '';
$error = '';
$licenses = [];

// Check if licenses table exists
$conn = getDB();
$tableCheckResult = $conn->query("SHOW TABLES LIKE 'licenses'");
$tableExists = $tableCheckResult && $tableCheckResult->num_rows > 0;

if (!$tableExists) {
    $error = '<strong>License System Not Initialized</strong><br>The license database tables have not been created yet. <a href="setup_licenses.php" class="btn btn-primary btn-sm">Click here to initialize</a>';
}

// Handle generate license
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_license'])) {
    if (!$tableExists) {
        $error = 'License system not initialized. Please set up the database first.';
    } else {
        $tier = sanitize($_POST['tier'] ?? 'starter');
        $companyId = !empty($_POST['company_id']) ? (int)$_POST['company_id'] : null;
        $expiresAt = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;

        $licenseData = [
            'tier' => $tier,
            'company_id' => $companyId,
            'expires_at' => $expiresAt
        ];

        $result = $licenseManager->createLicense($licenseData);
        if ($result['success']) {
            $message = 'License generated: ' . $result['license_key'];
        } else {
            $error = $result['message'];
        }
    }
}

// Handle suspend/reactivate
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_license'])) {
    if (!$tableExists) {
        $error = 'License system not initialized. Please set up the database first.';
    } else {
        $licenseKey = sanitize($_POST['license_key'] ?? '');
        $action = sanitize($_POST['action'] ?? '');

        if ($action === 'suspend') {
            $licenseManager->suspendLicense($licenseKey);
            $message = 'License suspended.';
        } elseif ($action === 'reactivate') {
            $licenseManager->reactivateLicense($licenseKey);
            $message = 'License reactivated.';
        }
    }
}

// Handle extend license
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['extend_license'])) {
    if (!$tableExists) {
        $error = 'License system not initialized. Please set up the database first.';
    } else {
        $licenseKey = sanitize($_POST['license_key'] ?? '');
        $days = (int)($_POST['days'] ?? 365);

        if ($licenseManager->extendLicense($licenseKey, $days)) {
            $message = "License extended by $days days.";
        } else {
            $error = 'Failed to extend license.';
        }
    }
}

// Handle delete license
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_license'])) {
    if (!$tableExists) {
        $error = 'License system not initialized. Please set up the database first.';
    } else {
        $licenseKey = sanitize($_POST['license_key'] ?? '');

        if ($licenseManager->deleteLicense($licenseKey)) {
            $message = 'License deleted successfully.';
        } else {
            $error = 'Failed to delete license.';
        }
    }
}

// Fetch all licenses (only if table exists)
$companies = [];
$stats = ['total' => 0, 'active' => 0, 'suspended' => 0, 'expired' => 0];

if ($tableExists) {
    $query = "SELECT l.*, c.name as company_name FROM licenses l LEFT JOIN companies c ON l.company_id = c.id ORDER BY l.created_at DESC";
    $result = getDB()->query($query);
    if ($result) {
        $licenses = $result->fetch_all(MYSQLI_ASSOC);
    }

    // Fetch companies for dropdown
    $companyResult = getDB()->query("SELECT id, name FROM companies ORDER BY name");
    if ($companyResult) {
        $companies = $companyResult->fetch_all(MYSQLI_ASSOC);
    }

    // Get statistics
    $stats = $licenseManager->getLicenseStats();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>License Administration - POS System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: #f5f5f5;
        }

        .admin-panel {
            background: white;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: bold;
        }

        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }

        .license-row {
            border-bottom: 1px solid #eee;
            padding: 15px 0;
        }

        .license-key {
            font-family: monospace;
            background: #f5f5f5;
            padding: 5px 10px;
            border-radius: 4px;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-suspended {
            background: #fff3cd;
            color: #856404;
        }
    </style>
</head>

<body>
    <div class="container-fluid py-4">
        <div class="admin-panel">
            <h1 class="mb-4"><i class="fas fa-key"></i> License Administration</h1>

            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Statistics (only show if table exists) -->
            <?php if ($tableExists): ?>
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-value"><?php echo (int)$stats['total']; ?></div>
                            <div class="stat-label">Total Licenses</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <div class="stat-value"><?php echo (int)$stats['active']; ?></div>
                            <div class="stat-label">Active</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                            <div class="stat-value"><?php echo (int)$stats['suspended']; ?></div>
                            <div class="stat-label">Suspended</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                            <div class="stat-value"><?php echo (int)$stats['expired']; ?></div>
                            <div class="stat-label">Expired</div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Generate New License (only show if table exists) -->
        <?php if ($tableExists): ?>
            <div class="admin-panel">
                <h3>Generate New License</h3>
                <form method="POST" class="row g-3">
                    <div class="col-md-3">
                        <label for="tier" class="form-label">Tier</label>
                        <select class="form-select" id="tier" name="tier" required>
                            <option value="starter">Starter (5 users, 1 branch)</option>
                            <option value="professional">Professional (20 users, 10 branches)</option>
                            <option value="enterprise">Enterprise (Unlimited)</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="company_id" class="form-label">Assign to Company (Optional)</label>
                        <select class="form-select" id="company_id" name="company_id">
                            <option value="">None - Unassigned</option>
                            <?php foreach ($companies as $company): ?>
                                <option value="<?php echo (int)$company['id']; ?>">
                                    <?php echo htmlspecialchars($company['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="expires_at" class="form-label">Expiration Date (Optional)</label>
                        <input type="date" class="form-control" id="expires_at" name="expires_at">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" name="generate_license" class="btn btn-success w-100">
                            <i class="fas fa-plus"></i> Generate License
                        </button>
                    </div>
                </form>
            </div>

            <!-- Licenses List -->
            <div class="admin-panel">
                <h3>All Licenses</h3>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>License Key</th>
                                <th>Company</th>
                                <th>Tier</th>
                                <th>Status</th>
                                <th>Users/Branches</th>
                                <th>Activated</th>
                                <th>Expires</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($licenses as $license):
                                $isExpired = strtotime($license['expires_at']) < time();
                                $expiresClass = $isExpired ? 'table-danger' : '';
                            ?>
                                <tr class="<?php echo $expiresClass; ?>">
                                    <td>
                                        <code class="license-key">
                                            <?php echo htmlspecialchars(substr($license['license_key'], 0, 12) . '****' . substr($license['license_key'], -8)); ?>
                                        </code>
                                    </td>
                                    <td><?php echo $license['company_name'] ? htmlspecialchars($license['company_name']) : '<span class="text-muted">Unassigned</span>'; ?></td>
                                    <td><span class="badge bg-info"><?php echo ucfirst(htmlspecialchars($license['tier'])); ?></span></td>
                                    <td>
                                        <span class="status-badge status-<?php echo htmlspecialchars($license['status']); ?>">
                                            <?php echo ucfirst(htmlspecialchars($license['status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small>
                                            <?php echo (int)$license['max_users']; ?> / <?php echo (int)$license['max_branches']; ?>
                                        </small>
                                    </td>
                                    <td><small><?php echo date('M d, Y', strtotime($license['activated_at'])); ?></small></td>
                                    <td>
                                        <small class="<?php echo $isExpired ? 'text-danger fw-bold' : ''; ?>">
                                            <?php echo date('M d, Y', strtotime($license['expires_at'])); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="license_key" value="<?php echo htmlspecialchars($license['license_key']); ?>">
                                                <input type="hidden" name="action" value="<?php echo $license['status'] === 'active' ? 'suspend' : 'reactivate'; ?>">
                                                <button type="submit" name="toggle_license" class="btn btn-sm <?php echo $license['status'] === 'active' ? 'btn-warning' : 'btn-success'; ?>" title="<?php echo $license['status'] === 'active' ? 'Suspend' : 'Reactivate'; ?>">
                                                    <i class="fas fa-<?php echo $license['status'] === 'active' ? 'pause' : 'play'; ?>"></i>
                                                </button>
                                            </form>
                                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#extendModal<?php echo (int)$license['id']; ?>">
                                                <i class="fas fa-calendar-plus"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo (int)$license['id']; ?>" title="Delete License">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Extend Modal -->
                                <div class="modal fade" id="extendModal<?php echo (int)$license['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Extend License</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST">
                                                <div class="modal-body">
                                                    <input type="hidden" name="license_key" value="<?php echo htmlspecialchars($license['license_key']); ?>">
                                                    <div class="mb-3">
                                                        <label for="days" class="form-label">Days to Extend</label>
                                                        <input type="number" class="form-control" name="days" value="365" min="1" required>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" name="extend_license" class="btn btn-primary">Extend</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Delete Modal -->
                                <div class="modal fade" id="deleteModal<?php echo (int)$license['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Delete License</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST">
                                                <div class="modal-body">
                                                    <p><strong>Are you sure you want to delete this license?</strong></p>
                                                    <p class="text-muted">License Key: <code><?php echo htmlspecialchars(substr($license['license_key'], 0, 12) . '****' . substr($license['license_key'], -8)); ?></code></p>
                                                    <p class="text-danger"><small><i class="fas fa-exclamation-triangle"></i> This action cannot be undone.</small></p>
                                                    <input type="hidden" name="license_key" value="<?php echo htmlspecialchars($license['license_key']); ?>">
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" name="delete_license" class="btn btn-danger">Delete License</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>

</html>