<?php
require_once 'config/config.php';
$conn = getDB();

$currentUser = getCurrentUser();
$companyId = $currentUser['company_id'] ?? 0;

// Get categories for filter (only this company's categories)
$categories = $conn->query("SELECT * FROM categories WHERE company_id = $companyId ORDER BY name");

// Get all active products (only this company's products)
$products = $conn->query("SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.company_id = $companyId AND p.status = 'active' AND p.quantity > 0 
    ORDER BY p.name");

// Get customers for dropdown (only this company's customers)
$customers = $conn->query("SELECT * FROM customers WHERE company_id = $companyId AND status = 'active' ORDER BY name");
?>

<div class="row">
    <!-- Product Selection -->
    <div class="col-md-7">
        <div class="card">
            <div class="card-header">
                <div class="row align-items-center">
                    <div class="col-md-3">
                        <input type="text" id="searchProduct" class="form-control" placeholder="Search products...">
                    </div>
                    <div class="col-md-3">
                        <select id="filterCategory" class="form-select">
                            <option value="">All Categories</option>
                            <?php while ($cat = $categories->fetch_assoc()): ?>
                                <option value="<?= $cat['id'] ?>"><?= $cat['name'] ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="text" id="barcodeScan" class="form-control" placeholder="Scan barcode...">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-outline-primary w-100" onclick="scanBarcode()">
                            <i class="fas fa-search"></i> Scan
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="row" id="productGrid">
                    <?php while ($product = $products->fetch_assoc()): ?>
                        <div class="col-md-4 mb-3 product-item" data-category="<?= $product['category_id'] ?>" data-barcode="<?= $product['barcode'] ?? '' ?>">
                            <div class="card product-card h-100" onclick="addToCart(<?= $product['id'] ?>, '<?= addslashes($product['name']) ?>', <?= $product['sell_price'] ?>, <?= $product['quantity'] ?>)" style="cursor: pointer;">
                                <div class="card-body text-center">
                                    <?php if ($product['image'] && file_exists($product['image'])): ?>
                                        <img src="<?= $product['image'] ?>" alt="<?= $product['name'] ?>" style="width: 80px; height: 80px; object-fit: cover; border-radius: 5px; margin-bottom: 10px;">
                                    <?php else: ?>
                                        <i class="fas fa-box fa-2x text-muted mb-2" style="display: block; margin-bottom: 10px;"></i>
                                    <?php endif; ?>
                                    <h6 class="mb-1"><?= $product['name'] ?></h6>
                                    <p class="text-success mb-1"><?= formatCurrency($product['sell_price']) ?></p>
                                    <small class="text-muted">Stock: <?= $product['quantity'] ?></small>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Cart / Checkout -->
    <div class="col-md-5">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-shopping-cart"></i> Cart</h5>
                <div class="d-flex align-items-center">
                    <select class="form-select form-select-sm me-2" id="selectedCustomer" style="width: 150px;">
                        <option value="">Walk-in Customer</option>
                        <?php while ($customer = $customers->fetch_assoc()): ?>
                            <option value="<?= $customer['id'] ?>"><?= $customer['name'] ?></option>
                        <?php endwhile; ?>
                    </select>
                    <button class="btn btn-sm btn-outline-danger" onclick="clearCart()">Clear</button>
                </div>
            </div>
            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                <table class="table table-sm" id="cartTable">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Qty</th>
                            <th>Price</th>
                            <th>Total</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="cartBody">
                        <tr>
                            <td colspan="5" class="text-center text-muted">No items in cart</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                <!-- Customer Selection -->
                <div class="mb-3">
                    <label class="form-label">Customer (Optional)</label>
                    <select id="customerSelect" class="form-select form-select-sm">
                        <option value="">Walk-in Customer</option>
                        <?php while ($customer = $customers->fetch_assoc()): ?>
                            <option value="<?= $customer['id'] ?>"><?= $customer['name'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- Totals -->
                <div class="d-flex justify-content-between mb-2">
                    <span>Subtotal:</span>
                    <span id="subtotal"><?= formatCurrency(0) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Tax (<?= TAX_RATE ?>%):</span>
                    <span id="taxAmount"><?= formatCurrency(0) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Discount:</span>
                    <input type="number" id="discount" class="form-control form-control-sm w-50 text-end" value="0" min="0" step="0.01" onchange="updateTotals()">
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span><strong>Total:</strong></span>
                    <span id="grandTotal" class="text-success fw-bold"><?= formatCurrency(0) ?></span>
                </div>

                <!-- Payment Method -->
                <div class="mb-3">
                    <label class="form-label">Payment Method</label>
                    <select id="paymentMethod" class="form-select" onchange="togglePaymentFields()">
                        <option value="cash">Cash</option>
                        <option value="card">Card</option>
                        <option value="transfer">Transfer</option>
                    </select>
                </div>

                <!-- Amount Paid (for cash) -->
                <div class="mb-3" id="amountPaidDiv">
                    <label class="form-label">Amount Paid</label>
                    <input type="number" id="amountPaid" class="form-control" value="0" min="0" step="0.01" onchange="calculateChange()">
                </div>

                <!-- Change -->
                <div class="d-flex justify-content-between mb-3">
                    <span>Change:</span>
                    <span id="changeAmount"><?= formatCurrency(0) ?></span>
                </div>

                <!-- Complete Sale Button -->
                <button class="btn btn-success w-100 btn-lg" onclick="completeSale()">
                    <i class="fas fa-check"></i> Complete Sale
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Receipt Preview Modal -->
<div class="modal fade" id="receiptPreviewModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Receipt Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="receiptPreviewContent" style="font-family: monospace; font-size: 11px; padding: 15px;">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="printReceipt()">Print</button>
            </div>
        </div>
    </div>
</div>

<!-- Hidden Receipt for Printing -->
<?php
$receiptSettings = getSettings();
$showLogo = $receiptSettings['show_logo_on_receipt'] ?? '1';
$showInfo = $receiptSettings['show_company_info_on_receipt'] ?? '1';
$receiptFooter = $receiptSettings['receipt_footer'] ?? 'Thank you for your patronage!';
?>
<div id="receiptContainer" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: white; z-index: 9999; padding: 20px; font-family: monospace; font-size: 12px; overflow: hidden;">
    <div style="text-align: center; border-bottom: 1px dashed #000; padding-bottom: 10px; margin-bottom: 10px;">
        <?php if ($showLogo === '1' && !empty($receiptSettings['company_logo'])): ?>
            <img src="<?= $receiptSettings['company_logo'] ?>" alt="Logo" style="max-height: 60px; margin-bottom: 10px;">
        <?php endif; ?>
        <h4 style="margin: 0;"><?= getAppName() ?></h4>
        <?php if ($showInfo === '1'): ?>
            <p style="margin: 5px 0; font-size: 10px;"><?= $receiptSettings['company_address'] ?? '' ?></p>
            <p style="margin: 5px 0; font-size: 10px;"><?= $receiptSettings['company_phone'] ?? '' ?></p>
        <?php endif; ?>
        <p style="margin: 5px 0;">Invoice: <span id="receiptInvoice"></span></p>
        <p style="margin: 5px 0;">Date: <span id="receiptDate"></span> <span id="receiptTime"></span></p>
    </div>

    <div style="border-bottom: 1px dashed #000; padding-bottom: 10px; margin-bottom: 10px; font-size: 11px;">
        <table style="width: 100%;">
            <thead>
                <tr>
                    <th style="text-align: left;">Item</th>
                    <th style="text-align: center;">Qty</th>
                    <th style="text-align: right;">Price</th>
                    <th style="text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody id="receiptItems"></tbody>
        </table>
    </div>

    <div style="border-bottom: 1px dashed #000; padding-bottom: 10px; margin-bottom: 10px; font-size: 11px;">
        <div style="display: flex; justify-content: space-between;">
            <span>Subtotal:</span>
            <span id="receiptSubtotal"></span>
        </div>
        <div style="display: flex; justify-content: space-between;">
            <span>Tax (<?= TAX_RATE ?>%):</span>
            <span id="receiptTax"></span>
        </div>
        <div style="display: flex; justify-content: space-between;" id="discountLine">
            <span>Discount:</span>
            <span id="receiptDiscount"></span>
        </div>
        <div style="display: flex; justify-content: space-between;">
            <span><strong>Total:</strong></span>
            <span id="receiptTotal"></span>
        </div>
    </div>

    <div style="border-bottom: 1px dashed #000; padding-bottom: 10px; margin-bottom: 10px; font-size: 11px;">
        <div style="display: flex; justify-content: space-between;">
            <span>Payment Method:</span>
            <span id="receiptPaymentMethod"></span>
        </div>
        <div style="display: flex; justify-content: space-between;" id="paidLine">
            <span>Amount Paid:</span>
            <span id="receiptAmountPaid"></span>
        </div>
        <div style="display: flex; justify-content: space-between;" id="changeLine">
            <span>Change:</span>
            <span id="receiptChange"></span>
        </div>
    </div>

    <div style="text-align: center; font-size: 10px; padding-top: 10px;">
        <p style="margin: 5px 0;"><?= $receiptFooter ?></p>
        <div style="margin-top: 10px; border-top: 1px dashed #000; padding-top: 10px;">
            <p style="margin: 5px 0; font-size: 9px;">Powered by <?= getAppName() ?></p>
        </div>
    </div>
</div>

<script>
    let cart = [];

    function addToCart(id, name, price, stock) {
        const existing = cart.find(item => item.id === id);
        if (existing) {
            if (existing.quantity < stock) {
                existing.quantity++;
            } else {
                alert('Not enough stock!');
                return;
            }
        } else {
            cart.push({
                id: id,
                name: name,
                price: price,
                quantity: 1,
                stock: stock
            });
        }
        renderCart();
    }

    function updateQuantity(id, change) {
        const item = cart.find(i => i.id === id);
        if (item) {
            const newQty = item.quantity + change;
            if (newQty > 0 && newQty <= item.stock) {
                item.quantity = newQty;
            } else if (newQty <= 0) {
                cart = cart.filter(i => i.id !== id);
            }
        }
        renderCart();
    }

    function removeFromCart(id) {
        cart = cart.filter(item => item.id !== id);
        renderCart();
    }

    function clearCart() {
        cart = [];
        renderCart();
    }

    function renderCart() {
        const tbody = document.getElementById('cartBody');
        if (cart.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No items in cart</td></tr>';
            updateTotals();
            return;
        }

        tbody.innerHTML = cart.map(item =>
            '<tr>' +
            '<td>' + item.name + '</td>' +
            '<td>' +
            '<button class="btn btn-sm btn-outline-secondary" onclick="updateQuantity(' + item.id + ', -1)">-</button>' +
            '<span class="mx-2">' + item.quantity + '</span>' +
            '<button class="btn btn-sm btn-outline-secondary" onclick="updateQuantity(' + item.id + ', 1)">+</button>' +
            '</td>' +
            '<td>₦' + item.price.toFixed(2) + '</td>' +
            '<td>₦' + (item.price * item.quantity).toFixed(2) + '</td>' +
            '<td><button class="btn btn-sm btn-outline-danger" onclick="removeFromCart(' + item.id + ')"><i class="fas fa-times"></i></button></td>' +
            '</tr>'
        ).join('');

        updateTotals();
    }

    function updateTotals() {
        const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
        const taxRate = <?= TAX_RATE ?>;
        const tax = subtotal * (taxRate / 100);
        const discount = parseFloat(document.getElementById('discount').value) || 0;
        const total = subtotal + tax - discount;

        document.getElementById('subtotal').textContent = '₦' + subtotal.toFixed(2);
        document.getElementById('taxAmount').textContent = '₦' + tax.toFixed(2);
        document.getElementById('grandTotal').textContent = '₦' + total.toFixed(2);

        return total;
    }

    function calculateChange() {
        const total = updateTotals();
        const paid = parseFloat(document.getElementById('amountPaid').value) || 0;
        const change = paid - total;
        document.getElementById('changeAmount').textContent = '₦' + Math.max(0, change).toFixed(2);
    }

    function togglePaymentFields() {
        const paymentMethod = document.getElementById('paymentMethod').value;
        const amountPaidDiv = document.getElementById('amountPaidDiv');
        if (paymentMethod === 'cash') {
            amountPaidDiv.style.display = '';
        } else {
            amountPaidDiv.style.display = 'none';
            document.getElementById('amountPaid').value = '0';
            calculateChange();
        }
    }

    function completeSale() {
        if (cart.length === 0) {
            alert('Cart is empty!');
            return;
        }

        const total = updateTotals();
        const paymentMethod = document.getElementById('paymentMethod').value;
        const amountPaid = parseFloat(document.getElementById('amountPaid').value) || 0;
        const customerId = document.getElementById('customerSelect').value;
        const discount = parseFloat(document.getElementById('discount').value) || 0;

        if (paymentMethod === 'cash' && amountPaid < total) {
            alert('Insufficient payment!');
            return;
        }

        // Prepare form data
        const formData = new FormData();
        formData.append('action', 'complete_sale');
        formData.append('cart', JSON.stringify(cart));
        formData.append('customer_id', customerId || '');
        formData.append('discount', discount);
        formData.append('payment_method', paymentMethod);
        formData.append('amount_paid', amountPaid);

        // Send to API
        fetch('api/sales.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    generateReceipt(data.invoice_no);
                    // Show preview modal instead of direct print
                    showReceiptPreview();
                    cart = [];
                    renderCart();
                    document.getElementById('amountPaid').value = '0';
                    calculateChange();
                } else {
                    alert('Sale failed: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Sale failed: ' + error.message);
            });
    }

    function generateReceipt(invoiceNo) {
        const now = new Date();
        const dateStr = now.toLocaleDateString();
        const timeStr = now.toLocaleTimeString();

        let itemsHtml = cart.map(item =>
            '<tr style="font-size: 11px;">' +
            '<td style="text-align: left; padding: 3px 0;">' + item.name + '</td>' +
            '<td style="text-align: center; padding: 3px 0;">' + item.quantity + '</td>' +
            '<td style="text-align: right; padding: 3px 0;">₦' + item.price.toFixed(2) + '</td>' +
            '<td style="text-align: right; padding: 3px 0;">₦' + (item.price * item.quantity).toFixed(2) + '</td>' +
            '</tr>'
        ).join('');

        const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
        const tax = subtotal * (<?= TAX_RATE ?> / 100);
        const discount = parseFloat(document.getElementById('discount').value) || 0;
        const total = subtotal + tax - discount;
        const paymentMethod = document.getElementById('paymentMethod').value;
        const amountPaid = parseFloat(document.getElementById('amountPaid').value) || 0;
        const change = amountPaid - total;

        // Build receipt HTML
        const showLogo = '<?= $showLogo === '1' && !empty($receiptSettings['company_logo']) ? '1' : '0' ?>';
        const showInfo = '<?= $showInfo === '1' ? '1' : '0' ?>';
        const appName = '<?= getAppName() ?>';
        const receiptFooter = '<?= addslashes($receiptFooter) ?>';

        let headerHtml = `<h4 style="margin: 0;">${appName}</h4>`;
        if (showLogo === '1') {
            headerHtml = `<img src="<?= $receiptSettings['company_logo'] ?? '' ?>" alt="Logo" style="max-height: 60px; margin-bottom: 10px;">` + headerHtml;
        }
        if (showInfo === '1') {
            headerHtml += `<p style="margin: 5px 0; font-size: 10px;"><?= addslashes($receiptSettings['company_address'] ?? '') ?></p>` +
                `<p style="margin: 5px 0; font-size: 10px;"><?= addslashes($receiptSettings['company_phone'] ?? '') ?></p>`;
        }
        headerHtml += `<p style="margin: 5px 0;">Invoice: ${invoiceNo}</p><p style="margin: 5px 0;">Date: ${dateStr} ${timeStr}</p>`;

        const receiptHtml = `
            <div style="text-align: center; border-bottom: 1px dashed #000; padding-bottom: 10px; margin-bottom: 10px;">
                ${headerHtml}
            </div>
            <div style="border-bottom: 1px dashed #000; padding-bottom: 10px; margin-bottom: 10px; font-size: 11px;">
                <table style="width: 100%;">
                    <thead>
                        <tr>
                            <th style="text-align: left;">Item</th>
                            <th style="text-align: center;">Qty</th>
                            <th style="text-align: right;">Price</th>
                            <th style="text-align: right;">Total</th>
                        </tr>
                    </thead>
                    <tbody>${itemsHtml}</tbody>
                </table>
            </div>
            <div style="border-bottom: 1px dashed #000; padding-bottom: 10px; margin-bottom: 10px; font-size: 11px;">
                <div style="display: flex; justify-content: space-between;">
                    <span>Subtotal:</span>
                    <span>₦${subtotal.toFixed(2)}</span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span>Tax (<?= TAX_RATE ?>%):</span>
                    <span>₦${tax.toFixed(2)}</span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span>Discount:</span>
                    <span>₦${discount.toFixed(2)}</span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span><strong>Total:</strong></span>
                    <span><strong>₦${total.toFixed(2)}</strong></span>
                </div>
            </div>
            <div style="border-bottom: 1px dashed #000; padding-bottom: 10px; margin-bottom: 10px; font-size: 11px;">
                <div style="display: flex; justify-content: space-between;">
                    <span>Payment Method:</span>
                    <span>${paymentMethod.toUpperCase()}</span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span>Amount Paid:</span>
                    <span>₦${amountPaid.toFixed(2)}</span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span>Change:</span>
                    <span>₦${Math.max(0, change).toFixed(2)}</span>
                </div>
            </div>
            <div style="text-align: center; font-size: 10px; padding-top: 10px;">
                <p style="margin: 5px 0;">${receiptFooter}</p>
                <div style="margin-top: 10px; border-top: 1px dashed #000; padding-top: 10px;">
                    <p style="margin: 5px 0; font-size: 9px;">Powered by ${appName}</p>
                </div>
            </div>
        `;

        // Set receipt content in hidden container for printing
        // Use a separate container for receipt data to avoid destroying span elements
        document.getElementById('receiptContainer').innerHTML = receiptHtml;

        // Also update individual elements for compatibility
        // Check if elements exist before setting textContent
        const invoiceEl = document.getElementById('receiptInvoice');
        const dateEl = document.getElementById('receiptDate');
        const timeEl = document.getElementById('receiptTime');
        const subtotalEl = document.getElementById('receiptSubtotal');
        const taxEl = document.getElementById('receiptTax');
        const discountEl = document.getElementById('receiptDiscount');
        const totalEl = document.getElementById('receiptTotal');
        const paymentMethodEl = document.getElementById('receiptPaymentMethod');
        const amountPaidEl = document.getElementById('receiptAmountPaid');
        const changeEl = document.getElementById('receiptChange');

        if (invoiceEl) invoiceEl.textContent = invoiceNo;
        if (dateEl) dateEl.textContent = dateStr;
        if (timeEl) timeEl.textContent = timeStr;
        if (subtotalEl) subtotalEl.textContent = '₦' + subtotal.toFixed(2);
        if (taxEl) taxEl.textContent = '₦' + tax.toFixed(2);
        if (discountEl) discountEl.textContent = '₦' + discount.toFixed(2);
        if (totalEl) totalEl.textContent = '₦' + total.toFixed(2);
        if (paymentMethodEl) paymentMethodEl.textContent = paymentMethod.toUpperCase();
        if (amountPaidEl) amountPaidEl.textContent = '₦' + amountPaid.toFixed(2);
        if (changeEl) changeEl.textContent = '₦' + Math.max(0, change).toFixed(2);
    }

    function showReceiptPreview() {
        // Clone the receipt container content for preview
        const receiptContent = document.getElementById('receiptContainer').innerHTML;
        document.getElementById('receiptPreviewContent').innerHTML = receiptContent;

        // Show the modal
        const modal = new bootstrap.Modal(document.getElementById('receiptPreviewModal'));
        modal.show();
    }

    function printReceipt() {
        // Close modal first
        bootstrap.Modal.getInstance(document.getElementById('receiptPreviewModal')).hide();

        // Small delay to ensure modal is closed
        setTimeout(() => {
            window.print();
        }, 300);
    }

    // Search functionality
    document.getElementById('searchProduct').addEventListener('keyup', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        const products = document.querySelectorAll('.product-item');

        products.forEach(product => {
            const name = product.querySelector('h6').textContent.toLowerCase();
            if (name.includes(searchTerm)) {
                product.style.display = '';
            } else {
                product.style.display = 'none';
            }
        });
    });

    // Category filter
    document.getElementById('filterCategory').addEventListener('change', function(e) {
        const categoryId = e.target.value;
        const products = document.querySelectorAll('.product-item');

        products.forEach(product => {
            if (categoryId === '' || product.dataset.category === categoryId) {
                product.style.display = '';
            } else {
                product.style.display = 'none';
            }
        });
    });

    // Barcode scanner
    document.getElementById('barcodeScan').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            scanBarcode();
        }
    });

    function scanBarcode() {
        const barcode = document.getElementById('barcodeScan').value.trim();
        if (!barcode) return;

        const productItems = document.querySelectorAll('.product-item');
        let found = false;

        productItems.forEach(item => {
            const itemBarcode = item.getAttribute('data-barcode');
            if (itemBarcode && itemBarcode === barcode) {
                const card = item.querySelector('.product-card');
                if (card) {
                    card.click();
                    found = true;
                }
            }
        });

        if (!found) {
            alert('Product not found for barcode: ' + barcode);
        }

        document.getElementById('barcodeScan').value = '';
    }
</script>

<style>
    .product-card {
        cursor: pointer;
        transition: 0.2s;
    }

    .product-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    }

    @media print {
        * {
            margin: 0;
            padding: 0;
        }

        body {
            background: white !important;
            color: black !important;
        }

        body>* {
            display: none !important;
        }

        #receiptContainer {
            display: block !important;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            padding: 10px;
            background: white !important;
            z-index: 10000;
        }

        @page {
            size: 80mm auto;
            margin: 0;
        }
    }
</style>