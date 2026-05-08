<?php
require_once '../config/config.php';
$conn = getDB();

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'get_invoice_details':
        getInvoiceDetails();
        break;
    case 'record_payment':
        recordPayment();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getInvoiceDetails()
{
    global $conn;
    $invoice_id = intval($_GET['invoice_id'] ?? 0);

    // IDOR Check
    $currentUser = getCurrentUser();
    if (!verifyIDOR('invoices', $invoice_id, $currentUser['company_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        exit;
    }

    $stmt = $conn->prepare("SELECT i.*, COALESCE(c.name, 'Walk-in') as customer_name, c.phone as customer_phone, c.email as customer_email 
        FROM invoices i 
        LEFT JOIN customers c ON i.customer_id = c.id 
        WHERE i.id = ?");
    $stmt->bind_param("i", $invoice_id);
    $stmt->execute();
    $invoice = $stmt->get_result()->fetch_assoc();

    if (!$invoice) {
        echo json_encode(['success' => false, 'message' => 'Invoice not found']);
        return;
    }

    echo json_encode(['invoice' => $invoice, 'success' => true]);
}

function recordPayment()
{
    global $conn;
    $invoice_id = $_POST['invoice_id'] ?? 0;
    $amount = floatval($_POST['amount'] ?? 0);

    // IDOR Check
    $currentUser = getCurrentUser();
    if (!verifyIDOR('invoices', $invoice_id, $currentUser['company_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        exit;
    }

    if ($invoice_id == 0 || $amount == 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        return;
    }

    // Get current invoice using prepared statement
    $stmt = $conn->prepare("SELECT * FROM invoices WHERE id = ?");
    $stmt->bind_param("i", $invoice_id);
    $stmt->execute();
    $invoice = $stmt->get_result()->fetch_assoc();

    if (!$invoice) {
        echo json_encode(['success' => false, 'message' => 'Invoice not found']);
        return;
    }

    $new_paid = $invoice['amount_paid'] + $amount;
    $status = 'partial';
    if ($new_paid >= $invoice['total_amount']) {
        $status = 'paid';
    } elseif ($new_paid > $invoice['amount_paid']) {
        $status = 'partial';
    }

    $stmt = $conn->prepare("UPDATE invoices SET amount_paid = ?, status = ? WHERE id = ?");
    $stmt->bind_param("dsi", $new_paid, $status, $invoice_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Payment recorded successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to record payment']);
    }
}
