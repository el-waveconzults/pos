<?php
require_once 'config/config.php';

// Sample sales data for demo
$sampleSales = [
    ['id' => 1, 'invoice_no' => 'INV-2024-001', 'customer_name' => 'John Doe', 'total_amount' => 450000, 'payment_method' => 'card', 'status' => 'completed', 'created_at' => '2024-01-15 14:30:00', 'created_by' => 'Admin User'],
    ['id' => 2, 'invoice_no' => 'INV-2024-002', 'customer_name' => 'Walk-in Customer', 'total_amount' => 225000, 'payment_method' => 'cash', 'status' => 'completed', 'created_at' => '2024-01-15 13:15:00', 'created_by' => 'Cashier 1'],
    ['id' => 3, 'invoice_no' => 'INV-2024-003', 'customer_name' => 'Jane Smith', 'total_amount' => 675000, 'payment_method' => 'card', 'status' => 'completed', 'created_at' => '2024-01-15 12:45:00', 'created_by' => 'Admin User'],
    ['id' => 4, 'invoice_no' => 'INV-2024-004', 'customer_name' => 'Walk-in Customer', 'total_amount' => 135000, 'payment_method' => 'cash', 'status' => 'pending', 'created_at' => '2024-01-15 11:20:00', 'created_by' => 'Cashier 2'],
    ['id' => 5, 'invoice_no' => 'INV-2024-005', 'customer_name' => 'Bob Johnson', 'total_amount' => 1012500, 'payment_method' => 'card', 'status' => 'completed', 'created_at' => '2024-01-15 10:30:00', 'created_by' => 'Manager'],
    ['id' => 6, 'invoice_no' => 'INV-2024-006', 'customer_name' => 'Alice Brown', 'total_amount' => 375000, 'payment_method' => 'cash', 'status' => 'completed', 'created_at' => '2024-01-14 16:45:00', 'created_by' => 'Cashier 1'],
    ['id' => 7, 'invoice_no' => 'INV-2024-007', 'customer_name' => 'Walk-in Customer', 'total_amount' => 187500, 'payment_method' => 'card', 'status' => 'cancelled', 'created_at' => '2024-01-14 15:20:00', 'created_by' => 'Admin User']
];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">Sales Management</h4>
    <button class="btn btn-success" disabled>
        <i class="fas fa-plus me-2"></i>New Sale
    </button>
</div>

<!-- Demo Notice -->
<div class="alert alert-info mb-4">
    <i class="fas fa-info-circle me-2"></i>
    <strong>Demo Mode:</strong> This is a demonstration of the sales management interface.
    Sample sales transactions are shown for illustration purposes.
</div>

<!-- Sales Table -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">All Sales (<?= count($sampleSales) ?>)</h5>
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
                        <th>Status</th>
                        <th>Cashier</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sampleSales as $sale): ?>
                        <tr>
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars($sale['invoice_no']) ?></div>
                            </td>
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars($sale['customer_name']) ?></div>
                            </td>
                            <td class="fw-bold text-success"><?= formatCurrency($sale['total_amount']) ?></td>
                            <td>
                                <span class="badge bg-<?= $sale['payment_method'] == 'cash' ? 'success' : 'info' ?>">
                                    <?= ucfirst($sale['payment_method']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?=
                                                        $sale['status'] == 'completed' ? 'success' : ($sale['status'] == 'pending' ? 'warning' : 'danger') ?>">
                                    <?= ucfirst($sale['status']) ?>
                                </span>
                            </td>
                            <td>
                                <small class="text-muted"><?= htmlspecialchars($sale['created_by']) ?></small>
                            </td>
                            <td>
                                <div><?= date('M d, Y', strtotime($sale['created_at'])) ?></div>
                                <small class="text-muted"><?= date('H:i', strtotime($sale['created_at'])) ?></small>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary me-1" disabled>
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-info me-1" disabled>
                                    <i class="fas fa-print"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Quick Stats -->
<div class="row mt-4">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="text-success">
                    ₦<?= number_format(array_sum(array_column(array_filter($sampleSales, fn($s) => $s['status'] == 'completed'), 'total_amount')) / 1000000, 1) ?>M
                </h5>
                <p class="mb-0">Total Sales</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="text-primary">
                    <?= count(array_filter($sampleSales, fn($s) => $s['status'] == 'completed')) ?>
                </h5>
                <p class="mb-0">Completed Orders</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="text-warning">
                    <?= count(array_filter($sampleSales, fn($s) => $s['status'] == 'pending')) ?>
                </h5>
                <p class="mb-0">Pending Orders</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="text-info">
                    ₦<?= number_format(array_sum(array_column($sampleSales, 'total_amount')) / count($sampleSales) / 1000, 0) ?>K
                </h5>
                <p class="mb-0">Avg Order Value</p>
            </div>
        </div>
    </div>
</div>