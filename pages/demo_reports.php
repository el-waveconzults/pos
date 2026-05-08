<?php
require_once 'config/config.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">Reports & Analytics</h4>
    <button class="btn btn-primary" disabled>
        <i class="fas fa-download me-2"></i>Export Report
    </button>
</div>

<!-- Demo Notice -->
<div class="alert alert-info mb-4">
    <i class="fas fa-info-circle me-2"></i>
    <strong>Demo Mode:</strong> This is a demonstration of the reports and analytics interface.
    Interactive charts and detailed reports would be available here.
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5>Sales Report</h5>
            </div>
            <div class="card-body text-center py-5">
                <i class="fas fa-chart-line fa-3x text-primary mb-3"></i>
                <h6>Sales Analytics Chart</h6>
                <p class="text-muted">Interactive sales trends and performance metrics</p>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5>Inventory Report</h5>
            </div>
            <div class="card-body text-center py-5">
                <i class="fas fa-boxes fa-3x text-success mb-3"></i>
                <h6>Stock Levels & Alerts</h6>
                <p class="text-muted">Product inventory status and low stock warnings</p>
            </div>
        </div>
    </div>
</div>