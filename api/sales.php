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
    case 'complete_sale':
        completeSale();
        break;
    case 'get_products':
        getProducts();
        break;
    case 'get_sales':
        getSales();
        break;
    case 'get_sale_details':
        getSaleDetails();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function completeSale()
{
    global $conn;

    try {
        $currentUser = getCurrentUser();
        $userCompanyId = $currentUser['company_id'] ?? 0;

        // Only staff can complete sales (not guests)
        if ($currentUser['role'] === 'guest') {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            return;
        }

        if ($userCompanyId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            return;
        }

        $cart = json_decode($_POST['cart'], true);
        $customer_id = !empty($_POST['customer_id']) ? validateInput($_POST['customer_id'], 'int') : null;
        $discount = floatval($_POST['discount'] ?? 0);
        $payment_method = validateInput($_POST['payment_method'] ?? 'cash', 'string');
        $amount_paid = floatval($_POST['amount_paid'] ?? 0);

        if (empty($cart) || !is_array($cart)) {
            echo json_encode(['success' => false, 'message' => 'Cart is empty']);
            return;
        }

        // Validate customer belongs to company (if provided)
        if ($customer_id) {
            if (!verifyIDOR('customer', $customer_id, $userCompanyId)) {
                echo json_encode(['success' => false, 'message' => 'Invalid customer']);
                return;
            }
        }

        // Calculate totals
        $subtotal = 0;
        foreach ($cart as $item) {
            $subtotal += floatval($item['price']) * intval($item['quantity']);
        }

        $tax_rate = getTaxRate();
        $tax_amount = $subtotal * ($tax_rate / 100);
        $total_amount = $subtotal + $tax_amount - $discount;
        $amount_change = $amount_paid - $total_amount;

        // Generate invoice number
        $invoice_no = generateInvoiceNo();

        // Insert sale
        $stmt = $conn->prepare("INSERT INTO sales (invoice_no, customer_id, subtotal, tax_amount, discount_amount, total_amount, payment_method, amount_paid, amount_change, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $user_id = $currentUser['id'] ?? 1;
        $stmt->bind_param("siddddsidd", $invoice_no, $customer_id, $subtotal, $tax_amount, $discount, $total_amount, $payment_method, $amount_paid, $amount_change, $user_id);

        if ($stmt->execute()) {
            $sale_id = $conn->insert_id;

            // Debug: Log successful sale creation
            error_log("Sale created successfully: ID=$sale_id, Invoice=$invoice_no, UserID=$user_id, CompanyID=$userCompanyId");

            // Insert sale items and update inventory
            foreach ($cart as $item) {
                $product_id = intval($item['id']);
                $quantity = intval($item['quantity']);
                $unit_price = floatval($item['price']);
                $total_price = $unit_price * $quantity;

                // Verify product belongs to company (allow guests in demo mode)
                $currentUser = getCurrentUser();
                if ($currentUser['role'] !== 'guest' && !verifyIDOR('product', $product_id, $userCompanyId)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid product in cart']);
                    return;
                }

                // Insert sale item
                $stmt2 = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?)");
                $stmt2->bind_param("iiddd", $sale_id, $product_id, $quantity, $unit_price, $total_price);
                $stmt2->execute();

                // Update inventory
                $stmt3 = $conn->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ?");
                $stmt3->bind_param("ii", $quantity, $product_id);
                $stmt3->execute();
            }

            echo json_encode(['success' => true, 'invoice_no' => $invoice_no, 'sale_id' => $sale_id, 'debug' => "UserID: $user_id, CompanyID: $userCompanyId"]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error, 'debug' => "UserID: $user_id, CompanyID: $userCompanyId"]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

function getProducts()
{
    global $conn;
    $currentUser = getCurrentUser();
    $userCompanyId = $currentUser['company_id'] ?? 0;

    // Only return products from user's company
    $stmt = $conn->prepare("SELECT p.*, c.name as category_name FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.company_id = ? AND p.status = 'active' 
        ORDER BY p.name");
    $stmt->bind_param("i", $userCompanyId);
    $stmt->execute();
    $result = $stmt->get_result();

    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    echo json_encode($products);
}

function getSales()
{
    global $conn;
    $currentUser = getCurrentUser();
    $userCompanyId = $currentUser['company_id'] ?? 0;
    $limit = (int)($_GET['limit'] ?? 50);

    if ($limit > 1000) $limit = 1000; // Cap limit
    if ($limit < 1) $limit = 50;

    // Only return sales from user's company
    $stmt = $conn->prepare("SELECT s.*, COALESCE(c.name, 'Walk-in') as customer_name FROM sales s 
        JOIN users u ON s.created_by = u.id
        LEFT JOIN customers c ON s.customer_id = c.id 
        WHERE u.company_id = ? 
        ORDER BY s.created_at DESC LIMIT ?");
    $stmt->bind_param("ii", $userCompanyId, $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    $sales = [];
    while ($row = $result->fetch_assoc()) {
        $sales[] = $row;
    }
    echo json_encode($sales);
}

function getSaleDetails()
{
    global $conn;
    $sale_id = intval($_GET['sale_id'] ?? 0);

    // IDOR Check
    $currentUser = getCurrentUser();
    if (!verifyIDOR('sales', $sale_id, $currentUser['company_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        exit;
    }

    $stmt = $conn->prepare("SELECT s.*, COALESCE(c.name, 'Walk-in') as customer_name FROM sales s LEFT JOIN customers c ON s.customer_id = c.id WHERE s.id = ?");
    $stmt->bind_param("i", $sale_id);
    $stmt->execute();
    $sale = $stmt->get_result()->fetch_assoc();

    $stmt = $conn->prepare("SELECT si.*, p.name, p.sku FROM sale_items si JOIN products p ON si.product_id = p.id WHERE si.sale_id = ?");
    $stmt->bind_param("i", $sale_id);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode(['sale' => $sale, 'items' => $items]);
}
