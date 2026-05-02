<?php
require_once 'config/config.php';
$conn = getDB();

$currentUser = getCurrentUser();
$companyId = $currentUser['company_id'] ?? 0;

// Get date range
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

// Sales Report (filtered by company)
$salesReport = $conn->query("SELECT 
    DATE(s.created_at) as date,
    COUNT(*) as orders,
    SUM(s.total_amount) as total
    FROM sales s
    JOIN users u ON s.created_by = u.id
    WHERE u.company_id = $companyId AND DATE(s.created_at) BETWEEN '$dateFrom' AND '$dateTo' AND s.status = 'completed'
    GROUP BY DATE(s.created_at)
    ORDER BY date");

// Top Products (filtered by company)
$topProducts = $conn->query("SELECT p.name, SUM(si.quantity) as qty, SUM(si.total_price) as sales 
    FROM sale_items si 
    JOIN products p ON si.product_id = p.id 
    JOIN sales s ON si.sale_id = s.id 
    JOIN users u ON s.created_by = u.id
    WHERE u.company_id = $companyId AND s.status = 'completed' AND DATE(s.created_at) BETWEEN '$dateFrom' AND '$dateTo'
    GROUP BY p.id 
    ORDER BY sales DESC LIMIT 10");

// Top Customers (filtered by company)
$topCustomers = $conn->query("SELECT c.name, COUNT(s.id) as orders, SUM(s.total_amount) as total 
    FROM sales s
    JOIN users u ON s.created_by = u.id
    LEFT JOIN customers c ON s.customer_id = c.id
    WHERE u.company_id = $companyId AND s.status = 'completed' AND DATE(s.created_at) BETWEEN '$dateFrom' AND '$dateTo'
    GROUP BY c.id 
    ORDER BY total DESC LIMIT 10");

// Summary (filtered by company)
$summary = $conn->query("SELECT 
    COUNT(*) as total_orders,
    COALESCE(SUM(s.total_amount), 0) as total_sales,
    COALESCE(SUM(s.discount_amount), 0) as total_discount
    FROM sales s
    JOIN users u ON s.created_by = u.id
    WHERE u.company_id = $companyId AND DATE(s.created_at) BETWEEN '$dateFrom' AND '$dateTo' AND s.status = 'completed'")->fetch_assoc();
?>

<h4 class="mb-4">Reports & Analytics</h4>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row align-items-end">
            <input type="hidden" name="page" value="reports">
            <div class="col-md-3">
                <label class="form-label">From Date</label>
                <input type="date" name="date_from" class="form-control" value="<?= $dateFrom ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">To Date</label>
                <input type="date" name="date_to" class="form-control" value="<?= $dateTo ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-chart-line"></i> Generate
                </button>
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-success w-100" onclick="window.print()">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Summary Stats -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h6 class="text-muted">Total Orders</h6>
                <h2 class="text-primary"><?= $summary['total_orders'] ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h6 class="text-muted">Total Sales</h6>
                <h2 class="text-success"><?= formatCurrency($summary['total_sales']) ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h6 class="text-muted">Total Discount</h6>
                <h2 class="text-warning"><?= formatCurrency($summary['total_discount']) ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h6 class="text-muted">Average Order</h6>
                <h2 class="text-info"><?= formatCurrency($summary['total_orders'] > 0 ? $summary['total_sales'] / $summary['total_orders'] : 0) ?></h2>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Daily Sales Chart -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Daily Sales</h5>
            </div>
            <div class="card-body">
                <canvas id="dailySalesChart" height="200"></canvas>
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
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Qty</th>
                            <th>Sales</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($p = $topProducts->fetch_assoc()): ?>
                            <tr>
                                <td><?= $p['name'] ?></td>
                                <td><?= $p['qty'] ?></td>
                                <td><?= formatCurrency($p['sales']) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <!-- Top Customers -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Top Customers</h5>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Orders</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($c = $topCustomers->fetch_assoc()): ?>
                            <tr>
                                <td><?= $c['name'] ?></td>
                                <td><?= $c['orders'] ?></td>
                                <td><?= formatCurrency($c['total']) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Sales by Payment Method -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Sales by Payment Method</h5>
            </div>
            <div class="card-body">
                <?php
                $paymentStats = $conn->query("SELECT payment_method, COUNT(*) as count, SUM(total_amount) as total 
                    FROM sales 
                    WHERE DATE(created_at) BETWEEN '$dateFrom' AND '$dateTo' AND status = 'completed'
                    GROUP BY payment_method");
                ?>
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Method</th>
                            <th>Orders</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($pm = $paymentStats->fetch_assoc()): ?>
                            <tr>
                                <td><span class="badge bg-<?= $pm['payment_method'] == 'cash' ? 'success' : 'info' ?>"><?= ucfirst($pm['payment_method']) ?></span></td>
                                <td><?= $pm['count'] ?></td>
                                <td><?= formatCurrency($pm['total']) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Daily Sales Chart
    const dailyData = [
        <?php
        $salesReport->data_seek(0);
        $labels = [];
        $data = [];
        while ($row = $salesReport->fetch_assoc()) {
            $labels[] = "'" . date('M d', strtotime($row['date'])) . "'";
            $data[] = $row['total'];
        }
        echo implode(',', $labels) . ',' . implode(',', $data);
        ?>
    ];

    new Chart(document.getElementById('dailySalesChart'), {
        type: 'bar',
        data: {
            labels: [<?= implode(',', $labels) ?>],
            datasets: [{
                label: 'Sales',
                data: [<?= implode(',', $data) ?>],
                backgroundColor: '#3498db'
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
</script>