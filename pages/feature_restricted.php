<?php
require_once(__DIR__ . '/../config/config.php');

$currentUser = getCurrentUser();
$featureGate = getLicenseFeatureGate();
$error = $_GET['error'] ?? '';
$feature = $_GET['feature'] ?? 'unknown';

$companyId = $currentUser['company_id'] ?? $_SESSION['company_id'] ?? null;
$license = $companyId ? getLicenseManager()->getCompanyLicense($companyId) : null;

// Get the required tier for this feature
$requiredTier = $featureGate->getFeatureRequirements($feature);

// Get all features by tier for comparison
$tiers = ['starter' => 'Starter (Free)', 'professional' => 'Professional ($99/mo)', 'enterprise' => 'Enterprise (Custom)'];
$tierColors = ['starter' => '#28a745', 'professional' => '#0066cc', 'enterprise' => '#6f42c1'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feature Restricted - POS System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }

        .container {
            max-width: 900px;
        }

        .locked-card {
            background: white;
            border-radius: 12px;
            padding: 50px 40px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            margin-bottom: 40px;
        }

        .lock-icon {
            font-size: 5rem;
            color: #dc3545;
            margin-bottom: 20px;
        }

        .locked-card h1 {
            color: #2c3e50;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .locked-card p {
            color: #666;
            font-size: 1.1rem;
            line-height: 1.8;
        }

        .current-license {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 20px;
            border-radius: 6px;
            margin: 30px 0;
        }

        .current-license h5 {
            color: #2c3e50;
            margin-bottom: 15px;
        }

        .license-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .license-info-item {
            text-align: left;
        }

        .license-info-item strong {
            color: #667eea;
        }

        .tier-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .tier-card {
            background: white;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
        }

        .tier-card:hover {
            transform: translateY(-5px);
        }

        .tier-header {
            padding-bottom: 15px;
            border-bottom: 3px solid;
            margin-bottom: 20px;
        }

        .tier-name {
            font-size: 1.3rem;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .tier-price {
            font-size: 1.5rem;
            font-weight: bold;
        }

        .features-list {
            list-style: none;
            padding: 0;
            margin-bottom: 20px;
        }

        .features-list li {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
            color: #333;
            display: flex;
            align-items: center;
        }

        .features-list li:last-child {
            border-bottom: none;
        }

        .features-list i {
            margin-right: 10px;
            width: 20px;
            font-weight: bold;
        }

        .features-list i.fa-check {
            color: #28a745;
        }

        .features-list i.fa-lock {
            color: #ccc;
        }

        .upgrade-btn {
            width: 100%;
            padding: 12px;
            font-weight: bold;
            border-radius: 6px;
            margin-top: 15px;
        }

        .current-tier {
            border: 3px solid #667eea;
            background: #f0f4ff;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }

        .action-buttons a,
        .action-buttons button {
            padding: 12px 30px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
            transition: 0.3s;
        }

        .action-buttons .btn-back {
            background: #6c757d;
            color: white;
        }

        .action-buttons .btn-back:hover {
            background: #5a6268;
        }

        .action-buttons .btn-upgrade {
            background: #667eea;
            color: white;
        }

        .action-buttons .btn-upgrade:hover {
            background: #556dd4;
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Locked Message -->
        <div class="locked-card">
            <div class="lock-icon">
                <i class="fas fa-lock"></i>
            </div>
            <h1>Feature Restricted</h1>
            <?php if ($error): ?>
                <p><?php echo htmlspecialchars($error); ?></p>
            <?php else: ?>
                <p>This feature requires a higher license tier to access.</p>
            <?php endif; ?>
        </div>

        <!-- Current License Status -->
        <?php if ($license): ?>
            <div class="current-license">
                <h5><i class="fas fa-certificate"></i> Your Current License</h5>
                <div class="license-info">
                    <div class="license-info-item">
                        <strong>Plan:</strong> <?php echo ucfirst(htmlspecialchars($license['tier'])); ?>
                    </div>
                    <div class="license-info-item">
                        <strong>Status:</strong>
                        <span class="badge <?php echo $license['status'] === 'active' ? 'bg-success' : 'bg-warning'; ?>">
                            <?php echo ucfirst(htmlspecialchars($license['status'])); ?>
                        </span>
                    </div>
                    <div class="license-info-item">
                        <strong>Max Users:</strong> <?php echo (int)$license['max_users']; ?>
                    </div>
                    <div class="license-info-item">
                        <strong>Max Branches:</strong> <?php echo (int)$license['max_branches']; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="current-license">
                <h5><i class="fas fa-exclamation-circle"></i> No Active License</h5>
                <p>Your company doesn't have an active license. Please activate a license or purchase a plan to access this feature.</p>
            </div>
        <?php endif; ?>

        <!-- Tier Comparison -->
        <h3 class="text-white mb-4 text-center">Available Plans</h3>
        <div class="tier-grid">
            <!-- Starter -->
            <div class="tier-card <?php echo ($license && $license['tier'] === 'starter') ? 'current-tier' : ''; ?>">
                <div class="tier-header" style="border-bottom-color: #28a745;">
                    <div class="tier-name" style="color: #28a745;">Starter</div>
                    <div class="tier-price">Free</div>
                    <small style="color: #666;">Entry-level POS</small>
                </div>
                <ul class="features-list">
                    <li><i class="fas fa-check"></i> Basic POS</li>
                    <li><i class="fas fa-check"></i> Inventory</li>
                    <li><i class="fas fa-check"></i> Customers</li>
                    <li><i class="fas fa-lock"></i> Reports</li>
                    <li><i class="fas fa-lock"></i> Analytics</li>
                    <li><i class="fas fa-lock"></i> Multi-Branch</li>
                </ul>
                <small style="color: #666;">
                    <i class="fas fa-users"></i> 5 Users |
                    <i class="fas fa-code-branch"></i> 1 Branch
                </small>
                <?php if ($license && $license['tier'] === 'starter'): ?>
                    <button class="upgrade-btn btn btn-success" disabled>Current Plan</button>
                <?php else: ?>
                    <a href="?page=license_manager" class="upgrade-btn btn btn-outline-success">Activate</a>
                <?php endif; ?>
            </div>

            <!-- Professional -->
            <div class="tier-card <?php echo ($license && $license['tier'] === 'professional') ? 'current-tier' : ''; ?>">
                <div class="tier-header" style="border-bottom-color: #0066cc;">
                    <div class="tier-name" style="color: #0066cc;">⭐ Professional</div>
                    <div class="tier-price">$99<small style="font-size: 0.6rem; display: block;">/month</small>
                    </div>
                    <small style="color: #666;">Recommended</small>
                </div>
                <ul class="features-list">
                    <li><i class="fas fa-check"></i> Basic POS</li>
                    <li><i class="fas fa-check"></i> Inventory</li>
                    <li><i class="fas fa-check"></i> Customers</li>
                    <li><i class="fas fa-check"></i> Reports</li>
                    <li><i class="fas fa-check"></i> Analytics</li>
                    <li><i class="fas fa-check"></i> Multi-Branch</li>
                </ul>
                <small style="color: #666;">
                    <i class="fas fa-users"></i> 20 Users |
                    <i class="fas fa-code-branch"></i> 10 Branches
                </small>
                <?php if ($license && $license['tier'] === 'professional'): ?>
                    <button class="upgrade-btn btn btn-primary" disabled>Current Plan</button>
                <?php else: ?>
                    <a href="?page=license_manager" class="upgrade-btn btn btn-primary">Upgrade Now</a>
                <?php endif; ?>
            </div>

            <!-- Enterprise -->
            <div class="tier-card <?php echo ($license && $license['tier'] === 'enterprise') ? 'current-tier' : ''; ?>">
                <div class="tier-header" style="border-bottom-color: #6f42c1;">
                    <div class="tier-name" style="color: #6f42c1;">Enterprise</div>
                    <div class="tier-price">Custom</div>
                    <small style="color: #666;">Unlimited</small>
                </div>
                <ul class="features-list">
                    <li><i class="fas fa-check"></i> All Professional</li>
                    <li><i class="fas fa-check"></i> Custom Reports</li>
                    <li><i class="fas fa-check"></i> API Access</li>
                    <li><i class="fas fa-check"></i> SSO Integration</li>
                    <li><i class="fas fa-check"></i> Priority Support</li>
                    <li><i class="fas fa-check"></i> Everything</li>
                </ul>
                <small style="color: #666;">
                    <i class="fas fa-users"></i> Unlimited |
                    <i class="fas fa-code-branch"></i> Unlimited
                </small>
                <?php if ($license && $license['tier'] === 'enterprise'): ?>
                    <button class="upgrade-btn btn btn-success" disabled>Current Plan</button>
                <?php else: ?>
                    <a href="?page=license_manager" class="upgrade-btn btn btn-success">Contact Sales</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="?page=dashboard" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <a href="?page=license_manager" class="btn-upgrade">
                <i class="fas fa-shopping-cart"></i> View Plans & Upgrade
            </a>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>

</html>