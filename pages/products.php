<?php
require_once 'config/config.php';
$conn = getDB();

$currentUser = getCurrentUser();
$companyId = $currentUser['company_id'] ?? 0;
$isOwner = ($currentUser['role'] ?? '') === 'owner';
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $useGuestCatalog = ($isOwner || $currentUser['role'] === 'guest') && isset($_POST['guest_catalog']) && $_POST['guest_catalog'] === '1';

    // CSRF Token Verification
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Security token verification failed. Please try again.";
    } elseif ($action === 'add_product') {
        // Input Validation
        $name = validateInput($_POST['name'] ?? '', 'string', true);
        $sku = validateInput($_POST['sku'] ?? '', 'string', true);
        $barcode = validateInput($_POST['barcode'] ?? '', 'string');
        $categoryId = validateInput($_POST['category_id'] ?? 0, 'int');
        $costPrice = validateInput($_POST['cost_price'] ?? 0, 'float', true);
        $sellPrice = validateInput($_POST['sell_price'] ?? 0, 'float', true);
        $quantity = validateInput($_POST['quantity'] ?? 0, 'int', true);
        $minQuantity = validateInput($_POST['min_quantity'] ?? 0, 'int');

        if ($name === false || $sku === false || $costPrice === false || $sellPrice === false || $quantity === false) {
            $error = "Invalid input data. Please check your entries.";
        } else {
            // Handle image upload
            $imagePath = '';
            if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = 'uploads/products/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $fileName = time() . '_' . basename($_FILES['product_image']['name']);
                $targetPath = $uploadDir . $fileName;

                if (move_uploaded_file($_FILES['product_image']['tmp_name'], $targetPath)) {
                    $imagePath = $targetPath;
                }
            }

            $image = $imagePath ? $imagePath : ($_POST['existing_image'] ?? '');
            $productCompanyId = $useGuestCatalog ? 0 : $companyId;
            $stmt = $conn->prepare("INSERT INTO products (company_id, category_id, name, sku, barcode, cost_price, sell_price, quantity, min_quantity, image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iisssddiis", $productCompanyId, $categoryId, $name, $sku, $barcode, $costPrice, $sellPrice, $quantity, $minQuantity, $image);
            if ($stmt->execute()) {
                $success = "Product added successfully!";
            } else {
                $error = "Database error: " . $conn->error;
            }
        }
    } elseif ($action === 'update_product') {
        // Input Validation
        $productId = validateInput($_POST['id'] ?? 0, 'int', true);
        $name = validateInput($_POST['name'] ?? '', 'string', true);
        $sku = validateInput($_POST['sku'] ?? '', 'string', true);
        $barcode = validateInput($_POST['barcode'] ?? '', 'string');
        $categoryId = validateInput($_POST['category_id'] ?? 0, 'int');
        $costPrice = validateInput($_POST['cost_price'] ?? 0, 'float', true);
        $sellPrice = validateInput($_POST['sell_price'] ?? 0, 'float', true);
        $quantity = validateInput($_POST['quantity'] ?? 0, 'int', true);
        $minQuantity = validateInput($_POST['min_quantity'] ?? 0, 'int');

        if ($productId === false || $name === false || $sku === false || $costPrice === false || $sellPrice === false || $quantity === false) {
            $error = "Invalid input data. Please check your entries.";
        } else {
            $existingProduct = $conn->query("SELECT company_id FROM products WHERE id = $productId")->fetch_assoc();
            if (!$existingProduct) {
                $error = "Product not found.";
            } else {
                $authorized = verifyIDOR('product', $productId, $companyId);

                if (!$authorized) {
                    $error = "Unauthorized access to this product.";
                } else {
                    // Handle image upload
                    $imagePath = '';
                    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                        $uploadDir = 'uploads/products/';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }

                        $fileName = time() . '_' . basename($_FILES['product_image']['name']);
                        $targetPath = $uploadDir . $fileName;

                        if (move_uploaded_file($_FILES['product_image']['tmp_name'], $targetPath)) {
                            $imagePath = $targetPath;
                        }
                    }

                    $image = $imagePath ? $imagePath : ($_POST['existing_image'] ?? '');
                    $productCompanyId = $useGuestCatalog ? 0 : $companyId;
                    $stmt = $conn->prepare("UPDATE products SET company_id=?, category_id=?, name=?, sku=?, barcode=?, cost_price=?, sell_price=?, quantity=?, min_quantity=?, image=? WHERE id=?");
                    $stmt->bind_param("iisssddiisi", $productCompanyId, $categoryId, $name, $sku, $barcode, $costPrice, $sellPrice, $quantity, $minQuantity, $image, $productId);
                    if ($stmt->execute()) {
                        $success = "Product updated successfully!";
                    } else {
                        $error = "Database error: " . $conn->error;
                    }
                }
            }
        }
    } elseif ($action === 'delete_product') {
        $productId = validateInput($_POST['id'] ?? 0, 'int', true);
        if ($productId === false) {
            $error = "Invalid product ID.";
        } else {
            $existingProduct = $conn->query("SELECT company_id FROM products WHERE id = $productId")->fetch_assoc();
            $allowed = verifyIDOR('product', $productId, $companyId);

            if (!$existingProduct) {
                $error = "Product not found.";
            } elseif (!$allowed) {
                $error = "Unauthorized access to this product.";
            } else {
                $stmt = $conn->prepare("UPDATE products SET status='inactive' WHERE id=?");
                $stmt->bind_param("i", $productId);
                if ($stmt->execute()) {
                    $success = "Product deleted successfully!";
                } else {
                    $error = "Database error: " . $conn->error;
                }
            }
        }
    }
}

// Get products - show sample data for guests, real data for others
if ($currentUser['role'] === 'guest') {
    // Sample products for guest demo
    $products = [
        ['id' => 1, 'name' => 'Wireless Headphones', 'sku' => 'WH-001', 'barcode' => '123456789012', 'cost_price' => 15000, 'sell_price' => 25000, 'quantity' => 15, 'min_quantity' => 5, 'image' => '', 'category_name' => 'Electronics'],
        ['id' => 2, 'name' => 'Smart Watch', 'sku' => 'SW-002', 'barcode' => '123456789013', 'cost_price' => 20000, 'sell_price' => 35000, 'quantity' => 8, 'min_quantity' => 3, 'image' => '', 'category_name' => 'Electronics'],
        ['id' => 3, 'name' => 'Laptop Stand', 'sku' => 'LS-003', 'barcode' => '123456789014', 'cost_price' => 5000, 'sell_price' => 8000, 'quantity' => 20, 'min_quantity' => 5, 'image' => '', 'category_name' => 'Accessories'],
        ['id' => 4, 'name' => 'USB Cable', 'sku' => 'UC-004', 'barcode' => '123456789015', 'cost_price' => 1000, 'sell_price' => 2000, 'quantity' => 50, 'min_quantity' => 10, 'image' => '', 'category_name' => 'Accessories'],
        ['id' => 5, 'name' => 'Phone Case', 'sku' => 'PC-005', 'barcode' => '123456789016', 'cost_price' => 2000, 'sell_price' => 4000, 'quantity' => 30, 'min_quantity' => 8, 'image' => '', 'category_name' => 'Accessories'],
        ['id' => 6, 'name' => 'Bluetooth Speaker', 'sku' => 'BS-006', 'barcode' => '123456789017', 'cost_price' => 8000, 'sell_price' => 15000, 'quantity' => 12, 'min_quantity' => 4, 'image' => '', 'category_name' => 'Electronics'],
        ['id' => 7, 'name' => 'Mouse Pad', 'sku' => 'MP-007', 'barcode' => '123456789018', 'cost_price' => 800, 'sell_price' => 1500, 'quantity' => 25, 'min_quantity' => 5, 'image' => '', 'category_name' => 'Accessories'],
        ['id' => 8, 'name' => 'Webcam', 'sku' => 'WC-008', 'barcode' => '123456789019', 'cost_price' => 12000, 'sell_price' => 20000, 'quantity' => 6, 'min_quantity' => 2, 'image' => '', 'category_name' => 'Electronics']
    ];
    $categories = [
        ['id' => 1, 'name' => 'Electronics'],
        ['id' => 2, 'name' => 'Accessories']
    ];
} else {
    // Get products with category (only this company's products and guest catalog products for owner)
    $productFilter = $isOwner ? "(p.company_id = 0 OR p.company_id = $companyId)" : "p.company_id = $companyId";
    $products = $conn->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE $productFilter AND p.status = 'active' ORDER BY p.name");
    $categories = $conn->query("SELECT * FROM categories WHERE company_id = $companyId ORDER BY name");
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4>Products</h4>
    <?php if ($currentUser['role'] !== 'guest'): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#productModal">
            <i class="fas fa-plus"></i> Add Product
        </button>
    <?php else: ?>
        <div class="alert alert-info py-2 mb-0">
            <small><i class="fas fa-info-circle me-1"></i>Demo products - Sign up to manage your own inventory</small>
        </div>
    <?php endif; ?>
</div>

<?php if (isset($success)): ?>
    <div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover" id="productsTable">
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>SKU</th>
                        <th>Barcode</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Cost</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($currentUser['role'] === 'guest') {
                        foreach ($products as $product):
                    ?>
                            <tr>
                                <td>
                                    <div style="width: 50px; height: 50px; background: #f8f9fa; border-radius: 5px; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-image text-muted"></i>
                                    </div>
                                </td>
                                <td><?= escape($product['sku']) ?></td>
                                <td><?= escape($product['barcode'] ?? '-') ?></td>
                                <td><strong><?= escape($product['name']) ?></strong></td>
                                <td><span class="badge bg-secondary"><?= escape($product['category_name']) ?></span></td>
                                <td>₦ <?= number_format($product['cost_price'], 2) ?></td>
                                <td><strong>₦ <?= number_format($product['sell_price'], 2) ?></strong></td>
                                <td>
                                    <span class="badge <?= $product['quantity'] <= $product['min_quantity'] ? 'bg-danger' : 'bg-success' ?>">
                                        <?= $product['quantity'] ?>
                                    </span>
                                </td>
                                <td><span class="badge bg-success">Active</span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary me-1" onclick="showAppModal('Demo Mode', 'This is sample data for demonstration purposes. Sign up to manage your own products!', 'info')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <span class="text-muted small">Demo - View Only</span>
                                </td>
                            </tr>
                        <?php
                        endforeach;
                    } else {
                        while ($product = $products->fetch_assoc()):
                        ?>
                            <tr>
                                <td>
                                    <?php if ($product['image'] && file_exists($product['image'])): ?>
                                        <img src="<?= escape($product['image']) ?>" alt="<?= escape($product['name']) ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px;">
                                    <?php else: ?>
                                        <div style="width: 50px; height: 50px; background: #f8f9fa; border-radius: 5px; display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-image text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?= escape($product['sku']) ?></td>
                                <td><?= escape($product['barcode'] ?? '-') ?></td>
                                <td><?= escape($product['name']) ?></td>
                                <td><?= escape($product['category_name'] ?? 'Uncategorized') ?></td>
                                <td><?= formatCurrency($product['cost_price']) ?></td>
                                <td><?= formatCurrency($product['sell_price']) ?></td>
                                <td>
                                    <?php if ($product['quantity'] <= $product['min_quantity']): ?>
                                        <span class="badge bg-danger"><?= $product['quantity'] ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-success"><?= $product['quantity'] ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-<?= $product['status'] == 'active' ? 'success' : 'secondary' ?>"><?= ucfirst($product['status']) ?></span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="editProduct(<?= $product['id'] ?>, '<?= addslashes($product['name']) ?>', '<?= $product['sku'] ?>', '<?= $product['barcode'] ?? '' ?>', '<?= $product['image'] ?? '' ?>', <?= $product['category_id'] ?? 1 ?>, <?= $product['cost_price'] ?>, <?= $product['sell_price'] ?>, <?= $product['quantity'] ?>, <?= $product['min_quantity'] ?>, <?= $product['company_id'] ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                        <input type="hidden" name="action" value="delete_product">
                                        <input type="hidden" name="id" value="<?= $product['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this product?')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Product Modal -->
<div class="modal fade" id="productModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" id="productAction" value="add_product">
                    <input type="hidden" name="id" id="productId" value="">
                    <input type="hidden" name="existing_image" id="existingImage" value="">
                    <div class="mb-3">
                        <label class="form-label">Product Image (Optional)</label>
                        <input type="file" name="product_image" class="form-control" accept="image/*">
                        <div id="imagePreview" class="mt-2"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">SKU</label>
                        <input type="text" name="sku" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Barcode (Optional)</label>
                        <div class="input-group">
                            <input type="text" name="barcode" class="form-control" placeholder="Enter barcode">
                            <button type="button" class="btn btn-outline-secondary" onclick="generateBarcode()">
                                <i class="fas fa-barcode"></i> Generate
                            </button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select name="category_id" class="form-select">
                            <?php while ($cat = $categories->fetch_assoc()): ?>
                                <option value="<?= $cat['id'] ?>"><?= $cat['name'] ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <?php if ($isOwner): ?>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="guestCatalog" name="guest_catalog" value="1">
                            <label class="form-check-label" for="guestCatalog">Add to guest catalog (visible in demo pages)</label>
                        </div>
                    <?php endif; ?>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Cost Price</label>
                                <input type="number" name="cost_price" class="form-control" step="0.01" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Sell Price</label>
                                <input type="number" name="sell_price" class="form-control" step="0.01" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Quantity</label>
                                <input type="number" name="quantity" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Min Stock Alert</label>
                                <input type="number" name="min_quantity" class="form-control" value="10">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function editProduct(id, name, sku, barcode, image, categoryId, cost, price, qty, minQty, companyId) {
        console.log('Edit called:', id, name);

        // Populate modal for editing
        document.getElementById('productAction').value = 'update_product';
        document.getElementById('productId').value = id;
        document.getElementById('existingImage').value = image || '';
        document.querySelector('#productModal .modal-title').textContent = 'Edit Product';
        document.querySelector('#productModal input[name="name"]').value = name;
        document.querySelector('#productModal input[name="sku"]').value = sku;
        document.querySelector('#productModal input[name="barcode"]').value = barcode || '';
        document.querySelector('#productModal select[name="category_id"]').value = categoryId;
        document.querySelector('#productModal input[name="cost_price"]').value = cost;
        document.querySelector('#productModal input[name="sell_price"]').value = price;
        document.querySelector('#productModal input[name="quantity"]').value = qty;
        document.querySelector('#productModal input[name="min_quantity"]').value = minQty;
        const guestCheckbox = document.querySelector('#productModal input[name="guest_catalog"]');
        if (guestCheckbox) {
            guestCheckbox.checked = companyId === 0;
        }

        // Show image preview
        const preview = document.getElementById('imagePreview');
        if (image) {
            preview.innerHTML = '<img src="' + image + '" alt="Current Image" style="max-width: 150px; border-radius: 5px;">';
        } else {
            preview.innerHTML = '';
        }

        // Show modal using Bootstrap 5 method
        const modalEl = document.getElementById('productModal');
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    }

    // Reset modal when closed
    document.getElementById('productModal').addEventListener('hidden.bs.modal', function() {
        document.getElementById('productAction').value = 'add_product';
        document.getElementById('productId').value = '';
        document.getElementById('existingImage').value = '';
        document.querySelector('#productModal .modal-title').textContent = 'Add Product';
        document.querySelector('#productModal form').reset();
        const guestCheckbox = document.querySelector('#productModal input[name="guest_catalog"]');
        if (guestCheckbox) {
            guestCheckbox.checked = false;
        }
        document.getElementById('imagePreview').innerHTML = '';
    });

    function generateBarcode() {
        // Generate a random 12-digit barcode
        const barcode = Math.floor(100000000000 + Math.random() * 900000000000).toString();
        document.querySelector('input[name="barcode"]').value = barcode;
    }
</script>