<?php

/**
 * Security Fixes Summary
 * Apply these fixes to your application for production
 */

// 1. ADD CSRF PROTECTION TO config/config.php
// Add after session_start():

/*
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}
*/

// 2. ADD RATE LIMITING TO login.php
// Add at the beginning of login form handling:

/*
function checkLoginAttempts($email) {
    $key = 'login_attempts_' . md5($email) . '_' . date('Hi');
    $_SESSION[$key] = ($_SESSION[$key] ?? 0) + 1;
    
    if ($_SESSION[$key] > 5) {
        return false; // Too many attempts
    }
    return true;
}
*/

// 3. VALIDATION EXAMPLES FOR FORMS

/*
// Email validation
$email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
if (!$email) {
    $error = 'Invalid email format';
}

// Phone validation (basic)
$phone = preg_match('/^[\d\s\+\-\(\)]{10,}$/', $_POST['phone']) ? $_POST['phone'] : null;

// Amount validation
$amount = floatval($_POST['amount']);
if ($amount <= 0) {
    $error = 'Amount must be greater than 0';
}

// Date validation
$date = DateTime::createFromFormat('Y-m-d', $_POST['date']);
if (!$date) {
    $error = 'Invalid date format';
}
*/

// 4. INPUT SANITIZATION IMPROVEMENTS
// Replace sanitize() function with:

/*
function sanitizeInput($data, $type = 'text') {
    $data = trim($data);
    
    switch($type) {
        case 'email':
            return filter_var($data, FILTER_SANITIZE_EMAIL);
        case 'number':
            return filter_var($data, FILTER_SANITIZE_NUMBER_INT);
        case 'float':
            return filter_var($data, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        case 'url':
            return filter_var($data, FILTER_SANITIZE_URL);
        default:
            return htmlspecialchars(strip_tags($data), ENT_QUOTES, 'UTF-8');
    }
}
*/

// 5. SECURITY HEADERS TO ADD TO index.php

/*
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
// Only enable in production with HTTPS:
// header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
*/

// 6. AUTHORIZATION CHECKS FOR API ENDPOINTS
// Add to api/sales.php, api/invoices.php, etc:

/*
// Verify user owns the sale/invoice
$currentUser = getCurrentUser();
$userCompanyId = $currentUser['company_id'] ?? 0;

$stmt = $conn->prepare("SELECT s.* FROM sales s JOIN users u ON s.created_by = u.id WHERE s.id = ? AND u.company_id = ?");
$stmt->bind_param("ii", $sale_id, $userCompanyId);
$stmt->execute();

if (!$stmt->get_result()->fetch_assoc()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
*/

// 7. SQL INJECTION PREVENTION CHECKLIST
// ✓ Use prepared statements with bind_param() for ALL queries
// ✓ Cast integers: intval($_GET['id'])
// ✓ Validate dates: DateTime::createFromFormat()
// ✓ Never concatenate user input directly into queries
// ✓ Use parameterized queries for filtering

// 8. XSS PREVENTION CHECKLIST
// ✓ Always escape output: htmlspecialchars()
// ✓ Use htmlentities() for forms
// ✓ Use json_encode() for JSON responses
// ✓ Content-Security-Policy header

// 9. PASSWORD SECURITY
// ✓ Use password_hash() - already done
// ✓ Use password_verify() - already done
// ✓ Enforce minimum 8 character passwords
// ✓ Add password complexity requirements

// 10. ENVIRONMENT VARIABLES
// Move database credentials to .env file:
// Use: composer require vlucas/phpdotenv

// 11. PRODUCTION CHECKLIST
// ✓ Set display_errors = 0 in php.ini
// ✓ Enable error logging to file
// ✓ Use HTTPS only
// ✓ Set secure cookies (Secure, HttpOnly, SameSite)
// ✓ Enable HSTS headers
// ✓ Regular security updates
// ✓ Database backups
// ✓ Monitor for suspicious activity
// ✓ Use Web Application Firewall (WAF)
