<?php
require_once 'config/config.php';
$conn = getDB();

// Check access - only admin, manager, and owner can manage users
$currentUser = getCurrentUser();
if (!in_array($currentUser['role'], ['admin', 'manager', 'owner'])) {
    die('Access denied');
}

$companyId = $currentUser['company_id'] ?? null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_user') {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $role = $_POST['role'];
        $status = 'active';

        if ($companyId) {
            $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, status, company_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssi", $_POST['name'], $_POST['email'], $password, $role, $status, $companyId);
        } else {
            $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $_POST['name'], $_POST['email'], $password, $role, $status);
        }
        $stmt->execute();
        $success = "User added successfully!";
    } elseif ($action === 'update_user') {
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET name=?, email=?, role=?, password=? WHERE id=?");
            $stmt->bind_param("ssssi", $_POST['name'], $_POST['email'], $_POST['role'], $password, $_POST['id']);
        } else {
            $stmt = $conn->prepare("UPDATE users SET name=?, email=?, role=? WHERE id=?");
            $stmt->bind_param("sssi", $_POST['name'], $_POST['email'], $_POST['role'], $_POST['id']);
        }
        $stmt->execute();
        $success = "User updated successfully!";
    } elseif ($action === 'delete_user') {
        $conn->query("UPDATE users SET status='inactive' WHERE id=" . intval($_POST['id']));
        $success = "User deleted successfully!";
    }
}

// Filter users by company and role (owner sees all, admin/manager see only their company)
// Owner can see all users, Admin can see manager and cashier, Manager can see only cashier
if ($currentUser['role'] === 'owner') {
    $users = $conn->query("SELECT * FROM users WHERE status='active' ORDER BY name");
} elseif ($currentUser['role'] === 'admin') {
    // Admin sees manager and cashier only (not owner or other admins)
    if ($companyId) {
        $users = $conn->query("SELECT * FROM users WHERE company_id = $companyId AND role IN ('manager', 'cashier') AND status='active' ORDER BY name");
    } else {
        $users = $conn->query("SELECT * FROM users WHERE role IN ('manager', 'cashier') AND status='active' ORDER BY name");
    }
} elseif ($currentUser['role'] === 'manager') {
    // Manager sees only cashiers
    if ($companyId) {
        $users = $conn->query("SELECT * FROM users WHERE company_id = $companyId AND role = 'cashier' AND status='active' ORDER BY name");
    } else {
        $users = $conn->query("SELECT * FROM users WHERE role = 'cashier' AND status='active' ORDER BY name");
    }
} else {
    $users = $conn->query("SELECT * FROM users WHERE status='active' ORDER BY name");
}

// Determine allowed roles based on current user role
$allowedRoles = [];
if ($currentUser['role'] === 'owner') {
    $allowedRoles = ['admin' => 'Company Admin', 'manager' => 'Manager', 'cashier' => 'Cashier'];
} elseif ($currentUser['role'] === 'admin') {
    $allowedRoles = ['manager' => 'Manager', 'cashier' => 'Cashier'];
} elseif ($currentUser['role'] === 'manager') {
    $allowedRoles = ['cashier' => 'Cashier'];
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4><i class="fas fa-users"></i> Staff Accounts</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal">
        <i class="fas fa-plus"></i> Add User
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
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($user = $users->fetch_assoc()): ?>
                        <tr>
                            <td><?= $user['name'] ?></td>
                            <td><?= $user['email'] ?></td>
                            <td>
                                <span class="badge bg-<?= $user['role'] == 'admin' ? 'danger' : ($user['role'] == 'manager' ? 'warning' : 'info') ?>">
                                    <?= ucfirst($user['role']) ?>
                                </span>
                            </td>
                            <td><span class="badge bg-success"><?= ucfirst($user['status']) ?></span></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick="editUser(<?= $user['id'] ?>, '<?= addslashes($user['name']) ?>', '<?= $user['email'] ?>', '<?= $user['role'] ?>')">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this user?')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- User Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" id="userAction" value="add_user">
                    <input type="hidden" name="id" id="userId" value="">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" id="userPassword" required>
                        <small class="text-muted">Leave empty to keep current password when editing</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-select" id="userRole">
                            <?php foreach ($allowedRoles as $value => $label): ?>
                                <option value="<?= $value ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
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
    function editUser(id, name, email, role) {
        document.getElementById('userAction').value = 'update_user';
        document.getElementById('userId').value = id;
        document.querySelector('#userModal .modal-title').textContent = 'Edit User';
        document.querySelector('#userModal input[name="name"]').value = name;
        document.querySelector('#userModal input[name="email"]').value = email;
        document.querySelector('#userModal select[name="role"]').value = role;
        document.getElementById('userPassword').required = false;

        const modal = new bootstrap.Modal(document.getElementById('userModal'));
        modal.show();
    }

    document.getElementById('userModal').addEventListener('hidden.bs.modal', function() {
        document.getElementById('userAction').value = 'add_user';
        document.getElementById('userId').value = '';
        document.querySelector('#userModal .modal-title').textContent = 'Add User';
        document.querySelector('#userModal form').reset();
        document.getElementById('userPassword').required = true;

        // Reset role dropdown to first option
        const roleSelect = document.getElementById('userRole');
        if (roleSelect.options.length > 0) {
            roleSelect.selectedIndex = 0;
        }
    });
</script>