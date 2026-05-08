<?php
// Load environment variables
function loadEnv($path = __DIR__ . '/../.env')
{
    if (!file_exists($path)) {
        $path = __DIR__ . '/../.env.example'; // Fallback to example
    }
    if (file_exists($path)) {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                if (!empty($key)) {
                    putenv("$key=$value");
                }
            }
        }
    }
}

loadEnv();

// Database Configuration (from .env or defaults)
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_NAME', getenv('DB_NAME') ?: 'pos_db');

// Application Configuration (defaults - can be overridden in settings)
define('DEFAULT_APP_NAME', getenv('APP_NAME') ?: 'Vendrixpos');
define('APP_URL', getenv('APP_URL') ?: 'http://localhost/pos');
define('CURRENCY', getenv('CURRENCY') ?: '₦'); // Nigeria Naira
define('TAX_RATE', (float)(getenv('TAX_RATE') ?: 7.5)); // Nigeria VAT rate
define('ENVIRONMENT', getenv('ENVIRONMENT') ?: 'development');
define('DEBUG', getenv('DEBUG') === 'true');

// Error handling
if (!DEBUG) {
    ini_set('display_errors', 0);
    error_reporting(E_ALL);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/error.log');
} else {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_only_cookies', 1);
ini_set('session.gc_maxlifetime', (int)(getenv('SESSION_TIMEOUT') ?: 3600));

// Start session
session_start();

// Email/SMTP Configuration
define('MAIL_DRIVER', getenv('MAIL_DRIVER') ?: 'mail');
define('MAIL_HOST', getenv('MAIL_HOST') ?: 'localhost');
define('MAIL_PORT', (int)(getenv('MAIL_PORT') ?: 587));
define('MAIL_USERNAME', getenv('MAIL_USERNAME') ?: '');
define('MAIL_PASSWORD', getenv('MAIL_PASSWORD') ?: '');
define('MAIL_FROM_ADDRESS', getenv('MAIL_FROM_ADDRESS') ?: 'noreply@localhost');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: DEFAULT_APP_NAME);
define('MAIL_ENCRYPTION', getenv('MAIL_ENCRYPTION') ?: 'tls');

// Get dynamic APP_NAME from settings
function getAppName()
{
    return 'Vendrix';
}

function getCompanyTaxRate($companyId = null)
{
    $settings = getSettings();
    if ($companyId) {
        $companyKey = 'tax_rate_company_' . $companyId;
        if (isset($settings[$companyKey]) && trim($settings[$companyKey]) !== '') {
            return floatval($settings[$companyKey]);
        }
    }
    return floatval($settings['tax_rate'] ?? TAX_RATE) ?: TAX_RATE;
}

function getTaxRate()
{
    static $taxRate = null;
    if ($taxRate === null) {
        $currentUser = getCurrentUser();
        if (!empty($currentUser['company_id'])) {
            $taxRate = getCompanyTaxRate($currentUser['company_id']);
        } else {
            $taxRate = floatval(getSettings()['tax_rate'] ?? TAX_RATE);
        }
        if ($taxRate <= 0) {
            $taxRate = TAX_RATE;
        }
    }
    return $taxRate;
}

// Backward compatibility - define constant as default value
if (!defined('APP_NAME')) {
    define('APP_NAME', DEFAULT_APP_NAME);
}

// Database Connection
function getDB()
{
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }
    }
    return $conn;
}

// Company Functions
function getCompany($companyId = null)
{
    $conn = getDB();
    if ($companyId) {
        $stmt = $conn->prepare("SELECT * FROM companies WHERE id = ?");
        $stmt->bind_param("i", $companyId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
    return null;
}

function getCurrentCompany()
{
    $user = getCurrentUser();
    if (isset($user['company_id']) && $user['company_id']) {
        return getCompany($user['company_id']);
    }
    return null;
}

// Cookie Functions
function setCookieValue($name, $value, $days = 30)
{
    $expires = time() + (86400 * $days);
    setcookie($name, $value, $expires, '/');
}

function getCookieValue($name)
{
    return $_COOKIE[$name] ?? null;
}

function deleteCookie($name)
{
    setcookie($name, '', time() - 3600, '/');
}

function rememberUser($userId, $userName, $role, $companyId, $branchId)
{
    $token = bin2hex(random_bytes(32));
    $conn = getDB();

    // Store token in database
    $stmt = $conn->prepare("INSERT INTO user_tokens (user_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))");
    $stmt->bind_param("is", $userId, $token);
    $stmt->execute();

    // Set cookies
    setCookieValue('remember_token', $token, 30);
    setCookieValue('remember_user', $userId, 30);
}

function autoLogin()
{
    if (isset($_SESSION['user_id'])) {
        return true; // Already logged in
    }

    $token = getCookieValue('remember_token');
    $userId = getCookieValue('remember_user');

    if ($token && $userId) {
        $conn = getDB();
        $stmt = $conn->prepare("SELECT u.*, t.token FROM users u JOIN user_tokens t ON u.id = t.user_id WHERE u.id = ? AND t.token = ? AND t.expires_at > NOW()");
        $stmt->bind_param("is", $userId, $token);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['company_id'] = $user['company_id'] ?? 0;
            $_SESSION['branch_id'] = $user['branch_id'] ?? 0;
            return true;
        }
    }
    return false;
}

function logoutUser()
{
    $token = getCookieValue('remember_token');
    if ($token) {
        $conn = getDB();
        $stmt = $conn->prepare("DELETE FROM user_tokens WHERE token = ?");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        deleteCookie('remember_token');
        deleteCookie('remember_user');
    }
    session_destroy();
}

// Branch Functions
function getBranch($branchId = null)
{
    $conn = getDB();
    if ($branchId) {
        $stmt = $conn->prepare("SELECT * FROM branches WHERE id = ?");
        $stmt->bind_param("i", $branchId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
    return null;
}

function getCurrentBranch()
{
    $user = getCurrentUser();
    if (isset($user['branch_id']) && $user['branch_id']) {
        return getBranch($user['branch_id']);
    }
    return null;
}

function getBranches($companyId = null)
{
    $conn = getDB();
    $user = getCurrentUser();
    $companyId = $companyId ?? $user['company_id'] ?? 0;

    $stmt = $conn->prepare("SELECT * FROM branches WHERE company_id = ? AND status = 'active' ORDER BY name");
    $stmt->bind_param("i", $companyId);
    $stmt->execute();
    return $stmt->get_result();
}

// Helper Functions
function sanitize($data)
{
    $conn = getDB();
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function redirect($url)
{
    header("Location: $url");
    exit();
}

function jsonResponse($data, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

function formatCurrency($amount)
{
    return CURRENCY . ' ' . number_format($amount, 2);
}

/**
 * Send email via SMTP or PHP mail()
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $html HTML content
 * @param string $from Sender email (optional)
 * @return bool True if sent successfully
 */
function sendHtmlEmail($to, $subject, $html, $from = null)
{
    $from = $from ?: MAIL_FROM_ADDRESS;
    $fromName = MAIL_FROM_NAME;

    // Try SMTP first if configured
    if (MAIL_DRIVER === 'smtp' && !empty(MAIL_HOST)) {
        if (sendViaSMTP($to, $subject, $html, $from, $fromName)) {
            return true;
        }
        // Fall back to PHP mail if SMTP fails
    }

    return sendViaPhpMail($to, $subject, $html, $from, $fromName);
}

/**
 * Send email via SMTP using stream context (more reliable)
 */
function sendViaSMTP($to, $subject, $html, $from, $fromName)
{
    try {
        $host = MAIL_HOST;
        $port = MAIL_PORT;
        $username = MAIL_USERNAME;
        $password = MAIL_PASSWORD;
        $encryption = MAIL_ENCRYPTION;

        // Check required settings
        if (empty($host) || empty($username) || empty($password)) {
            logSecurityEvent('email_failed', ['reason' => 'Missing SMTP credentials']);
            return false;
        }

        // Create stream context for secure connection
        $contextOptions = [];

        if ($encryption === 'tls') {
            $host = 'tls://' . $host;
            $contextOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
                'http' => [
                    'timeout' => 15
                ]
            ];
        } elseif ($encryption === 'ssl') {
            $host = 'ssl://' . $host;
            $contextOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
                'http' => [
                    'timeout' => 15
                ]
            ];
        }

        $context = stream_context_create($contextOptions);

        // Connect to SMTP server
        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client(
            $host . ':' . $port,
            $errno,
            $errstr,
            15,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$socket) {
            logSecurityEvent('email_failed', ['reason' => "Connection failed: $errstr ($errno)"]);
            return false;
        }

        stream_set_timeout($socket, 10);

        // Read initial response
        $response = fgets($socket, 1024);
        if (strpos($response, '220') === false) {
            fclose($socket);
            logSecurityEvent('email_failed', ['reason' => 'No SMTP banner']);
            return false;
        }

        // Send EHLO
        fwrite($socket, "EHLO localhost\r\n");
        $response = fgets($socket, 1024);
        if (strpos($response, '250') === false) {
            fclose($socket);
            logSecurityEvent('email_failed', ['reason' => 'EHLO failed']);
            return false;
        }

        // Upgrade to TLS if needed
        if ($encryption === 'tls') {
            fwrite($socket, "STARTTLS\r\n");
            $response = fgets($socket, 1024);

            if (strpos($response, '220') === false) {
                fclose($socket);
                logSecurityEvent('email_failed', ['reason' => 'STARTTLS not supported']);
                return false;
            }

            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($socket);
                logSecurityEvent('email_failed', ['reason' => 'TLS negotiation failed']);
                return false;
            }

            // Send EHLO again after TLS
            fwrite($socket, "EHLO localhost\r\n");
            $response = fgets($socket, 1024);
        }

        // Authenticate
        fwrite($socket, "AUTH LOGIN\r\n");
        $response = fgets($socket, 1024);
        if (strpos($response, '334') === false) {
            fclose($socket);
            logSecurityEvent('email_failed', ['reason' => 'AUTH not supported']);
            return false;
        }

        // Send username
        fwrite($socket, base64_encode($username) . "\r\n");
        $response = fgets($socket, 1024);
        if (strpos($response, '334') === false) {
            fclose($socket);
            logSecurityEvent('email_failed', ['reason' => 'Username rejected']);
            return false;
        }

        // Send password
        fwrite($socket, base64_encode($password) . "\r\n");
        $response = fgets($socket, 1024);
        if (strpos($response, '235') === false) {
            fclose($socket);
            logSecurityEvent('email_failed', ['reason' => 'Authentication failed']);
            return false;
        }

        // Send email
        fwrite($socket, "MAIL FROM: <$from>\r\n");
        $response = fgets($socket, 1024);
        if (strpos($response, '250') === false) {
            fclose($socket);
            logSecurityEvent('email_failed', ['reason' => 'MAIL FROM rejected']);
            return false;
        }

        fwrite($socket, "RCPT TO: <$to>\r\n");
        $response = fgets($socket, 1024);
        if (strpos($response, '250') === false) {
            fclose($socket);
            logSecurityEvent('email_failed', ['reason' => 'RCPT TO rejected']);
            return false;
        }

        fwrite($socket, "DATA\r\n");
        $response = fgets($socket, 1024);
        if (strpos($response, '354') === false) {
            fclose($socket);
            logSecurityEvent('email_failed', ['reason' => 'DATA not accepted']);
            return false;
        }

        // Build email
        $headers = "From: $fromName <$from>\r\n";
        $headers .= "To: <$to>\r\n";
        $headers .= "Subject: $subject\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "Content-Transfer-Encoding: 8bit\r\n";
        $headers .= "\r\n";

        fwrite($socket, $headers . $html . "\r\n.\r\n");
        $response = fgets($socket, 1024);

        if (strpos($response, '250') === false) {
            fclose($socket);
            logSecurityEvent('email_failed', ['reason' => 'Message rejected']);
            return false;
        }

        // Close connection
        fwrite($socket, "QUIT\r\n");
        fclose($socket);

        return true;
    } catch (Exception $e) {
        logSecurityEvent('email_failed', ['reason' => $e->getMessage()]);
        return false;
    }
}

/**
 * Send email via PHP mail()
 */
function sendViaPhpMail($to, $subject, $html, $from, $fromName)
{
    $headers = "From: $fromName <$from>\r\n";
    $headers .= "Reply-To: $from\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "Content-Transfer-Encoding: 8bit\r\n";

    return @mail($to, $subject, $html, $headers);
}

function generateInvoiceNo()
{
    $conn = getDB();
    $prefix = 'INV';
    $year = date('Y');
    $month = date('m');

    $result = $conn->query("SELECT MAX(id) as max_id FROM sales");
    $row = $result->fetch_assoc();
    $nextId = ($row['max_id'] ?? 0) + 1;

    return $prefix . '-' . $year . $month . str_pad($nextId, 5, '0', STR_PAD_LEFT);
}

function getSettings()
{
    $conn = getDB();
    $settings = [];
    $result = $conn->query("SELECT setting_key, setting_value FROM settings");
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    return $settings;
}

// CSRF Token Functions
function generateCSRFToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token)
{
    // If no session token exists yet, accept the token (first page load)
    if (empty($_SESSION['csrf_token'])) {
        return !empty($token) && strlen($token) === 64;
    }
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Authentication Functions
function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

function getCurrentUser()
{
    if (!isLoggedIn()) {
        return ['id' => 0, 'name' => 'Guest', 'email' => '', 'role' => 'guest', 'company_id' => 0, 'branch_id' => 0];
    }

    // Handle guest users
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'guest') {
        return [
            'id' => $_SESSION['user_id'] ?? 0,
            'name' => $_SESSION['user_name'] ?? 'Guest User',
            'email' => '',
            'role' => 'guest',
            'company_id' => 0,
            'branch_id' => 0
        ];
    }

    $conn = getDB();
    $stmt = $conn->prepare("SELECT id, name, email, role, company_id, branch_id FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc() ?: ['id' => 0, 'name' => 'Guest', 'email' => '', 'role' => 'guest', 'company_id' => 0, 'branch_id' => 0];
}

function hasAccess($page)
{
    $user = getCurrentUser();
    $rolePages = [
        'owner' => ['dashboard', 'companies', 'settings'],
        'admin' => ['dashboard', 'pos', 'products', 'categories', 'customers', 'sales', 'invoices', 'expenses', 'reports', 'settings', 'users'],
        'manager' => ['dashboard', 'pos', 'products', 'categories', 'customers', 'sales', 'invoices', 'reports'],
        'cashier' => ['dashboard', 'pos', 'sales']
    ];

    $allowedPages = $rolePages[$user['role']] ?? [];
    return in_array($page, $allowedPages);
}

function getCurrentUserRole()
{
    $user = getCurrentUser();
    return $user['role'] ?? 'guest';
}

// License Manager Integration
require_once(__DIR__ . '/LicenseManager.php');
require_once(__DIR__ . '/LicenseMiddleware.php');
require_once(__DIR__ . '/LicenseFeatureGate.php');

function getLicenseManager()
{
    static $manager = null;
    if ($manager === null) {
        $manager = new LicenseManager(getDB());
    }
    return $manager;
}

function getLicenseMiddleware()
{
    static $middleware = null;
    if ($middleware === null) {
        $middleware = new LicenseMiddleware(getDB());
    }
    return $middleware;
}

function getLicenseFeatureGate()
{
    static $gate = null;
    if ($gate === null) {
        $gate = new LicenseFeatureGate(getLicenseManager(), getCurrentUser());
    }
    return $gate;
}

/**
 * Check if user can access a feature
 * If not allowed, redirects to feature_restricted page
 * Usage: requireLicenseFeature('reports');
 */
function requireLicenseFeature($feature)
{
    $currentUser = getCurrentUser();

    // Guests, admins, and managers can access all features
    if ($currentUser['role'] === 'guest' || $currentUser['role'] === 'admin' || $currentUser['role'] === 'manager' || $currentUser['role'] === 'branch_manager') {
        return;
    }

    $gate = getLicenseFeatureGate();
    $result = $gate->canAccessFeature($feature);

    if (!$result['allowed']) {
        $message = $result['message'] ?? 'This feature requires a higher license tier.';
        redirect('index.php?page=feature_restricted&feature=' . urlencode($feature) . '&error=' . urlencode($message));
    }
}

// ============================================
// SECURITY FUNCTIONS
// ============================================

/**
 * Escape output for HTML context (XSS prevention)
 * Usage: <?= escape($userInput) ?>
 */
function escape($data)
{
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

/**
 * Escape output for JavaScript context
 * Usage: onclick="someFunction('<?= escapeJs($value) ?>')"
 */
function escapeJs($data)
{
    return json_encode($data);
}

/**
 * Escape output for HTML attributes
 * Usage: <input value="<?= escapeAttr($userInput) ?>">
 */
function escapeAttr($data)
{
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

/**
 * Validate input - returns sanitized value or null
 * Usage: $email = validateInput($_POST['email'], 'email');
 */
function validateInput($value, $type = 'string', $required = false)
{
    $value = trim($value ?? '');

    if (!$required && empty($value)) {
        return null;
    }

    if ($required && empty($value)) {
        return false;
    }

    switch ($type) {
        case 'email':
            $value = filter_var($value, FILTER_SANITIZE_EMAIL);
            return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : false;

        case 'phone':
            $value = preg_replace('/[^0-9+\-().\s]/', '', $value);
            return strlen($value) >= 7 ? $value : false;

        case 'number':
        case 'int':
            return filter_var($value, FILTER_VALIDATE_INT) !== false ? (int)$value : false;

        case 'float':
        case 'decimal':
            return filter_var($value, FILTER_VALIDATE_FLOAT) !== false ? (float)$value : false;

        case 'url':
            return filter_var($value, FILTER_VALIDATE_URL) ? $value : false;

        case 'date':
            $d = \DateTime::createFromFormat('Y-m-d', $value);
            return $d && $d->format('Y-m-d') === $value ? $value : false;

        case 'datetime':
            $d = \DateTime::createFromFormat('Y-m-d H:i:s', $value);
            return $d && $d->format('Y-m-d H:i:s') === $value ? $value : false;

        case 'string':
        default:
            return htmlspecialchars(strip_tags($value), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Verify IDOR - Check if user has access to a resource
 * Usage: verifyIDOR('sales', $saleId, $currentUser['company_id']);
 */
function verifyIDOR($resourceType, $resourceId, $userCompanyId)
{
    $conn = getDB();
    $resourceId = (int)$resourceId;
    $userCompanyId = (int)$userCompanyId;

    if ($userCompanyId <= 0) {
        return false;
    }

    switch ($resourceType) {
        case 'sales':
        case 'sale':
            $stmt = $conn->prepare(
                "SELECT s.id FROM sales s 
                 JOIN users u ON s.created_by = u.id 
                 WHERE s.id = ? AND u.company_id = ?"
            );
            $stmt->bind_param("ii", $resourceId, $userCompanyId);
            break;

        case 'invoices':
        case 'invoice':
            $stmt = $conn->prepare(
                "SELECT i.id FROM invoices i 
                 JOIN users u ON i.created_by = u.id 
                 WHERE i.id = ? AND u.company_id = ?"
            );
            $stmt->bind_param("ii", $resourceId, $userCompanyId);
            break;

        case 'products':
        case 'product':
            $stmt = $conn->prepare(
                "SELECT id FROM products WHERE id = ? AND company_id = ?"
            );
            $stmt->bind_param("ii", $resourceId, $userCompanyId);
            break;

        case 'customers':
        case 'customer':
            $stmt = $conn->prepare(
                "SELECT id FROM customers WHERE id = ? AND company_id = ?"
            );
            $stmt->bind_param("ii", $resourceId, $userCompanyId);
            break;

        case 'categories':
        case 'category':
            $stmt = $conn->prepare(
                "SELECT id FROM categories WHERE id = ? AND company_id = ?"
            );
            $stmt->bind_param("ii", $resourceId, $userCompanyId);
            break;

        default:
            return false;
    }

    if (!$stmt) {
        return false;
    }

    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

/**
 * Rate limiting helper - Check if user has exceeded rate limit
 * Usage: if (checkRateLimit('api_call', $_SESSION['user_id'], 100, 3600)) { 
 *     exit(json_encode(['error' => 'Rate limit exceeded'])); 
 * }
 */
function checkRateLimit($action, $identifier, $maxAttempts = 100, $windowSeconds = 3600)
{
    $key = "ratelimit_{$action}_{$identifier}";
    $attemptKey = "{$key}_attempts";
    $resetKey = "{$key}_reset";

    $now = time();
    $resetTime = $_SESSION[$resetKey] ?? 0;

    if ($now > $resetTime) {
        $_SESSION[$attemptKey] = 0;
        $_SESSION[$resetKey] = $now + $windowSeconds;
        return false;
    }

    $_SESSION[$attemptKey] = ($_SESSION[$attemptKey] ?? 0) + 1;

    return $_SESSION[$attemptKey] > $maxAttempts;
}

/**
 * Add security headers to responses
 * Call this at the top of pages that need extra security
 */
function addSecurityHeaders()
{
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: SAMEORIGIN");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
}

/**
 * Require login and redirect if not authenticated
 * Usage: requireLogin();
 */
function requireLogin()
{
    if (!isLoggedIn()) {
        redirect('login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    }
}

/**
 * Require specific role and redirect if insufficient permissions
 * Usage: requireRole('admin');
 */
function requireRole($role)
{
    $user = getCurrentUser();
    $allowedRoles = [];

    if ($role === 'admin') {
        $allowedRoles = ['owner', 'admin'];
    } elseif ($role === 'manager') {
        $allowedRoles = ['owner', 'admin', 'manager'];
    } elseif ($role === 'user') {
        $allowedRoles = ['owner', 'admin', 'manager', 'cashier'];
    }

    if (!in_array($user['role'], $allowedRoles)) {
        http_response_code(403);
        die('Unauthorized access');
    }
}

/**
 * Send welcome email to new company
 */
function sendWelcomeEmail($companyName, $companyEmail, $adminName, $adminEmail, $loginUrl)
{
    $appName = MAIL_FROM_NAME;
    $supportEmail = MAIL_FROM_ADDRESS;

    $subject = "Welcome to $appName";

    $html = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #007bff; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
            .content { background-color: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
            .footer { background-color: #f0f0f0; padding: 15px; text-align: center; font-size: 12px; border-radius: 0 0 5px 5px; }
            .button { display: inline-block; background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
            .info-box { background-color: #e7f3ff; border-left: 4px solid #007bff; padding: 10px; margin: 15px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Welcome to $appName</h1>
            </div>
            <div class='content'>
                <p>Hello <strong>$adminName</strong>,</p>
                <p>Thank you for registering your company <strong>$companyName</strong> with $appName!</p>
                
                <div class='info-box'>
                    <p><strong>Account Details:</strong></p>
                    <ul>
                        <li><strong>Company Name:</strong> $companyName</li>
                        <li><strong>Admin Email:</strong> $adminEmail</li>
                        <li><strong>Company Email:</strong> $companyEmail</li>
                    </ul>
                </div>
                
                <p>Your account is now active and ready to use. Click the button below to log in:</p>
                <center><a href='$loginUrl' class='button'>Log In Now</a></center>
                
                <div class='info-box'>
                    <p><strong>Getting Started:</strong></p>
                    <ul>
                        <li>Set up your products and categories</li>
                        <li>Configure your settings and preferences</li>
                        <li>Add staff members and branches (if needed)</li>
                        <li>Start processing sales</li>
                    </ul>
                </div>
                
                <p>If you have any questions or need assistance, please don't hesitate to contact our support team at <a href='mailto:$supportEmail'>$supportEmail</a>.</p>
                
                <p>Best regards,<br><strong>The $appName Team</strong></p>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " $appName. All rights reserved.</p>
                <p>This is an automated email. Please do not reply directly.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    return sendHtmlEmail($adminEmail, $subject, $html);
}

/**
 * Send pending approval email for new company registration
 */
function sendPendingApprovalEmail($companyName, $companyEmail, $adminName, $adminEmail, $loginUrl)
{
    $appName = MAIL_FROM_NAME;
    $supportEmail = MAIL_FROM_ADDRESS;
    $subject = "Your $appName registration is pending approval";

    $html = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #ffc107; color: #1f2937; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
            .content { background-color: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
            .footer { background-color: #f0f0f0; padding: 15px; text-align: center; font-size: 12px; border-radius: 0 0 5px 5px; }
            .button { display: inline-block; background-color: #0d6efd; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>$appName Registration Pending</h1>
            </div>
            <div class='content'>
                <p>Hello <strong>$adminName</strong>,</p>
                <p>Thank you for registering <strong>$companyName</strong> with $appName.</p>
                <p>Your company registration is currently pending review by our team. Once approved, you will receive another email and be able to log in.</p>
                <div class='info-box' style='background-color:#fff3cd;border-left:4px solid #ffc107;padding:10px;margin:15px 0;'>
                    <p><strong>Company Name:</strong> $companyName</p>
                    <p><strong>Admin Email:</strong> $adminEmail</p>
                    <p><strong>Company Email:</strong> $companyEmail</p>
                </div>
                <p>If you need help, contact support at <a href='mailto:$supportEmail'>$supportEmail</a>.</p>
                <p>We will notify you once your registration is approved.</p>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " $appName. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    return sendHtmlEmail($adminEmail, $subject, $html);
}

/**
 * Send company pending approval email
 */
function sendCompanyPendingApprovalEmail($companyName, $companyEmail, $loginUrl)
{
    $appName = MAIL_FROM_NAME;
    $subject = "$appName - Registration Pending Approval";

    $html = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #28a745; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
            .content { background-color: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
            .footer { background-color: #f0f0f0; padding: 15px; text-align: center; font-size: 12px; border-radius: 0 0 5px 5px; }
            .button { display: inline-block; background-color: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Registration Pending Approval</h1>
            </div>
            <div class='content'>
                <p>Hello,</p>
                <p>Thank you for registering <strong>$companyName</strong> with $appName.</p>
                <p>Your registration is currently pending review by our super admin team. Once approved, you will be notified and able to log in.</p>
                <p>If you have any questions, please contact support.</p>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " $appName. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    return sendHtmlEmail($companyEmail, $subject, $html);
}

/**
 * Send company welcome email
 */
function sendCompanyWelcomeEmail($companyName, $companyEmail, $loginUrl)
{
    $appName = MAIL_FROM_NAME;

    $subject = "$appName - Company Registration Confirmation";

    $html = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #28a745; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
            .content { background-color: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
            .footer { background-color: #f0f0f0; padding: 15px; text-align: center; font-size: 12px; border-radius: 0 0 5px 5px; }
            .button { display: inline-block; background-color: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Welcome to $appName</h1>
            </div>
            <div class='content'>
                <p>Hello,</p>
                <p>Thank you for registering <strong>$companyName</strong> with $appName. Your registration has been successfully completed.</p>
                
                <p><strong>Company Information:</strong></p>
                <ul>
                    <li><strong>Company Name:</strong> $companyName</li>
                    <li><strong>Email:</strong> $companyEmail</li>
                </ul>
                
                <p>Your company account is ready to use. Log in to start managing your business:</p>
                <center><a href='$loginUrl' class='button'>Access Your Account</a></center>
                
                <p>Thank you for choosing $appName!</p>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " $appName. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    return sendHtmlEmail($companyEmail, $subject, $html);
}

/**
 * Log security events
 * Usage: logSecurityEvent('login_failed', ['email' => $email, 'ip' => $_SERVER['REMOTE_ADDR']]);
 */
function logSecurityEvent($eventType, $details = [])
{
    if (!is_dir(__DIR__ . '/../logs')) {
        mkdir(__DIR__ . '/../logs', 0755, true);
    }

    $logFile = __DIR__ . '/../logs/security.log';
    $user = getCurrentUser();
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'event_type' => $eventType,
        'user_id' => $user['id'] ?? 0,
        'user_email' => $user['email'] ?? 'unknown',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        'details' => $details
    ];

    error_log(json_encode($logEntry) . "\n", 3, $logFile);
}
