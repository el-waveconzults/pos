<?php
require_once '../config/config.php';
$conn = getDB();

header('Content-Type: application/json');

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
        $cart = json_decode($_POST['cart'], true);
        $customer_id = !empty($_POST['customer_id']) ? $_POST['customer_id'] : null;
        $discount = floatval($_POST['discount'] ?? 0);
        $payment_method = $_POST['payment_method'] ?? 'cash';
        $amount_paid = floatval($_POST['amount_paid'] ?? 0);

        if (empty($cart)) {
            echo json_encode(['success' => false, 'message' => 'Cart is empty']);
            return;
        }

        // Calculate totals
        $subtotal = 0;
        foreach ($cart as $item) {
            $subtotal += floatval($item['price']) * intval($item['quantity']);
        }

        $tax_rate = TAX_RATE;
        $tax_amount = $subtotal * ($tax_rate / 100);
        $total_amount = $subtotal + $tax_amount - $discount;
        $amount_change = $amount_paid - $total_amount;

        // Generate invoice number
        $invoice_no = generateInvoiceNo();

        // Insert sale
        $stmt = $conn->prepare("INSERT INTO sales (invoice_no, customer_id, subtotal, tax_amount, discount_amount, total_amount, payment_method, amount_paid, amount_change, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $user_id = $_SESSION['user_id'] ?? 1;
        $stmt->bind_param("siddddsidd", $invoice_no, $customer_id, $subtotal, $tax_amount, $discount, $total_amount, $payment_method, $amount_paid, $amount_change, $user_id);

        if ($stmt->execute()) {
            $sale_id = $conn->insert_id;

            // Insert sale items and update inventory
            foreach ($cart as $item) {
                $product_id = intval($item['id']);
                $quantity = intval($item['quantity']);
                $unit_price = floatval($item['price']);
                $total_price = $unit_price * $quantity;

                // Insert sale item
                $stmt2 = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?)");
                $stmt2->bind_param("iiddd", $sale_id, $product_id, $quantity, $unit_price, $total_price);
                $stmt2->execute();

                // Update inventory
                $stmt3 = $conn->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ?");
                $stmt3->bind_param("ii", $quantity, $product_id);
                $stmt3->execute();
            }

            echo json_encode(['success' => true, 'invoice_no' => $invoice_no, 'sale_id' => $sale_id]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

function getProducts()
{
    global $conn;
    $result = $conn->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.status = 'active' ORDER BY p.name");
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    echo json_encode($products);
}

function getSales()
{
    global $conn;
    $limit = $_GET['limit'] ?? 50;
    $result = $conn->query("SELECT s.*, COALESCE(c.name, 'Walk-in') as customer_name FROM sales s LEFT JOIN customers c ON s.customer_id = c.id ORDER BY s.created_at DESC LIMIT $limit");
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
