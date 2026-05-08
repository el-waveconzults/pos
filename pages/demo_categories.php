<?php
require_once 'config/config.php';
$conn = getDB();

$guestCategories = [];
$categoryQuery = $conn->query("SELECT c.id, c.name, c.description, COUNT(p.id) as product_count FROM categories c LEFT JOIN products p ON p.category_id = c.id AND p.company_id = 0 AND p.status = 'active' WHERE c.company_id = 0 GROUP BY c.id ORDER BY c.name");
if ($categoryQuery && $categoryQuery->num_rows > 0) {
    while ($row = $categoryQuery->fetch_assoc()) {
        $guestCategories[] = $row;
    }
}

$sampleCategories = [
    ['name' => 'Smartphones', 'products' => 8, 'color' => 'primary'],
    ['name' => 'Laptops', 'products' => 5, 'color' => 'success'],
    ['name' => 'Audio', 'products' => 12, 'color' => 'info'],
    ['name' => 'Wearables', 'products' => 6, 'color' => 'warning'],
    ['name' => 'Accessories', 'products' => 25, 'color' => 'secondary'],
    ['name' => 'Tablets', 'products' => 4, 'color' => 'danger']
];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">Categories Management</h4>
    <button class="btn btn-primary" disabled>
        <i class="fas fa-plus me-2"></i>Add Category
    </button>
</div>

<!-- Demo Notice -->
<div class="alert alert-info mb-4">
    <i class="fas fa-info-circle me-2"></i>
    <strong>Demo Mode:</strong> This is a demonstration of the categories management interface.
    <?= empty($guestCategories) ? 'Sample categories are shown for illustration purposes.' : 'Showing categories configured by the super admin for guest view.' ?>
</div>

<div class="row">
    <?php
    $displayCategories = !empty($guestCategories) ? $guestCategories : $sampleCategories;
    foreach ($displayCategories as $category): ?>
        <div class="col-md-4 mb-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <div class="bg-<?= $category['color'] ?? 'primary' ?> text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 60px; height: 60px; font-size: 1.5rem;">
                        <i class="fas fa-tag"></i>
                    </div>
                    <h5 class="mt-3"><?= htmlspecialchars($category['name']) ?></h5>
                    <p class="text-muted mb-0"><?= htmlspecialchars($category['product_count'] ?? $category['products']) ?> products</p>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>