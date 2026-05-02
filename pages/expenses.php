<?php
require_once 'config/config.php';
$conn = getDB();

$currentUser = getCurrentUser();
$companyId = $currentUser['company_id'] ?? 0;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_expense') {
        $stmt = $conn->prepare("INSERT INTO expenses (company_id, category, description, amount, expense_date) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issds", $companyId, $_POST['category'], $_POST['description'], $_POST['amount'], $_POST['expense_date']);
        $stmt->execute();
        $success = "Expense added successfully!";
    } elseif ($action === 'delete_expense') {
        $conn->query("DELETE FROM expenses WHERE id=" . intval($_POST['id']) . " AND company_id = $companyId");
        $success = "Expense deleted successfully!";
    }
}

// Get expenses (only this company's expenses)
$expenses = $conn->query("SELECT * FROM expenses WHERE company_id = $companyId ORDER BY expense_date DESC");

// Summary (only this company's expenses)
$totalExpenses = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE company_id = $companyId AND MONTH(expense_date) = MONTH(CURRENT_DATE)")->fetch_assoc()['total'];
$byCategory = $conn->query("SELECT category, SUM(amount) as total FROM expenses WHERE company_id = $companyId AND MONTH(expense_date) = MONTH(CURRENT_DATE) GROUP BY category");
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4>Expenses</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#expenseModal">
        <i class="fas fa-plus"></i> Add Expense
    </button>
</div>

<?php if (isset($success)): ?>
    <div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card bg-danger text-white">
            <div class="card-body">
                <h6>This Month Expenses</h6>
                <h2><?= formatCurrency($totalExpenses) ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">By Category</h6>
            </div>
            <div class="card-body">
                <?php while ($cat = $byCategory->fetch_assoc()): ?>
                    <div class="d-flex justify-content-between mb-2">
                        <span><?= $cat['category'] ?></span>
                        <span class="text-danger"><?= formatCurrency($cat['total']) ?></span>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
</div>

<!-- Expenses Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Amount</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($exp = $expenses->fetch_assoc()): ?>
                        <tr>
                            <td><?= date('M d, Y', strtotime($exp['expense_date'])) ?></td>
                            <td><span class="badge bg-secondary"><?= $exp['category'] ?></span></td>
                            <td><?= $exp['description'] ?></td>
                            <td class="text-danger"><?= formatCurrency($exp['amount']) ?></td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="delete_expense">
                                    <input type="hidden" name="id" value="<?= $exp['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this expense?')">
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

<!-- Expense Modal -->
<div class="modal fade" id="expenseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Expense</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_expense">
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-select" required>
                            <option value="Rent">Rent</option>
                            <option value="Utilities">Utilities</option>
                            <option value="Supplies">Supplies</option>
                            <option value="Salaries">Salaries</option>
                            <option value="Transport">Transport</option>
                            <option value="Marketing">Marketing</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <input type="text" name="description" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount</label>
                        <input type="number" name="amount" class="form-control" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Date</label>
                        <input type="date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
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