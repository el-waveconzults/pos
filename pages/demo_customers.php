<?php
require_once 'config/config.php';

// Sample customers data for demo
$sampleCustomers = [
    ['id' => 1, 'name' => 'John Doe', 'email' => 'john.doe@email.com', 'phone' => '+234 801 234 5678', 'total_purchases' => 1850000, 'last_purchase' => '2024-01-15', 'status' => 'active'],
    ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane.smith@email.com', 'phone' => '+234 802 345 6789', 'total_purchases' => 2250000, 'last_purchase' => '2024-01-14', 'status' => 'active'],
    ['id' => 3, 'name' => 'Bob Johnson', 'email' => 'bob.johnson@email.com', 'phone' => '+234 803 456 7890', 'total_purchases' => 950000, 'last_purchase' => '2024-01-13', 'status' => 'active'],
    ['id' => 4, 'name' => 'Alice Brown', 'email' => 'alice.brown@email.com', 'phone' => '+234 804 567 8901', 'total_purchases' => 1650000, 'last_purchase' => '2024-01-12', 'status' => 'active'],
    ['id' => 5, 'name' => 'Mike Wilson', 'email' => 'mike.wilson@email.com', 'phone' => '+234 805 678 9012', 'total_purchases' => 750000, 'last_purchase' => '2024-01-10', 'status' => 'inactive'],
    ['id' => 6, 'name' => 'Sarah Davis', 'email' => 'sarah.davis@email.com', 'phone' => '+234 806 789 0123', 'total_purchases' => 1350000, 'last_purchase' => '2024-01-08', 'status' => 'active']
];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">Customer Management</h4>
    <button class="btn btn-primary" disabled>
        <i class="fas fa-plus me-2"></i>Add Customer
    </button>
</div>

<!-- Demo Notice -->
<div class="alert alert-info mb-4">
    <i class="fas fa-info-circle me-2"></i>
    <strong>Demo Mode:</strong> This is a demonstration of the customer management interface.
    Sample customer data is shown for illustration purposes.
</div>

<!-- Customers Table -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">All Customers (<?= count($sampleCustomers) ?>)</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Customer</th>
                        <th>Contact</th>
                        <th>Total Purchases</th>
                        <th>Last Purchase</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sampleCustomers as $customer): ?>
                        <tr>
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars($customer['name']) ?></div>
                                <small class="text-muted">ID: #<?= $customer['id'] ?></small>
                            </td>
                            <td>
                                <div><i class="fas fa-envelope me-1"></i><?= htmlspecialchars($customer['email']) ?></div>
                                <div><i class="fas fa-phone me-1"></i><?= htmlspecialchars($customer['phone']) ?></div>
                            </td>
                            <td class="fw-bold text-success"><?= formatCurrency($customer['total_purchases']) ?></td>
                            <td><?= date('M d, Y', strtotime($customer['last_purchase'])) ?></td>
                            <td>
                                <span class="badge bg-<?= $customer['status'] == 'active' ? 'success' : 'secondary' ?>">
                                    <?= ucfirst($customer['status']) ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary me-1" disabled>
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-info me-1" disabled>
                                    <i class="fas fa-eye"></i>
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
                <h5 class="text-primary"><?= count($sampleCustomers) ?></h5>
                <p class="mb-0">Total Customers</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="text-success">
                    <?= count(array_filter($sampleCustomers, fn($c) => $c['status'] == 'active')) ?>
                </h5>
                <p class="mb-0">Active Customers</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="text-info">
                    ₦<?= number_format(array_sum(array_column($sampleCustomers, 'total_purchases')) / 1000000, 1) ?>M
                </h5>
                <p class="mb-0">Total Revenue</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="text-warning">
                    <?= round(array_sum(array_column($sampleCustomers, 'total_purchases')) / count($sampleCustomers) / 1000) ?>K
                </h5>
                <p class="mb-0">Avg Purchase</p>
            </div>
        </div>
    </div>
</div>