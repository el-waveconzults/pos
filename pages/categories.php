<?php
require_once 'config/config.php';
$conn = getDB();

$currentUser = getCurrentUser();
$companyId = $currentUser['company_id'] ?? 0;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_category') {
        $stmt = $conn->prepare("INSERT INTO categories (company_id, name, description) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $companyId, $_POST['name'], $_POST['description']);
        $stmt->execute();
        $success = "Category added successfully!";
    } elseif ($action === 'delete_category') {
        $conn->query("DELETE FROM categories WHERE id=" . intval($_POST['id']) . " AND company_id = $companyId");
        $success = "Category deleted successfully!";
    }
}

$categories = $conn->query("SELECT * FROM categories WHERE company_id = $companyId ORDER BY name");
$productCount = $conn->query("SELECT category_id, COUNT(*) as count FROM products WHERE company_id = $companyId AND status='active' GROUP BY category_id");
$counts = [];
while ($pc = $productCount->fetch_assoc()) {
    $counts[$pc['category_id']] = $pc['count'];
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4>Categories</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#categoryModal">
        <i class="fas fa-plus"></i> Add Category
    </button>
</div>

<?php if (isset($success)): ?>
    <div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>

<div class="row">
    <?php while ($cat = $categories->fetch_assoc()): ?>
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5><i class="fas fa-tag text-primary"></i> <?= $cat['name'] ?></h5>
                    <p class="text-muted"><?= $cat['description'] ?? 'No description' ?></p>
                    <span class="badge bg-secondary"><?= $counts[$cat['id']] ?? 0 ?> Products</span>
                </div>
                <div class="card-footer">
                    <form method="POST" style="display:inline;">
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