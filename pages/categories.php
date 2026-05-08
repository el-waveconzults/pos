<?php
require_once 'config/config.php';
$conn = getDB();

$currentUser = getCurrentUser();
$companyId = $currentUser['company_id'] ?? 0;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // CSRF Token Verification
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Security token verification failed. Please try again.";
    } elseif ($action === 'add_category') {
        // Input Validation
        $name = validateInput($_POST['name'] ?? '', 'string', true);
        $description = validateInput($_POST['description'] ?? '', 'string');

        if ($name === false) {
            $error = "Invalid category name.";
        } else {
            $stmt = $conn->prepare("INSERT INTO categories (company_id, name, description) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $companyId, $name, $description);
            if ($stmt->execute()) {
                $success = "Category added successfully!";
            } else {
                $error = "Database error: " . $conn->error;
            }
        }
    } elseif ($action === 'delete_category') {
        $categoryId = validateInput($_POST['id'] ?? 0, 'int', true);
        if ($categoryId === false) {
            $error = "Invalid category ID.";
        } elseif (!verifyIDOR('category', $categoryId, $companyId)) {
            $error = "Unauthorized access to this category.";
        } else {
            $stmt = $conn->prepare("DELETE FROM categories WHERE id=? AND company_id=?");
            $stmt->bind_param("ii", $categoryId, $companyId);
            if ($stmt->execute()) {
                $success = "Category deleted successfully!";
            } else {
                $error = "Database error: " . $conn->error;
            }
        }
    }
}

// Get categories - show sample data for guests, real data for others
if ($currentUser['role'] === 'guest') {
    // Sample categories for guest demo
    $categories = [
        ['id' => 1, 'name' => 'Electronics', 'description' => 'Electronic devices and gadgets'],
        ['id' => 2, 'name' => 'Accessories', 'description' => 'Computer and device accessories'],
        ['id' => 3, 'name' => 'Office Supplies', 'description' => 'Stationery and office items']
    ];
    $productCount = [
        ['category_id' => 1, 'count' => 3], // Electronics: 3 products
        ['category_id' => 2, 'count' => 4], // Accessories: 4 products
        ['category_id' => 3, 'count' => 1]  // Office Supplies: 1 product
    ];
} else {
    $categories = $conn->query("SELECT * FROM categories WHERE company_id = $companyId ORDER BY name");
    $productCount = $conn->query("SELECT category_id, COUNT(*) as count FROM products WHERE company_id = $companyId AND status='active' GROUP BY category_id");
}
$counts = [];
if ($currentUser['role'] === 'guest') {
    foreach ($productCount as $pc) {
        $counts[$pc['category_id']] = $pc['count'];
    }
} else {
    while ($pc = $productCount->fetch_assoc()) {
        $counts[$pc['category_id']] = $pc['count'];
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4>Categories</h4>
    <?php if ($currentUser['role'] !== 'guest'): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#categoryModal">
            <i class="fas fa-plus"></i> Add Category
        </button>
    <?php else: ?>
        <div class="alert alert-info py-2 mb-0">
            <small><i class="fas fa-info-circle me-1"></i>Demo categories - Sign up to organize your products</small>
        </div>
    <?php endif; ?>
</div>

<?php if (isset($success)): ?>
    <div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>

<div class="row">
    <?php
    if ($currentUser['role'] === 'guest') {
        foreach ($categories as $cat):
    ?>
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5><i class="fas fa-tag text-primary"></i> <?= escape($cat['name']) ?></h5>
                        <p class="text-muted"><?= escape($cat['description'] ?? 'No description') ?></p>
                        <span class="badge bg-secondary"><?= $counts[$cat['id']] ?? 0 ?> Products</span>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-sm btn-outline-primary" onclick="showAppModal('Demo Mode', 'This is sample data for demonstration purposes. Sign up to manage your own categories!', 'info')">
                            <i class="fas fa-eye"></i> View
                        </button>
                        <span class="text-muted small ms-2">Demo - View Only</span>
                    </div>
                </div>
            </div>
        <?php
        endforeach;
    } else {
        while ($cat = $categories->fetch_assoc()):
        ?>
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5><i class="fas fa-tag text-primary"></i> <?= escape($cat['name']) ?></h5>
                        <p class="text-muted"><?= escape($cat['description'] ?? 'No description') ?></p>
                        <span class="badge bg-secondary"><?= $counts[$cat['id']] ?? 0 ?> Products</span>
                    </div>
                    <div class="card-footer">
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="action" value="delete_category">
                            <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this category?')">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    <?php } ?>
</div>

<!-- Category Modal -->
<div class="modal fade" id="categoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="add_category">
                    <div class="mb-3">
                        <label class="form-label">Category Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
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