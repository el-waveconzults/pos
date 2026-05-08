<?php
require_once 'config/config.php';

$sale_id = intval($_GET['sale_id'] ?? 0);
if (!$sale_id) {
    die('Invalid sale ID');
}

$currentUser = getCurrentUser();
$companyId = $currentUser['company_id'] ?? 0;

// Get sale details
$conn = getDB();
$stmt = $conn->prepare("SELECT s.*, COALESCE(c.name, 'Walk-in Customer') as customer_name, u.name as cashier_name, COALESCE(b.name, 'Main Branch') as branch_name
    FROM sales s
    LEFT JOIN customers c ON s.customer_id = c.id
    JOIN users u ON s.created_by = u.id
    LEFT JOIN branches b ON u.branch_id = b.id
    WHERE s.id = ? AND (u.company_id = ? OR ? = 0)");
$stmt->bind_param("iii", $sale_id, $companyId, $companyId);
$stmt->execute();
$sale = $stmt->get_result()->fetch_assoc();

if (!$sale) {
    die('Sale not found or access denied');
}

// Get sale items
$stmt = $conn->prepare("SELECT si.*, p.name, p.sku FROM sale_items si JOIN products p ON si.product_id = p.id WHERE si.sale_id = ?");
$stmt->bind_param("i", $sale_id);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get company settings
$settings = getSettings();

// Get company info for the receipt
$company = getCompany($sale['company_id'] ?? $companyId);
$companyName = $company ? $company['name'] : ($settings['company_name'] ?? 'POS System');
$companyAddress = $company ? $company['address'] : '';
$companyPhone = $company ? $company['phone'] : '';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - <?= $sale['invoice_no'] ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            body {
                margin: 0;
            }

            .no-print {
                display: none;
            }

            .receipt {
                max-width: none;
                margin: 0;
                padding: 10px;
            }
        }

        .receipt {
            max-width: 400px;
            margin: 20px auto;
            padding: 20px;
            border: 1px solid #ddd;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.4;
        }

        .receipt-header {
            text-align: center;
            border-bottom: 1px dashed #000;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }

        .receipt-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .receipt-info {
            margin-bottom: 15px;
        }

        .receipt-table {
            width: 100%;
            margin-bottom: 15px;
        }

        .receipt-table th,
        .receipt-table td {
            padding: 3px 0;
            text-align: left;
        }

        .receipt-table .qty {
            width: 40px;
            text-align: center;
        }

        .receipt-table .price {
            text-align: right;
            width: 80px;
        }

        .receipt-table .total {
            text-align: right;
            width: 80px;
            font-weight: bold;
        }

        .receipt-totals {
            border-top: 1px dashed #000;
            padding-top: 10px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
        }

        .grand-total {
            border-top: 1px solid #000;
            padding-top: 5px;
            font-weight: bold;
            font-size: 14px;
        }

        .receipt-footer {
            text-align: center;
            border-top: 1px dashed #000;
            padding-top: 10px;
            margin-top: 15px;
            font-size: 10px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3 no-print">
            <h4>Print Receipt</h4>
            <div>
                <button onclick="window.print()" class="btn btn-primary me-2">
                    <i class="fas fa-print"></i> Print
                </button>
                <button onclick="window.close()" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        </div>

        <div class="receipt">
            <div class="receipt-header">
                <div class="receipt-title"><?= htmlspecialchars($companyName) ?></div>
                <div><?= htmlspecialchars($companyAddress) ?></div>
                <div>Phone: <?= htmlspecialchars($companyPhone) ?></div>
            </div>

            <div class="receipt-info">
                <div><strong>Invoice:</strong> <?= htmlspecialchars($sale['invoice_no']) ?></div>
                <div><strong>Date:</strong> <?= date('M d, Y H:i', strtotime($sale['created_at'])) ?></div>
                <div><strong>Cashier:</strong> <?= htmlspecialchars($sale['cashier_name']) ?></div>
                <div><strong>Branch:</strong> <?= htmlspecialchars($sale['branch_name']) ?></div>
                <div><strong>Customer:</strong> <?= htmlspecialchars($sale['customer_name']) ?></div>
            </div>

            <table class="receipt-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th class="qty">Qty</th>
                        <th class="price">Price</th>
                        <th class="total">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <div><?= htmlspecialchars($item['name']) ?></div>
                                <small class="text-muted">SKU: <?= htmlspecialchars($item['sku']) ?></small>
                            </td>
                            <td class="qty"><?= $item['quantity'] ?></td>
                            <td class="price"><?= formatCurrency($item['unit_price']) ?></td>
                            <td class="total"><?= formatCurrency($item['total_price']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="receipt-totals">
                <div class="total-row">
                    <span>Subtotal:</span>
                    <span><?= formatCurrency($sale['subtotal']) ?></span>
                </div>
                <div class="total-row">
                    <span>Tax (<?= getTaxRate() ?>%):</span>
                    <span><?= formatCurrency($sale['tax_amount']) ?></span>
                </div>
                <?php if ($sale['discount_amount'] > 0): ?>
                    <div class="total-row">
                        <span>Discount:</span>
                        <span>-<?= formatCurrency($sale['discount_amount']) ?></span>
                    </div>
                <?php endif; ?>
                <div class="total-row grand-total">
                    <span>TOTAL:</span>
                    <span><?= formatCurrency($sale['total_amount']) ?></span>
                </div>
                <div class="total-row">
                    <span>Payment:</span>
                    <span><?= ucfirst($sale['payment_method']) ?></span>
                </div>
                <div class="total-row">
                    <span>Amount Paid:</span>
                    <span><?= formatCurrency($sale['amount_paid']) ?></span>
                </div>
                <?php if ($sale['amount_change'] > 0): ?>
                    <div class="total-row">
                        <span>Change:</span>
                        <span><?= formatCurrency($sale['amount_change']) ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="receipt-footer">
                <div>Thank you for your business!</div>
                <div>Printed: <?= date('M d, Y H:i:s') ?></div>
            </div>
        </div>
    </div>

    <script>
        // Auto-print when page loads (optional)
        // window.onload = function() {
        //     window.print();
        // };
    </script>
</body>

</html>