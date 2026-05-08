<?php
require_once 'config/config.php';

$currentUser = getCurrentUser();
$company = getCurrentCompany();
$settings = getSettings();

$supportEmail = $settings['company_email'] ?? 'info@vendrixpos.com';
$supportPhone = $settings['company_phone'] ?? '08080500766';
$supportAddress = $settings['company_address'] ?? '';

// If user somehow gets here without a company, redirect to login
if (empty($currentUser['company_id'])) {
    redirect('login.php');
}
?>

<div class="container-fluid">
    <div class="row justify-content-center align-items-center min-vh-100">
        <div class="col-md-6 col-lg-4">
            <div class="card shadow">
                <div class="card-body text-center p-5">
                    <!-- Lock Icon -->
                    <div class="mb-4">
                        <i class="fas fa-lock fa-4x text-warning"></i>
                    </div>

                    <!-- Title -->
                    <h2 class="card-title mb-3">License Required</h2>

                    <!-- Message -->
                    <p class="card-text text-muted mb-4">
                        Your account is currently locked pending license activation.
                        The super administrator needs to generate and activate a license key for your company.
                    </p>

                    <!-- Company Info -->
                    <?php if ($company): ?>
                        <div class="alert alert-info mb-4">
                            <strong>Company:</strong> <?= escape($company['name']) ?><br>
                            <strong>Status:</strong> <span class="badge bg-warning">Waiting for License</span>
                        </div>
                    <?php endif; ?>

                    <!-- Instructions -->
                    <div class="text-start mb-4">
                        <h6>What happens next?</h6>
                        <ul class="text-muted small">
                            <li>The super administrator will review your registration</li>
                            <li>A license key will be generated for your company</li>
                            <li>You'll receive access to all system features</li>
                            <li>This process usually takes 24-48 hours</li>
                        </ul>
                    </div>

                    <!-- Contact Info -->
                    <div class="alert alert-light text-start">
                        <small>
                            <div><i class="fas fa-envelope me-1"></i>Email: <strong><?= escape($supportEmail) ?></strong></div>
                            <div><i class="fas fa-phone me-1"></i>Phone: <strong><?= escape($supportPhone) ?></strong></div>
                            <?php if ($supportAddress): ?>
                                <div><i class="fas fa-map-marker-alt me-1"></i>Address: <strong><?= escape($supportAddress) ?></strong></div>
                            <?php endif; ?>
                        </small>
                    </div>

                    <!-- Logout Button -->
                    <div class="mt-4">
                        <a href="logout.php" class="btn btn-outline-secondary">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="text-center mt-3">
                <small class="text-muted">
                    Vendrix POS System - License Management
                </small>
            </div>
        </div>
    </div>
</div>

<style>
    .min-vh-100 {
        min-height: 100vh;
    }

    .card {
        border: none;
        border-radius: 15px;
    }

    .card-body {
        border-radius: 15px;
    }

    .fa-lock {
        color: #ffc107 !important;
    }

    .btn-outline-secondary:hover {
        background-color: #6c757d;
        border-color: #6c757d;
    }
</style>