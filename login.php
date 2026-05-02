<?php
require_once 'config/config.php';
$conn = getDB();

$error = '';

// Try auto-login first if session/cookies are still valid
autoLogin();
if (isset($_SESSION['user_id'])) {
    redirect('index.php?page=dashboard');
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
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            max-width: 400px;
            width: 100%;
        }

        .login-header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .login-body {
            padding: 40px;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px;
            font-weight: 600;
        }

        .btn-login:hover {
            background: linear-gradient(135deg, #5a6fd6 0%, #6a4190 100%);
        }
    </style>
</head>

<body>
    <?php
    // Get app name for login page - check if super admin has set company name
    $loginAppName = getAppName();
    ?>
    <div class="login-card">
        <div class="login-header">
            <i class="fas fa-cash-register fa-3x mb-3"></i>
            <h3><?= $loginAppName ?></h3>
            <p class="mb-0">Sign in to your account</p>
        </div>
        <div class="login-body">
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
                        <input type="password" name="password" class="form-control" placeholder="Enter password" required>
                    </div>
                </div>
                <div class="mb-3 form-check">
                    <input type="checkbox" name="remember" class="form-check-input" id="rememberMe">
                    <label class="form-check-label" for="rememberMe">Remember me</label>
                </div>
                <button type="submit" class="btn btn-primary btn-login w-100">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>

            <div class="mt-4 text-center">
                <p class="mb-2">Register your company? <a href="register_company.php">Register here</a></p>
                <small class="text-muted">
                    Owner: owner@pos.com - owner123<br>
                    Admin: admin@pos.com - admin123<br>
                    Manager: manager@pos.com - manager123<br>
                    Cashier: cashier@pos.com - cashier123
                </small>
            </div>
        </div>
    </div>
</body>

</html>