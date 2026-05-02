<?php
require_once 'config/config.php';
$conn = getDB();

$currentUser = getCurrentUser();
$companyId = $currentUser['company_id'] ?? 0;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_customer') {
        $stmt = $conn->prepare("INSERT INTO customers (company_id, name, email, phone, address, company_name) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssss", $companyId, $_POST['name'], $_POST['email'], $_POST['phone'], $_POST['address'], $_POST['company_name']);
        $stmt->execute();
        $success = "Customer added successfully!";
    } elseif ($action === 'update_customer') {
        $stmt = $conn->prepare("UPDATE customers SET company_id=?, name=?, email=?, phone=?, address=?, company_name=? WHERE id=?");
        $stmt->bind_param("isssssi", $companyId, $_POST['name'], $_POST['email'], $_POST['phone'], $_POST['address'], $_POST['company_name'], $_POST['id']);
        $stmt->execute();
        $success = "Customer updated successfully!";
    } elseif ($action === 'delete_customer') {
        $conn->query("UPDATE customers SET status='inactive' WHERE id=" . intval($_POST['id']) . " AND company_id = $companyId");
        $success = "Customer deleted successfully!";
    }
}

$customers = $conn->query("SELECT * FROM customers WHERE company_id = $companyId AND status='active' ORDER BY name");
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4>Customers</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#customerModal">
        <i class="fas fa-plus"></i> Add Customer
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
                        <th>Name</th>
                        <th>Company</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($customer = $customers->fetch_assoc()): ?>
                        <tr>
                            <td><?= $customer['name'] ?></td>
                            <td><?= $customer['company_name'] ?? '-' ?></td>
                            <td><?= $customer['email'] ?? '-' ?></td>
                            <td><?= $customer['phone'] ?? '-' ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick="editCustomer(<?= $customer['id'] ?>, '<?= addslashes($customer['name']) ?>', '<?= $customer['email'] ?? '' ?>', '<?= $customer['phone'] ?? '' ?>', '<?= addslashes($customer['address'] ?? '') ?>', '<?= addslashes($customer['company_name'] ?? '') ?>')">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="delete_customer">
                                    <input type="hidden" name="id" value="<?= $customer['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this customer?')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Customer Modal -->
<div class="modal fade" id="customerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Customer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_customer">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Company</label>
                        <input type="text" name="company_name" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function editCustomer(id, name, email, phone, address, company) {
        const modal = new bootstrap.Modal(document.getElementById('customerModal'));
        modal.show();
    }
</script>