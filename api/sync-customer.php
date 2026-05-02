<?php
// API endpoint for syncing offline customer updates
require_once '../config/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON data']);
    exit;
}

$conn = getDB();

// Validate required fields
if (!isset($input['id']) || !isset($input['name'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields: id, name']);
    exit;
}

try {
    // Check if customer exists
    $stmt = $conn->prepare("SELECT id FROM customers WHERE id = ?");
    $stmt->bind_param("i", $input['id']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Update existing customer
        $stmt = $conn->prepare("UPDATE customers SET name = ?, email = ?, phone = ?, address = ?, status = ?, updated_at = NOW() WHERE id = ?");

        $email = $input['email'] ?? '';
        $phone = $input['phone'] ?? '';
        $address = $input['address'] ?? '';
        $status = $input['status'] ?? 'active';

        $stmt->bind_param(
            "sssssi",
            $input['name'],
            $email,
            $phone,
            $address,
            $status,
            $input['id']
        );

        $message = 'Customer updated successfully';
    } else {
        // Insert new customer
        $stmt = $conn->prepare("INSERT INTO customers (name, email, phone, address, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())");

        $email = $input['email'] ?? '';
        $phone = $input['phone'] ?? '';
        $address = $input['address'] ?? '';
        $status = $input['status'] ?? 'active';

        $stmt->bind_param(
            "sssss",
            $input['name'],
            $email,
            $phone,
            $address,
            $status
        );

        $message = 'Customer created successfully';
    }

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'customer_id' => $input['id'],
            'message' => $message
        ]);
    } else {
        throw new Exception('Database operation failed: ' . $stmt->error);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Sync failed',
        'message' => $e->getMessage()
    ]);
}
