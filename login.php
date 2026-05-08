<?php
require_once 'config/config.php';
$conn = getDB();

$error = '';

if (!empty($_GET['error'])) {
    $error = sanitize($_GET['error']);
}

// Get settings for logo
$settings = getSettings();

// Try auto-login first if session/cookies are still valid
autoLogin();
if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
    redirect('index.php?page=dashboard');
}

// Handle guest access
if (isset($_GET['guest'])) {
    // Set up guest session
    $_SESSION['user_id'] = 0;
    $_SESSION['user_name'] = 'Guest User';
    $_SESSION['user_role'] = 'guest';
    $_SESSION['company_id'] = 0;
    $_SESSION['branch_id'] = 0;
    redirect('index.php?page=guest_dashboard');
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid session token. Please refresh the page and try again.';
    } else {
        $email = sanitize($_POST['email']);
        $password = $_POST['password'];
        $remember = isset($_POST['remember']);

        // Rate limiting - max 5 attempts per 10 minutes
        $attemptKey = 'login_attempts_' . md5($email) . '_' . (int)(time() / 600);
        $_SESSION[$attemptKey] = ($_SESSION[$attemptKey] ?? 0) + 1;

        if ($_SESSION[$attemptKey] > 5) {
            $error = 'Too many login attempts. Please try again in 10 minutes.';
        } else {
            $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($user = $result->fetch_assoc()) {
                if (password_verify($password, $user['password'])) {
                    $companyStatus = 'active';
                    if (!empty($user['company_id'])) {
                        $companyStmt = $conn->prepare("SELECT status FROM companies WHERE id = ?");
                        $companyStmt->bind_param('i', $user['company_id']);
                        $companyStmt->execute();
                        $companyResult = $companyStmt->get_result();
                        $companyData = $companyResult->fetch_assoc();
                        $companyStatus = $companyData['status'] ?? 'inactive';
                    }

                    if ($companyStatus === 'pending') {
                        $error = 'Your company registration is under review by super admin. Please wait for approval before logging in.';
                    } elseif ($companyStatus !== 'active') {
                        $error = 'Your company account is not active. Please contact support.';
                    } else {
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['name'];
                        $_SESSION['user_role'] = $user['role'];
                        $_SESSION['company_id'] = $user['company_id'] ?? 0;
                        $_SESSION['branch_id'] = $user['branch_id'] ?? 0;

                        // Remember me functionality
                        if ($remember) {
                            rememberUser($user['id'], $user['name'], $user['role'], $user['company_id'] ?? 0, $user['branch_id'] ?? 0);
                        }

                        redirect('index.php?page=dashboard');
                    }
                } else {
                    $error = 'Invalid password';
                }
            } else {
                $error = 'User not found';
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
    <title>Login - <?= getAppName() ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            color-scheme: dark;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: radial-gradient(circle at top left, rgba(59, 130, 246, 0.24), transparent 20%),
                radial-gradient(circle at bottom right, rgba(236, 72, 153, 0.18), transparent 18%),
                linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #e2e8f0;
        }

        .auth-page {
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            gap: 2rem;
            max-width: 1080px;
            margin: 0 auto;
            padding: 3rem 1rem;
            align-items: center;
        }

        .auth-side,
        .auth-card {
            border-radius: 32px;
            overflow: hidden;
        }

        .auth-side {
            position: relative;
            padding: 3rem;
            background: rgba(15, 23, 42, 0.92);
            border: 1px solid rgba(148, 163, 184, 0.16);
            box-shadow: 0 35px 90px rgba(15, 23, 42, 0.35);
        }

        .auth-side::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at top left, rgba(96, 165, 250, 0.28), transparent 28%),
                radial-gradient(circle at bottom right, rgba(236, 72, 153, 0.18), transparent 22%);
            opacity: 0.85;
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
            font-size: clamp(2.2rem, 4vw, 3.2rem);
            line-height: 1.05;
            letter-spacing: -0.04em;
            color: #f8fafc;
        }

        .auth-side p {
            color: #cbd5e1;
            max-width: 42rem;
            margin-top: 1rem;
            margin-bottom: 2rem;
            line-height: 1.85;
        }

        .feature-list {
            display: grid;
            gap: 1rem;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 0.85rem;
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

        .btn-primary {
            border-radius: 16px;
            padding: 1rem 1.25rem;
            font-weight: 700;
            background: #2563eb;
            border: none;
        }

        .btn-primary:hover {
            background: #1d4ed8;
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

        /* Continue as Guest Button Styling */
        .guest-btn {
            background: linear-gradient(135deg, #64748b 0%, #475569 100%) !important;
            border: 2px solid #0ea5e9 !important;
            color: #f0f9ff !important;
            transition: all 0.3s ease;
        }

        .guest-btn:hover {
            background: linear-gradient(135deg, #0ea5e9 0%, #06b6d4 100%) !important;
            border-color: #0ea5e9 !important;
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(14, 165, 233, 0.4) !important;
            color: #fff !important;
        }

        .guest-btn i {
            transition: transform 0.3s ease;
        }

        .guest-btn:hover i {
            transform: scale(1.25) rotate(5deg);
        }
    </style>
</head>

<body>
    <div class="auth-page">
        <div class="auth-side">
            <div class="auth-side-inner">
                <div class="eyebrow"><i class="fas fa-shield-alt"></i> Secure Sign In</div>
                <h1>Welcome back to your sales dashboard</h1>
                <p>Sign in to manage inventory, track sales, and monitor your license status in one clean business portal.</p>
                <div class="feature-list">
                    <div class="feature-item"><i class="fas fa-check-circle"></i><span>Fast, secure access</span></div>
                    <div class="feature-item"><i class="fas fa-check-circle"></i><span>Centralized company management</span></div>
                    <div class="feature-item"><i class="fas fa-check-circle"></i><span>Easy role-based access</span></div>
                </div>
            </div>
        </div>

        <div class="auth-card">
            <div class="auth-card-header">
                <h2 class="welcome-title">WELCOME</h2>
            </div>
            <div class="auth-card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" name="email" class="form-control" placeholder="Enter email" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" name="password" class="form-control" placeholder="Enter password" required id="password">
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="remember" class="form-check-input" id="rememberMe">
                        <label class="form-check-label" for="rememberMe">Remember me</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-sign-in-alt me-2"></i>Login
                    </button>
                </form>

                <div class="my-4">
                    <div class="d-flex align-items-center">
                        <hr class="flex-grow-1">
                        <span class="px-3 small fw-5" style="color: #cbd5e1; letter-spacing: 0.5px;">or</span>
                        <hr class="flex-grow-1">
                    </div>
                </div>

                <div class="text-center">
                    <p class=\"mb-3\" style=\"font-size: 1rem; letter-spacing: 0.5px; color: #ffffff; font-weight: 600;\">Explore as a guest</p>
                    <a href="?guest=1" class="btn w-100 guest-btn" style="padding: 14px 0; border-width: 2px; font-weight: 600; transition: all 0.3s ease; background: linear-gradient(135deg, #64748b 0%, #475569 100%); border-color: #0ea5e9; color: #f0f9ff; font-size: 1.05rem;">
                        <i class="fas fa-user-secret me-2"></i>Continue as Guest
                    </a>
                </div>

                <div class="auth-footer">
                    <p class="mb-2">Need an account? <a href="register_company.php">Register your company</a></p>
                    <p class="help-text">Use your company email and secure password to sign in.</p>
                </div>

                <div class="support-block mt-4 p-3 rounded-3 border bg-light text-start">
                    <p class="mb-2 fw-bold">Need support?</p>
                    <p class="mb-1"><i class="fas fa-envelope me-1"></i>
                        <a href="mailto:<?= escape($settings['company_email'] ?? 'info@vendrixpos.com') ?>"><?= escape($settings['company_email'] ?? 'info@vendrixpos.com') ?></a>
                    </p>
                    <p class="mb-1"><i class="fas fa-phone me-1"></i>
                        <a href="tel:<?= escape($settings['company_phone'] ?? '08080500766') ?>"><?= escape($settings['company_phone'] ?? '08080500766') ?></a>
                    </p>
                    <?php if (!empty($settings['company_address'])): ?>
                        <p class="mb-0"><i class="fas fa-map-marker-alt me-1"></i><?= escape($settings['company_address']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
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