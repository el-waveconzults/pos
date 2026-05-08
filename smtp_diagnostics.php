<?php
/**
 * SMTP Diagnostics & Configuration Checker
 * This helps diagnose SMTP configuration issues
 */

require_once 'config/config.php';

// Get current configuration
$config = [
    'MAIL_DRIVER' => MAIL_DRIVER,
    'MAIL_HOST' => MAIL_HOST,
    'MAIL_PORT' => MAIL_PORT,
    'MAIL_USERNAME' => MAIL_USERNAME,
    'MAIL_PASSWORD' => (strlen(MAIL_PASSWORD) > 0) ? '***' . substr(MAIL_PASSWORD, -3) : '(empty)',
    'MAIL_FROM_ADDRESS' => MAIL_FROM_ADDRESS,
    'MAIL_FROM_NAME' => MAIL_FROM_NAME,
    'MAIL_ENCRYPTION' => MAIL_ENCRYPTION,
];

$issues = [];
$warnings = [];

// Check for placeholder values
if (strpos(MAIL_USERNAME, 'your-') === 0 || strpos(MAIL_USERNAME, '@yourdomain') !== false) {
    $issues[] = "❌ MAIL_USERNAME appears to be a placeholder. Replace with your actual Namecheap email.";
}

if (strpos(MAIL_PASSWORD, 'your-') === 0 || MAIL_PASSWORD === 'your-email-password' || empty(MAIL_PASSWORD)) {
    $issues[] = "❌ MAIL_PASSWORD appears to be a placeholder or empty. Replace with your actual password.";
}

if (strpos(MAIL_FROM_ADDRESS, '@yourdomain') !== false) {
    $issues[] = "❌ MAIL_FROM_ADDRESS appears to be a placeholder. Replace with your actual Namecheap email.";
}

if (MAIL_HOST !== 'mail.privateemail.com') {
    $warnings[] = "⚠️  MAIL_HOST is not set to Namecheap. Expected: mail.privateemail.com, Got: " . MAIL_HOST;
}

if (MAIL_PORT != 587 && MAIL_PORT != 465) {
    $warnings[] = "⚠️  Unusual MAIL_PORT. Recommended: 587 (TLS) or 465 (SSL). Got: " . MAIL_PORT;
}

if (MAIL_ENCRYPTION !== 'tls' && MAIL_ENCRYPTION !== 'ssl') {
    $warnings[] = "⚠️  MAIL_ENCRYPTION should be 'tls' or 'ssl'. Got: " . MAIL_ENCRYPTION;
}

// Test connectivity
$test_result = null;
$test_message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'test_connection') {
    if (!empty($issues)) {
        $test_result = 'error';
        $test_message = 'Fix the above issues before testing.';
    } else {
        // Try to connect to SMTP server
        $errno = 0;
        $errstr = '';
        
        $socket = @stream_socket_client(
            MAIL_HOST . ':' . MAIL_PORT,
            $errno,
            $errstr,
            5
        );

        if ($socket) {
            stream_set_timeout($socket, 5);
            $response = fgets($socket, 1024);
            fclose($socket);
            
            if (strpos($response, '220') !== false) {
                $test_result = 'success';
                $test_message = '✅ Server connection successful! SMTP server responded correctly.';
            } else {
                $test_result = 'warning';
                $test_message = '⚠️ Connected but got unexpected response: ' . trim($response);
            }
        } else {
            $test_result = 'error';
            $test_message = "❌ Connection failed: $errstr (Error $errno)";
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMTP Diagnostics</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 40px;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffc107;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-icon {
            font-size: 20px;
            flex-shrink: 0;
        }
        .section {
            margin-bottom: 30px;
        }
        .section-title {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
        }
        .config-item {
            background-color: #f9f9f9;
            padding: 12px;
            margin-bottom: 8px;
            border-left: 4px solid #667eea;
            border-radius: 3px;
            display: flex;
            justify-content: space-between;
            font-size: 13px;
        }
        .config-key {
            font-weight: bold;
            color: #333;
        }
        .config-value {
            color: #666;
            word-break: break-all;
            text-align: right;
            max-width: 60%;
        }
        .instruction-box {
            background-color: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .instruction-box h4 {
            color: #1565c0;
            margin-bottom: 10px;
        }
        .instruction-box p {
            color: #0d47a1;
            margin-bottom: 8px;
            font-size: 13px;
        }
        .instruction-box code {
            background-color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
            font-size: 12px;
        }
        button {
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: transform 0.2s;
            margin-right: 10px;
            margin-bottom: 10px;
        }
        button:hover {
            transform: translateY(-2px);
        }
        .button-group {
            margin: 20px 0;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-error {
            background-color: #f8d7da;
            color: #721c24;
        }
        .status-warning {
            background-color: #fff3cd;
            color: #856404;
        }
        .status-success {
            background-color: #d4edda;
            color: #155724;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 SMTP Diagnostics</h1>
        <p class="subtitle">Check your Namecheap email configuration</p>

        <!-- Issues Section -->
        <?php if (!empty($issues)): ?>
            <div class="section">
                <div class="section-title">❌ Critical Issues Found</div>
                <?php foreach ($issues as $issue): ?>
                    <div class="alert alert-error">
                        <div class="alert-icon">⚠️</div>
                        <div><?= $issue ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Warnings Section -->
        <?php if (!empty($warnings)): ?>
            <div class="section">
                <div class="section-title">⚠️ Warnings</div>
                <?php foreach ($warnings as $warning): ?>
                    <div class="alert alert-warning">
                        <div class="alert-icon">ℹ️</div>
                        <div><?= $warning ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Configuration Section -->
        <div class="section">
            <div class="section-title">📋 Current Configuration</div>
            <?php foreach ($config as $key => $value): ?>
                <div class="config-item">
                    <span class="config-key"><?= $key ?></span>
                    <span class="config-value"><?= escape($value) ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Test Connection -->
        <div class="section">
            <div class="section-title">🔌 Connection Test</div>
            
            <?php if ($test_result === 'error'): ?>
                <div class="alert alert-error">
                    <div class="alert-icon">❌</div>
                    <div><?= escape($test_message) ?></div>
                </div>
            <?php elseif ($test_result === 'success'): ?>
                <div class="alert alert-success">
                    <div class="alert-icon">✅</div>
                    <div><?= escape($test_message) ?></div>
                </div>
            <?php elseif ($test_result === 'warning'): ?>
                <div class="alert alert-warning">
                    <div class="alert-icon">⚠️</div>
                    <div><?= escape($test_message) ?></div>
                </div>
            <?php endif; ?>

            <form method="POST" style="margin-top: 15px;">
                <input type="hidden" name="action" value="test_connection">
                <button type="submit" <?= !empty($issues) ? 'disabled' : '' ?>>
                    🔌 Test SMTP Connection
                </button>
            </form>
        </div>

        <!-- How to Fix -->
        <div class="section">
            <div class="section-title">✅ How to Fix</div>
            
            <div class="instruction-box">
                <h4>Step 1: Edit your .env file</h4>
                <p>Open <code>C:\xampp\htdocs\pos\.env</code> with a text editor</p>
            </div>

            <div class="instruction-box">
                <h4>Step 2: Update with your Namecheap email</h4>
                <p>Replace the placeholder values:</p>
                <p>
                    <code>MAIL_USERNAME=<strong>your-real-email@yourdomain.com</strong></code><br>
                    <code>MAIL_PASSWORD=<strong>your-real-password</strong></code><br>
                    <code>MAIL_FROM_ADDRESS=<strong>your-real-email@yourdomain.com</strong></code>
                </p>
            </div>

            <div class="instruction-box">
                <h4>Step 3: Save and refresh this page</h4>
                <p>Then click "Test SMTP Connection" to verify it works.</p>
            </div>

            <div class="instruction-box">
                <h4>Step 4: Test sending an email</h4>
                <p>Once the connection test passes, visit:</p>
                <p><code>http://localhost/pos/test_smtp.php</code></p>
                <p>and send a test email to verify full functionality.</p>
            </div>
        </div>

        <!-- Namecheap Help -->
        <div class="section">
            <div class="section-title">🎯 Namecheap Email Setup</div>
            
            <div class="instruction-box">
                <h4>Namecheap SMTP Settings</h4>
                <p><strong>Host:</strong> <code>mail.privateemail.com</code></p>
                <p><strong>Port:</strong> <code>587</code> (TLS) or <code>465</code> (SSL)</p>
                <p><strong>Encryption:</strong> <code>tls</code> (for port 587) or <code>ssl</code> (for port 465)</p>
                <p><strong>Username:</strong> Your full Namecheap email address (e.g., info@yourdomain.com)</p>
                <p><strong>Password:</strong> Your email account password (NOT your Namecheap account password)</p>
            </div>
        </div>

    </div>
</body>
</html>
