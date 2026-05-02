<?php
// API endpoint for syncing offline sales
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
$requiredFields = ['customer_id', 'subtotal', 'tax_amount', 'total_amount', 'payment_method'];
foreach ($requiredFields as $field) {
    if (!isset($input[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit;
    }
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Generate invoice number
    $invoiceNo = generateInvoiceNo();

    // Insert sale
    $stmt = $conn->prepare("INSERT INTO sales (invoice_no, customer_id, subtotal, tax_amount, discount_amount, total_amount, payment_method, amount_paid, amount_change, status, notes, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?, ?, ?)");

    $customerId = $input['customer_id'] ?: null;
    $discountAmount = $input['discount_amount'] ?? 0;
    $amountPaid = $input['amount_paid'] ?? $input['total_amount'];
    $amountChange = $input['amount_change'] ?? ($amountPaid - $input['total_amount']);
    $notes = $input['notes'] ?? 'Synced from offline';
    $createdBy = $input['created_by'] ?? 1; // Default to first user
    $createdAt = $input['created_at'] ?? date('Y-m-d H:i:s');

    $stmt->bind_param(
        "sddddsdsssss",
        $invoiceNo,
        $customerId,
        $input['subtotal'],
        $input['tax_amount'],
        $discountAmount,
        $input['total_amount'],
        $input['payment_method'],
        $amountPaid,
        $amountChange,
        $notes,
        $createdBy,
        $createdAt
    );

    if (!$stmt->execute()) {
        throw new Exception('Failed to insert sale: ' . $stmt->error);
    }

    $saleId = $conn->insert_id;

    // Insert sale items if provided
    if (isset($input['items']) && is_array($input['items'])) {
        $itemStmt = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?)");

        foreach ($input['items'] as $item) {
            $itemStmt->bind_param(
                "iiidd",
                $saleId,
                $item['product_id'],
                $item['quantity'],
                $item['unit_price'],
                $item['total_price']
            );

            if (!$itemStmt->execute()) {
                throw new Exception('Failed to insert sale item: ' . $itemStmt->error);
            }

            // Update product quantity
            $updateStmt = $conn->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ?");
            $updateStmt->bind_param("ii", $item['quantity'], $item['product_id']);
            $updateStmt->execute();
        }
    }

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'sale_id' => $saleId,
        'invoice_no' => $invoiceNo,
        'message' => 'Sale synced successfully'
    ]);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        'error' => 'Sync failed',
        'message' => $e->getMessage()
    ]);
}
