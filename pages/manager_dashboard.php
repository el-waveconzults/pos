<?php
require_once 'config/config.php';
$conn = getDB();

$currentUser = getCurrentUser();
$companyId = $currentUser['company_id'] ?? 0;
$branchId = $currentUser['branch_id'] ?? 0;
$company = getCompany($companyId);
$currentBranch = getCurrentBranch();

// Get all branches for this company
$branches = getBranches($companyId);

// Manager stats - filter by branch if assigned
$today = date('Y-m-d');
$companyIdFilter = $companyId > 0 ? "AND u.company_id = $companyId" : "";
$branchFilter = $branchId > 0 ? "AND u.branch_id = $branchId" : "";
$todaySales = $conn->query("SELECT COALESCE(SUM(s.total_amount), 0) as total FROM sales s JOIN users u ON s.created_by = u.id WHERE DATE(s.created_at) = '$today' AND s.status = 'completed' $companyIdFilter $branchFilter")->fetch_assoc()['total'];
$todayOrders = $conn->query("SELECT COUNT(*) as count FROM sales s JOIN users u ON s.created_by = u.id WHERE DATE(s.created_at) = '$today' AND s.status = 'completed' $companyIdFilter $branchFilter")->fetch_assoc()['count'];
$totalProducts = $conn->query("SELECT COUNT(*) as count FROM products WHERE company_id = $companyId AND status = 'active'")->fetch_assoc()['count'];
$totalCustomers = $conn->query("SELECT COUNT(*) as count FROM customers WHERE company_id = $companyId AND status = 'active'")->fetch_assoc()['count'];

// Recent sales
$recentSales = $conn->query("
    SELECT s.*, COALESCE(cust.name, 'Walk-in') as customer_name
    FROM sales s 
    JOIN users u ON s.created_by = u.id
    LEFT JOIN customers cust ON s.customer_id = cust.id
    WHERE s.status = 'completed' $companyIdFilter $branchFilter
    ORDER BY s.created_at DESC LIMIT 10
");

// Top products
$topProducts = $conn->query("
    SELECT p.name, SUM(si.quantity) as total_qty, SUM(si.total_price) as total_sales 
    FROM sale_items si 
    JOIN products p ON si.product_id = p.id 
    JOIN sales s ON si.sale_id = s.id 
    JOIN users u ON s.created_by = u.id
    WHERE s.status = 'completed' $companyIdFilter $branchFilter
    GROUP BY p.id 
    ORDER BY total_sales DESC LIMIT 5
");

// Daily sales for last 7 days
$dailySales = [];
$dailyLabels = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $label = date('M d', strtotime("-$i days"));
    $result = $conn->query("SELECT COALESCE(SUM(s.total_amount), 0) as total, COUNT(*) as orders FROM sales s JOIN users u ON s.created_by = u.id WHERE DATE(s.created_at) = '$date' AND s.status = 'completed' $companyIdFilter $branchFilter");
    $data = $result->fetch_assoc();
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
    $result = $conn->query("SELECT COALESCE(SUM(s.total_amount), 0) as total, COUNT(*) as orders FROM sales s JOIN users u ON s.created_by = u.id WHERE DATE(s.created_at) BETWEEN '$startDate' AND '$endDate' AND s.status = 'completed' $companyIdFilter $branchFilter");
    $data = $result->fetch_assoc();
    $weeklySales[] = $data['total'];
    $weeklyLabels[] = $label;
}

// Monthly sales for last 12 months
$monthlySales = [];
for ($i = 11; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $result = $conn->query("SELECT COALESCE(SUM(s.total_amount), 0) as total FROM sales s JOIN users u ON s.created_by = u.id WHERE DATE_FORMAT(s.created_at, '%Y-%m') = '$month' AND s.status = 'completed' $companyIdFilter $branchFilter");
    $monthlySales[] = $result->fetch_assoc()['total'];
}
?>

<!-- MANAGER DASHBOARD -->
<div class="manager-dashboard">
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
                <p class="text-muted mb-0">
                    <?php if ($currentBranch): ?>
                        Branch Manager Dashboard
                    <?php else: ?>
                        Manager Dashboard
                    <?php endif; ?>
                </p>
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
                            <?php
                            $branches_list = getBranches($companyId);
                            while ($b = $branches_list->fetch_assoc()): ?>
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

<!-- Stats Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="stat-card stat-card-success">
            <div class="stat-icon"><i class="fas fa-cash-register"></i></div>
            <div class="stat-content">
                <h6>TODAY'S SALES</h6>
                <h2><?= formatCurrency($todaySales) ?></h2>
                <small><?= $todayOrders ?> orders</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-card-blue">
            <div class="stat-icon"><i class="fas fa-box"></i></div>
            <div class="stat-content">
                <h6>PRODUCTS</h6>
                <h2><?= $totalProducts ?></h2>
                <small>In stock</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-card-purple">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-content">
                <h6>CUSTOMERS</h6>
                <h2><?= $totalCustomers ?></h2>
                <small>Registered</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-card-orange">
            <div class="stat-icon"><i class="fas fa-warehouse"></i></div>
            <div class="stat-content">
                <h6>QUICK ACTIONS</h6>
                <a href="?page=pos" class="btn btn-sm btn-success mt-1">New Sale</a>
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
                    $thisWeekSales = $conn->query("SELECT COALESCE(SUM(s.total_amount), 0) as total, COUNT(*) as orders FROM sales s JOIN users u ON s.created_by = u.id WHERE DATE(s.created_at) BETWEEN '$thisWeekStart' AND '$thisWeekEnd' AND s.status = 'completed' $companyIdFilter $branchFilter")->fetch_assoc();
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
                    $thisMonthSales = $conn->query("SELECT COALESCE(SUM(s.total_amount), 0) as total, COUNT(*) as orders FROM sales s JOIN users u ON s.created_by = u.id WHERE DATE_FORMAT(s.created_at, '%Y-%m') = '$thisMonth' AND s.status = 'completed' $companyIdFilter $branchFilter")->fetch_assoc();
                    ?>
                    <small class="text-muted">
                        <strong>This Month:</strong> <?= formatCurrency($thisMonthSales['total']) ?> (<?= $thisMonthSales['orders'] ?> orders)
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Recent Sales -->
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0"><i class="fas fa-shopping-cart text-success"></i> Recent Sales</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Invoice</th>
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

    <!-- Top Products -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0"><i class="fas fa-star text-warning"></i> Top Products</h5>
            </div>
            <div class="card-body">
                <?php while ($product = $topProducts->fetch_assoc()): ?>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h6 class="mb-0"><?= $product['name'] ?></h6>
                            <small class="text-muted"><?= $product['total_qty'] ?> sold</small>
                        </div>
                        <span class="text-success fw-bold"><?= formatCurrency($product['total_sales']) ?></span>
                    </div>
                <?php endwhile; ?>
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

    .stat-card-success {
        border-left: 4px solid #27ae60;
    }

    .stat-card-blue {
        border-left: 4px solid #3498db;
    }

    .stat-card-purple {
        border-left: 4px solid #8e44ad;
    }

    .stat-card-orange {
        border-left: 4px solid #e67e22;
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

    .stat-card-success .stat-icon {
        background: rgba(39, 174, 96, 0.1);
        color: #27ae60;
    }

    .stat-card-blue .stat-icon {
        background: rgba(52, 152, 219, 0.1);
        color: #3498db;
    }

    .stat-card-purple .stat-icon {
        background: rgba(142, 68, 173, 0.1);
        color: #8e44ad;
    }

    .stat-card-orange .stat-icon {
        background: rgba(230, 126, 34, 0.1);
        color: #e67e22;
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
</style>

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