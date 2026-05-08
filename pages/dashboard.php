<?php
require_once 'config/config.php';
$conn = getDB();

$currentUser = getCurrentUser();
$userRole = $currentUser['role'];

// Get dashboard stats
$companyId = $currentUser['company_id'] ?? 0;
$today = date('Y-m-d');

// Build role-based filters
$userIdFilter = "";
$companyFilterSales = "";
$companyFilterProducts = "";
$companyFilterCustomers = "";

if ($userRole === 'cashier') {
    // Cashiers only see their own sales
    $userIdFilter = "AND s.created_by = ?";
    $userIdValue = $currentUser['id'];
} else if ($userRole !== 'owner') {
    // Non-owners see only their company sales (through users table)
    $companyFilterSales = "AND u.company_id = ?";
    $companyFilterValue = $companyId;
}

// Regular admins and branch managers see only their company data
if ($userRole !== 'owner' && $userRole !== 'cashier') {
    $companyFilterProducts = "AND company_id = ?";
    $companyFilterCustomers = "AND company_id = ?";
}

// Today's Sales - Join with users to get company_id
if ($userRole === 'cashier') {
    $stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) as total 
        FROM sales s 
        WHERE DATE(s.created_at) = ? AND s.status = 'completed' AND s.created_by = ?");
    $stmt->bind_param("si", $today, $currentUser['id']);
} else if ($userRole === 'owner') {
    $stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) as total 
        FROM sales s 
        WHERE DATE(s.created_at) = ? AND s.status = 'completed'");
    $stmt->bind_param("s", $today);
} else {
    $stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) as total 
        FROM sales s 
        JOIN users u ON s.created_by = u.id
        WHERE DATE(s.created_at) = ? AND s.status = 'completed' AND u.company_id = ?");
    $stmt->bind_param("si", $today, $companyId);
}
$stmt->execute();
$todaySales = $stmt->get_result()->fetch_assoc()['total'];

// Today's Orders Count
if ($userRole === 'cashier') {
    $stmt = $conn->prepare("SELECT COUNT(*) as count 
        FROM sales s 
        WHERE DATE(s.created_at) = ? AND s.status = 'completed' AND s.created_by = ?");
    $stmt->bind_param("si", $today, $currentUser['id']);
} else if ($userRole === 'owner') {
    $stmt = $conn->prepare("SELECT COUNT(*) as count 
        FROM sales s 
        WHERE DATE(s.created_at) = ? AND s.status = 'completed'");
    $stmt->bind_param("s", $today);
} else {
    $stmt = $conn->prepare("SELECT COUNT(*) as count 
        FROM sales s 
        JOIN users u ON s.created_by = u.id
        WHERE DATE(s.created_at) = ? AND s.status = 'completed' AND u.company_id = ?");
    $stmt->bind_param("si", $today, $companyId);
}
$stmt->execute();
$todayOrders = $stmt->get_result()->fetch_assoc()['count'];

// Total Products
if ($userRole === 'owner') {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE status = 'active'");
    $stmt->execute();
} else {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE status = 'active' AND company_id = ?");
    $stmt->bind_param("i", $companyId);
    $stmt->execute();
}
$totalProducts = $stmt->get_result()->fetch_assoc()['count'];

// Total Customers
if ($userRole === 'owner') {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM customers WHERE status = 'active'");
    $stmt->execute();
} else {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM customers WHERE status = 'active' AND company_id = ?");
    $stmt->bind_param("i", $companyId);
    $stmt->execute();
}
$totalCustomers = $stmt->get_result()->fetch_assoc()['count'];

// Low Stock
if ($userRole === 'owner') {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE quantity <= min_quantity AND status = 'active'");
    $stmt->execute();
} else {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE quantity <= min_quantity AND status = 'active' AND company_id = ?");
    $stmt->bind_param("i", $companyId);
    $stmt->execute();
}
$lowStock = $stmt->get_result()->fetch_assoc()['count'];

// Get recent sales
if ($userRole === 'cashier') {
    $stmt = $conn->prepare("SELECT s.*, COALESCE(c.name, 'Walk-in Customer') as customer_name, u.name as created_by_name
        FROM sales s 
        LEFT JOIN customers c ON s.customer_id = c.id
        LEFT JOIN users u ON s.created_by = u.id
        WHERE s.created_by = ?
        ORDER BY s.created_at DESC LIMIT 10");
    $stmt->bind_param("i", $currentUser['id']);
} else if ($userRole === 'owner') {
    $stmt = $conn->prepare("SELECT s.*, COALESCE(c.name, 'Walk-in Customer') as customer_name, u.name as created_by_name
        FROM sales s 
        LEFT JOIN customers c ON s.customer_id = c.id
        LEFT JOIN users u ON s.created_by = u.id
        ORDER BY s.created_at DESC LIMIT 10");
} else {
    $stmt = $conn->prepare("SELECT s.*, COALESCE(c.name, 'Walk-in Customer') as customer_name, u.name as created_by_name
        FROM sales s 
        LEFT JOIN customers c ON s.customer_id = c.id
        LEFT JOIN users u ON s.created_by = u.id
        WHERE u.company_id = ?
        ORDER BY s.created_at DESC LIMIT 10");
    $stmt->bind_param("i", $companyId);
}
$stmt->execute();
$recentSales = $stmt->get_result();

// Get top products
if ($userRole === 'cashier') {
    $stmt = $conn->prepare("SELECT p.name, SUM(si.quantity) as total_qty, SUM(si.total_price) as total_sales 
        FROM sale_items si 
        JOIN products p ON si.product_id = p.id 
        JOIN sales s ON si.sale_id = s.id 
        WHERE s.status = 'completed' AND s.created_by = ?
        GROUP BY p.id 
        ORDER BY total_sales DESC LIMIT 5");
    $stmt->bind_param("i", $currentUser['id']);
} else if ($userRole === 'owner') {
    $stmt = $conn->prepare("SELECT p.name, SUM(si.quantity) as total_qty, SUM(si.total_price) as total_sales 
        FROM sale_items si 
        JOIN products p ON si.product_id = p.id 
        JOIN sales s ON si.sale_id = s.id 
        WHERE s.status = 'completed'
        GROUP BY p.id 
        ORDER BY total_sales DESC LIMIT 5");
} else {
    $stmt = $conn->prepare("SELECT p.name, SUM(si.quantity) as total_qty, SUM(si.total_price) as total_sales 
        FROM sale_items si 
        JOIN products p ON si.product_id = p.id 
        JOIN sales s ON si.sale_id = s.id 
        JOIN users u ON s.created_by = u.id
        WHERE s.status = 'completed' AND u.company_id = ?
        GROUP BY p.id 
        ORDER BY total_sales DESC LIMIT 5");
    $stmt->bind_param("i", $companyId);
}
$stmt->execute();
$topProducts = $stmt->get_result();

// Monthly sales data
$monthlySales = [];
for ($i = 11; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));

    if ($userRole === 'cashier') {
        $stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) as total 
            FROM sales 
            WHERE DATE_FORMAT(created_at, '%Y-%m') = ? AND status = 'completed' AND created_by = ?");
        $stmt->bind_param("si", $month, $currentUser['id']);
    } else if ($userRole === 'owner') {
        $stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) as total 
            FROM sales 
            WHERE DATE_FORMAT(created_at, '%Y-%m') = ? AND status = 'completed'");
        $stmt->bind_param("s", $month);
    } else {
        $stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) as total 
            FROM sales s
            JOIN users u ON s.created_by = u.id
            WHERE DATE_FORMAT(s.created_at, '%Y-%m') = ? AND s.status = 'completed' AND u.company_id = ?");
        $stmt->bind_param("si", $month, $companyId);
    }

    $stmt->execute();
    $monthlySales[] = $stmt->get_result()->fetch_assoc()['total'];
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
                    <canvas id="salesChart" height="80" style="cursor: pointer;" onclick="showFullChart()"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Full Chart Modal -->
    <div class="modal fade" id="chartModal" tabindex="-1" aria-labelledby="chartModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="chartModalLabel">Monthly Sales Trend - Full View</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <canvas id="fullSalesChart" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Data for charts
        const chartLabels = ['<?= implode("','", array_map(function ($i) {
                                    return date('M Y', strtotime("-$i months"));
                                }, range(11, 0))) ?>'];
        const chartData = <?= json_encode($monthlySales) ?>;

        // Small chart
        const ctx = document.getElementById('salesChart').getContext('2d');
        const smallChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Sales',
                    data: chartData,
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

        // Function to show full chart modal
        function showFullChart() {
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('chartModal'));
            modal.show();

            // Create full chart after modal is shown
            setTimeout(() => {
                const fullCtx = document.getElementById('fullSalesChart').getContext('2d');
                new Chart(fullCtx, {
                    type: 'line',
                    data: {
                        labels: chartLabels,
                        datasets: [{
                            label: 'Monthly Sales',
                            data: chartData,
                            borderColor: '#3498db',
                            backgroundColor: 'rgba(52, 152, 219, 0.2)',
                            fill: true,
                            tension: 0.4,
                            pointBackgroundColor: '#3498db',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 6,
                            pointHoverRadius: 8
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top'
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return 'Sales: ' + new Intl.NumberFormat('en-US', {
                                            style: 'currency',
                                            currency: 'USD'
                                        }).format(context.parsed.y);
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return new Intl.NumberFormat('en-US', {
                                            style: 'currency',
                                            currency: 'USD',
                                            minimumFractionDigits: 0
                                        }).format(value);
                                    }
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                }
                            }
                        },
                        interaction: {
                            intersect: false,
                            mode: 'index'
                        }
                    }
                });
            }, 300);
        }
    </script>
<?php endif; ?>