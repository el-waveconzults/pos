<?php
require_once 'config/config.php';
$conn = getDB();

$error = '';
$success = '';

// Get plan prices from settings
$settings = getSettings();
$plan_free_days = $settings['plan_free_days'] ?? 7;
$plan_basic_price = $settings['plan_basic_price'] ?? 5000;
$plan_premium_price = $settings['plan_premium_price'] ?? 15000;

// Handle company registration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid session token. Please refresh the page and try again.';
    } else {
        $company_name = sanitize($_POST['company_name']);
        $company_email = sanitize($_POST['company_email']);
        $company_phone = sanitize($_POST['company_phone']);
        $company_address = sanitize($_POST['company_address']);

        $admin_name = sanitize($_POST['admin_name']);
        $admin_email = sanitize($_POST['admin_email']);
        $admin_password = $_POST['admin_password'];
        $confirm_password = $_POST['confirm_password'];

        // Plan selection
        $plan = $_POST['plan'] ?? 'free';

        // Branch options
        $has_branches = isset($_POST['has_branches']) ? 1 : 0;
        $branch_name = sanitize($_POST['branch_name'] ?? '');
        $branch_phone = sanitize($_POST['branch_phone'] ?? '');
        $branch_address = sanitize($_POST['branch_address'] ?? '');

        // Validation
        if (empty($company_name) || empty($company_email) || empty($admin_name) || empty($admin_email) || empty($admin_password)) {
            $error = 'All fields are required';
        } elseif ($admin_password !== $confirm_password) {
            $error = 'Passwords do not match';
        } elseif (strlen($admin_password) < 6) {
            $error = 'Password must be at least 6 characters';
        } else {
            // Check if company email already exists
            $stmt = $conn->prepare("SELECT id FROM companies WHERE email = ?");
            $stmt->bind_param("s", $company_email);
            $stmt->execute();

            if ($stmt->get_result()->num_rows > 0) {
                $error = 'Company email already registered';
            } else {
                // Check if admin email already exists
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->bind_param("s", $admin_email);
                $stmt->execute();

                if ($stmt->get_result()->num_rows > 0) {
                    $error = 'Admin email already registered';
                } else {
                    // Start transaction
                    $conn->begin_transaction();

                    try {
                        // Determine subscription status based on plan
                        if ($plan === 'free') {
                            // Free trial - use dynamic days from settings
                            $subscription_status = 'trial';
                            $trial_start = date('Y-m-d');
                            $expiry_date = date('Y-m-d', strtotime('+' . $plan_free_days . ' days'));
                        } else {
                            // Paid plans - activate immediately
                            $subscription_status = 'active';
                            $trial_start = date('Y-m-d');
                            $expiry_date = date('Y-m-d', strtotime('+30 days'));
                        }

                        // Create company with subscription details
                        $status = 'active';
                        $stmt = $conn->prepare("INSERT INTO companies (name, email, phone, address, plan, subscription_status, trial_start, expiry_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("sssssssss", $company_name, $company_email, $company_phone, $company_address, $plan, $subscription_status, $trial_start, $expiry_date, $status);
                        $stmt->execute();
                        $company_id = $conn->insert_id;

                        // Create first branch if company has multiple branches
                        $branch_id = null;
                        if ($has_branches && !empty($branch_name)) {
                            $branch_status = 'active';
                            $stmt = $conn->prepare("INSERT INTO branches (company_id, name, phone, address, status) VALUES (?, ?, ?, ?, ?)");
                            $stmt->bind_param("issss", $company_id, $branch_name, $branch_phone, $branch_address, $branch_status);
                            $stmt->execute();
                            $branch_id = $conn->insert_id;
                        }

                        // Create admin user for this company
                        $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
                        $role = 'admin';
                        $status = 'active';
                        $null_branch = null;
                        $email_verified = 1;
                        $verification_token = null;

                        $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, status, company_id, branch_id, email_verified, verification_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("sssssiiis", $admin_name, $admin_email, $hashed_password, $role, $status, $company_id, $null_branch, $email_verified, $verification_token);
                        $stmt->execute();
                        $user_id = $conn->insert_id;

                        $conn->commit();

                        $success = 'Registration successful! You may now log in with your credentials.';

                        echo '<script>setTimeout(function(){ window.location.href = "login.php"; }, 3000);</script>';
                    } catch (Exception $e) {
                        $conn->rollback();
                        $error = 'Registration failed. Please try again. Error: ' . $e->getMessage();
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Company - <?= getAppName() ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            padding: 40px 0;
        }

        .register-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            max-width: 700px;
            margin: 0 auto;
            overflow: hidden;
        }

        .register-header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .register-body {
            padding: 40px;
        }

        .form-control:focus {
            border-color: #2a5298;
            box-shadow: 0 0 0 0.2rem rgba(42, 82, 152, 0.25);
        }

        .btn-register {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            border: none;
            padding: 12px;
            font-weight: 600;
        }

        .section-title {
            color: #1e3c72;
            border-bottom: 2px solid #1e3c72;
            padding-bottom: 10px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .login-link {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
        }

        .plan-card {
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
        }

        .plan-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .plan-card.border-primary {
            border-color: #0d6efd;
            background-color: #f8f9fa;
        }

        .plan-card input[type="radio"] {
            margin-top: 10px;
        }
    </style>
</head>

<body>

    <div class="register-card">
        <div class="register-header">
            <h3><i class="fas fa-building"></i> Register Your Company</h3>
            <p class="mb-0">Create your business account and get started</p>
        </div>

        <div class="register-body">
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= $success ?>
                    <div class="mt-2">
                        <a href="login.php" class="btn btn-primary">Go to Login Now</a>
                    </div>
                </div>
            <?php else: ?>
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <!-- Company Information -->
                    <h5 class="section-title"><i class="fas fa-building"></i> Company Information</h5>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Company Name</label>
                                <input type="text" name="company_name" class="form-control" placeholder="Enter company name" required value="<?= isset($_POST['company_name']) ? htmlspecialchars($_POST['company_name']) : '' ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Company Email</label>
                                <input type="email" name="company_email" class="form-control" placeholder="company@example.com" required value="<?= isset($_POST['company_email']) ? htmlspecialchars($_POST['company_email']) : '' ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Phone Number</label>
                                <input type="text" name="company_phone" class="form-control" placeholder="+234..." value="<?= isset($_POST['company_phone']) ? htmlspecialchars($_POST['company_phone']) : '' ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Address</label>
                                <input type="text" name="company_address" class="form-control" placeholder="Company address" value="<?= isset($_POST['company_address']) ? htmlspecialchars($_POST['company_address']) : '' ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Plan Selection -->
                    <h5 class="section-title mt-4"><i class="fas fa-tags"></i> Choose Your Plan</h5>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <div class="card plan-card <?= (isset($_POST['plan']) && $_POST['plan'] === 'free') ? 'border-primary' : '' ?>" onclick="selectPlan('free')">
                                <div class="card-body text-center">
                                    <h5><i class="fas fa-gift text-primary"></i> Free Trial</h5>
                                    <p class="text-muted mb-0"><?= $plan_free_days ?> Days Free</p>
                                    <input type="radio" name="plan" value="free" id="planFree" <?= (!isset($_POST['plan']) || $_POST['plan'] === 'free') ? 'checked' : '' ?>>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card plan-card <?= (isset($_POST['plan']) && $_POST['plan'] === 'basic') ? 'border-primary' : '' ?>" onclick="selectPlan('basic')">
                                <div class="card-body text-center">
                                    <h5><i class="fas fa-star text-success"></i> Basic</h5>
                                    <p class="text-muted mb-0">₦<?= number_format($plan_basic_price) ?>/month</p>
                                    <input type="radio" name="plan" value="basic" id="planBasic" <?= isset($_POST['plan']) && $_POST['plan'] === 'basic' ? 'checked' : '' ?>>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card plan-card <?= (isset($_POST['plan']) && $_POST['plan'] === 'premium') ? 'border-primary' : '' ?>" onclick="selectPlan('premium')">
                                <div class="card-body text-center">
                                    <h5><i class="fas fa-crown text-warning"></i> Premium</h5>
                                    <p class="text-muted mb-0">₦<?= number_format($plan_premium_price) ?>/month</p>
                                    <input type="radio" name="plan" value="premium" id="planPremium" <?= isset($_POST['plan']) && $_POST['plan'] === 'premium' ? 'checked' : '' ?>>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Branch Setup -->
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="hasBranches" name="has_branches" value="1" onchange="toggleBranchFields()">
                            <label class="form-check-label fw-bold" for="hasBranches">
                                <i class="fas fa-sitemap"></i> This company has multiple branches
                            </label>
                        </div>
                        <small class="text-muted">Check this if you want to manage multiple store locations under one company</small>
                    </div>

                    <div id="branchFields" style="display: none;">
                        <h5 class="section-title mt-3"><i class="fas fa-store"></i> Head Office (First Branch)</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Branch Name</label>
                                    <input type="text" name="branch_name" class="form-control" placeholder="e.g., Main Branch / Headquarters">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Branch Phone</label>
                                    <input type="text" name="branch_phone" class="form-control" placeholder="+234...">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Branch Address</label>
                            <textarea name="branch_address" class="form-control" rows="2" placeholder="Branch address"></textarea>
                        </div>
                    </div>

                    <script>
                        function toggleBranchFields() {
                            const checkbox = document.getElementById('hasBranches');
                            const branchFields = document.getElementById('branchFields');
                            branchFields.style.display = checkbox.checked ? 'block' : 'none';
                        }

                        function selectPlan(plan) {
                            document.getElementById('plan' + plan.charAt(0).toUpperCase() + plan.slice(1)).checked = true;
                            document.querySelectorAll('.plan-card').forEach(card => card.classList.remove('border-primary'));
                            event.currentTarget.classList.add('border-primary');
                        }
                    </script>

                    <!-- Admin Information -->
                    <h5 class="section-title mt-4"><i class="fas fa-user-shield"></i> Admin Account</h5>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Admin Full Name</label>
                                <input type="text" name="admin_name" class="form-control" placeholder="Your full name" required value="<?= isset($_POST['admin_name']) ? htmlspecialchars($_POST['admin_name']) : '' ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Admin Email</label>
                                <input type="email" name="admin_email" class="form-control" placeholder="admin@company.com" required value="<?= isset($_POST['admin_email']) ? htmlspecialchars($_POST['admin_email']) : '' ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" name="admin_password" class="form-control" placeholder="Create password (min 6 characters)" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Confirm Password</label>
                                <input type="password" name="confirm_password" class="form-control" placeholder="Confirm password" required>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-primary btn-register">
                            <i class="fas fa-user-plus"></i> Register Company
                        </button>
                    </div>
                </form>

            <?php endif; ?>

            <div class="login-link">
                <p class="mb-0">Already have a company account? <a href="login.php">Login here</a></p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>