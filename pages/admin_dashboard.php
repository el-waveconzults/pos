<?php
require_once 'config/config.php';
$conn = getDB();

$currentUser = getCurrentUser();
$companyId = $currentUser['company_id'] ?? 0;
$company = getCompany($companyId);
$currentBranch = getCurrentBranch();

// Get all branches for this company
$branches = getBranches($companyId);

// Get selected branch from URL
$selectedBranch = $_GET['branch'] ?? 0;
$branchFilter = $selectedBranch > 0 ? "AND u.branch_id = $selectedBranch" : "";

// Company admin stats
$today = date('Y-m-d');
$selectedBranch = intval($_GET['branch'] ?? 0);

$stmt = $conn->prepare("SELECT COALESCE(SUM(s.total_amount), 0) as total FROM sales s JOIN users u ON s.created_by = u.id WHERE DATE(s.created_at) = ? AND s.status = 'completed' AND u.company_id = ? " . ($selectedBranch > 0 ? "AND u.branch_id = ?" : ""));
$selectedBranch > 0 ? $stmt->bind_param("sii", $today, $companyId, $selectedBranch) : $stmt->bind_param("si", $today, $companyId);
$stmt->execute();
$todaySales = $stmt->get_result()->fetch_assoc()['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM sales s JOIN users u ON s.created_by = u.id WHERE DATE(s.created_at) = ? AND s.status = 'completed' AND u.company_id = ? " . ($selectedBranch > 0 ? "AND u.branch_id = ?" : ""));
$selectedBranch > 0 ? $stmt->bind_param("sii", $today, $companyId, $selectedBranch) : $stmt->bind_param("si", $today, $companyId);
$stmt->execute();
$todayOrders = $stmt->get_result()->fetch_assoc()['count'];

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE company_id = ? AND status = 'active'");
$stmt->bind_param("i", $companyId);
$stmt->execute();
$totalProducts = $stmt->get_result()->fetch_assoc()['count'];

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM customers WHERE company_id = ? AND status = 'active'");
$stmt->bind_param("i", $companyId);
$stmt->execute();
$totalCustomers = $stmt->get_result()->fetch_assoc()['count'];

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE company_id = ? AND quantity <= min_quantity AND status = 'active'");
$stmt->bind_param("i", $companyId);
$stmt->execute();
$lowStock = $stmt->get_result()->fetch_assoc()['count'];

// Get branch stats
$branchStats = [];
if ($branches) {
    $branches->data_seek(0);
    while ($b = $branches->fetch_assoc()) {
        $bId = $b['id'];
        $stmt = $conn->prepare("SELECT COALESCE(SUM(s.total_amount), 0) as total, COUNT(*) as orders FROM sales s JOIN users u ON s.created_by = u.id WHERE DATE(s.created_at) = ? AND s.status = 'completed' AND u.company_id = ? AND u.branch_id = ?");
        $stmt->bind_param("sii", $today, $companyId, $bId);
        $stmt->execute();
        $todayBranchSales = $stmt->get_result()->fetch_assoc();

        // Get users in this branch
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE company_id = ? AND branch_id = ? AND status = 'active'");
        $stmt->bind_param("ii", $companyId, $bId);
        $stmt->execute();
        $branchUsers = $stmt->get_result()->fetch_assoc()['count'];

        $branchStats[$bId] = [
            'name' => $b['name'],
            'today_sales' => $todayBranchSales['total'],
            'today_orders' => $todayBranchSales['orders'],
            'users' => $branchUsers
        ];
    }
}

// Company users (admin sees only manager and cashier, not other admins or owner)
$stmt = $conn->prepare("SELECT * FROM users WHERE company_id = ? AND role IN ('manager', 'cashier') AND status = 'active' ORDER BY name");
$stmt->bind_param("i", $companyId);
$stmt->execute();
$companyUsers = $stmt->get_result();

// Recent sales for this company
$stmt = $conn->prepare("
    SELECT s.*, COALESCE(cust.name, 'Walk-in') as customer_name, COALESCE(b.name, 'No Branch') as branch_name
    FROM sales s 
    JOIN users u ON s.created_by = u.id
    LEFT JOIN customers cust ON s.customer_id = cust.id
    LEFT JOIN branches b ON u.branch_id = b.id
    WHERE s.status = 'completed' AND u.company_id = ?
    ORDER BY s.created_at DESC LIMIT 10
");
$stmt->bind_param("i", $companyId);
$stmt->execute();
$recentSales = $stmt->get_result();

// Monthly sales for company
$monthlySales = [];
for ($i = 11; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $stmt = $conn->prepare("SELECT COALESCE(SUM(s.total_amount), 0) as total FROM sales s JOIN users u ON s.created_by = u.id WHERE DATE_FORMAT(s.created_at, '%Y-%m') = ? AND s.status = 'completed' AND u.company_id = ?");
    $stmt->bind_param("si", $month, $companyId);
    $stmt->execute();
    $monthlySales[] = $stmt->get_result()->fetch_assoc()['total'];
}

// Daily sales for last 7 days
$dailySales = [];
$dailyLabels = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $label = date('M d', strtotime("-$i days"));
    $stmt = $conn->prepare("SELECT COALESCE(SUM(s.total_amount), 0) as total, COUNT(*) as orders FROM sales s JOIN users u ON s.created_by = u.id WHERE DATE(s.created_at) = ? AND s.status = 'completed' AND u.company_id = ? " . ($selectedBranch > 0 ? "AND u.branch_id = ?" : ""));
    if ($selectedBranch > 0) {
        $stmt->bind_param("sii", $date, $companyId, $selectedBranch);
    } else {
        $stmt->bind_param("si", $date, $companyId);
    }
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    $dailySales[] = $data['total'];
    $dailyLabels[] = $label;
}

// Weekly sales for last 4 weeks
$weeklySales = [];
$weeklyLabels = [];
for ($i = 3; $i >= 0; $i--) {
    $startDate = date('Y-m-d', strtotime("-$i weeks"));
    $endDate = date('Y-m-d', strtotime("-$i weeks +6 days"));
    $label = 'Week ' . date('W', strtotime($startDate));
    $stmt = $conn->prepare("SELECT COALESCE(SUM(s.total_amount), 0) as total, COUNT(*) as orders FROM sales s JOIN users u ON s.created_by = u.id WHERE DATE(s.created_at) BETWEEN ? AND ? AND s.status = 'completed' AND u.company_id = ? " . ($selectedBranch > 0 ? "AND u.branch_id = ?" : ""));
    if ($selectedBranch > 0) {
        $stmt->bind_param("ssii", $startDate, $endDate, $companyId, $selectedBranch);
    } else {
        $stmt->bind_param("ssi", $startDate, $endDate, $companyId);
    }
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    $weeklySales[] = $data['total'];
    $weeklyLabels[] = $label;
}
?>

<!-- COMPANY ADMIN DASHBOARD -->
<div class="admin-dashboard">
    <!-- Header -->
    <div class="dashboard-header mb-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-1">
                    <i class="fas fa-building text-primary"></i> <?= $company['name'] ?? 'Company' ?>
                    <?php if ($currentBranch): ?>
                        <span class="text-muted">/</span> <span class="text-success"><?= $currentBranch['name'] ?></span>
                    <?php endif; ?>
                </h4>
                <p class="text-muted mb-0">Admin Dashboard</p>
            </div>
            <div class="text-end">
                <?php if ($branches && $branches->num_rows > 0): ?>
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="fas fa-sitemap"></i>
                            <?= $currentBranch ? $currentBranch['name'] : 'All Branches' ?>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="?branch=0">All Branches</a></li>
                            <?php while ($b = $branches->fetch_assoc()): ?>
                                <li><a class="dropdown-item" href="?branch=<?= $b['id'] ?>"><?= $b['name'] ?></a></li>
                            <?php endwhile; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <small class="text-muted d-block"><?= date('l, F j, Y') ?></small>
            </div>
        </div>
    </div>
</div>
</div>
</div>

<!-- Stats Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="stat-card stat-card-primary">
            <div class="stat-icon"><i class="fas fa-cash-register"></i></div>
            <div class="stat-content">
                <h6>TODAY'S SALES</h6>
                <h2><?= formatCurrency($todaySales) ?></h2>
                <small><?= $todayOrders ?> orders</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-card-success">
            <div class="stat-icon"><i class="fas fa-box"></i></div>
            <div class="stat-content">
                <h6>PRODUCTS</h6>
                <h2><?= $totalProducts ?></h2>
                <small>Active items</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-card-warning">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-content">
                <h6>CUSTOMERS</h6>
                <h2><?= $totalCustomers ?></h2>
                <small>Registered</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-card-danger">
            <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="stat-content">
                <h6>LOW STOCK</h6>
                <h2><?= $lowStock ?></h2>
                <small>Need restock</small>
            </div>
        </div>
    </div>
</div>

<!-- Sales Overviews -->
<div class="row mb-4">
    <!-- Daily Overview -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0">
                <h6 class="mb-0"><i class="fas fa-calendar-day text-primary"></i> Daily Sales (Last 7 Days)</h6>
            </div>
            <div class="card-body">
                <canvas id="dailyChart" width="100%" height="200"></canvas>
                <div class="mt-3">
                    <small class="text-muted">
                        <strong>Today:</strong> <?= formatCurrency($todaySales) ?> (<?= $todayOrders ?> orders)
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Weekly Overview -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0">
                <h6 class="mb-0"><i class="fas fa-calendar-week text-success"></i> Weekly Sales (Last 4 Weeks)</h6>
            </div>
            <div class="card-body">
                <canvas id="weeklyChart" width="100%" height="200"></canvas>
                <div class="mt-3">
                    <?php
                    $thisWeekStart = date('Y-m-d', strtotime('monday this week'));
                    $thisWeekEnd = date('Y-m-d', strtotime('sunday this week'));
                    $stmt = $conn->prepare("SELECT COALESCE(SUM(s.total_amount), 0) as total, COUNT(*) as orders FROM sales s JOIN users u ON s.created_by = u.id WHERE DATE(s.created_at) BETWEEN ? AND ? AND s.status = 'completed' AND u.company_id = ?");
                    $stmt->bind_param("ssi", $thisWeekStart, $thisWeekEnd, $companyId);
                    $stmt->execute();
                    $thisWeekSales = $stmt->get_result()->fetch_assoc();
                    ?>
                    <small class="text-muted">
                        <strong>This Week:</strong> <?= formatCurrency($thisWeekSales['total']) ?> (<?= $thisWeekSales['orders'] ?> orders)
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Monthly Overview -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0">
                <h6 class="mb-0"><i class="fas fa-calendar-alt text-info"></i> Monthly Sales (Last 12 Months)</h6>
            </div>
            <div class="card-body">
                <canvas id="monthlyChart" width="100%" height="200"></canvas>
                <div class="mt-3">
                    <?php
                    $thisMonth = date('Y-m');
                    $stmt = $conn->prepare("SELECT COALESCE(SUM(s.total_amount), 0) as total, COUNT(*) as orders FROM sales s JOIN users u ON s.created_by = u.id WHERE DATE_FORMAT(s.created_at, '%Y-%m') = ? AND s.status = 'completed' AND u.company_id = ?");
                    $stmt->bind_param("si", $thisMonth, $companyId);
                    $stmt->execute();
                    $thisMonthSales = $stmt->get_result()->fetch_assoc();
                    ?>
                    <small class="text-muted">
                        <strong>This Month:</strong> <?= formatCurrency($thisMonthSales['total']) ?> (<?= $thisMonthSales['orders'] ?> orders)
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Branch Overview -->
<?php if (!empty($branchStats)): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0">
                    <h5 class="mb-0"><i class="fas fa-sitemap text-primary"></i> Branch Overview - Today's Performance</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Branch</th>
                                    <th>Today's Sales</th>
                                    <th>Orders</th>
                                    <th>Staff</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($branchStats as $id => $stat): ?>
                                    <tr>
                                        <td><strong><?= $stat['name'] ?></strong></td>
                                        <td><?= formatCurrency($stat['today_sales']) ?></td>
                                        <td><?= $stat['today_orders'] ?></td>
                                        <td><?= $stat['users'] ?> staff</td>
                                        <td>
                                            <?php if ($stat['today_orders'] > 0): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">No Sales</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Recent Sales -->
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-shopping-cart text-success"></i> Recent Sales</h5>
                <a href="?page=sales" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Invoice</th>
                                <th>Branch</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Payment</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($sale = $recentSales->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $sale['invoice_no'] ?></td>
                                    <td><span class="badge bg-secondary"><?= $sale['branch_name'] ?></span></td>
                                    <td><?= $sale['customer_name'] ?></td>
                                    <td><?= formatCurrency($sale['total_amount']) ?></td>
                                    <td><span class="badge bg-<?= $sale['payment_method'] == 'cash' ? 'success' : 'info' ?>"><?= ucfirst($sale['payment_method']) ?></span></td>
                                    <td><?= date('M d, H:i', strtotime($sale['created_at'])) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Company Users -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-users text-info"></i> Team Members</h5>
                <a href="?page=users" class="btn btn-sm btn-outline-primary"><i class="fas fa-plus"></i></a>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php while ($user = $companyUsers->fetch_assoc()): ?>
                        <div class="list-group-item d-flex align-items-center">
                            <div class="avatar-circle bg-<?= $user['role'] == 'admin' ? 'danger' : ($user['role'] == 'manager' ? 'warning' : 'info') ?>">
                                <?= strtoupper(substr($user['name'], 0, 1)) ?>
                            </div>
                            <div class="ms-3">
                                <h6 class="mb-0"><?= $user['name'] ?></h6>
                                <small class="text-muted"><?= ucfirst($user['role']) ?></small>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Sales Chart -->
<div class="row mt-4">
    <div class="col-md-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0"><i class="fas fa-chart-line text-primary"></i> Monthly Sales Trend</h5>
            </div>
            <div class="card-body">
                <canvas id="adminChart" height="80"></canvas>
            </div>
        </div>
    </div>
</div>
</div>

<style>
    .stat-card {
        background: white;
        border-radius: 15px;
        padding: 20px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .stat-card-primary {
        border-left: 4px solid #3498db;
    }

    .stat-card-success {
        border-left: 4px solid #27ae60;
    }

    .stat-card-warning {
        border-left: 4px solid #f39c12;
    }

    .stat-card-danger {
        border-left: 4px solid #e74c3c;
    }

    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
    }

    .stat-card-primary .stat-icon {
        background: rgba(52, 152, 219, 0.1);
        color: #3498db;
    }

    .stat-card-success .stat-icon {
        background: rgba(39, 174, 96, 0.1);
        color: #27ae60;
    }

    .stat-card-warning .stat-icon {
        background: rgba(243, 156, 18, 0.1);
        color: #f39c12;
    }

    .stat-card-danger .stat-icon {
        background: rgba(231, 76, 60, 0.1);
        color: #e74c3c;
    }

    .stat-content h6 {
        color: #6c757d;
        font-size: 12px;
        margin-bottom: 5px;
    }

    .stat-content h2 {
        font-size: 28px;
        font-weight: 700;
        margin-bottom: 0;
    }

    .avatar-circle {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
        font-size: 14px;
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    new Chart(document.getElementById('adminChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: ['<?= implode("','", array_map(function ($i) {
                            return date('M', strtotime("-$i months"));
                        }, range(11, 0))) ?>'],
            datasets: [{
                label: 'Sales',
                data: <?= json_encode($monthlySales) ?>,
                borderColor: '#3498db',
                backgroundColor: 'rgba(52, 152, 219, 0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: v => '₦' + v.toLocaleString()
                    }
                }
            }
        }
    });
</script>

<!-- Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Daily Chart -->
<script>
    const dailyCtx = document.getElementById('dailyChart').getContext('2d');
    new Chart(dailyCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode($dailyLabels) ?>,
            datasets: [{
                label: 'Daily Sales',
                data: <?= json_encode($dailySales) ?>,
                borderColor: '#3498db',
                backgroundColor: 'rgba(52, 152, 219, 0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: v => '₦' + v.toLocaleString()
                    }
                }
            }
        }
    });
</script>

<!-- Weekly Chart -->
<script>
    const weeklyCtx = document.getElementById('weeklyChart').getContext('2d');
    new Chart(weeklyCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($weeklyLabels) ?>,
            datasets: [{
                label: 'Weekly Sales',
                data: <?= json_encode($weeklySales) ?>,
                backgroundColor: 'rgba(39, 174, 96, 0.7)',
                borderColor: '#27ae60',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: v => '₦' + v.toLocaleString()
                    }
                }
            }
        }
    });
</script>

<!-- Monthly Chart -->
<script>
    const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
    new Chart(monthlyCtx, {
        type: 'line',
        data: {
            labels: ['<?= implode("','", array_map(function ($i) {
                            return date('M', strtotime("-$i months"));
                        }, range(11, 0))) ?>'],
            datasets: [{
                label: 'Sales',
                data: <?= json_encode($monthlySales) ?>,
                borderColor: '#3498db',
                backgroundColor: 'rgba(52, 152, 219, 0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: v => '₦' + v.toLocaleString()
                    }
                }
            }
        }
    });
</script>