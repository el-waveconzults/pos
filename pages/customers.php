<?php
require_once 'config/config.php';
$conn = getDB();

$currentUser = getCurrentUser();
$companyId = $currentUser['company_id'] ?? 0;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // CSRF Token Verification
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Security token verification failed. Please try again.";
    } elseif ($action === 'add_customer') {
        // Input Validation
        $name = validateInput($_POST['name'] ?? '', 'string', true);
        $email = validateInput($_POST['email'] ?? '', 'email');
        $phone = validateInput($_POST['phone'] ?? '', 'phone');
        $address = validateInput($_POST['address'] ?? '', 'string');
        $companyName = validateInput($_POST['company_name'] ?? '', 'string');

        if ($name === false) {
            $error = "Invalid customer name.";
        } else {
            $stmt = $conn->prepare("INSERT INTO customers (company_id, name, email, phone, address, company_name) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssss", $companyId, $name, $email, $phone, $address, $companyName);
            if ($stmt->execute()) {
                $success = "Customer added successfully!";
            } else {
                $error = "Database error: " . $conn->error;
            }
        }
    } elseif ($action === 'update_customer') {
        // Input Validation
        $customerId = validateInput($_POST['id'] ?? 0, 'int', true);
        $name = validateInput($_POST['name'] ?? '', 'string', true);
        $email = validateInput($_POST['email'] ?? '', 'email');
        $phone = validateInput($_POST['phone'] ?? '', 'phone');
        $address = validateInput($_POST['address'] ?? '', 'string');
        $companyName = validateInput($_POST['company_name'] ?? '', 'string');

        if ($customerId === false || $name === false) {
            $error = "Invalid input data.";
        } else {
            // IDOR Check
            if (!verifyIDOR('customer', $customerId, $companyId)) {
                $error = "Unauthorized access to this customer.";
            } else {
                $stmt = $conn->prepare("UPDATE customers SET company_id=?, name=?, email=?, phone=?, address=?, company_name=? WHERE id=?");
                $stmt->bind_param("isssssi", $companyId, $name, $email, $phone, $address, $companyName, $customerId);
                if ($stmt->execute()) {
                    $success = "Customer updated successfully!";
                } else {
                    $error = "Database error: " . $conn->error;
                }
            }
        }
    } elseif ($action === 'delete_customer') {
        $customerId = validateInput($_POST['id'] ?? 0, 'int', true);
        if ($customerId === false) {
            $error = "Invalid customer ID.";
        } elseif (!verifyIDOR('customer', $customerId, $companyId)) {
            $error = "Unauthorized access to this customer.";
        } else {
            $stmt = $conn->prepare("UPDATE customers SET status='inactive' WHERE id=? AND company_id=?");
            $stmt->bind_param("ii", $customerId, $companyId);
            if ($stmt->execute()) {
                $success = "Customer deleted successfully!";
            } else {
                $error = "Database error: " . $conn->error;
            }
        }
    }
}

// Get customers - show sample data for guests, real data for others
if ($currentUser['role'] === 'guest') {
    // Sample customers for guest demo
    $customers = [
        ['id' => 1, 'name' => 'John Doe', 'email' => 'john.doe@email.com', 'phone' => '+1-555-0123', 'address' => '123 Main St, City, State', 'company_name' => 'ABC Corp'],
        ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane.smith@email.com', 'phone' => '+1-555-0124', 'address' => '456 Oak Ave, City, State', 'company_name' => 'XYZ Ltd'],
        ['id' => 3, 'name' => 'Bob Johnson', 'email' => 'bob.johnson@email.com', 'phone' => '+1-555-0125', 'address' => '789 Pine Rd, City, State', 'company_name' => 'Tech Solutions'],
        ['id' => 4, 'name' => 'Alice Brown', 'email' => 'alice.brown@email.com', 'phone' => '+1-555-0126', 'address' => '321 Elm St, City, State', 'company_name' => 'Global Services']
    ];
} else {
    $customers = $conn->query("SELECT * FROM customers WHERE company_id = $companyId AND status='active' ORDER BY name");
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4>Customers</h4>
    <?php if ($currentUser['role'] !== 'guest'): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#customerModal">
            <i class="fas fa-plus"></i> Add Customer
        </button>
    <?php else: ?>
        <div class="alert alert-info py-2 mb-0">
            <small><i class="fas fa-info-circle me-1"></i>Demo customers - Sign up to manage your customer database</small>
        </div>
    <?php endif; ?>
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
                    <?php
                    if ($currentUser['role'] === 'guest') {
                        foreach ($customers as $customer):
                    ?>
                            <tr>
                                <td><strong><?= escape($customer['name']) ?></strong></td>
                                <td><?= escape($customer['company_name'] ?? '-') ?></td>
                                <td><?= escape($customer['email'] ?? '-') ?></td>
                                <td><?= escape($customer['phone'] ?? '-') ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary me-1" onclick="showAppModal('Demo Mode', 'This is sample data for demonstration purposes. Sign up to manage your own customers!', 'info')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <span class="text-muted small">Demo - View Only</span>
                                </td>
                            </tr>
                        <?php
                        endforeach;
                    } else {
                        while ($customer = $customers->fetch_assoc()):
                        ?>
                            <tr>
                                <td><?= escape($customer['name']) ?></td>
                                <td><?= escape($customer['company_name'] ?? '-') ?></td>
                                <td><?= escape($customer['email'] ?? '-') ?></td>
                                <td><?= escape($customer['phone'] ?? '-') ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="editCustomer(<?= $customer['id'] ?>, '<?= escapeJs($customer['name']) ?>', '<?= escapeJs($customer['email'] ?? '') ?>', '<?= escapeJs($customer['phone'] ?? '') ?>', '<?= escapeJs($customer['address'] ?? '') ?>', '<?= escapeJs($customer['company_name'] ?? '') ?>')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                        <input type="hidden" name="action" value="delete_customer">
                                        <input type="hidden" name="id" value="<?= $customer['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this customer?')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php } ?>
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
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
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