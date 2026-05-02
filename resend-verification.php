<?php
require_once 'config/config.php';
$conn = getDB();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid session token. Please refresh the page and try again.';
    } else {
        $email = sanitize($_POST['email'] ?? '');

        if (empty($email)) {
            $error = 'Please enter the email address used to register.';
        } else {
            $stmt = $conn->prepare('SELECT id, name, email_verified, verification_token FROM users WHERE email = ? AND status = "active"');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();

            if (!$user) {
                $error = 'No active account was found for that email address.';
            } elseif ($user['email_verified']) {
                $success = 'Your email is already verified. You may now log in.';
            } else {
                $verification_token = $user['verification_token'];
                if (empty($verification_token)) {
                    $verification_token = bin2hex(random_bytes(32));
                    $stmt = $conn->prepare('UPDATE users SET verification_token = ? WHERE id = ?');
                    $stmt->bind_param('si', $verification_token, $user['id']);
                    $stmt->execute();
                }

                $verify_link = APP_URL . '/verify-email.php?token=' . $verification_token;
                $subject = 'Resend: Verify your email - ' . getAppName();
                $message = "<html><body style='font-family: Arial, sans-serif; padding: 20px;'>"
                    . "<h2 style='color: #2c3e50;'>Verify your email</h2>"
                    . "<p>Hello <strong>" . htmlspecialchars($user['name'], ENT_QUOTES) . "</strong>,</p>"
                    . "<p>We received a request to resend your email verification link. Click the button below to verify your account:</p>"
                    . "<p style='margin: 30px 0;'><a href='" . $verify_link . "' style='background: #3498db; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px;'>Verify Email Address</a></p>"
                    . "<p>If the button does not work, copy and paste this link into your browser:</p>"
                    . "<p style='color: #3498db;'>" . $verify_link . "</p>"
                    . "<hr><p style='color: #7f8c8d; font-size: 12px;'>If you did not request this, please ignore this message.</p>"
                    . "</body></html>";

                sendHtmlEmail($email, $subject, $message);
                $success = 'Verification email resent successfully. Please check your inbox.';
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
    <title>Resend Verification - <?= getAppName() ?></title>
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

        .verify-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 480px;
            padding: 40px;
        }

        .verify-card h3 {
            margin-bottom: 20px;
        }
    </style>
</head>

<body>
    <div class="verify-card">
        <h3><i class="fas fa-envelope-open-text text-primary"></i> Resend Verification Email</h3>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            <div class="mb-3">
                <label class="form-label">Registered Email</label>
                <input type="email" name="email" class="form-control" placeholder="you@example.com" required>
            </div>
            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-paper-plane"></i> Resend Verification</button>
        </form>
        <div class="mt-3 text-center">
            <a href="login.php">Back to login</a>
        </div>
    </div>
</body>

</html>