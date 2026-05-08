<?php
require_once 'config/config.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">Invoices</h4>
    <button class="btn btn-primary" disabled>
        <i class="fas fa-plus me-2"></i>Create Invoice
    </button>
</div>

<!-- Demo Notice -->
<div class="alert alert-info mb-4">
    <i class="fas fa-info-circle me-2"></i>
    <strong>Demo Mode:</strong> This is a demonstration of the invoices management interface.
    Invoice creation, tracking, and management would be available here.
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>INV-2024-001</td>
                                <td>John Doe</td>
                                <td><?php echo formatCurrency(250000); ?></td>
                                <td><span class="badge bg-success">Paid</span></td>
                                <td>2024-01-15</td>
                            </tr>
                            <tr>
                                <td>INV-2024-002</td>
                                <td>Jane Smith</td>
                                <td><?php echo formatCurrency(180000); ?></td>
                                <td><span class="badge bg-warning">Pending</span></td>
                                <td>2024-01-14</td>
                            </tr>
                            <tr>
                                <td>INV-2024-003</td>
                                <td>Bob Johnson</td>
                                <td><?php echo formatCurrency(320000); ?></td>
                                <td><span class="badge bg-success">Paid</span></td>
                                <td>2024-01-13</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>