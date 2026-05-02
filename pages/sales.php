<?php
require_once 'config/config.php';
$conn = getDB();

$currentUser = getCurrentUser();
$companyId = $currentUser['company_id'] ?? 0;

// Handle filter
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$branchFilter = $_GET['branch'] ?? 0;

// Build filters
$companyFilter = $companyId > 0 ? "AND u.company_id = $companyId" : "";
$branchSql = $branchFilter > 0 ? "AND u.branch_id = $branchFilter" : "";

$where = "WHERE DATE(s.created_at) BETWEEN '$dateFrom' AND '$dateTo' $companyFilter $branchSql";
$sales = $conn->query("SELECT s.*, COALESCE(c.name, 'Walk-in Customer') as customer_name, COALESCE(b.name, 'No Branch') as branch_name 
    FROM sales s 
    JOIN users u ON s.created_by = u.id
    LEFT JOIN customers c ON s.customer_id = c.id
    LEFT JOIN branches b ON u.branch_id = b.id
    $where 
    ORDER BY s.created_at DESC");

// Summary stats
$summary = $conn->query("SELECT 
    COUNT(*) as total_orders,
    COALESCE(SUM(s.total_amount), 0) as total_sales,
    COALESCE(SUM(s.discount_amount), 0) as total_discount
    FROM sales s 
    JOIN users u ON s.created_by = u.id
    $where AND s.status = 'completed'")->fetch_assoc();

// Get branches for filter dropdown
$branches = $companyId > 0 ? getBranches($companyId) : null;
$branchesList = [];
if ($branches) {
    while ($b = $branches->fetch_assoc()) {
        $branchesList[] = $b;
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4>Sales History</h4>
    <a href="?page=pos" class="btn btn-success">
        <i class="fas fa-plus"></i> New Sale
    </a>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row align-items-end">
            <input type="hidden" name="page" value="sales">
            <div class="col-md-2">
                <label class="form-label">From Date</label>
                <input type="date" name="date_from" class="form-control" value="<?= $dateFrom ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">To Date</label>
                <input type="date" name="date_to" class="form-control" value="<?= $dateTo ?>">
            </div>
            <?php if (!empty($branchesList)): ?>
                <div class="col-md-2">
                    <label class="form-label">Branch</label>
                    <select name="branch" class="form-select">
                        <option value="0">All Branches</option>
                        <?php foreach ($branchesList as $b): ?>
                            <option value="<?= $b['id'] ?>" <?= $branchFilter == $b['id'] ? 'selected' : '' ?>><?= $b['name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-filter"></i> Filter
                </button>
            </div>
            <div class="col-md-2">
                <a href="?page=sales" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h6>Total Orders</h6>
                <h2><?= $summary['total_orders'] ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h6>Total Sales</h6>
                <h2><?= formatCurrency($summary['total_sales']) ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <h6>Total Discount</h6>
                <h2><?= formatCurrency($summary['total_discount']) ?></h2>
            </div>
        </div>
    </div>
</div>

<!-- Sales Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Invoice No</th>
                        <th>Branch</th>
                        <th>Customer</th>
                        <th>Subtotal</th>
                        <th>Tax</th>
                        <th>Discount</th>
                        <th>Total</th>
                        <th>Payment</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($sale = $sales->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?= $sale['invoice_no'] ?></strong></td>
                            <td><span class="badge bg-secondary"><?= $sale['branch_name'] ?></span></td>
                            <td><?= $sale['customer_name'] ?></td>
                            <td><?= formatCurrency($sale['subtotal']) ?></td>
                            <td><?= formatCurrency($sale['tax_amount']) ?></td>
                            <td><?= formatCurrency($sale['discount_amount']) ?></td>
                            <td><strong><?= formatCurrency($sale['total_amount']) ?></strong></td>
                            <td><span class="badge bg-<?= $sale['payment_method'] == 'cash' ? 'success' : ($sale['payment_method'] == 'card' ? 'info' : 'warning') ?>"><?= ucfirst($sale['payment_method']) ?></span></td>
                            <td><?= date('M d, Y H:i', strtotime($sale['created_at'])) ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick="viewSale(<?= $sale['id'] ?>)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <a href="api/sales.php?action=get_sale_details&sale_id=<?= $sale['id'] ?>" class="btn btn-sm btn-outline-secondary" target="_blank">
                                    <i class="fas fa-print"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Sale Details Modal -->
<div class="modal fade" id="saleDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Sale Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="saleDetailsContent">
                Loading...
            </div>
        </div>
    </div>
</div>

<script>
    function viewSale(saleId) {
        fetch('api/sales.php?action=get_sale_details&sale_id=' + saleId)
            .then(res => res.json())
            .then(data => {
                const sale = data.sale;
                let html = '<table class="table table-bordered"><tr><th>Invoice:</th><td>' + sale.invoice_no + '</td></tr>';
                html += '<tr><th>Customer:</th><td>' + sale.customer_name + '</td></tr>';
                html += '<tr><th>Date:</th><td>' + sale.created_at + '</td></tr>';
                html += '<tr><th>Payment:</th><td>' + sale.payment_method + '</td></tr></table>';
                html += '<h6>Items:</h6><table class="table table-sm"><thead><tr><th>Product</th><th>Qty</th><th>Price</th><th>Total</th></tr></thead><tbody>';
                data.items.forEach(item => {
                    html += '<tr><td>' + item.name + '</td><td>' + item.quantity + '</td><td>₦' + parseFloat(item.unit_price).toFixed(2) + '</td><td>₦' + parseFloat(item.total_price).toFixed(2) + '</td></tr>';
                });
                html += '</tbody></table>';
                document.getElementById('saleDetailsContent').innerHTML = html;
                new bootstrap.Modal(document.getElementById('saleDetailsModal')).show();
            });
    }
</script>