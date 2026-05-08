<?php
require_once 'config/config.php';

// Sample data for guest demo (in Naira)
$todaySales = 1875000; // ₦1,875,000
$todayOrders = 8;
$totalProducts = 45;
$totalCustomers = 23;
$lowStock = 3;

// Sample recent sales data (in Naira)
$recentSales = [
    ['invoice_no' => 'INV-2024-001', 'customer_name' => 'John Doe', 'total_amount' => 450000, 'payment_method' => 'card', 'created_at' => '2024-01-15 14:30:00'],
    ['invoice_no' => 'INV-2024-002', 'customer_name' => 'Walk-in Customer', 'total_amount' => 225000, 'payment_method' => 'cash', 'created_at' => '2024-01-15 13:15:00'],
    ['invoice_no' => 'INV-2024-003', 'customer_name' => 'Jane Smith', 'total_amount' => 675000, 'payment_method' => 'card', 'created_at' => '2024-01-15 12:45:00'],
    ['invoice_no' => 'INV-2024-004', 'customer_name' => 'Walk-in Customer', 'total_amount' => 135000, 'payment_method' => 'cash', 'created_at' => '2024-01-15 11:20:00'],
    ['invoice_no' => 'INV-2024-005', 'customer_name' => 'Bob Johnson', 'total_amount' => 1012500, 'payment_method' => 'card', 'created_at' => '2024-01-15 10:30:00']
];

// Sample top products (in Naira)
$topProducts = [
    ['name' => 'Wireless Headphones', 'total_qty' => 12, 'total_sales' => 5400000],
    ['name' => 'Smart Watch', 'total_qty' => 8, 'total_sales' => 3600000],
    ['name' => 'Laptop Stand', 'total_qty' => 15, 'total_sales' => 2250000],
    ['name' => 'USB Cable', 'total_qty' => 25, 'total_sales' => 1875000],
    ['name' => 'Phone Case', 'total_qty' => 18, 'total_sales' => 1350000]
];

// Sample monthly sales data (last 12 months) (in Naira)
$monthlySales = [4800000, 4275000, 6150000, 5475000, 6300000, 5700000, 6750000, 5925000, 7200000, 6375000, 7650000, 6975000];
?>

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
            <h6>LOW STOCK ALERT</h6>
            <h2><?= $lowStock ?></h2>
            <p class="mb-0">Items need restock</p>
        </div>
    </div>
</div>

<div class="row mb-4">
    <!-- Recent Sales -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Recent Sales</h5>
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
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentSales as $sale): ?>
                                <tr>
                                    <td><?= $sale['invoice_no'] ?></td>
                                    <td><?= $sale['customer_name'] ?></td>
                                    <td><?= formatCurrency($sale['total_amount']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $sale['payment_method'] == 'cash' ? 'success' : 'info' ?>">
                                            <?= ucfirst($sale['payment_method']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('H:i', strtotime($sale['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
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
                <?php foreach ($topProducts as $product): ?>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <div class="fw-bold"><?= $product['name'] ?></div>
                            <small class="text-muted"><?= $product['total_qty'] ?> sold</small>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold"><?= formatCurrency($product['total_sales']) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Monthly Sales Chart Placeholder -->
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Monthly Sales Trend</h5>
            </div>
            <div class="card-body">
                <div class="text-center py-5">
                    <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                    <h6 class="text-muted">Sales Analytics Chart</h6>
                    <p class="text-muted small">Interactive charts would display here showing sales trends over time</p>
                    <div class="row text-center mt-4">
                        <?php
                        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                        foreach ($monthlySales as $index => $sales): ?>
                            <div class="col-1">
                                <div class="small text-muted"><?= $months[$index] ?></div>
                                <div class="fw-bold">₦<?= number_format($sales / 100000, 1) ?>M</div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Guest Notice -->
<div class="row mt-4">
    <div class="col-md-12">
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            <strong>Guest Mode:</strong> This is a demonstration dashboard showing sample data.
            <a href="login.php" class="alert-link">Sign in</a> to access your real business data and full features.
        </div>
    </div>
</div>