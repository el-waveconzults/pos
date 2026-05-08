<?php
require_once(__DIR__ . '/../config/config.php');

$currentUser = getCurrentUser();

$settings = getSettings();
$licenseCurrency = htmlspecialchars($settings['license_currency'] ?? '$', ENT_QUOTES);
$starterPrice = htmlspecialchars($settings['license_starter_price'] ?? 'Free', ENT_QUOTES);
$professionalPrice = htmlspecialchars($settings['license_professional_price'] ?? '99', ENT_QUOTES);
$enterprisePrice = htmlspecialchars($settings['license_enterprise_price'] ?? 'Custom', ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>License Plans - POS System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }

        .pricing-header {
            text-align: center;
            color: white;
            margin-bottom: 50px;
        }

        .pricing-header h1 {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .pricing-header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .pricing-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .pricing-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.3);
        }

        .pricing-card.featured {
            border: 3px solid #667eea;
            position: relative;
            top: -20px;
            z-index: 10;
        }

        .pricing-badge {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            margin-bottom: 15px;
            text-transform: uppercase;
            font-weight: bold;
        }

        .plan-name {
            font-size: 1.5rem;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .plan-price {
            font-size: 2.5rem;
            font-weight: bold;
            color: #667eea;
            margin: 15px 0;
        }

        .plan-price small {
            font-size: 1rem;
            color: #666;
            display: block;
            margin-top: 5px;
        }

        .plan-description {
            color: #666;
            margin-bottom: 20px;
            font-size: 0.95rem;
        }

        .features-list {
            list-style: none;
            padding: 0;
            margin: 25px 0;
        }

        .features-list li {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
            color: #333;
            display: flex;
            align-items: center;
        }

        .features-list li:last-child {
            border-bottom: none;
        }

        .features-list i {
            color: #28a745;
            margin-right: 10px;
            font-weight: bold;
        }

        .features-list i.disabled {
            color: #ccc;
        }

        .btn-select {
            width: 100%;
            padding: 12px;
            font-weight: bold;
            margin-top: 20px;
            border-radius: 6px;
            transition: 0.3s;
        }

        .btn-select:hover {
            transform: scale(1.02);
        }

        .back-button {
            margin-bottom: 30px;
        }

        .back-button a {
            color: white;
            text-decoration: none;
            font-size: 1rem;
        }

        .back-button a:hover {
            text-decoration: underline;
        }

        .comparison-table {
            margin-top: 50px;
        }

        .comparison-table table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }

        .comparison-table th,
        .comparison-table td {
            padding: 15px;
            text-align: center;
            border-bottom: 1px solid #eee;
        }

        .comparison-table th {
            background: #2c3e50;
            color: white;
            font-weight: bold;
        }

        .comparison-table th:first-child {
            text-align: left;
        }

        .comparison-table td:first-child {
            text-align: left;
            background: #f5f5f5;
            color: #333;
            font-weight: 500;
        }

        .comparison-table i.fa-check {
            color: #28a745;
        }

        .comparison-table i.fa-times {
            color: #dc3545;
        }

        @media (max-width: 768px) {
            .pricing-header h1 {
                font-size: 2rem;
            }

            .pricing-card.featured {
                top: 0;
            }

            .plan-price {
                font-size: 2rem;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="back-button">
            <a href="?page=license_manager"><i class="fas fa-arrow-left"></i> Back to License Manager</a>
        </div>

        <div class="pricing-header">
            <h1>🔐 License Plans</h1>
            <p>Choose the perfect plan for your business</p>
        </div>

        <div class="row">
            <!-- Starter Plan -->
            <div class="col-md-4">
                <div class="pricing-card">
                    <div class="pricing-badge">STARTER</div>
                    <div class="plan-name">Starter</div>
                    <div class="plan-price">
                        <?= $starterPrice ?>
                        <small>Entry-level POS</small>
                    </div>
                    <p class="plan-description">Perfect for small businesses just starting out</p>

                    <ul class="features-list">
                        <li><i class="fas fa-check"></i> Basic POS System</li>
                        <li><i class="fas fa-check"></i> Inventory Management</li>
                        <li><i class="fas fa-check"></i> Customer Database</li>
                        <li><i class="fas fa-times disabled"></i> Reports & Analytics</li>
                        <li><i class="fas fa-times disabled"></i> Multi-Branch Support</li>
                        <li><i class="fas fa-times disabled"></i> Advanced Features</li>
                    </ul>

                    <div style="color: #667; font-weight: bold; padding: 15px; background: #f5f5f5; border-radius: 6px; text-align: center;">
                        <p style="margin: 0;">
                            <i class="fas fa-users"></i> 5 Users<br>
                            <i class="fas fa-code-branch"></i> 1 Branch
                        </p>
                    </div>

                    <button class="btn btn-select btn-outline-primary">Current Plan</button>
                </div>
            </div>

            <!-- Professional Plan -->
            <div class="col-md-4">
                <div class="pricing-card featured">
                    <div class="pricing-badge" style="background: #667eea;">⭐ RECOMMENDED</div>
                    <div class="plan-name">Professional</div>
                    <div class="plan-price">
                        <?= $licenseCurrency ?><?= $professionalPrice ?>
                        <small>per month / Up to 10 branches</small>
                    </div>
                    <p class="plan-description">Best for growing businesses and chains</p>

                    <ul class="features-list">
                        <li><i class="fas fa-check"></i> Basic POS System</li>
                        <li><i class="fas fa-check"></i> Inventory Management</li>
                        <li><i class="fas fa-check"></i> Customer Database</li>
                        <li><i class="fas fa-check"></i> Reports & Analytics</li>
                        <li><i class="fas fa-check"></i> Multi-Branch Support</li>
                        <li><i class="fas fa-times disabled"></i> Advanced Features</li>
                    </ul>

                    <div style="color: #667; font-weight: bold; padding: 15px; background: #f5f5f5; border-radius: 6px; text-align: center;">
                        <p style="margin: 0;">
                            <i class="fas fa-users"></i> 20 Users<br>
                            <i class="fas fa-code-branch"></i> 10 Branches
                        </p>
                    </div>

                    <button class="btn btn-select btn-primary">Upgrade to Professional</button>
                </div>
            </div>

            <!-- Enterprise Plan -->
            <div class="col-md-4">
                <div class="pricing-card">
                    <div class="pricing-badge">ENTERPRISE</div>
                    <div class="plan-name">Enterprise</div>
                    <div class="plan-price">
                        <?= $enterprisePrice ?>
                        <small>Unlimited everything</small>
                    </div>
                    <p class="plan-description">For large enterprises and organizations</p>

                    <ul class="features-list">
                        <li><i class="fas fa-check"></i> Basic POS System</li>
                        <li><i class="fas fa-check"></i> Inventory Management</li>
                        <li><i class="fas fa-check"></i> Customer Database</li>
                        <li><i class="fas fa-check"></i> Reports & Analytics</li>
                        <li><i class="fas fa-check"></i> Multi-Branch Support</li>
                        <li><i class="fas fa-check"></i> Advanced Features & APIs</li>
                    </ul>

                    <div style="color: #667; font-weight: bold; padding: 15px; background: #f5f5f5; border-radius: 6px; text-align: center;">
                        <p style="margin: 0;">
                            <i class="fas fa-users"></i> Unlimited Users<br>
                            <i class="fas fa-code-branch"></i> Unlimited Branches
                        </p>
                    </div>

                    <button class="btn btn-select btn-success">Contact Sales</button>
                </div>
            </div>
        </div>

        <!-- Detailed Comparison Table -->
        <div class="comparison-table mt-5">
            <h3 class="text-white mb-4 text-center">Detailed Feature Comparison</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th style="text-align: left;">Feature</th>
                        <th>Starter</th>
                        <th style="background: #667eea;">Professional</th>
                        <th>Enterprise</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Basic POS</strong></td>
                        <td><i class="fas fa-check"></i></td>
                        <td><i class="fas fa-check"></i></td>
                        <td><i class="fas fa-check"></i></td>
                    </tr>
                    <tr>
                        <td><strong>Inventory System</strong></td>
                        <td><i class="fas fa-check"></i></td>
                        <td><i class="fas fa-check"></i></td>
                        <td><i class="fas fa-check"></i></td>
                    </tr>
                    <tr>
                        <td><strong>Customer Database</strong></td>
                        <td><i class="fas fa-check"></i></td>
                        <td><i class="fas fa-check"></i></td>
                        <td><i class="fas fa-check"></i></td>
                    </tr>
                    <tr>
                        <td><strong>Sales Reports</strong></td>
                        <td><i class="fas fa-times"></i></td>
                        <td><i class="fas fa-check"></i></td>
                        <td><i class="fas fa-check"></i></td>
                    </tr>
                    <tr>
                        <td><strong>Analytics</strong></td>
                        <td><i class="fas fa-times"></i></td>
                        <td><i class="fas fa-check"></i></td>
                        <td><i class="fas fa-check"></i></td>
                    </tr>
                    <tr>
                        <td><strong>Multi-Branch</strong></td>
                        <td><i class="fas fa-times"></i></td>
                        <td><i class="fas fa-check"></i></td>
                        <td><i class="fas fa-check"></i></td>
                    </tr>
                    <tr>
                        <td><strong>Custom Reports</strong></td>
                        <td><i class="fas fa-times"></i></td>
                        <td><i class="fas fa-times"></i></td>
                        <td><i class="fas fa-check"></i></td>
                    </tr>
                    <tr>
                        <td><strong>API Access</strong></td>
                        <td><i class="fas fa-times"></i></td>
                        <td><i class="fas fa-times"></i></td>
                        <td><i class="fas fa-check"></i></td>
                    </tr>
                    <tr>
                        <td><strong>SSO Integration</strong></td>
                        <td><i class="fas fa-times"></i></td>
                        <td><i class="fas fa-times"></i></td>
                        <td><i class="fas fa-check"></i></td>
                    </tr>
                    <tr>
                        <td><strong>Priority Support</strong></td>
                        <td><i class="fas fa-times"></i></td>
                        <td><i class="fas fa-times"></i></td>
                        <td><i class="fas fa-check"></i></td>
                    </tr>
                    <tr>
                        <td><strong>Max Users</strong></td>
                        <td>5</td>
                        <td>20</td>
                        <td>Unlimited</td>
                    </tr>
                    <tr>
                        <td><strong>Max Branches</strong></td>
                        <td>1</td>
                        <td>10</td>
                        <td>Unlimited</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- FAQ Section -->
        <div class="bg-white rounded-3 p-5 mt-5">
            <h3 class="mb-4"><i class="fas fa-question-circle"></i> Frequently Asked Questions</h3>
            <div class="accordion" id="faqAccordion">
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                            Can I upgrade or downgrade my plan anytime?
                        </button>
                    </h2>
                    <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Yes! You can change your plan at any time. Changes take effect immediately, and we'll prorate billing accordingly.
                        </div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                            Do you offer refunds?
                        </button>
                    </h2>
                    <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            We offer a 30-day money-back guarantee if you're not satisfied with our service. No questions asked.
                        </div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                            Is there a setup fee?
                        </button>
                    </h2>
                    <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            No setup fees! You only pay the monthly subscription. We offer free onboarding and training for all plans.
                        </div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                            Do you offer annual billing discounts?
                        </button>
                    </h2>
                    <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Yes! Annual plans get 20% off. Contact our sales team to set up annual billing.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>

</html>