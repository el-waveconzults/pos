<?php
require_once 'config/config.php';
$conn = getDB();

// Get all companies with stats
$companies = $conn->query("
    SELECT c.*, 
           (SELECT COUNT(*) FROM users WHERE company_id = c.id AND status = 'active') as user_count,
           (SELECT COUNT(*) FROM sales s JOIN users u ON s.created_by = u.id WHERE u.company_id = c.id AND s.status = 'completed') as sales_count
    FROM companies c 
    ORDER BY c.created_at DESC
");

// Handle company actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyCSRFToken($csrfToken)) {
        $error = 'Invalid session token. Please refresh the page and try again.';
    } else {
        if ($action === 'update_company') {
            $stmt = $conn->prepare("UPDATE companies SET name=?, email=?, phone=?, address=? WHERE id=?");
            $stmt->bind_param("ssssi", $_POST['name'], $_POST['email'], $_POST['phone'], $_POST['address'], $_POST['id']);
            $stmt->execute();
            $success = "Company updated successfully!";
        } elseif ($action === 'toggle_status') {
            $newStatus = $_POST['current_status'] === 'active' ? 'inactive' : 'active';
            $conn->query("UPDATE companies SET status = '$newStatus' WHERE id = " . intval($_POST['id']));
            $success = "Company status updated!";
        } elseif ($action === 'delete_company') {
            $currentUser = getCurrentUser();
            if ($currentUser['role'] !== 'owner') {
                $error = 'Only super admin can remove a company.';
            } else {
                $companyId = intval($_POST['id']);
                $conn->begin_transaction();
                try {
                    $conn->query("DELETE FROM sale_items WHERE sale_id IN (SELECT id FROM sales WHERE created_by IN (SELECT id FROM users WHERE company_id = $companyId))");
                    $conn->query("DELETE FROM sales WHERE created_by IN (SELECT id FROM users WHERE company_id = $companyId)");
                    $conn->query("DELETE FROM invoices WHERE company_id = $companyId");
                    $conn->query("DELETE FROM expenses WHERE company_id = $companyId");
                    $conn->query("DELETE FROM products WHERE company_id = $companyId");
                    $conn->query("DELETE FROM categories WHERE company_id = $companyId");
                    $conn->query("DELETE FROM customers WHERE company_id = $companyId");
                    $conn->query("DELETE FROM branches WHERE company_id = $companyId");
                    $conn->query("DELETE FROM users WHERE company_id = $companyId");
                    $conn->query("DELETE FROM companies WHERE id = $companyId");
                    $conn->commit();
                    $success = "Company removed from database successfully.";
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = 'Failed to remove company. Please try again.';
                }
            }
        }
    }
}

// Refresh companies query after any action
$companies = $conn->query("
    SELECT c.*, 
           (SELECT COUNT(*) FROM users WHERE company_id = c.id AND status = 'active') as user_count,
           (SELECT COUNT(*) FROM sales s JOIN users u ON s.created_by = u.id WHERE u.company_id = c.id AND s.status = 'completed') as sales_count
    FROM companies c 
    ORDER BY c.created_at DESC
");

$totalCompanies = $conn->query("SELECT COUNT(*) as count FROM companies")->fetch_assoc()['count'];
$activeCompanies = $conn->query("SELECT COUNT(*) as count FROM companies WHERE status = 'active'")->fetch_assoc()['count'];
$totalUsers = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'active'")->fetch_assoc()['count'];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4><i class="fas fa-building"></i> Company Management</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#companyModal">
        <i class="fas fa-plus"></i> Add Company
    </button>
</div>

<?php if (isset($success)): ?>
    <div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>

<!-- Stats -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="stat-card primary">
            <h6>TOTAL COMPANIES</h6>
            <h2><?= $totalCompanies ?></h2>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card success">
            <h6>ACTIVE COMPANIES</h6>
            <h2><?= $activeCompanies ?></h2>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card warning">
            <h6>TOTAL USERS</h6>
            <h2><?= $totalUsers ?></h2>
        </div>
    </div>
</div>

<!-- Companies Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Company</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Users</th>
                        <th>Sales</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($company = $companies->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <strong><?= $company['name'] ?></strong>
                            </td>
                            <td><?= $company['email'] ?></td>
                            <td><?= $company['phone'] ?? '-' ?></td>
                            <td><span class="badge bg-info"><?= $company['user_count'] ?></span></td>
                            <td><span class="badge bg-success"><?= $company['sales_count'] ?></span></td>
                            <td>
                                <span class="badge bg-<?= $company['status'] == 'active' ? 'success' : 'secondary' ?>">
                                    <?= ucfirst($company['status']) ?>
                                </span>
                            </td>
                            <td><?= date('M d, Y', strtotime($company['created_at'])) ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick="editCompany(<?= $company['id'] ?>, '<?= addslashes($company['name']) ?>', '<?= $company['email'] ?>', '<?= addslashes($company['phone'] ?? '') ?>', '<?= addslashes($company['address'] ?? '') ?>')">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" style="display:inline; margin-left: 4px;">
                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="id" value="<?= $company['id'] ?>">
                                    <input type="hidden" name="current_status" value="<?= $company['status'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-<?= $company['status'] == 'active' ? 'warning' : 'success' ?>">
                                        <i class="fas fa-<?= $company['status'] == 'active' ? 'ban' : 'check' ?>"></i>
                                    </button>
                                </form>
                                <form method="POST" style="display:inline; margin-left: 4px;">
                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                    <input type="hidden" name="action" value="delete_company">
                                    <input type="hidden" name="id" value="<?= $company['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove this company and all associated data?')">
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

<!-- Company Modal -->
<div class="modal fade" id="companyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Company</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" id="companyAction" value="add_company">
                    <input type="hidden" name="id" id="companyId" value="">
                    <div class="mb-3">
                        <label class="form-label">Company Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <input type="text" name="address" class="form-control">
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
    function editCompany(id, name, email, phone, address) {
        document.getElementById('companyAction').value = 'update_company';
        document.getElementById('companyId').value = id;
        document.querySelector('#companyModal .modal-title').textContent = 'Edit Company';
        document.querySelector('#companyModal input[name="name"]').value = name;
        document.querySelector('#companyModal input[name="email"]').value = email;
        document.querySelector('#companyModal input[name="phone"]').value = phone;
        document.querySelector('#companyModal input[name="address"]').value = address;

        const modal = new bootstrap.Modal(document.getElementById('companyModal'));
        modal.show();
    }

    document.getElementById('companyModal').addEventListener('hidden.bs.modal', function() {
        document.getElementById('companyAction').value = 'add_company';
        document.getElementById('companyId').value = '';
        document.querySelector('#companyModal .modal-title').textContent = 'Add Company';
        document.querySelector('#companyModal form').reset();
    });
</script>