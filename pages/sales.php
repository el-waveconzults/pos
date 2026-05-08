<?php
require_once 'config/config.php';
$conn = getDB();

$currentUser = getCurrentUser();

$companyId = $currentUser['company_id'] ?? 0;

// Handle filter - validate inputs
$dateFrom = validateInput($_GET['date_from'] ?? date('Y-m-01'), 'date');
$dateTo = validateInput($_GET['date_to'] ?? date('Y-m-d'), 'date');
$branchFilter = validateInput($_GET['branch'] ?? 0, 'int');

if ($dateFrom === false) $dateFrom = date('Y-m-01');
if ($dateTo === false) $dateTo = date('Y-m-d');
if ($branchFilter === false) $branchFilter = 0;

// Get sales data - show sample data for guests, real data for others
if ($currentUser['role'] === 'guest') {
    // Sample sales data for guest demo
    $sales = [
        ['id' => 1, 'invoice_no' => 'INV-2024-001', 'customer_name' => 'John Doe', 'subtotal' => 409090, 'tax_amount' => 40910, 'discount_amount' => 0, 'total_amount' => 450000, 'payment_method' => 'card', 'created_at' => '2024-01-15 14:30:00', 'cashier_name' => 'Demo User', 'branch_name' => 'Main Branch'],
        ['id' => 2, 'invoice_no' => 'INV-2024-002', 'customer_name' => 'Walk-in Customer', 'subtotal' => 204545, 'tax_amount' => 20455, 'discount_amount' => 5000, 'total_amount' => 225000, 'payment_method' => 'cash', 'created_at' => '2024-01-15 13:15:00', 'cashier_name' => 'Demo User', 'branch_name' => 'Main Branch'],
        ['id' => 3, 'invoice_no' => 'INV-2024-003', 'customer_name' => 'Jane Smith', 'subtotal' => 613636, 'tax_amount' => 61364, 'discount_amount' => 15000, 'total_amount' => 675000, 'payment_method' => 'card', 'created_at' => '2024-01-15 12:45:00', 'cashier_name' => 'Demo User', 'branch_name' => 'Main Branch'],
        ['id' => 4, 'invoice_no' => 'INV-2024-004', 'customer_name' => 'Walk-in Customer', 'subtotal' => 122727, 'tax_amount' => 12273, 'discount_amount' => 0, 'total_amount' => 135000, 'payment_method' => 'cash', 'created_at' => '2024-01-15 11:20:00', 'cashier_name' => 'Demo User', 'branch_name' => 'Main Branch'],
        ['id' => 5, 'invoice_no' => 'INV-2024-005', 'customer_name' => 'Bob Johnson', 'subtotal' => 921590, 'tax_amount' => 92159, 'discount_amount' => 25000, 'total_amount' => 1012500, 'payment_method' => 'card', 'created_at' => '2024-01-15 10:30:00', 'cashier_name' => 'Demo User', 'branch_name' => 'Main Branch']
    ];

    // Sample summary data
    $summary = [
        'total_orders' => 5,
        'total_sales' => 2835000, // ₦2,835,000
        'total_discount' => 45000   // ₦45,000
    ];

    $branchesList = [['id' => 1, 'name' => 'Main Branch']];
} else {
    // Build filters with prepared statement
    $companyFilter = "AND u.company_id = $companyId";
    $branchSql = $branchFilter > 0 ? "AND u.branch_id = $branchFilter" : "";

    // Role-based access control for sales visibility
    $userFilter = "";
    if ($currentUser['role'] === 'cashier') {
        // Cashiers can only see their own sales
        $userFilter = "AND s.created_by = ?";
    }

    $where = "WHERE DATE(s.created_at) BETWEEN ? AND ? $companyFilter $branchSql $userFilter";
    $stmt = $conn->prepare("SELECT s.*, COALESCE(c.name, 'Walk-in Customer') as customer_name, COALESCE(b.name, 'No Branch') as branch_name,
        u.name as cashier_name
        FROM sales s
        LEFT JOIN users u ON s.created_by = u.id
        LEFT JOIN customers c ON s.customer_id = c.id
        LEFT JOIN branches b ON u.branch_id = b.id
        $where
        ORDER BY s.created_at DESC");

    if ($currentUser['role'] === 'cashier') {
        $stmt->bind_param("sss", $dateFrom, $dateTo, $currentUser['id']);
    } else {
        $stmt->bind_param("ss", $dateFrom, $dateTo);
    }
    $stmt->execute();
    $sales = $stmt->get_result();

    // Summary stats
    $summary_where = "WHERE DATE(s.created_at) BETWEEN ? AND ? $companyFilter $branchSql $userFilter AND s.status = 'completed'";
    $summary_stmt = $conn->prepare("SELECT
        COUNT(*) as total_orders,
        COALESCE(SUM(s.total_amount), 0) as total_sales,
        COALESCE(SUM(s.discount_amount), 0) as total_discount
        FROM sales s
        LEFT JOIN users u ON s.created_by = u.id
        $summary_where");

    if ($currentUser['role'] === 'cashier') {
        $summary_stmt->bind_param("sss", $dateFrom, $dateTo, $currentUser['id']);
    } else {
        $summary_stmt->bind_param("ss", $dateFrom, $dateTo);
    }
    $summary_stmt->execute();
    $summary = $summary_stmt->get_result()->fetch_assoc();

    // Get branches for filter dropdown
    $branches = $companyId > 0 ? getBranches($companyId) : null;
    $branchesList = [];
    if ($branches) {
        while ($b = $branches->fetch_assoc()) {
            $branchesList[] = $b;
        }
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
                        <?php if ($currentUser['role'] !== 'cashier'): ?>
                            <th>Cashier</th>
                        <?php endif; ?>
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
                    <?php
                    if ($currentUser['role'] === 'guest') {
                        foreach ($sales as $sale):
                    ?>
                            <tr>
                                <td><strong><?= $sale['invoice_no'] ?></strong></td>
                                <td><span class="badge bg-secondary"><?= $sale['branch_name'] ?></span></td>
                                <?php if ($currentUser['role'] !== 'cashier'): ?>
                                    <td><span class="badge bg-light text-dark"><?= htmlspecialchars($sale['cashier_name']) ?></span></td>
                                <?php endif; ?>
                                <td><strong><?= $sale['customer_name'] ?></strong></td>
                                <td>₦ <?= number_format($sale['subtotal'], 2) ?></td>
                                <td>₦ <?= number_format($sale['tax_amount'], 2) ?></td>
                                <td>₦ <?= number_format($sale['discount_amount'], 2) ?></td>
                                <td><strong>₦ <?= number_format($sale['total_amount'], 2) ?></strong></td>
                                <td><span class="badge bg-<?= $sale['payment_method'] == 'cash' ? 'success' : ($sale['payment_method'] == 'card' ? 'info' : 'warning') ?>"><?= ucfirst($sale['payment_method']) ?></span></td>
                                <td><?= date('M d, Y H:i', strtotime($sale['created_at'])) ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary me-1" onclick="showAppModal('Demo Mode', 'This is sample data for demonstration purposes. Sign up to view detailed sales!', 'info')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <span class="text-muted small">Demo - View Only</span>
                                </td>
                            </tr>
                        <?php
                        endforeach;
                    } else {
                        while ($sale = $sales->fetch_assoc()):
                        ?>
                            <tr>
                                <td><strong><?= $sale['invoice_no'] ?></strong></td>
                                <td><span class="badge bg-secondary"><?= $sale['branch_name'] ?></span></td>
                                <?php if ($currentUser['role'] !== 'cashier'): ?>
                                    <td><span class="badge bg-light text-dark"><?= htmlspecialchars($sale['cashier_name']) ?></span></td>
                                <?php endif; ?>
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
                                    <a href="print_sale.php?sale_id=<?= $sale['id'] ?>" class="btn btn-sm btn-outline-secondary" target="_blank">
                                        <i class="fas fa-print"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php } ?>
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