<?php
require_once 'config/config.php';
?>

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px; font-size: 2rem;">
                    <i class="fas fa-user"></i>
                </div>
                <h5 class="mt-3">Demo User</h5>
                <p class="text-muted">Guest Account</p>
                <button class="btn btn-primary" disabled>
                    <i class="fas fa-camera me-2"></i>Change Photo
                </button>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5>Profile Information</h5>
            </div>
            <div class="card-body">
                <!-- Demo Notice -->
                <div class="alert alert-info mb-4">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Demo Mode:</strong> This is a demonstration of the user profile interface.
                    Profile editing and account management features would be available here.
                </div>

                <form>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">First Name</label>
                                <input type="text" class="form-control" value="Demo" disabled>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Last Name</label>
                                <input type="text" class="form-control" value="User" disabled>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" value="demo@demo.com" disabled>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <input type="text" class="form-control" value="Guest" disabled>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Account Status</label>
                        <input type="text" class="form-control" value="Active" disabled>
                    </div>

                    <button type="submit" class="btn btn-primary" disabled>
                        <i class="fas fa-save me-2"></i>Update Profile
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>