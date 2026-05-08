<?php
require_once(__DIR__ . '/../config/config.php');
require_once(__DIR__ . '/../config/LicenseManager.php');

$currentUser = getCurrentUser();

$message = '';
$error = '';

// Handle license activation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['activate_license'])) {
    $licenseKey = sanitize($_POST['license_key'] ?? '');

    if (!$licenseKey) {
        $error = 'Please enter a license key';
    } else {
        $licenseManager = new LicenseManager(getDB());
        $validation = $licenseManager->validateLicense($licenseKey);

        if ($validation['valid']) {
            // Assign license to current company
            $companyId = $_SESSION['company_id'] ?? null;
            if ($companyId) {
                $conn = getDB();
                $stmt = $conn->prepare("UPDATE licenses SET company_id = ? WHERE license_key = ?");
                $stmt->bind_param("is", $companyId, $licenseKey);
                if ($stmt->execute()) {
                    $message = 'License activated successfully! Features are now available.';
                } else {
                    $error = 'Failed to activate license: ' . $conn->error;
                }
            } else {
                $error = 'Company ID not found in session';
            }
        } else {
            $error = $validation['message'];
        }
    }
}

// Get current license
$licenseManager = new LicenseManager(getDB());
$currentCompanyId = $_SESSION['company_id'] ?? null;
$currentLicense = $currentCompanyId ? $licenseManager->getCompanyLicense($currentCompanyId) : null;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>License Manager - POS System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <style>
        body {
            background: #f5f5f5;
        }

        .license-card {
            background: white;
            border-radius: 8px;
            padding: 30px;
            margin-top: 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .tier-badge {
            font-size: 14px;
            padding: 5px 12px;
            border-radius: 20px;
        }

        .tier-starter {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .tier-professional {
            background: #e3f2fd;
            color: #1565c0;
        }

        .tier-enterprise {
            background: #f3e5f5;
            color: #6a1b9a;
        }

        .feature-list {
            list-style: none;
            padding: 0;
        }

        .feature-list li {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }

        .feature-list li:before {
            content: "✓ ";
            color: #4caf50;
            font-weight: bold;
            margin-right: 8px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="license-card">
            <h1 class="mb-4">License Manager</h1>

            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-6">
                    <h3>Current License</h3>
                    <?php if ($currentLicense): ?>
                        <div class="bg-light p-3 rounded">
                            <p><strong>License Key:</strong>
                                <code><?php echo htmlspecialchars(substr($currentLicense['license_key'], 0, 8) . '****' . substr($currentLicense['license_key'], -8)); ?></code>
                            </p>
                            <p><strong>Tier:</strong>
                                <span class="tier-badge tier-<?php echo htmlspecialchars($currentLicense['tier']); ?>">
                                    <?php echo ucfirst(htmlspecialchars($currentLicense['tier'])); ?>
                                </span>
                            </p>
                            <p><strong>Status:</strong>
                                <span class="badge <?php echo $currentLicense['status'] === 'active' ? 'bg-success' : 'bg-warning'; ?>">
                                    <?php echo ucfirst(htmlspecialchars($currentLicense['status'])); ?>
                                </span>
                            </p>
                            <p><strong>Max Users:</strong> <?php echo (int)$currentLicense['max_users']; ?></p>
                            <p><strong>Max Branches:</strong> <?php echo (int)$currentLicense['max_branches']; ?></p>
                            <p><strong>Expires:</strong> <code><?php echo date('M d, Y', strtotime($currentLicense['expires_at'])); ?></code></p>
                            <?php if (strtotime($currentLicense['expires_at']) < strtotime('+10 days')): ?>
                                <div class="alert alert-warning mb-0">
                                    ⚠ Your license expires soon. Please renew to continue service.
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            No active license. Enter a license key to activate.
                        </div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <h3>Activate License</h3>
                    <form method="POST" class="bg-light p-3 rounded">
                        <div class="mb-3">
                            <label for="license_key" class="form-label">License Key</label>
                            <input type="text" class="form-control" id="license_key" name="license_key"
                                placeholder="POS-XXXX-XXXX-XXXX-XXXX" required>
                            <small class="text-muted">Format: POS-XXXX-XXXX-XXXX-XXXX</small>
                        </div>
                        <button type="submit" name="activate_license" class="btn btn-primary w-100">Activate License</button>
                    </form>
                </div>
            </div>

            <!-- Feature Comparison -->
            <div class="mt-5">
                <h3>Features by Plan</h3>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Feature</th>
                                <th class="tier-starter">Starter</th>
                                <th class="tier-professional">Professional</th>
                                <th class="tier-enterprise">Enterprise</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Max Users</td>
                                <td>5</td>
                                <td>20</td>
                                <td>Unlimited</td>
                            </tr>
                            <tr>
                                <td>Branches</td>
                                <td>1</td>
                                <td>10</td>
                                <td>Unlimited</td>
                            </tr>
                            <tr>
                                <td>Basic POS</td>
                                <td>✓</td>
                                <td>✓</td>
                                <td>✓</td>
                            </tr>
                            <tr>
                                <td>Inventory Management</td>
                                <td>✓</td>
                                <td>✓</td>
                                <td>✓</td>
                            </tr>
                            <tr>
                                <td>Customer Management</td>
                                <td>✓</td>
                                <td>✓</td>
                                <td>✓</td>
                            </tr>
                            <tr>
                                <td>Reports</td>
                                <td>-</td>
                                <td>✓</td>
                                <td>✓</td>
                            </tr>
                            <tr>
                                <td>Multi-Branch Support</td>
                                <td>-</td>
                                <td>✓</td>
                                <td>✓</td>
                            </tr>
                            <tr>
                                <td>Advanced Analytics</td>
                                <td>-</td>
                                <td>✓</td>
                                <td>✓</td>
                            </tr>
                            <tr>
                                <td>Custom Reports</td>
                                <td>-</td>
                                <td>-</td>
                                <td>✓</td>
                            </tr>
                            <tr>
                                <td>API Access</td>
                                <td>-</td>
                                <td>-</td>
                                <td>✓</td>
                            </tr>
                            <tr>
                                <td>SSO Integration</td>
                                <td>-</td>
                                <td>-</td>
                                <td>✓</td>
                            </tr>
                            <tr>
                                <td>Priority Support</td>
                                <td>-</td>
                                <td>-</td>
                                <td>✓</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>

</html>