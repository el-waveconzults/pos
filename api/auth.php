<?php
require_once '../config/config.php';
$conn = getDB();

// CORS headers for web app
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
switch ($action) {
    case 'login':
        login();
        break;
    case 'logout':
        logout();
        break;
    case 'current_user':
        currentUser();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function login()
{
    global $conn;
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Invalid method']);
        return;
    }

    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = !empty($_POST['remember']);

    if (empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Email and password are required']);
        return;
    }

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user || !password_verify($password, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        return;
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['company_id'] = $user['company_id'] ?? 0;
    $_SESSION['branch_id'] = $user['branch_id'] ?? 0;

    if ($remember) {
        rememberUser($user['id'], $user['name'], $user['role'], $user['company_id'] ?? 0, $user['branch_id'] ?? 0);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'company_id' => $user['company_id'] ?? 0,
            'branch_id' => $user['branch_id'] ?? 0,
        ],
    ]);
}

function logout()
{
    logoutUser();
    echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
}

function currentUser()
{
    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        return;
    }

    $user = getCurrentUser();
    echo json_encode(['success' => true, 'user' => $user]);
}
