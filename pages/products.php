<?php
require_once 'config/config.php';
$conn = getDB();

$currentUser = getCurrentUser();
$companyId = $currentUser['company_id'] ?? 0;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

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

    if ($action === 'add_product') {
        $image = $imagePath ? $imagePath : ($_POST['existing_image'] ?? '');
        $stmt = $conn->prepare("INSERT INTO products (company_id, category_id, name, sku, barcode, cost_price, sell_price, quantity, min_quantity, image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iisssddiis", $companyId, $_POST['category_id'], $_POST['name'], $_POST['sku'], $_POST['barcode'], $_POST['cost_price'], $_POST['sell_price'], $_POST['quantity'], $_POST['min_quantity'], $image);
        $stmt->execute();
        $success = "Product added successfully!";
    } elseif ($action === 'update_product') {
        $image = $imagePath ? $imagePath : ($_POST['existing_image'] ?? '');
        $stmt = $conn->prepare("UPDATE products SET company_id=?, category_id=?, name=?, sku=?, barcode=?, cost_price=?, sell_price=?, quantity=?, min_quantity=?, image=? WHERE id=?");
        $stmt->bind_param("iisssddiisi", $companyId, $_POST['category_id'], $_POST['name'], $_POST['sku'], $_POST['barcode'], $_POST['cost_price'], $_POST['sell_price'], $_POST['quantity'], $_POST['min_quantity'], $image, $_POST['id']);
        $stmt->execute();
        $success = "Product updated successfully!";
    } elseif ($action === 'delete_product') {
        $conn->query("UPDATE products SET status='inactive' WHERE id=" . intval($_POST['id']));
        $success = "Product deleted successfully!";
    }
}

// Get products with category (only this company's products)
$products = $conn->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.company_id = $companyId AND p.status = 'active' ORDER BY p.name");
$categories = $conn->query("SELECT * FROM categories WHERE company_id = $companyId ORDER BY name");
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4>Products</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#productModal">
        <i class="fas fa-plus"></i> Add Product
    </button>
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
                    <?php while ($product = $products->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <?php if ($product['image'] && file_exists($product['image'])): ?>
                                    <img src="<?= $product['image'] ?>" alt="<?= $product['name'] ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px;">
                                <?php else: ?>
                                    <div style="width: 50px; height: 50px; background: #f8f9fa; border-radius: 5px; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-image text-muted"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?= $product['sku'] ?></td>
                            <td><?= $product['barcode'] ?? '-' ?></td>
                            <td><?= $product['name'] ?></td>
                            <td><?= $product['category_name'] ?? 'Uncategorized' ?></td>
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
                                <button class="btn btn-sm btn-outline-primary" onclick="editProduct(<?= $product['id'] ?>, '<?= addslashes($product['name']) ?>', '<?= $product['sku'] ?>', '<?= $product['barcode'] ?? '' ?>', '<?= $product['image'] ?? '' ?>', <?= $product['category_id'] ?? 1 ?>, <?= $product['cost_price'] ?>, <?= $product['sell_price'] ?>, <?= $product['quantity'] ?>, <?= $product['min_quantity'] ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="delete_product">
                                    <input type="hidden" name="id" value="<?= $product['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this product?')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
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
    function editProduct(id, name, sku, barcode, image, categoryId, cost, price, qty, minQty) {
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
        document.getElementById('imagePreview').innerHTML = '';
    });

    function generateBarcode() {
        // Generate a random 12-digit barcode
        const barcode = Math.floor(100000000000 + Math.random() * 900000000000).toString();
        document.querySelector('input[name="barcode"]').value = barcode;
    }
</script>