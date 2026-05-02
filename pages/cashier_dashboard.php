<?php
require_once 'config/config.php';
$conn = getDB();

$currentUser = getCurrentUser();
$companyId = $currentUser['company_id'] ?? null;
$company = getCompany($companyId);
$currentBranch = getCurrentBranch();
$userId = $currentUser['id'];

// Cashier stats - only their sales
$today = date('Y-m-d');
$todaySales = $conn->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM sales WHERE DATE(created_at) = '$today' AND created_by = $userId AND status = 'completed'")->fetch_assoc()['total'];
$todayOrders = $conn->query("SELECT COUNT(*) as count FROM sales WHERE DATE(created_at) = '$today' AND created_by = $userId AND status = 'completed'")->fetch_assoc()['count'];

// Today's sales for this cashier
$recentSales = $conn->query("
    SELECT s.*, COALESCE(c.name, 'Walk-in') as customer_name
    FROM sales s 
    LEFT JOIN customers c ON s.customer_id = c.id
    WHERE DATE(s.created_at) = '$today' AND s.created_by = $userId AND s.status = 'completed'
    ORDER BY s.created_at DESC
");
?>

<!-- CASHIER DASHBOARD -->
<div class="cashier-dashboard">
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
                <p class="text-muted mb-0">Cashier Dashboard - Welcome, <?= $currentUser['name'] ?></p>
            </div>
            <div class="text-end">
                <small class="text-muted"><?= date('l, F j, Y') ?></small>
            </div>
        </div>
    </div>

    <!-- Quick Action -->
    <div class="row mb-4">
        <div class="col-12">
            <a href="?page=pos" class="btn btn-lg btn-success w-100 py-3">
                <i class="fas fa-cash-register me-2"></i> START NEW SALE
            </a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="stat-card stat-card-success">
                <div class="stat-icon"><i class="fas fa-naira-sign"></i></div>
                <div class="stat-content">
                    <h6>TODAY'S SALES</h6>
                    <h2><?= formatCurrency($todaySales) ?></h2>
                    <small>Your sales today</small>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="stat-card stat-card-blue">
                <div class="stat-icon"><i class="fas fa-receipt"></i></div>
                <div class="stat-content">
                    <h6>TODAY'S ORDERS</h6>
                    <h2><?= $todayOrders ?></h2>
                    <small>Transactions completed</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Today's Sales -->
    <div class="row">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0">
                    <h5 class="mb-0"><i class="fas fa-clock text-info"></i> Today's Transactions</h5>
                </div>
                <div class="card-body p-0">
                    <?php if ($recentSales->num_rows > 0): ?>
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
                                    <?php while ($sale = $recentSales->fetch_assoc()): ?>
                                        <tr>
                                            <td><strong><?= $sale['invoice_no'] ?></strong></td>
                                            <td><?= $sale['customer_name'] ?></td>
                                            <td><span class="text-success fw-bold"><?= formatCurrency($sale['total_amount']) ?></span></td>
                                            <td><span class="badge bg-<?= $sale['payment_method'] == 'cash' ? 'success' : 'info' ?>"><?= ucfirst($sale['payment_method']) ?></span></td>
                                            <td><?= date('H:i', strtotime($sale['created_at'])) ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-receipt text-muted" style="font-size: 48px;"></i>
                            <p class="text-muted mt-3">No sales today</p>
                            <a href="?page=pos" class="btn btn-primary">Start a Sale</a>
                        </div>
                    <?php endif; ?>
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