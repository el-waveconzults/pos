<?php
require_once 'config/config.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">Expenses</h4>
    <button class="btn btn-primary" disabled>
        <i class="fas fa-plus me-2"></i>Add Expense
    </button>
</div>

<!-- Demo Notice -->
<div class="alert alert-info mb-4">
    <i class="fas fa-info-circle me-2"></i>
    <strong>Demo Mode:</strong> This is a demonstration of the expenses management interface.
    Expense tracking and categorization would be available here.
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th>Category</th>
                                <th>Amount</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Office Supplies</td>
                                <td>Operations</td>
                                <td><?php echo formatCurrency(45000); ?></td>
                                <td>2024-01-15</td>
                                <td><span class="badge bg-success">Approved</span></td>
                            </tr>
                            <tr>
                                <td>Electricity Bill</td>
                                <td>Utilities</td>
                                <td><?php echo formatCurrency(125000); ?></td>
                                <td>2024-01-14</td>
                                <td><span class="badge bg-success">Approved</span></td>
                            </tr>
                            <tr>
                                <td>Marketing Materials</td>
                                <td>Marketing</td>
                                <td><?php echo formatCurrency(75000); ?></td>
                                <td>2024-01-13</td>
                                <td><span class="badge bg-warning">Pending</span></td>
                            </tr>
                            <tr>
                                <td>Staff Training</td>
                                <td>HR</td>
                                <td><?php echo formatCurrency(200000); ?></td>
                                <td>2024-01-12</td>
                                <td><span class="badge bg-success">Approved</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>