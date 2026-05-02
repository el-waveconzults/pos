<?php
require_once 'config/config.php';
$conn = getDB();

$message = '';
$message_type = 'success';

$token = $_GET['token'] ?? '';

// Check if token is provided
if (empty($token)) {
    $message = 'Invalid verification link. No token provided.';
    $message_type = 'danger';
} else {
    // Find user with this token
    $stmt = $conn->prepare("SELECT id, name, email, email_verified FROM users WHERE verification_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        if ($user['email_verified']) {
            $message = 'Your email is already verified! You can proceed to login.';
        } else {
            // Verify the email
            $verified_at = date('Y-m-d H:i:s');
            $stmt = $conn->prepare("UPDATE users SET email_verified = 1, verification_token = NULL, verified_at = ? WHERE id = ?");
            $stmt->bind_param("si", $verified_at, $user['id']);

            if ($stmt->execute()) {
                // Send welcome email
                $subject = 'Welcome to ' . getAppName() . ' - Email Verified!';
                $welcome_message = "
                    <html>
                    <body style='font-family: Arial, sans-serif; padding: 20px;'>
                        <h2 style='color: #27ae60;'>🎉 Email Verified Successfully!</h2>
                        <p>Hello <strong>{$user['name']}</strong>,</p>
                        <p>Welcome to <strong>" . getAppName() . "</strong>!</p>
                        <p>Your email has been successfully verified. You can now access your account and start using all features.</p>
                        <h3>Getting Started:</h3>
                        <ul>
                            <li>Add your products and categories</li>
                            <li>Set up your branches (if needed)</li>
                            <li>Add your team members</li>
                            <li>Start making sales!</li>
                        </ul>
                        <p style='margin: 30px 0;'>
                            <a href='" . APP_URL . "/login.php' style='background: #27ae60; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>Go to Login</a>
                        </p>
                        <hr>
                        <p style='color: #7f8c8d; font-size: 12px;'>
                            If you have any questions, contact support.
                        </p>
                    </body>
                    </html>
                ";
                $headers = "From: noreply@" . $_SERVER['HTTP_HOST'] . "\r\n";
                $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                @mail($user['email'], $subject, $welcome_message, $headers);

                $message = '✅ Email verified successfully! A welcome email has been sent to your inbox.';
            } else {
                $message = 'Failed to verify email. Please try again.';
                $message_type = 'danger';
            }
        }
    } else {
        $message = 'Invalid verification link. Token not found.';
        $message_type = 'danger';
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - <?= getAppName() ?></title>
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
            max-width: 500px;
            width: 100%;
            padding: 40px;
            text-align: center;
        }

        .success-icon {
            font-size: 60px;
            color: #27ae60;
        }

        .error-icon {
            font-size: 60px;
            color: #e74c3c;
        }
    </style>
</head>

<body>
    <div class="verify-card">
        <?php if ($message_type === 'success'): ?>
            <i class="fas fa-check-circle success-icon mb-3"></i>
        <?php else: ?>
            <i class="fas fa-times-circle error-icon mb-3"></i>
        <?php endif; ?>

        <h3 class="mb-3">Email Verification</h3>

        <div class="alert alert-<?= $message_type ?>">
            <?= $message ?>
        </div>

        <?php if ($message_type === 'success'): ?>
            <p>You can now log in to your account.</p>
            <a href="login.php" class="btn btn-primary">
                <i class="fas fa-sign-in-alt"></i> Go to Login
            </a>
        <?php else: ?>
            <p>Please request a new verification link or contact support.</p>
            <a href="register_company.php" class="btn btn-secondary">
                Register Again
            </a>
        <?php endif; ?>
    </div>
</body>

</html>