<?php
require_once 'config/config.php';

// Add security headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

$page = $_GET['page'] ?? 'dashboard';
$settings = getSettings();
$currentUser = getCurrentUser();

// Block guest users from accessing restricted areas, but allow guest dashboard and demo features
if ($currentUser['role'] === 'guest' && !in_array($page, ['guest_dashboard', 'pos', 'products', 'categories'])) {
    redirect('index.php?page=guest_dashboard');
}

// License and company status validation for company users (not owners/admins who manage licenses)
// Skip license check for license_waiting page to avoid infinite redirect
if ($page !== 'license_waiting' && $currentUser['role'] !== 'guest' && $currentUser['role'] !== 'owner' && !empty($currentUser['company_id'])) {
    $conn = getDB();
    $companyStmt = $conn->prepare("SELECT status FROM companies WHERE id = ?");
    $companyStmt->bind_param('i', $currentUser['company_id']);
    $companyStmt->execute();
    $companyResult = $companyStmt->get_result();
    $companyData = $companyResult->fetch_assoc();
    $companyStatus = $companyData['status'] ?? 'inactive';

    if ($companyStatus === 'pending') {
        redirect('login.php?error=' . urlencode('Your registration is under review by super admin.'));
    }

    if ($companyStatus !== 'active') {
        redirect('login.php?error=' . urlencode('Your company account is not active. Please contact support.'));
    }

    $licenseManager = new LicenseManager(getDB());
    $license = $licenseManager->getCompanyLicense($currentUser['company_id']);

    if (!$license || $license['status'] !== 'active' || strtotime($license['expires_at']) < time()) {
        // Redirect to license waiting page for unlicensed companies
        redirect('index.php?page=license_waiting');
    }
}

// Role-based access control
// Owner = super admin who manages all companies
// Admin = company admin who manages their company
// Branch Manager = manages specific branch
$roleAccess = [
    'owner' => ['owner_dashboard', 'dashboard', 'pos', 'products', 'categories', 'customers', 'sales', 'invoices', 'expenses', 'reports', 'settings', 'users', 'companies', 'calculator', 'license_manager', 'admin_licenses', 'license_waiting'],
    'admin' => ['admin_dashboard', 'dashboard', 'pos', 'products', 'categories', 'customers', 'sales', 'invoices', 'expenses', 'reports', 'settings', 'users', 'calculator', 'license_manager', 'license_waiting'],
    'manager' => ['manager_dashboard', 'dashboard', 'pos', 'products', 'categories', 'customers', 'sales', 'invoices', 'expenses', 'reports', 'users', 'calculator', 'license_waiting'],
    'branch_manager' => ['manager_dashboard', 'dashboard', 'pos', 'products', 'categories', 'customers', 'sales', 'invoices', 'reports', 'calculator', 'license_waiting'],
    'cashier' => ['cashier_dashboard', 'dashboard', 'pos', 'sales', 'calculator', 'license_waiting'],
    'guest' => ['guest_dashboard', 'pos', 'products', 'categories'] // Guest access to core demo features only
];

$allowedPages = $roleAccess[$currentUser['role']] ?? ['dashboard'];

// Check if page is allowed for user role
if (!in_array($page, $allowedPages)) {
    $page = 'dashboard';
}

// Redirect dashboard to role-specific dashboard
if ($page === 'dashboard') {
    $roleDashboard = [
        'owner' => 'owner_dashboard',
        'admin' => 'admin_dashboard',
        'manager' => 'manager_dashboard',
        'branch_manager' => 'manager_dashboard',
        'cashier' => 'cashier_dashboard'
    ];
    $page = $roleDashboard[$currentUser['role']] ?? 'dashboard';
}

// Page-specific authorization checks
if ($page === 'license_manager' && !in_array($currentUser['role'], ['owner', 'admin', 'guest'])) {
    redirect('index.php?page=dashboard&error=' . urlencode('License management is only available to company admins.'));
}

if ($page === 'license_pricing' && $currentUser['role'] !== 'owner') {
    redirect('index.php?page=dashboard&error=' . urlencode('Only super admin can view license pricing.'));
}

if ($page === 'admin_licenses' && $currentUser['role'] !== 'owner') {
    redirect('login.php');
}

// Require valid license for company admin dashboard
if ($page === 'admin_dashboard' && $currentUser['role'] === 'admin') {
    $licenseManager = new LicenseManager(getDB());
    $companyId = $currentUser['company_id'] ?? 0;
    $license = $licenseManager->getCompanyLicense($companyId);

    if (!$license || $license['status'] !== 'active') {
        redirect('index.php?page=license_manager&error=' . urlencode('Your license has expired or is not activated. Please activate a license to access the dashboard.'));
    }
}

if ($page === 'dashboard' && $currentUser['role'] === 'guest') {
    redirect('index.php?page=guest_dashboard');
}

if ($page === 'reports' && $currentUser['role'] === 'owner') {
    redirect('index.php?page=owner_dashboard&error=' . urlencode('Super admin may not view company reports.'));
}

if ($page === 'sales' && $currentUser['role'] === 'owner') {
    redirect('index.php?page=owner_dashboard&error=' . urlencode('Super admin may not view company sales data.'));
}

if ($page === 'feature_restricted' && !isLoggedIn()) {
    redirect('login.php');
}

// License feature checks
$licenseRequiredPages = ['expenses', 'reports'];
if (in_array($page, $licenseRequiredPages)) {
    requireLicenseFeature($page);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= getAppName() ?> - <?= ucfirst($page) ?></title>
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#2c3e50">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="POS System">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --success: #27ae60;
            --danger: #e74c3c;
            --warning: #f39c12;
            --light: #ecf0f1;
            --dark: #2c3e50;
        }

        body {
            background: #f8f9fa;
        }

        .sidebar {
            min-height: 100vh;
            background: var(--primary);
            color: white;
        }

        .sidebar a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            padding: 12px 20px;
            display: block;
            transition: 0.3s;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border-left: 3px solid var(--secondary);
        }

        .sidebar a i {
            width: 25px;
        }

        .top-bar {
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 15px 20px;
            border-bottom: 1px solid #e9ecef;
        }

        .top-bar .d-flex {
            min-height: 36px;
            align-items: center;
        }

        .top-bar h5 {
            font-weight: 600;
            color: var(--primary);
        }

        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .stat-card {
            border-radius: 10px;
            padding: 20px;
            color: white;
        }

        .stat-card.primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .stat-card.success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }

        .stat-card.warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .stat-card.info {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .table-card {
            background: white;
            border-radius: 10px;
        }

        .btn-primary {
            background: var(--secondary);
            border: none;
        }

        .btn-primary:hover {
            background: #2980b9;
        }

        /* Hamburger Menu for Mobile/Tablet */
        .hamburger-btn {
            display: none;
            background: var(--primary);
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: white;
            padding: 8px 12px;
            line-height: 1;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .hamburger-btn:hover {
            background: var(--secondary);
        }

        @media (max-width: 991px) {
            .hamburger-btn {
                display: block;
            }

            .top-bar {
                padding: 12px 15px;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            }

            .sidebar {
                position: fixed;
                left: -250px;
                top: 0;
                z-index: 1000;
                width: 250px;
                transition: left 0.3s ease;
                min-height: 100vh;
            }

            .sidebar.active {
                left: 0;
            }

            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                z-index: 999;
            }

            .sidebar-overlay.active {
                display: block;
            }
        }
    </style>
</head>

<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar p-0" id="sidebar">
                <div class="text-center pt-2 pb-1">
                    <?php
                    $settings = getSettings();
                    $showLogoInSidebar = $settings['show_logo_in_sidebar'] ?? '0';
                    $companyLogo = $settings['company_logo'] ?? '';

                    if ($showLogoInSidebar === '1' && !empty($companyLogo)) {
                        // Show uploaded logo and connection status
                        echo '<img src="' . htmlspecialchars($companyLogo, ENT_QUOTES) . '" alt="Logo" class="mb-1" style="display:block; margin:0 auto 8px; max-height: 100px; max-width: 240px;">';
                        echo '<div class="mb-2 text-center">
                                <span class="badge bg-success" id="onlineBadge">
                                    <i class="fas fa-wifi"></i> Online
                                </span>
                                <span class="badge bg-warning" id="offlineBadge" style="display: none;">
                                    <i class="fas fa-wifi-slash"></i> Offline
                                </span>
                              </div>';
                    } else {
                        // Show text logo and connection status
                        $sidebarTitle = 'POS';
                        if ($currentUser['role'] === 'owner') {
                            $sidebarTitle = $settings['company_name'] ?? 'Super Admin';
                        }
                    ?>
                        <h4 class="mb-1"><i class="fas fa-cash-register"></i> <?= $sidebarTitle ?></h4>
                        <small class="text-white-50 d-block mb-1"><?= ucfirst($currentUser['role']) === 'owner' ? 'Super Admin' : ucfirst($currentUser['role']) ?></small>
                        <div class="mb-2 text-center">
                            <span class="badge bg-success" id="onlineBadge">
                                <i class="fas fa-wifi"></i> Online
                            </span>
                            <span class="badge bg-warning" id="offlineBadge" style="display: none;">
                                <i class="fas fa-wifi-slash"></i> Offline
                            </span>
                        </div>
                    <?php
                    }
                    ?>
                </div>
                <nav>
                    <?php
                    // Dynamic sidebar based on role
                    $sidebarItems = [];

                    if ($currentUser['role'] === 'owner') {
                        $sidebarItems = [
                            ['page' => 'owner_dashboard', 'icon' => 'fa-home', 'label' => 'Dashboard'],
                            ['page' => 'companies', 'icon' => 'fa-building', 'label' => 'Companies'],
                            ['page' => 'admin_licenses', 'icon' => 'fa-key', 'label' => 'License Management'],
                            ['page' => 'users', 'icon' => 'fa-users', 'label' => 'All Users'],
                            ['page' => 'sales', 'icon' => 'fa-chart-line', 'label' => 'All Sales'],
                            ['page' => 'reports', 'icon' => 'fa-chart-bar', 'label' => 'Reports'],
                            ['page' => 'calculator', 'icon' => 'fa-calculator', 'label' => 'Calculator'],
                            ['page' => 'settings', 'icon' => 'fa-cog', 'label' => 'Settings'],
                            ['page' => 'profile', 'icon' => 'fa-user-circle', 'label' => 'My Profile'],
                        ];
                    } elseif ($currentUser['role'] === 'admin') {
                        $sidebarItems = [
                            ['page' => 'admin_dashboard', 'icon' => 'fa-home', 'label' => 'Dashboard'],
                            ['page' => 'pos', 'icon' => 'fa-shopping-cart', 'label' => 'Point of Sale'],
                            ['page' => 'license_manager', 'icon' => 'fa-key', 'label' => 'License Manager'],
                            ['page' => 'products', 'icon' => 'fa-box', 'label' => 'Products'],
                            ['page' => 'categories', 'icon' => 'fa-tags', 'label' => 'Categories'],
                            ['page' => 'customers', 'icon' => 'fa-users', 'label' => 'Customers'],
                            ['page' => 'sales', 'icon' => 'fa-chart-line', 'label' => 'Sales'],
                            ['page' => 'invoices', 'icon' => 'fa-file-invoice', 'label' => 'Invoices'],
                            ['page' => 'expenses', 'icon' => 'fa-wallet', 'label' => 'Expenses'],
                            ['page' => 'reports', 'icon' => 'fa-chart-bar', 'label' => 'Reports'],
                            ['page' => 'calculator', 'icon' => 'fa-calculator', 'label' => 'Calculator'],
                            ['page' => 'settings', 'icon' => 'fa-cog', 'label' => 'Settings'],
                            ['page' => 'users', 'icon' => 'fa-users', 'label' => 'Users'],
                            ['page' => 'profile', 'icon' => 'fa-user-circle', 'label' => 'My Profile'],
                        ];
                    } elseif ($currentUser['role'] === 'manager') {
                        $sidebarItems = [
                            ['page' => 'manager_dashboard', 'icon' => 'fa-home', 'label' => 'Dashboard'],
                            ['page' => 'pos', 'icon' => 'fa-shopping-cart', 'label' => 'Point of Sale'],
                            ['page' => 'products', 'icon' => 'fa-box', 'label' => 'Products'],
                            ['page' => 'categories', 'icon' => 'fa-tags', 'label' => 'Categories'],
                            ['page' => 'customers', 'icon' => 'fa-users', 'label' => 'Customers'],
                            ['page' => 'sales', 'icon' => 'fa-chart-line', 'label' => 'Sales'],
                            ['page' => 'invoices', 'icon' => 'fa-file-invoice', 'label' => 'Invoices'],
                            ['page' => 'reports', 'icon' => 'fa-chart-bar', 'label' => 'Reports'],
                            ['page' => 'calculator', 'icon' => 'fa-calculator', 'label' => 'Calculator'],
                            ['page' => 'users', 'icon' => 'fa-users', 'label' => 'Users'],
                            ['page' => 'profile', 'icon' => 'fa-user-circle', 'label' => 'My Profile'],
                        ];
                    } elseif ($currentUser['role'] === 'branch_manager') {
                        $sidebarItems = [
                            ['page' => 'manager_dashboard', 'icon' => 'fa-home', 'label' => 'Dashboard'],
                            ['page' => 'pos', 'icon' => 'fa-shopping-cart', 'label' => 'Point of Sale'],
                            ['page' => 'products', 'icon' => 'fa-box', 'label' => 'Products'],
                            ['page' => 'categories', 'icon' => 'fa-tags', 'label' => 'Categories'],
                            ['page' => 'customers', 'icon' => 'fa-users', 'label' => 'Customers'],
                            ['page' => 'sales', 'icon' => 'fa-chart-line', 'label' => 'Sales'],
                            ['page' => 'invoices', 'icon' => 'fa-file-invoice', 'label' => 'Invoices'],
                            ['page' => 'reports', 'icon' => 'fa-chart-bar', 'label' => 'Reports'],
                            ['page' => 'calculator', 'icon' => 'fa-calculator', 'label' => 'Calculator'],
                            ['page' => 'profile', 'icon' => 'fa-user-circle', 'label' => 'My Profile'],
                        ];
                    } elseif ($currentUser['role'] === 'cashier') {
                        $sidebarItems = [
                            ['page' => 'cashier_dashboard', 'icon' => 'fa-home', 'label' => 'Dashboard'],
                            ['page' => 'pos', 'icon' => 'fa-shopping-cart', 'label' => 'New Sale'],
                            ['page' => 'sales', 'icon' => 'fa-chart-line', 'label' => 'My Sales'],
                            ['page' => 'calculator', 'icon' => 'fa-calculator', 'label' => 'Calculator'],
                            ['page' => 'profile', 'icon' => 'fa-user-circle', 'label' => 'My Profile'],
                        ];
                    } elseif ($currentUser['role'] === 'guest') {
                        $sidebarItems = [
                            ['page' => 'guest_dashboard', 'icon' => 'fa-home', 'label' => 'Dashboard'],
                            ['page' => 'pos', 'icon' => 'fa-shopping-cart', 'label' => 'Point of Sale'],
                            ['page' => 'products', 'icon' => 'fa-box', 'label' => 'Products'],
                            ['page' => 'categories', 'icon' => 'fa-tags', 'label' => 'Categories']
                        ];
                    }

                    foreach ($sidebarItems as $item): ?>
                        <a href="?page=<?= $item['page'] ?>" class="<?= $page == $item['page'] ? 'active' : '' ?>">
                            <i class="fas <?= $item['icon'] ?>"></i> <?= $item['label'] ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 p-0">
                <!-- Top Bar -->
                <div class="top-bar px-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <button class="hamburger-btn me-3" id="hamburgerBtn">
                                <i class="fas fa-bars"></i>
                            </button>
                            <h5 class="mb-0">
                                <?php
                                $pageTitles = [
                                    'owner_dashboard' => 'Owner Dashboard',
                                    'admin_dashboard' => 'Admin Dashboard',
                                    'manager_dashboard' => 'Manager Dashboard',
                                    'cashier_dashboard' => 'Cashier Dashboard',
                                    'companies' => 'Companies',
                                    'admin_licenses' => 'License Management',
                                    'users' => 'User Management',
                                    'sales' => 'Sales',
                                    'pos' => 'Point of Sale',
                                    'products' => 'Products',
                                    'categories' => 'Categories',
                                    'customers' => 'Customers',
                                    'invoices' => 'Invoices',
                                    'expenses' => 'Expenses',
                                    'reports' => 'Reports',
                                    'settings' => 'Settings',
                                    'profile' => 'My Profile',
                                    'calculator' => 'Calculator'
                                ];
                                echo $pageTitles[$page] ?? ucfirst($page);
                                ?>
                            </h5>
                        </div>
                        <div class="d-flex align-items-center">
                            <?php
                            // Get low stock count for notifications
                            $lowStockCount = 0;
                            if ($currentUser['role'] !== 'owner' && $currentUser['role'] !== 'cashier') {
                                $companyId = $currentUser['company_id'] ?? 0;
                                if ($companyId > 0) {
                                    $conn = getDB();
                                    $lowStockResult = $conn->query("SELECT COUNT(*) as count FROM products WHERE company_id = $companyId AND quantity <= min_quantity AND status = 'active'");
                                    $lowStockCount = $lowStockResult->fetch_assoc()['count'] ?? 0;
                                }
                            }
                            ?>
                            <?php if ($lowStockCount > 0): ?>
                                <a href="?page=products&filter=low_stock" class="btn btn-sm btn-warning me-2" title="Low Stock Alert">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <span class="badge bg-danger"><?= $lowStockCount ?></span>
                                </a>
                            <?php endif; ?>
                            <span class="me-3">
                                <i class="fas fa-user"></i>
                                <?= $currentUser['name'] ?>
                                <span class="badge bg-<?= $currentUser['role'] == 'owner' ? 'dark' : ($currentUser['role'] == 'admin' ? 'danger' : ($currentUser['role'] == 'manager' ? 'warning' : 'info')) ?>">
                                    <?= ucfirst($currentUser['role']) ?>
                                </span>
                            </span>
                            <a href="logout.php" class="btn btn-sm btn-outline-danger">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Page Content -->
                <div class="p-4">
                    <?php
                    $pageFile = 'pages/' . $page . '.php';
                    if (file_exists($pageFile)) {
                        include $pageFile;
                    } else {
                        include 'pages/dashboard.php';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <footer class="app-footer bg-light text-center py-2 border-top mt-4">
        <small class="text-muted">
            Support: <a href="mailto:<?= escape($settings['company_email'] ?? 'info@vendrixpos.com') ?>"><?= escape($settings['company_email'] ?? 'info@vendrixpos.com') ?></a>
            | <a href="tel:<?= escape($settings['company_phone'] ?? '08080500766') ?>"><?= escape($settings['company_phone'] ?? '08080500766') ?></a>
            <?php if (!empty($settings['company_address'])): ?>
                | <?= escape($settings['company_address']) ?>
            <?php endif; ?>
        </small>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Hamburger menu toggle
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        hamburgerBtn.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            sidebarOverlay.classList.toggle('active');
        });

        sidebarOverlay.addEventListener('click', function() {
            sidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
        });

        // Global modal function for showing messages
        function showAppModal(message, title = 'Message', type = 'info') {
            // Remove any existing modal
            const existingModal = document.getElementById('appModal');
            if (existingModal) {
                existingModal.remove();
            }

            // Create modal HTML
            const modalHtml = `
                <div class="modal fade" id="appModal" tabindex="-1" aria-labelledby="appModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="appModalLabel">${title}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                ${message}
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Add modal to body
            document.body.insertAdjacentHTML('beforeend', modalHtml);

            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('appModal'));
            modal.show();
        }

        function updateConnectionStatus() {
            const onlineBadge = document.getElementById('onlineBadge');
            const offlineBadge = document.getElementById('offlineBadge');
            if (!onlineBadge || !offlineBadge) return;

            if (navigator.onLine) {
                onlineBadge.style.display = 'inline-block';
                offlineBadge.style.display = 'none';
            } else {
                onlineBadge.style.display = 'none';
                offlineBadge.style.display = 'inline-block';
            }
        }

        window.addEventListener('online', updateConnectionStatus);
        window.addEventListener('offline', updateConnectionStatus);
        updateConnectionStatus();
    </script>
</body>

</html>