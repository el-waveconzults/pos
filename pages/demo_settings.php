<?php
require_once 'config/config.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">System Settings</h4>
    <button class="btn btn-primary" disabled>
        <i class="fas fa-save me-2"></i>Save Changes
    </button>
</div>

<!-- Demo Notice -->
<div class="alert alert-info mb-4">
    <i class="fas fa-info-circle me-2"></i>
    <strong>Demo Mode:</strong> This is a demonstration of the system settings interface.
    Configuration options and system preferences would be available here.
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5>General Settings</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Company Name</label>
                    <input type="text" class="form-control" value="Demo Company Ltd" disabled>
                </div>
                <div class="mb-3">
                    <label class="form-label">Currency</label>
                    <input type="text" class="form-control" value="₦ Naira (NGN)" disabled>
                </div>
                <div class="mb-3">
                    <label class="form-label">Timezone</label>
                    <input type="text" class="form-control" value="Africa/Lagos" disabled>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5>Tax Settings</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">VAT Rate</label>
                    <input type="text" class="form-control" value="7.5%" disabled>
                </div>
                <div class="mb-3">
                    <label class="form-label">Tax Calculation</label>
                    <input type="text" class="form-control" value="Inclusive" disabled>
                </div>
            </div>
        </div>
    </div>
</div>