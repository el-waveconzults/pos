<?php
require_once 'config/config.php';
$conn = getDB();

$error = '';
$success = '';

// Get settings for logo display
$settings = getSettings();

// Plan prices removed - now handled by license system
// $plan_free_days = $settings['plan_free_days'] ?? 7;
// $plan_basic_price = $settings['plan_basic_price'] ?? 5000;
// $plan_premium_price = $settings['plan_premium_price'] ?? 15000;

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
                        // Create company (no plan/subscription handling - licenses managed separately)
                        $status = 'pending'; // Companies start as pending until license is assigned
                        $stmt = $conn->prepare("INSERT INTO companies (name, email, phone, address, status) VALUES (?, ?, ?, ?, ?)");
                        $stmt->bind_param("sssss", $company_name, $company_email, $company_phone, $company_address, $status);
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

                        // Send pending registration emails
                        $loginUrl = APP_URL . '/login.php';
                        sendPendingApprovalEmail($company_name, $company_email, $admin_name, $admin_email, $loginUrl);
                        sendCompanyPendingApprovalEmail($company_name, $company_email, $loginUrl);

                        $success = 'Registration successful! Your company is pending review by the super admin. You will be able to log in once approval is complete.';

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
            margin: 0;
            min-height: 100vh;
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: radial-gradient(circle at top left, rgba(59, 130, 246, 0.22), transparent 22%),
                radial-gradient(circle at bottom right, rgba(236, 72, 153, 0.16), transparent 18%),
                linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #e2e8f0;
        }

        .auth-page {
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            gap: 2rem;
            max-width: 1120px;
            margin: 0 auto;
            padding: 3rem 1rem;
            align-items: start;
        }

        .auth-side,
        .auth-card {
            border-radius: 32px;
            overflow: hidden;
        }

        .auth-side {
            position: relative;
            padding: 3rem;
            background: rgba(15, 23, 42, 0.94);
            border: 1px solid rgba(148, 163, 184, 0.16);
            box-shadow: 0 35px 90px rgba(15, 23, 42, 0.35);
        }

        .auth-side::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at top left, rgba(96, 165, 250, 0.28), transparent 28%),
                radial-gradient(circle at bottom right, rgba(236, 72, 153, 0.18), transparent 22%);
            opacity: 0.88;
            pointer-events: none;
        }

        .auth-side-inner {
            position: relative;
            z-index: 1;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.14em;
            color: #93c5fd;
            margin-bottom: 1.25rem;
        }

        .auth-side h1 {
            margin: 0;
            font-size: clamp(2.2rem, 4vw, 3rem);
            line-height: 1.05;
            color: #f8fafc;
        }

        .auth-side p {
            color: #cbd5e1;
            margin-top: 1rem;
            margin-bottom: 2rem;
            line-height: 1.8;
            max-width: 42rem;
        }

        .feature-list {
            display: grid;
            gap: 1rem;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            color: #e2e8f0;
            font-size: 0.98rem;
        }

        .feature-item i {
            color: #38bdf8;
            min-width: 1.5rem;
            text-align: center;
            font-size: 1.1rem;
        }

        .auth-card {
            background: #ffffff;
            box-shadow: 0 35px 90px rgba(15, 23, 42, 0.12);
        }

        .auth-card-header {
            padding: 2rem;
            text-align: center;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        }

        .welcome-title {
            margin: 0;
            font-size: clamp(2rem, 5vw, 2.8rem);
            font-weight: 800;
            letter-spacing: -0.03em;
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1.1;
        }

        .logo-box {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100px;
            height: 100px;
            border-radius: 28px;
            background: #eef2ff;
            overflow: hidden;
        }

        .logo-box img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .logo-box i {
            color: #2563eb;
            font-size: 2.5rem;
        }

        .auth-card-body {
            padding: 2.5rem;
            color: #0f172a;
        }

        .form-label {
            font-weight: 600;
            color: #0f172a;
        }

        .form-check-label {
            color: #0f172a;
            font-weight: 600;
        }

        .form-control {
            border-radius: 16px;
            border: 1px solid #cbd5e1;
            padding: 1rem 1rem;
        }

        .form-control:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.18);
        }

        .input-group-text {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-right: 0;
        }

        .form-check-input {
            width: 1.2rem;
            height: 1.2rem;
        }

        .btn-register {
            border-radius: 16px;
            padding: 1rem 1.25rem;
            font-weight: 700;
            background: #2563eb;
            border: none;
        }

        .btn-register:hover {
            background: #1d4ed8;
        }

        .section-title {
            color: #0f172a;
            border-bottom: 2px solid #cbd5e1;
            padding-bottom: 10px;
            margin-bottom: 20px;
            font-weight: 700;
        }

        .help-text {
            color: #475569;
            font-size: 0.95rem;
        }

        .auth-footer {
            margin-top: 1.75rem;
            text-align: center;
            color: #64748b;
            font-size: 0.95rem;
        }

        .auth-footer a {
            color: #2563eb;
            text-decoration: none;
        }

        @media (max-width: 990px) {
            .auth-page {
                grid-template-columns: 1fr;
                padding: 2rem 1rem;
            }

            .auth-side {
                order: 2;
            }
        }
    </style>
</head>

<body>

    <div class="auth-page">
        <div class="auth-side">
            <div class="auth-side-inner">
                <div class="eyebrow"><i class="fas fa-rocket"></i> Create your account</div>
                <h1>Register your company in minutes</h1>
                <p>Set up your business account, add your first branch, and start managing sales with ease.</p>
                <div class="feature-list">
                    <div class="feature-item"><i class="fas fa-check-circle"></i><span>Simple onboarding flow</span></div>
                    <div class="feature-item"><i class="fas fa-check-circle"></i><span>Company and admin setup</span></div>
                    <div class="feature-item"><i class="fas fa-check-circle"></i><span>Built for fast deployment</span></div>
                </div>
            </div>
        </div>

        <div class="auth-card">
            <div class="auth-card-header">
                <h2 class="welcome-title">GET STARTED</h2>
            </div>
            <div class="auth-card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?= $success ?>
                        <div class="mt-3">
                            <a href="login.php" class="btn btn-primary">Go to Login</a>
                        </div>
                    </div>
                <?php else: ?>
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <h5 class="section-title">Company Information</h5>

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

                        <div class="mb-3 form-check">
                            <input class="form-check-input" type="checkbox" id="hasBranches" name="has_branches" value="1" onchange="toggleBranchFields()" <?= isset($_POST['has_branches']) ? 'checked' : '' ?>>
                            <label class="form-check-label fw-bold" for="hasBranches">
                                <i class="fas fa-sitemap"></i> This company has multiple branches
                            </label>
                        </div>
                        <p class="help-text mb-4">Check this if you want to manage multiple store locations under one company.</p>

                        <div id="branchFields" style="display: none;">
                            <h5 class="section-title">Head Office (First Branch)</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Branch Name</label>
                                        <input type="text" name="branch_name" class="form-control" placeholder="Main Branch / Headquarters" value="<?= isset($_POST['branch_name']) ? htmlspecialchars($_POST['branch_name']) : '' ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Branch Phone</label>
                                        <input type="text" name="branch_phone" class="form-control" placeholder="+234..." value="<?= isset($_POST['branch_phone']) ? htmlspecialchars($_POST['branch_phone']) : '' ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Branch Address</label>
                                <input type="text" name="branch_address" class="form-control" placeholder="Branch address" value="<?= isset($_POST['branch_address']) ? htmlspecialchars($_POST['branch_address']) : '' ?>">
                            </div>
                        </div>

                        <h5 class="section-title mt-4">Admin User</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Admin Name</label>
                                    <input type="text" name="admin_name" class="form-control" placeholder="Admin name" required value="<?= isset($_POST['admin_name']) ? htmlspecialchars($_POST['admin_name']) : '' ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Admin Email</label>
                                    <input type="email" name="admin_email" class="form-control" placeholder="admin@example.com" required value="<?= isset($_POST['admin_email']) ? htmlspecialchars($_POST['admin_email']) : '' ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Password</label>
                                    <div class="input-group">
                                        <input type="password" name="admin_password" class="form-control" placeholder="Create a password" required id="admin_password">
                                        <button class="btn btn-outline-secondary" type="button" id="toggleAdminPassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Confirm Password</label>
                                    <div class="input-group">
                                        <input type="password" name="confirm_password" class="form-control" placeholder="Confirm password" required id="confirm_password">
                                        <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-register w-100 mt-3">Create account</button>
                    </form>
                <?php endif; ?>

                <div class="auth-footer">
                    <p class="mb-2">Already have an account? <a href="login.php">Sign in</a></p>
                    <p class="help-text">Your company information and admin user are set up in one secure step.</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleBranchFields() {
            const checkbox = document.getElementById('hasBranches');
            const branchFields = document.getElementById('branchFields');
            if (!branchFields) return;
            branchFields.style.display = checkbox.checked ? 'block' : 'none';
        }
        document.addEventListener('DOMContentLoaded', function() {
            toggleBranchFields();
        });

        // Password visibility toggle
        document.getElementById('toggleAdminPassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('admin_password');
            const icon = this.querySelector('i');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });

        document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('confirm_password');
            const icon = this.querySelector('i');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    </script>
</body>

</html>