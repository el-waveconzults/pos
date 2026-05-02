<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'pos_db');

// Application Configuration (defaults - can be overridden in settings)
define('DEFAULT_APP_NAME', 'POS System');
define('APP_URL', 'http://localhost/pos');
define('CURRENCY', '₦'); // Nigeria Naira
define('TAX_RATE', 7.5); // Nigeria VAT rate

// Start session (ini_set must be before session_start in a separate file or php.ini)
session_start();

// Get dynamic APP_NAME from settings
function getAppName()
{
    static $appName = null;
    if ($appName === null) {
        $settings = getSettings();
        // Use company_name for super admin, fallback to receipt_header or default
        $appName = $settings['company_name'] ?? $settings['receipt_header'] ?? DEFAULT_APP_NAME;
    }
    return $appName;
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

function sendHtmlEmail($to, $subject, $html, $from = null)
{
    $from = $from ?: 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $headers = "From: " . $from . "\r\n";
    $headers .= "Reply-To: " . $from . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    @mail($to, $subject, $html, $headers);
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
