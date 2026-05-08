<?php
require_once 'config/config.php';
$conn = getDB();

$currentUser = getCurrentUser();
$companyId = $currentUser['company_id'] ?? 0;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_invoice') {
        $invoice_no = 'INV-' . time();
        $stmt = $conn->prepare("INSERT INTO invoices (company_id, invoice_no, customer_id, due_date, subtotal, tax_amount, discount_amount, total_amount, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $due_date = $_POST['due_date'];
        $subtotal = floatval($_POST['subtotal']);
        $tax = $subtotal * (getTaxRate() / 100);
        $discount = floatval($_POST['discount']);
        $total = $subtotal + $tax - $discount;
        $stmt->bind_param("isisddsss", $companyId, $invoice_no, $_POST['customer_id'], $due_date, $subtotal, $tax, $discount, $total, $_POST['notes']);
        $stmt->execute();
        $success = "Invoice created successfully!";
    } elseif ($action === 'update_payment') {
        $stmt = $conn->prepare("UPDATE invoices SET amount_paid = amount_paid + ?, status = CASE WHEN total_amount <= amount_paid + ? THEN 'paid' ELSE 'partial' END WHERE id = ?");
        $amount = floatval($_POST['amount']);
        $stmt->bind_param("ddi", $amount, $amount, $_POST['id']);
        $stmt->execute();
        $success = "Payment recorded!";
    }
}

$invoices = $conn->query("SELECT i.*, COALESCE(c.name, 'Walk-in') as customer_name 
    FROM invoices i 
    LEFT JOIN customers c ON i.customer_id = c.id 
    WHERE i.company_id = $companyId
    ORDER BY i.created_at DESC");
$customers = $conn->query("SELECT * FROM customers WHERE company_id = $companyId AND status='active' ORDER BY name");
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4>Invoices</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#invoiceModal">
        <i class="fas fa-plus"></i> Create Invoice
    </button>
</div>

<?php if (isset($success)): ?>
    <div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Invoice No</th>
                        <th>Customer</th>
                        <th>Total</th>
                        <th>Paid</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($inv = $invoices->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?= $inv['invoice_no'] ?></strong></td>
                            <td><?= $inv['customer_name'] ?></td>
                            <td><?= formatCurrency($inv['total_amount']) ?></td>
                            <td><?= formatCurrency($inv['amount_paid']) ?></td>
                            <td><?= $inv['due_date'] ? date('M d, Y', strtotime($inv['due_date'])) : '-' ?></td>
                            <td>
                                <?php
                                $statusClass = match ($inv['status']) {
                                    'paid' => 'success',
                                    'partial' => 'warning',
                                    'overdue' => 'danger',
                                    default => 'secondary'
                                };
                                ?>
                                <span class="badge bg-<?= $statusClass ?>"><?= ucfirst($inv['status']) ?></span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick="viewInvoice(<?= $inv['id'] ?>)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-success" onclick="payInvoice(<?= $inv['id'] ?>, <?= $inv['total_amount'] - $inv['amount_paid'] ?>)">
                                    <i class="fas fa-dollar-sign"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create Invoice Modal -->
<div class="modal fade" id="invoiceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Invoice</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_invoice">
                    <div class="mb-3">
                        <label class="form-label">Customer</label>
                        <select name="customer_id" class="form-select" required>
                            <option value="">Select Customer</option>
                            <?php while ($c = $customers->fetch_assoc()): ?>
                                <option value="<?= $c['id'] ?>"><?= $c['name'] ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Due Date</label>
                        <input type="date" name="due_date" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Subtotal</label>
                                <input type="number" name="subtotal" class="form-control" step="0.01" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Discount</label>
                                <input type="number" name="discount" class="form-control" value="0" step="0.01">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Create Invoice</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Record Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_payment">
                    <input type="hidden" name="id" id="payInvoiceId">
                    <div class="mb-3">
                        <label class="form-label">Amount</label>
                        <input type="number" name="amount" class="form-control" step="0.01" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-success">Record Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Invoice Details Modal -->
<div class="modal fade" id="invoiceDetailsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Invoice Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="invoiceDetailsContent"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    function viewInvoice(id) {
        fetch('api/invoices.php?action=get_invoice_details&invoice_id=' + id)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const inv = data.invoice;
                    let html = '<table class="table table-bordered"><tr><th>Invoice:</th><td>' + inv.invoice_no + '</td></tr>';
                    html += '<tr><th>Customer:</th><td>' + inv.customer_name + '</td></tr>';
                    html += '<tr><th>Date:</th><td>' + inv.created_at + '</td></tr>';
                    html += '<tr><th>Due Date:</th><td>' + (inv.due_date || 'N/A') + '</td></tr>';
                    html += '<tr><th>Total:</th><td>₦' + parseFloat(inv.total_amount).toFixed(2) + '</td></tr>';
                    html += '<tr><th>Paid:</th><td>₦' + parseFloat(inv.amount_paid).toFixed(2) + '</td></tr>';
                    html += '<tr><th>Balance:</th><td>₦' + parseFloat(inv.total_amount - inv.amount_paid).toFixed(2) + '</td></tr>';
                    html += '<tr><th>Status:</th><td><span class="badge bg-' + (inv.status === 'paid' ? 'success' : inv.status === 'partial' ? 'warning' : 'danger') + '">' + inv.status + '</span></td></tr>';
                    if (inv.notes) {
                        html += '<tr><th>Notes:</th><td>' + inv.notes + '</td></tr>';
                    }
                    html += '</table>';
                    document.getElementById('invoiceDetailsContent').innerHTML = html;
                    new bootstrap.Modal(document.getElementById('invoiceDetailsModal')).show();
                } else {
                    showAppModal(data.message, 'Invoice Error', 'danger');
                }
            });
    }

    function payInvoice(id, balance) {
        document.getElementById('payInvoiceId').value = id;
        document.querySelector('#paymentModal input[name="amount"]').value = balance.toFixed(2);
        new bootstrap.Modal(document.getElementById('paymentModal')).show();
    }
</script>