<?php
// API endpoint for syncing offline product updates
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
    // Check if product exists
    $stmt = $conn->prepare("SELECT id FROM products WHERE id = ?");
    $stmt->bind_param("i", $input['id']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Update existing product
        $stmt = $conn->prepare("UPDATE products SET name = ?, description = ?, category_id = ?, price = ?, cost_price = ?, quantity = ?, min_quantity = ?, unit = ?, barcode = ?, status = ?, updated_at = NOW() WHERE id = ?");

        $description = $input['description'] ?? '';
        $categoryId = $input['category_id'] ?? null;
        $price = $input['price'] ?? 0;
        $costPrice = $input['cost_price'] ?? 0;
        $quantity = $input['quantity'] ?? 0;
        $minQuantity = $input['min_quantity'] ?? 0;
        $unit = $input['unit'] ?? 'pcs';
        $barcode = $input['barcode'] ?? '';
        $status = $input['status'] ?? 'active';

        $stmt->bind_param(
            "ssidddssssi",
            $input['name'],
            $description,
            $categoryId,
            $price,
            $costPrice,
            $quantity,
            $minQuantity,
            $unit,
            $barcode,
            $status,
            $input['id']
        );

        $message = 'Product updated successfully';
    } else {
        // Insert new product
        $stmt = $conn->prepare("INSERT INTO products (name, description, category_id, price, cost_price, quantity, min_quantity, unit, barcode, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

        $description = $input['description'] ?? '';
        $categoryId = $input['category_id'] ?? null;
        $price = $input['price'] ?? 0;
        $costPrice = $input['cost_price'] ?? 0;
        $quantity = $input['quantity'] ?? 0;
        $minQuantity = $input['min_quantity'] ?? 0;
        $unit = $input['unit'] ?? 'pcs';
        $barcode = $input['barcode'] ?? '';
        $status = $input['status'] ?? 'active';

        $stmt->bind_param(
            "ssidddssss",
            $input['name'],
            $description,
            $categoryId,
            $price,
            $costPrice,
            $quantity,
            $minQuantity,
            $unit,
            $barcode,
            $status
        );

        $message = 'Product created successfully';
    }

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'product_id' => $input['id'],
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
