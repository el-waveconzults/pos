<?php
require_once 'config/config.php';
$conn = getDB();

function saveSetting($conn, $key, $value)
{
    $safeKey = $conn->real_escape_string($key);
    $safeValue = $conn->real_escape_string($value);
    $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('$safeKey', '$safeValue') ON DUPLICATE KEY UPDATE setting_value = '$safeValue'");
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid session token. Please refresh the page and try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'update_app_name') {
            $currentUser = getCurrentUser();
            if ($currentUser['role'] !== 'owner') {
                $error = 'Only super admin can update app name!';
            } else {
                $company_name = sanitize($_POST['company_name']);
                saveSetting($conn, 'company_name', $company_name);

                if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
                    $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif'];
                    if (in_array($_FILES['company_logo']['type'], $allowedTypes, true)) {
                        $uploadDir = 'uploads/logos/';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }
                        $fileName = time() . '_' . basename($_FILES['company_logo']['name']);
                        $targetPath = $uploadDir . $fileName;
                        if (move_uploaded_file($_FILES['company_logo']['tmp_name'], $targetPath)) {
                            saveSetting($conn, 'company_logo', $targetPath);
                        }
                    }
                }

                $success = 'App name and logo updated successfully!';
            }
        } elseif ($action === 'update_login_settings') {
            saveSetting($conn, 'require_email_verification', isset($_POST['require_email_verification']) ? '1' : '0');
            saveSetting($conn, 'login_attempt_limit', intval($_POST['login_attempt_limit'] ?? 5));
            saveSetting($conn, 'login_lockout_minutes', intval($_POST['login_lockout_minutes'] ?? 10));
            saveSetting($conn, 'allow_guest_access', isset($_POST['allow_guest_access']) ? '1' : '0');
            $success = 'Login settings saved successfully!';
        } elseif ($action === 'update_payment_settings') {
            saveSetting($conn, 'enable_payments', isset($_POST['enable_payments']) ? '1' : '0');
            saveSetting($conn, 'payment_gateway', sanitize($_POST['payment_gateway'] ?? ''));
            saveSetting($conn, 'transaction_fee', floatval($_POST['transaction_fee'] ?? 0));
            saveSetting($conn, 'payment_currency', sanitize($_POST['payment_currency'] ?? '₦'));
            $success = 'Payment settings saved successfully!';
        } elseif ($action === 'update_security_notifications') {
            saveSetting($conn, 'security_questions_enabled', isset($_POST['security_questions_enabled']) ? '1' : '0');
            saveSetting($conn, 'security_question_1', sanitize($_POST['security_question_1'] ?? ''));
            saveSetting($conn, 'security_question_2', sanitize($_POST['security_question_2'] ?? ''));
            saveSetting($conn, 'notify_low_stock_email', isset($_POST['notify_low_stock_email']) ? '1' : '0');
            saveSetting($conn, 'notify_new_user_email', isset($_POST['notify_new_user_email']) ? '1' : '0');
            saveSetting($conn, 'notify_sales_email', isset($_POST['notify_sales_email']) ? '1' : '0');
            $success = 'Security and notification settings saved successfully!';
        } elseif ($action === 'update_about') {
            saveSetting($conn, 'app_description', sanitize($_POST['app_description'] ?? ''));
            saveSetting($conn, 'company_address', sanitize($_POST['company_address'] ?? ''));
            saveSetting($conn, 'company_phone', sanitize($_POST['company_phone'] ?? ''));
            saveSetting($conn, 'company_email', sanitize($_POST['company_email'] ?? ''));
            saveSetting($conn, 'support_email', sanitize($_POST['support_email'] ?? ''));
            saveSetting($conn, 'support_phone', sanitize($_POST['support_phone'] ?? ''));
            $success = 'About settings saved successfully!';
        } elseif ($action === 'close_account') {
            $currentUser = getCurrentUser();
            if ($currentUser['role'] === 'owner') {
                $error = 'Super admin account closure is not supported here.';
            } else {
                $closePassword = $_POST['close_password'] ?? '';
                $closeConfirm = isset($_POST['close_confirm']);
                if (!$closeConfirm) {
                    $error = 'Please confirm account closure before proceeding.';
                } else {
                    $stmt = $conn->prepare('SELECT password, company_id FROM users WHERE id = ?');
                    $stmt->bind_param('i', $currentUser['id']);
                    $stmt->execute();
                    $userData = $stmt->get_result()->fetch_assoc();

                    if (!$userData || !password_verify($closePassword, $userData['password'])) {
                        $error = 'Invalid password. Account closure was not completed.';
                    } else {
                        $companyId = intval($userData['company_id']);
                        if ($companyId > 0) {
                            $conn->query("UPDATE companies SET status='inactive' WHERE id = $companyId");
                            $conn->query("UPDATE users SET status='inactive' WHERE company_id = $companyId");
                            logoutUser();
                            redirect('login.php?account_closed=1');
                        } else {
                            $error = 'Unable to find account to close.';
                        }
                    }
                }
            }
        } elseif ($action === 'update_settings') {
            foreach ($_POST as $key => $value) {
                if ($key !== 'action' && $key !== 'csrf_token') {
                    saveSetting($conn, $key, $value);
                }
            }
            $success = 'Settings updated successfully!';
        } elseif ($action === 'update_receipt_settings') {
            $receiptFields = ['receipt_header', 'receipt_footer', 'show_logo_on_receipt', 'show_company_info_on_receipt'];
            foreach ($receiptFields as $field) {
                $value = $_POST[$field] ?? ($field === 'show_logo_on_receipt' || $field === 'show_company_info_on_receipt' ? '0' : '');
                saveSetting($conn, $field, $value);
            }

            if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = 'uploads/logos/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $fileName = time() . '_' . basename($_FILES['company_logo']['name']);
                $targetPath = $uploadDir . $fileName;
                if (move_uploaded_file($_FILES['company_logo']['tmp_name'], $targetPath)) {
                    saveSetting($conn, 'company_logo', $targetPath);
                }
            }
            $success = 'Receipt settings updated successfully!';
        } elseif ($action === 'add_user') {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $company_id = $_SESSION['user_id'] ? $conn->query('SELECT company_id FROM users WHERE id=' . intval($_SESSION['user_id']))->fetch_assoc()['company_id'] ?? 0 : 0;
            $branch_id = !empty($_POST['branch_id']) ? intval($_POST['branch_id']) : null;
            $role = $_POST['role'];

            $stmt = $conn->prepare('INSERT INTO users (name, email, password, role, company_id, branch_id) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('ssssii', $_POST['name'], $_POST['email'], $password, $role, $company_id, $branch_id);
            $stmt->execute();
            $success = 'User added successfully!';
        } elseif ($action === 'delete_user') {
            $conn->query('UPDATE users SET status="inactive" WHERE id=' . intval($_POST['id']));
            $success = 'User deleted successfully!';
        } elseif ($action === 'add_branch') {
            $company_id = $_SESSION['user_id'] ? $conn->query('SELECT company_id FROM users WHERE id=' . intval($_SESSION['user_id']))->fetch_assoc()['company_id'] ?? 0 : 0;
            $stmt = $conn->prepare('INSERT INTO branches (company_id, name, address, phone, email, status) VALUES (?, ?, ?, ?, ?, "active")');
            $stmt->bind_param('issss', $company_id, $_POST['name'], $_POST['address'], $_POST['phone'], $_POST['email']);
            $stmt->execute();
            $success = 'Branch added successfully!';
        } elseif ($action === 'delete_branch') {
            $conn->query('UPDATE branches SET status="inactive" WHERE id=' . intval($_POST['branch_id']));
            $success = 'Branch deleted successfully!';
        }
    }
}

$settings = getSettings();
$currentUser = getCurrentUser();
$company_id = $currentUser['company_id'] ?? 0;
$user_role = $currentUser['role'] ?? '';

if ($company_id > 0) {
    $users = $conn->query("SELECT * FROM users WHERE company_id = $company_id AND status='active' ORDER BY name");
    $branches_result = $conn->query("SELECT * FROM branches WHERE company_id = $company_id AND status='active' ORDER BY name");
} else {
    $users = null;
    $branches_result = null;
}

// Get plan prices from settings
$plan_free_price = $settings['plan_free_price'] ?? 0;
$plan_basic_price = $settings['plan_basic_price'] ?? 5000;
$plan_premium_price = $settings['plan_premium_price'] ?? 15000;
?>

<?php if (isset($success)): ?>
    <div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>
<?php if (isset($error) && $error): ?>
    <div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<?php
$currentUser = getCurrentUser();
$isSuperAdmin = ($currentUser['role'] === 'owner');
?>

<?php if ($isSuperAdmin): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-cog"></i> App Name Settings (Super Admin Only)</h5>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="action" value="update_app_name">
                <div class="mb-3">
                    <label class="form-label"><strong>Application Name</strong></label>
                    <input type="text" name="company_name" class="form-control form-control-lg" value="<?= htmlspecialchars($settings['company_name'] ?? 'ELWAVE-POS', ENT_QUOTES) ?>" placeholder="Enter app name">
                    <small class="text-muted">This name appears in the sidebar, login page, and throughout the app.</small>
                </div>
                <div class="mb-4">
                    <label class="form-label"><strong>Application Logo</strong></label>
                    <?php if (!empty($settings['company_logo'])): ?>
                        <div class="mb-3">
                            <img src="<?= htmlspecialchars($settings['company_logo'], ENT_QUOTES) ?>" alt="Current logo" style="max-height: 120px; display: block; margin-bottom: 10px;">
                        </div>
                    <?php endif; ?>
                    <input type="file" name="company_logo" class="form-control">
                    <small class="text-muted">Upload a PNG, JPG, JPEG, or GIF logo for the application and receipts.</small>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save App Name
                </button>
            </form>
        </div>
    </div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header">
        <ul class="nav nav-tabs card-header-tabs" id="settingsTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="tab-login-settings" data-bs-toggle="tab" data-bs-target="#login-settings" type="button" role="tab">Login Settings</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-payment-settings" data-bs-toggle="tab" data-bs-target="#payment-settings" type="button" role="tab">Payments</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-security-settings" data-bs-toggle="tab" data-bs-target="#security-settings" type="button" role="tab">Security & Notifications</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-about-settings" data-bs-toggle="tab" data-bs-target="#about-settings" type="button" role="tab">About</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-close-account" data-bs-toggle="tab" data-bs-target="#close-account" type="button" role="tab">Close Account</button>
            </li>
        </ul>
    </div>
    <div class="card-body">
        <div class="tab-content" id="settingsTabsContent">
            <div class="tab-pane fade show active" id="login-settings" role="tabpanel">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="update_login_settings">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3 form-check">
                                <input type="checkbox" name="require_email_verification" class="form-check-input" id="requireEmailVerification" <?= isset($settings['require_email_verification']) && $settings['require_email_verification'] === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="requireEmailVerification">Require email verification</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Login attempt limit</label>
                                <input type="number" name="login_attempt_limit" class="form-control" value="<?= intval($settings['login_attempt_limit'] ?? 5) ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Lockout period (minutes)</label>
                                <input type="number" name="login_lockout_minutes" class="form-control" value="<?= intval($settings['login_lockout_minutes'] ?? 10) ?>">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="allow_guest_access" class="form-check-input" id="allowGuestAccess" <?= isset($settings['allow_guest_access']) && $settings['allow_guest_access'] === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="allowGuestAccess">Allow guest access</label>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Login Settings</button>
                </form>
            </div>

            <div class="tab-pane fade" id="payment-settings" role="tabpanel">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="update_payment_settings">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3 form-check">
                                <input type="checkbox" name="enable_payments" class="form-check-input" id="enablePayments" <?= isset($settings['enable_payments']) && $settings['enable_payments'] === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="enablePayments">Enable online payments</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Payment gateway</label>
                                <input type="text" name="payment_gateway" class="form-control" value="<?= htmlspecialchars($settings['payment_gateway'] ?? 'Paystack', ENT_QUOTES) ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Transaction fee (%)</label>
                                <input type="number" step="0.01" name="transaction_fee" class="form-control" value="<?= htmlspecialchars($settings['transaction_fee'] ?? '0', ENT_QUOTES) ?>">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment currency</label>
                        <input type="text" name="payment_currency" class="form-control" value="<?= htmlspecialchars($settings['payment_currency'] ?? '₦', ENT_QUOTES) ?>">
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Payment Settings</button>
                </form>
            </div>

            <div class="tab-pane fade" id="security-settings" role="tabpanel">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="update_security_notifications">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3 form-check">
                                <input type="checkbox" name="security_questions_enabled" class="form-check-input" id="securityQuestionsEnabled" <?= isset($settings['security_questions_enabled']) && $settings['security_questions_enabled'] === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="securityQuestionsEnabled">Enable security questions</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3 form-check">
                                <input type="checkbox" name="notify_sales_email" class="form-check-input" id="notifySalesEmail" <?= isset($settings['notify_sales_email']) && $settings['notify_sales_email'] === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="notifySalesEmail">Notify on new sales</label>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Security Question 1</label>
                        <input type="text" name="security_question_1" class="form-control" value="<?= htmlspecialchars($settings['security_question_1'] ?? 'What is your mother\'s maiden name?', ENT_QUOTES) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Security Question 2</label>
                        <input type="text" name="security_question_2" class="form-control" value="<?= htmlspecialchars($settings['security_question_2'] ?? 'What was your first pet\'s name?', ENT_QUOTES) ?>">
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3 form-check">
                                <input type="checkbox" name="notify_low_stock_email" class="form-check-input" id="notifyLowStockEmail" <?= isset($settings['notify_low_stock_email']) && $settings['notify_low_stock_email'] === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="notifyLowStockEmail">Notify on low stock</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3 form-check">
                                <input type="checkbox" name="notify_new_user_email" class="form-check-input" id="notifyNewUserEmail" <?= isset($settings['notify_new_user_email']) && $settings['notify_new_user_email'] === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="notifyNewUserEmail">Notify on new user registration</label>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Security & Notification Settings</button>
                </form>
            </div>

            <div class="tab-pane fade" id="about-settings" role="tabpanel">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="update_about">
                    <div class="mb-3">
                        <label class="form-label">Application Description</label>
                        <textarea name="app_description" class="form-control" rows="3"><?= htmlspecialchars($settings['app_description'] ?? 'Easy POS web app for small businesses.', ENT_QUOTES) ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Support Email</label>
                        <input type="email" name="support_email" class="form-control" value="<?= htmlspecialchars($settings['support_email'] ?? 'support@yourcompany.com', ENT_QUOTES) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Support Phone</label>
                        <input type="text" name="support_phone" class="form-control" value="<?= htmlspecialchars($settings['support_phone'] ?? '+2340000000000', ENT_QUOTES) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Company Address</label>
                        <textarea name="company_address" class="form-control" rows="2"><?= htmlspecialchars($settings['company_address'] ?? '', ENT_QUOTES) ?></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Company Phone</label>
                                <input type="text" name="company_phone" class="form-control" value="<?= htmlspecialchars($settings['company_phone'] ?? '', ENT_QUOTES) ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Company Email</label>
                                <input type="email" name="company_email" class="form-control" value="<?= htmlspecialchars($settings['company_email'] ?? '', ENT_QUOTES) ?>">
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save About Settings</button>
                </form>
            </div>

            <div class="tab-pane fade" id="close-account" role="tabpanel">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="close_account">
                    <?php if ($currentUser['role'] === 'owner'): ?>
                        <div class="alert alert-warning">Super admin account closure is not supported here.</div>
                    <?php else: ?>
                        <div class="mb-3">
                            <label class="form-label">Confirm your password</label>
                            <input type="password" name="close_password" class="form-control" required>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" name="close_confirm" class="form-check-input" id="closeConfirm" required>
                            <label class="form-check-label" for="closeConfirm">I understand this will deactivate my company account and all users.</label>
                        </div>
                        <button type="submit" class="btn btn-danger"><i class="fas fa-lock"></i> Close Account</button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    var triggerTabList = [].slice.call(document.querySelectorAll('#settingsTabs button'));
    triggerTabList.forEach(function(triggerEl) {
        var tabTrigger = new bootstrap.Tab(triggerEl);
        triggerEl.addEventListener('click', function(event) {
            event.preventDefault();
            tabTrigger.show();
        });
    });
</script>

<!-- User Management -->
<div class="col-md-6">
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-users"></i> User Management</h5>
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#userModal">
                <i class="fas fa-plus"></i> Add User
            </button>
        </div>
        <div class="card-body">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Branch</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($users): while ($user = $users->fetch_assoc()):
                            $userBranch = $user['branch_id'] ? getBranch($user['branch_id']) : null;
                    ?>
                            <tr>
                                <td><?= $user['name'] ?></td>
                                <td><?= $user['email'] ?></td>
                                <td><span class="badge bg-<?= $user['role'] == 'admin' ? 'danger' : ($user['role'] == 'manager' ? 'warning' : 'info') ?>"><?= ucfirst($user['role']) ?></span></td>
                                <td><?= $userBranch ? $userBranch['name'] : '<span class="text-muted">All Branches</span>' ?></td>
                                <td>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this user?')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile;
                    else: ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted">No users found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>

<!-- Branch Management -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-sitemap"></i> Branch Management</h5>
                <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#branchModal">
                    <i class="fas fa-plus"></i> Add Branch
                </button>
            </div>
            <div class="card-body">
                <?php if ($branches_result && $branches_result->num_rows > 0): ?>
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Branch Name</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th>Address</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($branch = $branches_result->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?= $branch['name'] ?></strong></td>
                                    <td><?= $branch['phone'] ?? '-' ?></td>
                                    <td><?= $branch['email'] ?? '-' ?></td>
                                    <td><?= $branch['address'] ?? '-' ?></td>
                                    <td><span class="badge bg-success"><?= ucfirst($branch['status']) ?></span></td>
                                    <td>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                            <input type="hidden" name="action" value="delete_branch">
                                            <input type="hidden" name="branch_id" value="<?= $branch['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this branch?')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-muted text-center py-4">
                        <i class="fas fa-sitemap fa-2x mb-2 d-block"></i>
                        No branches added yet.<br>
                        Add branches to manage multiple store locations.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="add_user">
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
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-select" id="userRoleSelect" onchange="toggleBranchManagerOption()">
                            <option value="cashier">Cashier</option>
                            <option value="manager">Manager (All Branches)</option>
                            <option value="branch_manager">Branch Manager (Specific Branch)</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="mb-3" id="branchSelectDiv">
                        <label class="form-label">Assign to Branch</label>
                        <select name="branch_id" class="form-select">
                            <option value="">Select Branch</option>
                            <?php
                            $branches_list = $conn->query("SELECT * FROM branches WHERE company_id = $company_id AND status='active' ORDER BY name");
                            while ($b = $branches_list->fetch_assoc()): ?>
                                <option value="<?= $b['id'] ?>"><?= $b['name'] ?></option>
                            <?php endwhile; ?>
                        </select>
                        <small class="text-muted">Select the branch this manager will be in charge of</small>
                    </div>
                    <script>
                        function toggleBranchManagerOption() {
                            var role = document.getElementById('userRoleSelect').value;
                            var branchDiv = document.getElementById('branchSelectDiv');
                            if (role === 'branch_manager') {
                                branchDiv.style.display = 'block';
                            } else {
                                branchDiv.style.display = 'block';
                            }
                        }
                    </script>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Branch Modal -->
<div class="modal fade" id="branchModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-store"></i> Add New Branch</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="add_branch">
                    <div class="mb-3">
                        <label class="form-label">Branch Name</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g., Lagos Branch" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control" placeholder="+234...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" placeholder="branch@company.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control" rows="2" placeholder="Branch address"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-success">Add Branch</button>
                </div>
            </form>
        </div>
    </div>
</div>