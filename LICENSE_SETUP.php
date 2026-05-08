<?php

/**
 * License Manager Setup Guide
 * Follow these steps to enable license management in your POS system
 */
?>
<!DOCTYPE html>
<html>

<head>
    <title>License Manager - Setup Guide</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }

        .step {
            background: #f5f5f5;
            padding: 20px;
            margin: 20px 0;
            border-left: 4px solid #2196F3;
        }

        .step h3 {
            margin-top: 0;
        }

        code {
            background: #f0f0f0;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }

        .command {
            background: #000;
            color: #0f0;
            padding: 10px;
            border-radius: 5px;
            font-family: monospace;
            margin: 10px 0;
            overflow-x: auto;
        }

        .success {
            color: #4caf50;
            font-weight: bold;
        }

        .warning {
            color: #ff9800;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <h1>🔐 License Manager Setup</h1>

    <div class="step">
        <h3>Step 1: Verify Installation</h3>
        <p>These files should exist in your POS directory:</p>
        <ul>
            <li><code>config/LicenseManager.php</code></li>
            <li><code>config/LicenseMiddleware.php</code></li>
            <li><code>pages/license_manager.php</code></li>
            <li><code>pages/admin_licenses.php</code></li>
            <li><code>migrations/create_licenses_table.php</code></li>
        </ul>
        <p>Check that these files exist before proceeding.</p>
    </div>

    <div class="step">
        <h3>Step 2: Create Database Tables</h3>
        <p>Run the migration to create the required tables:</p>
        <div class="command">
            Visit: http://localhost/pos/migrations/create_licenses_table.php
        </div>
        <p>You should see a success message. If there's an error, check your database connection.</p>
    </div>

    <div class="step">
        <h3>Step 3: Verify Integration</h3>
        <p>The license manager should already be integrated into <code>config/config.php</code>. Check that these lines exist:</p>
        <div class="command">
            require_once(__DIR__ . '/LicenseManager.php');
            require_once(__DIR__ . '/LicenseMiddleware.php');
        </div>
    </div>

    <div class="step">
        <h3>Step 4: Access Admin Dashboard</h3>
        <p>Log in as Super Admin and visit:</p>
        <div class="command">
            http://localhost/pos/pages/admin_licenses.php
        </div>
        <p>You should see license management interface with statistics.</p>
    </div>

    <div class="step">
        <h3>Step 5: Generate Your First License</h3>
        <ol>
            <li>Go to Admin Licenses page</li>
            <li>Select a tier (Starter, Professional, or Enterprise)</li>
            <li>Optionally assign to a company</li>
            <li>Click "Generate License"</li>
            <li>Copy the license key displayed</li>
        </ol>
        <p><span class="success">✓</span> You now have a valid license key.</p>
    </div>

    <div class="step">
        <h3>Step 6: Activate License</h3>
        <p>Users can activate their license at:</p>
        <div class="command">
            http://localhost/pos/pages/license_manager.php
        </div>
        <p>Or as Super Admin, assign it directly using the admin panel.</p>
    </div>

    <div class="step">
        <h3>Step 7: Enforce License Restrictions (Optional)</h3>
        <p>To enforce user/branch limits, add this to pages where users/branches are created:</p>
        <div class="command">
            &lt;?php
            $middleware = getLicenseMiddleware();
            $check = $middleware->checkUserLimit($_SESSION['company_id']);
            if (!$check['allowed']) {
            jsonResponse(['error' => $check['message']], 400);
            }
            ?&gt;
        </div>
    </div>

    <div class="step">
        <h3>Step 8: Control Features by License (Optional)</h3>
        <p>To hide features from lower-tier licenses:</p>
        <div class="command">
            &lt;?php
            $manager = getLicenseManager();
            if ($manager->hasFeature($companyId, 'advanced_analytics')) {
            // Show analytics features
            }
            ?&gt;
        </div>
    </div>

    <h2>Common Tasks</h2>

    <div class="step">
        <h3>Generate Multiple Licenses</h3>
        <div class="command">
            POST /pages/admin_licenses.php
            Data: tier=professional, company_id=1, expires_at=2025-12-31
        </div>
    </div>

    <div class="step">
        <h3>Suspend a License</h3>
        <div class="command">
            POST /pages/admin_licenses.php
            Data: license_key=POS-XXXX-XXXX-XXXX-XXXX, action=suspend
        </div>
    </div>

    <div class="step">
        <h3>Extend License Expiration</h3>
        <div class="command">
            POST /pages/admin_licenses.php
            Data: license_key=POS-XXXX-XXXX-XXXX-XXXX, days=365
        </div>
    </div>

    <h2>API Usage</h2>

    <div class="step">
        <h3>In Your PHP Pages</h3>
        <div class="command">
            &lt;?php
            // Get license manager
            $manager = getLicenseManager();

            // Generate license
            $result = $manager->createLicense([
            'tier' => 'professional',
            'company_id' => 1
            ]);

            // Validate
            $validation = $manager->validateLicense('POS-XXXX-XXXX-XXXX-XXXX');

            // Check features
            $hasReports = $manager->hasFeature($companyId, 'reports');

            // Get middleware
            $middleware = getLicenseMiddleware();

            // Check limits
            $userCheck = $middleware->checkUserLimit($companyId);
            $branchCheck = $middleware->checkBranchLimit($companyId);
            ?&gt;
        </div>
    </div>

    <div class="step">
        <h3><span class="warning">⚠</span> Troubleshooting</h3>
        <ul>
            <li><strong>Tables not created:</strong> Visit <code>migrations/create_licenses_table.php</code> directly</li>
            <li><strong>Functions not found:</strong> Ensure <code>config/config.php</code> has license imports</li>
            <li><strong>License validation fails:</strong> Check database connection and that license exists</li>
            <li><strong>Permission denied on admin page:</strong> Must be logged in as Super Admin</li>
        </ul>
    </div>

    <h2>Next Steps</h2>
    <ul>
        <li>Read <a href="LICENSE_MANAGER_README.md">LICENSE_MANAGER_README.md</a> for full documentation</li>
        <li>Customize license tiers in <code>config/LicenseManager.php</code></li>
        <li>Add license checks to restricted features</li>
        <li>Set up payment gateway integration for license sales</li>
    </ul>
</body>

</html>