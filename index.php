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

// Block guest users
if ($currentUser['role'] === 'guest') {
    redirect('login.php');
}

// Role-based access control
// Owner = super admin who manages all companies
// Admin = company admin who manages their company
// Branch Manager = manages specific branch
$roleAccess = [
    'owner' => ['owner_dashboard', 'dashboard', 'pos', 'products', 'categories', 'customers', 'sales', 'invoices', 'expenses', 'reports', 'settings', 'users', 'companies', 'calculator'],
    'admin' => ['admin_dashboard', 'dashboard', 'pos', 'products', 'categories', 'customers', 'sales', 'invoices', 'expenses', 'reports', 'settings', 'users', 'calculator'],
    'manager' => ['manager_dashboard', 'dashboard', 'pos', 'products', 'categories', 'customers', 'sales', 'invoices', 'expenses', 'reports', 'users', 'calculator'],
    'branch_manager' => ['manager_dashboard', 'dashboard', 'pos', 'products', 'categories', 'customers', 'sales', 'invoices', 'reports', 'calculator'],
    'cashier' => ['cashier_dashboard', 'dashboard', 'pos', 'sales', 'calculator'],
    'guest' => [] // No access for guests
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
                <div class="text-center py-4">
                    <?php
                    // For super admin (owner), show their company name from settings
                    $sidebarTitle = 'POS';
                    if ($currentUser['role'] === 'owner') {
                        $ownerSettings = getSettings();
                        $sidebarTitle = $ownerSettings['company_name'] ?? 'Super Admin';
                    }
                    ?>
                    <h4><i class="fas fa-cash-register"></i> <?= $sidebarTitle ?></h4>
                    <small class="text-white-50"><?= ucfirst($currentUser['role']) === 'owner' ? 'Super Admin' : ucfirst($currentUser['role']) ?></small>
                </div>
                <nav>
                    <?php
                    // Dynamic sidebar based on role
                    $sidebarItems = [];

                    if ($currentUser['role'] === 'owner') {
                        $sidebarItems = [
                            ['page' => 'owner_dashboard', 'icon' => 'fa-home', 'label' => 'Dashboard'],
                            ['page' => 'companies', 'icon' => 'fa-building', 'label' => 'Companies'],
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
                            <div>
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
        </script>
</body>

</html>