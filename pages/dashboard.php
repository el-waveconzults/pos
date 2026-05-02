<?php
require_once 'config/config.php';
$conn = getDB();

$currentUser = getCurrentUser();
$userRole = $currentUser['role'];

// Redirect guest to login
if ($userRole === 'guest') {
    redirect('login.php');
}

// Get dashboard stats
$companyId = $currentUser['company_id'] ?? 0;
$today = date('Y-m-d');

// Owner sees all companies, others see only their company
$companyFilter = $userRole === 'owner' ? "" : "AND company_id = $companyId";

$todaySales = $conn->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM sales WHERE DATE(created_at) = '$today' AND status = 'completed' $companyFilter")->fetch_assoc()['total'];
$todayOrders = $conn->query("SELECT COUNT(*) as count FROM sales WHERE DATE(created_at) = '$today' AND status = 'completed' $companyFilter")->fetch_assoc()['count'];
$totalProducts = $conn->query("SELECT COUNT(*) as count FROM products WHERE status = 'active' $companyFilter")->fetch_assoc()['count'];
$totalCustomers = $conn->query("SELECT COUNT(*) as count FROM customers WHERE status = 'active' $companyFilter")->fetch_assoc()['count'];
$lowStock = $conn->query("SELECT COUNT(*) as count FROM products WHERE quantity <= min_quantity AND status = 'active' $companyFilter")->fetch_assoc()['count'];

// Get recent sales
$recentSales = $conn->query("SELECT s.*, COALESCE(c.name, 'Walk-in Customer') as customer_name 
    FROM sales s 
    LEFT JOIN customers c ON s.customer_id = c.id 
    WHERE 1=1 $companyFilter
    ORDER BY s.created_at DESC LIMIT 10");

// Get top products
$topProducts = $conn->query("SELECT p.name, SUM(si.quantity) as total_qty, SUM(si.total_price) as total_sales 
    FROM sale_items si 
    JOIN products p ON si.product_id = p.id 
    JOIN sales s ON si.sale_id = s.id 
    WHERE s.status = 'completed' $companyFilter
    GROUP BY p.id 
    ORDER BY total_sales DESC LIMIT 5");

// Monthly sales data
$monthlySales = [];
for ($i = 11; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $result = $conn->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM sales WHERE DATE_FORMAT(created_at, '%Y-%m') = '$month' AND status = 'completed' $companyFilter");
    $monthlySales[] = $result->fetch_assoc()['total'];
}
?>

<?php if ($userRole === 'cashier'): ?>
    <!-- CASHIER DASHBOARD - Simplified -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="stat-card primary">
                <h6>TODAY'S SALES</h6>
                <h2><?= formatCurrency($todaySales) ?></h2>
                <p class="mb-0"><?= $todayOrders ?> orders</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card success">
                <h6>QUICK ACTIONS</h6>
                <a href="?page=pos" class="btn btn-success w-100 mt-2">
                    <i class="fas fa-cash-register"></i> New Sale
                </a>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card info">
                <h6>LOW STOCK ALERT</h6>
                <h2><?= $lowStock ?></h2>
                <p class="mb-0">Items need restock</p>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Today's Recent Sales</h5>
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
                                <?php
                                $todaySalesList = $conn->query("SELECT s.*, COALESCE(c.name, 'Walk-in Customer') as customer_name 
                                FROM sales s 
                                LEFT JOIN customers c ON s.customer_id = c.id 
                                WHERE DATE(s.created_at) = '$today' AND s.status = 'completed'
                                ORDER BY s.created_at DESC LIMIT 10");
                                while ($sale = $todaySalesList->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= $sale['invoice_no'] ?></td>
                                        <td><?= $sale['customer_name'] ?></td>
                                        <td><?= formatCurrency($sale['total_amount']) ?></td>
                                        <td><span class="badge bg-<?= $sale['payment_method'] == 'cash' ? 'success' : 'info' ?>"><?= ucfirst($sale['payment_method']) ?></span></td>
                                        <td><?= date('H:i', strtotime($sale['created_at'])) ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- ADMIN/MANAGER DASHBOARD - Full Features -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card primary">
                <h6>TODAY'S SALES</h6>
                <h2><?= formatCurrency($todaySales) ?></h2>
                <p class="mb-0"><?= $todayOrders ?> orders</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card success">
                <h6>TOTAL PRODUCTS</h6>
                <h2><?= $totalProducts ?></h2>
                <p class="mb-0">Active items</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card warning">
                <h6>TOTAL CUSTOMERS</h6>
                <h2><?= $totalCustomers ?></h2>
                <p class="mb-0">Registered</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card info">
                <h6>LOW STOCK</h6>
                <h2><?= $lowStock ?></h2>
                <p class="mb-0">Items need restock</p>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Recent Sales -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Sales</h5>
                    <a href="?page=sales" class="btn btn-sm btn-primary">View All</a>
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
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Top Products</h5>
                </div>
                <div class="card-body">
                    <?php while ($product = $topProducts->fetch_assoc()): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h6 class="mb-0"><?= $product['name'] ?></h6>
                                <small class="text-muted"><?= $product['total_qty'] ?> sold</small>
                            </div>
                            <span class="text-success"><?= formatCurrency($product['total_sales']) ?></span>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Sales Chart -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Monthly Sales Trend</h5>
                </div>
                <div class="card-body">
                    <canvas id="salesChart" height="80"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const ctx = document.getElementById('salesChart').getContext('2d');
        new Chart(ctx, {
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
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
<?php endif; ?>