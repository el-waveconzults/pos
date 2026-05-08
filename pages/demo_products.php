<?php
require_once 'config/config.php';
$conn = getDB();

$guestProducts = [];
$productQuery = $conn->query("SELECT p.id, p.name, p.sku, c.name as category, p.sell_price as price, p.quantity, p.min_quantity, p.status FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.company_id = 0 AND p.status = 'active' ORDER BY p.name");
if ($productQuery && $productQuery->num_rows > 0) {
    while ($row = $productQuery->fetch_assoc()) {
        $guestProducts[] = $row;
    }
}

$sampleProducts = [
    ['id' => 1, 'name' => 'iPhone 16 Pro', 'sku' => 'IPH16P-128', 'category' => 'Smartphones', 'price' => 1850000, 'quantity' => 15, 'min_quantity' => 5, 'status' => 'active'],
    ['id' => 2, 'name' => 'Samsung Galaxy S24', 'sku' => 'SGS24-256', 'category' => 'Smartphones', 'price' => 1650000, 'quantity' => 8, 'min_quantity' => 3, 'status' => 'active'],
    ['id' => 3, 'name' => 'MacBook Pro M3', 'sku' => 'MBPM3-14', 'category' => 'Laptops', 'price' => 3200000, 'quantity' => 5, 'min_quantity' => 2, 'status' => 'active'],
    ['id' => 4, 'name' => 'Wireless Headphones', 'sku' => 'WH-BT500', 'category' => 'Audio', 'price' => 450000, 'quantity' => 25, 'min_quantity' => 5, 'status' => 'active'],
    ['id' => 5, 'name' => 'Smart Watch Series 9', 'sku' => 'SW-S9-45', 'category' => 'Wearables', 'price' => 600000, 'quantity' => 12, 'min_quantity' => 4, 'status' => 'active'],
    ['id' => 6, 'name' => 'Gaming Mouse', 'sku' => 'GM-PRO-1', 'category' => 'Accessories', 'price' => 75000, 'quantity' => 3, 'min_quantity' => 5, 'status' => 'active'], // Low stock
    ['id' => 7, 'name' => 'USB-C Cable', 'sku' => 'USC-2M', 'category' => 'Accessories', 'price' => 15000, 'quantity' => 50, 'min_quantity' => 10, 'status' => 'active'],
    ['id' => 8, 'name' => 'Phone Case', 'sku' => 'PC-IP16-C', 'category' => 'Accessories', 'price' => 25000, 'quantity' => 30, 'min_quantity' => 8, 'status' => 'active']
];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">Products Management</h4>
    <button class="btn btn-primary" disabled>
        <i class="fas fa-plus me-2"></i>Add Product
    </button>
</div>

<?php
$displayProducts = !empty($guestProducts) ? $guestProducts : $sampleProducts;
?>

<!-- Demo Notice -->
<div class="alert alert-info mb-4">
    <i class="fas fa-info-circle me-2"></i>
    <strong>Demo Mode:</strong> This is a demonstration of the products management interface.
    <?= empty($guestProducts) ? 'Sample data is shown for illustration purposes.' : 'Showing products configured by the super admin for guest view.' ?>
</div>

<!-- Products Table -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">All Products (<?= count($displayProducts) ?>)</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($displayProducts as $product): ?>
                        <tr>
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars($product['name']) ?></div>
                            </td>
                            <td><code><?= htmlspecialchars($product['sku'] ?? '') ?></code></td>
                            <td>
                                <span class="badge bg-secondary"><?= htmlspecialchars($product['category'] ?? 'Uncategorized') ?></span>
                            </td>
                            <td class="fw-bold text-primary"><?= formatCurrency($product['price']) ?></td>
                            <td>
                                <span class="badge bg-<?= ($product['quantity'] ?? 0) <= ($product['min_quantity'] ?? 0) ? 'danger' : 'success' ?>">
                                    <?= $product['quantity'] ?? 0 ?> units
                                </span>
                                <?php if (($product['quantity'] ?? 0) <= ($product['min_quantity'] ?? 0)): ?>
                                    <small class="text-danger d-block">Low stock!</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?= ($product['status'] ?? 'active') == 'active' ? 'success' : 'secondary' ?>">
                                    <?= ucfirst($product['status'] ?? 'active') ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary me-1" disabled>
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" disabled>
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Quick Stats -->
<div class="row mt-4">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="text-primary"><?= count($displayProducts) ?></h5>
                <p class="mb-0">Total Products</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="text-success">
                    <?= count(array_filter($displayProducts, fn($p) => ($p['quantity'] ?? 0) > ($p['min_quantity'] ?? 0))) ?>
                </h5>
                <p class="mb-0">In Stock</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="text-warning">
                    <?= count(array_filter($displayProducts, fn($p) => ($p['quantity'] ?? 0) <= ($p['min_quantity'] ?? 0))) ?>
                </h5>
                <p class="mb-0">Low Stock</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="text-info">
                    <?= count(array_unique(array_map(fn($p) => $p['category'] ?? 'Uncategorized', $displayProducts))) ?>
                </h5>
                <p class="mb-0">Categories</p>
            </div>
        </div>
    </div>
</div>