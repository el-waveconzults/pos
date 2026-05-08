<?php
require_once 'config/config.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">User Management</h4>
    <button class="btn btn-primary" disabled>
        <i class="fas fa-user-plus me-2"></i>Add User
    </button>
</div>

<!-- Demo Notice -->
<div class="alert alert-info mb-4">
    <i class="fas fa-info-circle me-2"></i>
    <strong>Demo Mode:</strong> This is a demonstration of the user management interface.
    User accounts, roles, and permissions would be managed here.
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Last Login</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>John Admin</td>
                                <td>admin@demo.com</td>
                                <td><span class="badge bg-danger">Admin</span></td>
                                <td><span class="badge bg-success">Active</span></td>
                                <td>2 hours ago</td>
                            </tr>
                            <tr>
                                <td>Sarah Manager</td>
                                <td>manager@demo.com</td>
                                <td><span class="badge bg-warning">Manager</span></td>
                                <td><span class="badge bg-success">Active</span></td>
                                <td>1 day ago</td>
                            </tr>
                            <tr>
                                <td>Mike Cashier</td>
                                <td>cashier@demo.com</td>
                                <td><span class="badge bg-info">Cashier</span></td>
                                <td><span class="badge bg-success">Active</span></td>
                                <td>3 hours ago</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>