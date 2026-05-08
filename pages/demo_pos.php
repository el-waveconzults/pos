<?php
require_once 'config/config.php';
$conn = getDB();

$sampleProducts = [
    ['name' => 'Smartphone X', 'price' => 235000, 'stock' => 12, 'image' => 'https://via.placeholder.com/90x90?text=Phone'],
    ['name' => 'Bluetooth Speaker', 'price' => 85000, 'stock' => 9, 'image' => 'https://via.placeholder.com/90x90?text=Speaker'],
    ['name' => 'Wireless Headphones', 'price' => 120000, 'stock' => 7, 'image' => 'https://via.placeholder.com/90x90?text=Headset'],
    ['name' => 'Smart Watch', 'price' => 98000, 'stock' => 5, 'image' => 'https://via.placeholder.com/90x90?text=Watch'],
    ['name' => 'Phone Case', 'price' => 15000, 'stock' => 18, 'image' => 'https://via.placeholder.com/90x90?text=Case'],
    ['name' => 'USB-C Cable', 'price' => 6500, 'stock' => 24, 'image' => 'https://via.placeholder.com/90x90?text=Cable'],
];

$guestProducts = [];
$productQuery = $conn->query("SELECT p.name, p.sell_price AS price, p.quantity AS stock FROM products p WHERE p.company_id = 0 AND p.status = 'active' ORDER BY p.name LIMIT 12");
if ($productQuery && $productQuery->num_rows > 0) {
    while ($row = $productQuery->fetch_assoc()) {
        $row['image'] = 'https://via.placeholder.com/90x90?text=' . urlencode(substr($row['name'], 0, 12));
        $guestProducts[] = $row;
    }
}

$displayProducts = !empty($guestProducts) ? $guestProducts : $sampleProducts;

$cartItems = [
    ['name' => 'Smartphone X', 'qty' => 1, 'price' => 235000],
    ['name' => 'Phone Case', 'qty' => 2, 'price' => 15000],
];

$subtotal = 235000 + (15000 * 2);
$taxRate = 7.5;
$taxAmount = $subtotal * ($taxRate / 100);
$grandTotal = $subtotal + $taxAmount;
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-0">Point of Sale</h4>
        <small class="text-muted">Guest demo view</small>
    </div>
    <button class="btn btn-success" disabled>
        <i class="fas fa-shopping-cart me-2"></i>New Sale
    </button>
</div>

<div class="alert alert-info mb-4">
    <i class="fas fa-info-circle me-2"></i>
    <strong>Demo Mode:</strong> Use the sample product list and cart preview to explore the POS layout.
</div>

<div class="row">
    <div class="col-md-7">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Available Products</h5>
                <span class="badge bg-secondary"><?= empty($guestProducts) ? 'Sample inventory' : 'Guest catalog' ?></span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <?php foreach ($sampleProducts as $product): ?>
                        <div class="col-md-4">
                            <div class="card product-card h-100">
                                <div class="card-body text-center">
                                    <img src="<?= $product['image'] ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="mb-3" style="width: 90px; height: 90px; object-fit: cover; border-radius: 10px;">
                                    <h6 class="mb-2"><?= htmlspecialchars($product['name']) ?></h6>
                                    <p class="text-success mb-1"><?= formatCurrency($product['price']) ?></p>
                                    <small class="text-muted d-block mb-3">Stock: <?= $product['stock'] ?></small>
                                    <button class="btn btn-sm btn-outline-primary w-100" onclick="addToCart('<?= addslashes($product['name']) ?>', <?= $product['price'] ?>, this)">
                                        <i class="fas fa-cart-plus me-2"></i>Add to Cart
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-5">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-shopping-bucket"></i> Cart Summary</h5>
                <span class="badge bg-success" id="cartCount">0 items</span>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Item</th>
                            <th class="text-center">Qty</th>
                            <th class="text-end">Total</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="cartBody">
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">No items in cart</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                <div class="mb-2 d-flex justify-content-between">
                    <span>Subtotal</span>
                    <span id="subtotal"><?= formatCurrency(0) ?></span>
                </div>
                <div class="mb-2 d-flex justify-content-between">
                    <span>Tax (7.5%)</span>
                    <span id="taxAmount"><?= formatCurrency(0) ?></span>
                </div>
                <div class="mb-3 d-flex justify-content-between fw-bold fs-5">
                    <span>Total</span>
                    <span id="grandTotal"><?= formatCurrency(0) ?></span>
                </div>
                <button class="btn btn-primary w-100" id="checkoutBtn" disabled>
                    <i class="fas fa-credit-card me-2"></i>Proceed to Payment
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const cart = [];
    const currencySymbol = '₦';

    function formatMoney(amount) {
        return currencySymbol + amount.toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function addToCart(name, price, button) {
        const item = cart.find(product => product.name === name);
        if (item) {
            item.qty += 1;
        } else {
            cart.push({
                name,
                price,
                qty: 1
            });
        }

        updateCart();

        button.classList.remove('btn-outline-primary');
        button.classList.add('btn-success');
        button.innerHTML = '<i class="fas fa-check me-2"></i>Added';

        setTimeout(() => {
            button.classList.remove('btn-success');
            button.classList.add('btn-outline-primary');
            button.innerHTML = '<i class="fas fa-cart-plus me-2"></i>Add to Cart';
        }, 1200);
    }

    function removeCartItem(index) {
        cart.splice(index, 1);
        updateCart();
    }

    function updateCart() {
        const cartBody = document.getElementById('cartBody');
        const cartCount = document.getElementById('cartCount');
        const subtotalEl = document.getElementById('subtotal');
        const taxEl = document.getElementById('taxAmount');
        const totalEl = document.getElementById('grandTotal');
        const checkoutBtn = document.getElementById('checkoutBtn');

        if (cart.length === 0) {
            cartBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">No items in cart</td></tr>';
            cartCount.innerText = '0 items';
            subtotalEl.innerText = formatMoney(0);
            taxEl.innerText = formatMoney(0);
            totalEl.innerText = formatMoney(0);
            checkoutBtn.setAttribute('disabled', 'disabled');
            return;
        }

        let subtotal = 0;
        cartBody.innerHTML = '';

        cart.forEach((item, index) => {
            const itemTotal = item.price * item.qty;
            subtotal += itemTotal;
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${item.name}</td>
                <td class="text-center">${item.qty}</td>
                <td class="text-end">${formatMoney(itemTotal)}</td>
                <td class="text-end"><button class="btn btn-sm btn-outline-danger" onclick="removeCartItem(${index})"><i class="fas fa-trash-alt"></i></button></td>
            `;
            cartBody.appendChild(row);
        });

        const tax = subtotal * 0.075;
        const total = subtotal + tax;

        cartCount.innerText = `${cart.length} item${cart.length > 1 ? 's' : ''}`;
        subtotalEl.innerText = formatMoney(subtotal);
        taxEl.innerText = formatMoney(tax);
        totalEl.innerText = formatMoney(total);
        checkoutBtn.removeAttribute('disabled');
    }
</script>