<?php
require_once 'config/config.php';
$conn = getDB();
$currentUser = getCurrentUser();

$ownerEmailStatus = '';
$ownerEmailStatusType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['owner_email_outreach'])) {
    if ($currentUser['role'] !== 'owner') {
        $ownerEmailStatus = 'Unauthorized request.';
        $ownerEmailStatusType = 'danger';
    } elseif (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $ownerEmailStatus = 'Invalid session token. Please refresh the page and try again.';
        $ownerEmailStatusType = 'danger';
    } else {
        $subject = sanitize($_POST['subject'] ?? '');
        $messageBody = trim($_POST['message'] ?? '');
        $recipientOption = $_POST['recipient_option'] ?? 'all';
        $selectedCompanyId = intval($_POST['company_id'] ?? 0);

        if (empty($subject) || empty($messageBody)) {
            $ownerEmailStatus = 'Subject and message are required.';
            $ownerEmailStatusType = 'danger';
        } else {
            $companies = [];
            if ($recipientOption === 'company' && $selectedCompanyId > 0) {
                $stmt = $conn->prepare("SELECT id, name, email FROM companies WHERE id = ? AND status = 'active'");
                $stmt->bind_param("i", $selectedCompanyId);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($company = $result->fetch_assoc()) {
                    $companies[] = $company;
                }
            } else {
                $result = $conn->query("SELECT id, name, email FROM companies WHERE status = 'active' ORDER BY name");
                while ($company = $result->fetch_assoc()) {
                    $companies[] = $company;
                }
            }

            if (count($companies) === 0) {
                $ownerEmailStatus = 'No recipient companies were found for the selected option.';
                $ownerEmailStatusType = 'danger';
            } else {
                $sentCount = 0;
                $appName = htmlspecialchars(getAppName(), ENT_QUOTES, 'UTF-8');

                foreach ($companies as $company) {
                    if (empty($company['email'])) {
                        continue;
                    }
                    $companyName = htmlspecialchars($company['name'], ENT_QUOTES, 'UTF-8');
                    $emailContent = "<html><body style='font-family: Arial, sans-serif; padding: 20px;'>"
                        . "<h2 style='color: #2c3e50;'>Message from $appName</h2>"
                        . "<p>Dear $companyName,</p>"
                        . "<div style='margin: 20px 0; line-height: 1.6;'>" . nl2br(htmlspecialchars($messageBody, ENT_QUOTES, 'UTF-8')) . "</div>"
                        . "<p>Thank you,<br/>The $appName Team</p>"
                        . "</body></html>";

                    sendHtmlEmail($company['email'], $subject, $emailContent);
                    $sentCount++;
                }

                if ($sentCount > 0) {
                    $ownerEmailStatus = "Message sent successfully to $sentCount company(ies).";
                    $ownerEmailStatusType = 'success';
                } else {
                    $ownerEmailStatus = 'The email could not be sent. Please verify the recipient list.';
                    $ownerEmailStatusType = 'danger';
                }
            }
        }
    }
}

// Owner stats - platform wide
$totalCompanies = $conn->query("SELECT COUNT(*) as count FROM companies WHERE status = 'active'")->fetch_assoc()['count'] ?? 0;
$totalUsers = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'active'")->fetch_assoc()['count'] ?? 0;
$totalSales = $conn->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM sales WHERE status = 'completed'")->fetch_assoc()['total'] ?? 0;
$pendingCompanies = $conn->query("SELECT COUNT(*) as count FROM companies WHERE status = 'pending'")->fetch_assoc()['count'] ?? 0;
$pendingCompanyList = $conn->query("SELECT id, name, email, created_at FROM companies WHERE status = 'pending' ORDER BY created_at DESC LIMIT 10");

// Subscription stats
$activeSubscriptions = $conn->query("SELECT COUNT(*) as count FROM companies WHERE subscription_status = 'active'")->fetch_assoc()['count'] ?? 0;
$expiredAccounts = $conn->query("SELECT COUNT(*) as count FROM companies WHERE subscription_status = 'expired'")->fetch_assoc()['count'] ?? 0;
$disabledAccounts = $conn->query("SELECT COUNT(*) as count FROM companies WHERE status = 'inactive'")->fetch_assoc()['count'] ?? 0;
$trialAccounts = $conn->query("SELECT COUNT(*) as count FROM companies WHERE subscription_status = 'trial'")->fetch_assoc()['count'] ?? 0;

// Support tickets
$openTickets = $conn->query("SELECT COUNT(*) as count FROM support_tickets WHERE status = 'open'")->fetch_assoc()['count'] ?? 0;
$pendingTickets = $conn->query("SELECT COUNT(*) as count FROM support_tickets WHERE status = 'pending'")->fetch_assoc()['count'] ?? 0;

// Recent companies with staff count
$recentCompanies = $conn->query("
    SELECT c.*, 
           (SELECT COUNT(*) FROM users WHERE company_id = c.id AND status = 'active') as staff_count
    FROM companies c 
    ORDER BY c.created_at DESC LIMIT 5
");
// Fetch active companies for owner communication
$emailCompanies = $conn->query("SELECT id, name, email FROM companies WHERE status = 'active' ORDER BY name");
// Recent sales across all companies
$recentSales = $conn->query("
    SELECT s.*, u.name as user_name, c.name as company_name
    FROM sales s 
    JOIN users u ON s.created_by = u.id
    JOIN companies c ON u.company_id = c.id
    ORDER BY s.created_at DESC LIMIT 10
");

// Monthly revenue
$monthlyRevenue = [];
$monthlyLabels = [];
for ($i = 11; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $monthlyLabels[] = date('M', strtotime("-$i months"));
    $result = $conn->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM sales WHERE DATE_FORMAT(created_at, '%Y-%m') = '$month' AND status = 'completed'");
    $monthlyRevenue[] = $result->fetch_assoc()['total'];
}

// Get license statistics
$licenseManager = getLicenseManager();
$licenseStats = $licenseManager->getLicenseStats();
?>

<!-- OWNER DASHBOARD -->
<div class="owner-dashboard">
    <!-- Header -->
    <div class="dashboard-header mb-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-1"><i class="fas fa-crown text-warning"></i> Owner Dashboard</h4>
                <p class="text-muted mb-0">Platform Management</p>
            </div>
            <div class="text-end">
                <small class="text-muted"><?= date('l, F j, Y') ?></small>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="stat-card stat-card-purple">
                <div class="stat-icon"><i class="fas fa-building"></i></div>
                <div class="stat-content">
                    <h6>TOTAL COMPANIES</h6>
                    <h2><?= $totalCompanies ?></h2>
                    <small><?= $pendingCompanies ?> pending</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card stat-card-green">
                <div class="stat-icon"><i class="fas fa-naira-sign"></i></div>
                <div class="stat-content">
                    <h6>TOTAL REVENUE</h6>
                    <h2><?= formatCurrency($totalSales) ?></h2>
                    <small>All time</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card stat-card-blue">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-content">
                    <h6>ACTIVE SUBS</h6>
                    <h2><?= $activeSubscriptions ?></h2>
                    <small><?= $trialAccounts ?> trial</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card stat-card-orange">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-content">
                    <h6>EXPIRED</h6>
                    <h2><?= $expiredAccounts ?></h2>
                    <small>Accounts</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card stat-card-danger">
                <div class="stat-icon"><i class="fas fa-ban"></i></div>
                <div class="stat-content">
                    <h6>DISABLED</h6>
                    <h2><?= $disabledAccounts ?></h2>
                    <small>Accounts</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card stat-card-warning">
                <div class="stat-icon"><i class="fas fa-life-ring"></i></div>
                <div class="stat-content">
                    <h6>SUPPORT</h6>
                    <h2><?= $openTickets + $pendingTickets ?></h2>
                    <small><?= $openTickets ?> open</small>
                </div>
            </div>
        </div>
    </div>

    <!-- License Stats -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-3">
                            <h6 class="mb-3"><i class="fas fa-key"></i> License Overview</h6>
                            <div style="font-size: 24px; font-weight: bold;">
                                <div><?= (int)$licenseStats['total'] ?> <small style="font-size: 12px;">Total Licenses</small></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <h6 class="mb-3"><i class="fas fa-check-circle"></i> Active</h6>
                            <div style="font-size: 28px; font-weight: bold; color: #4ade80;">
                                <?= (int)$licenseStats['active'] ?>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <h6 class="mb-3"><i class="fas fa-pause-circle"></i> Suspended</h6>
                            <div style="font-size: 28px; font-weight: bold; color: #facc15;">
                                <?= (int)$licenseStats['suspended'] ?>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <h6 class="mb-3"><i class="fas fa-times-circle"></i> Expired</h6>
                            <div style="font-size: 28px; font-weight: bold; color: #ff6b6b;">
                                <?= (int)$licenseStats['expired'] ?>
                            </div>
                            <a href="?page=admin_licenses" class="btn btn-sm btn-light mt-3">Manage Licenses</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($pendingCompanies > 0): ?>
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0"><i class="fas fa-user-clock text-warning"></i> Pending Company Registrations</h5>
                            <small class="text-muted">Review and approve new registrations before issuing licenses.</small>
                        </div>
                        <a href="?page=companies" class="btn btn-sm btn-primary">Review All Companies</a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>Company</th>
                                        <th>Email</th>
                                        <th>Requested</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($pendingCompany = $pendingCompanyList->fetch_assoc()): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($pendingCompany['name'], ENT_QUOTES) ?></td>
                                            <td><?= htmlspecialchars($pendingCompany['email'], ENT_QUOTES) ?></td>
                                            <td><?= date('M d, Y', strtotime($pendingCompany['created_at'])) ?></td>
                                            <td class="text-end">
                                                <a href="?page=companies" class="btn btn-sm btn-outline-primary">Review</a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Owner Email Outreach -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-envelope text-primary"></i> Email Outreach</h5>
                    <small class="text-muted">Send announcements to active companies</small>
                </div>
                <div class="card-body">
                    <?php if ($ownerEmailStatus): ?>
                        <div class="alert alert-<?= $ownerEmailStatusType ?> mb-4">
                            <?= $ownerEmailStatus ?>
                        </div>
                    <?php endif; ?>
                    <form method="POST">
                        <input type="hidden" name="owner_email_outreach" value="1">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Recipient</label>
                                <select class="form-select" name="recipient_option" id="recipientOption">
                                    <option value="all">All Active Companies</option>
                                    <option value="company">Single Company</option>
                                </select>
                            </div>
                            <div class="col-md-8 mb-3" id="companySelectGroup" style="display: none;">
                                <label class="form-label">Select Company</label>
                                <select class="form-select" name="company_id">
                                    <option value="">Choose a company</option>
                                    <?php if ($emailCompanies && $emailCompanies->num_rows > 0): ?>
                                        <?php while ($emailCompany = $emailCompanies->fetch_assoc()): ?>
                                            <option value="<?= $emailCompany['id'] ?>"><?= htmlspecialchars($emailCompany['name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($emailCompany['email'], ENT_QUOTES, 'UTF-8') ?>)</option>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Subject</label>
                            <input type="text" name="subject" class="form-control" placeholder="Email subject" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Message</label>
                            <textarea name="message" class="form-control" rows="5" placeholder="Write your message here" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Send Email
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Recent Companies -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0">
                    <h5 class="mb-0"><i class="fas fa-building text-purple"></i> Recent Companies</h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php while ($company = $recentCompanies->fetch_assoc()): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-0"><?= $company['name'] ?></h6>
                                        <small class="text-muted"><?= $company['email'] ?></small>
                                    </div>
                                    <span class="badge bg-<?= $company['status'] == 'active' ? 'success' : 'warning' ?>">
                                        <?= $company['status'] ?>
                                    </span>
                                </div>
                                <div class="mt-2">
                                    <small class="text-muted">
                                        <i class="fas fa-users"></i> <?= $company['staff_count'] ?> staff(s) |
                                        <span class="badge bg-<?= $company['subscription_status'] == 'active' ? 'success' : ($company['subscription_status'] == 'trial' ? 'info' : 'secondary') ?>">
                                            <?= $company['subscription_status'] ?? 'N/A' ?>
                                        </span>
                                        <?php if (!empty($company['expiry_date'])): ?>
                                            | Expires: <?= date('M d, Y', strtotime($company['expiry_date'])) ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <!-- Staff list for this company (collapsed by default) -->
                                <div class="mt-2">
                                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#staff-<?= $company['id'] ?>">
                                        <i class="fas fa-eye"></i> View Staff
                                    </button>
                                    <div class="collapse mt-2" id="staff-<?= $company['id'] ?>">
                                        <?php
                                        $companyStaff = $conn->query("SELECT name, email, role FROM users WHERE company_id = " . $company['id'] . " AND status = 'active' ORDER BY name");
                                        if ($companyStaff && $companyStaff->num_rows > 0): ?>
                                            <ul class="list-group list-group-flush small">
                                                <?php while ($staff = $companyStaff->fetch_assoc()): ?>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center py-1">
                                                        <span><i class="fas fa-user"></i> <?= $staff['name'] ?></span>
                                                        <span class="badge bg-<?= $staff['role'] == 'admin' ? 'danger' : ($staff['role'] == 'manager' ? 'warning' : 'info') ?>"><?= $staff['role'] ?></span>
                                                    </li>
                                                <?php endwhile; ?>
                                            </ul>
                                        <?php else: ?>
                                            <small class="text-muted">No staff members</small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
                <div class="card-footer bg-white">
                    <a href="?page=companies" class="btn btn-sm btn-outline-primary">View All Companies</a>
                </div>
            </div>
        </div>

        <!-- Recent Sales -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0">
                    <h5 class="mb-0"><i class="fas fa-shopping-cart text-success"></i> Recent Sales</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Invoice</th>
                                    <th>Company</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($sale = $recentSales->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= $sale['invoice_no'] ?></td>
                                        <td><small><?= $sale['company_name'] ?></small></td>
                                        <td><?= formatCurrency($sale['total_amount']) ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Revenue Chart -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0">
                    <h5 class="mb-0"><i class="fas fa-chart-line text-primary"></i> Platform Revenue Trend</h5>
                </div>
                <div class="card-body">
                    <canvas id="ownerChart" height="100"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .stat-card {
        background: white;
        border-radius: 15px;
        padding: 20px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .stat-card-purple {
        border-left: 4px solid #8e44ad;
    }

    .stat-card-blue {
        border-left: 4px solid #3498db;
    }

    .stat-card-green {
        border-left: 4px solid #27ae60;
    }

    .stat-card-orange {
        border-left: 4px solid #e67e22;
    }

    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
    }

    .stat-card-purple .stat-icon {
        background: rgba(142, 68, 173, 0.1);
        color: #8e44ad;
    }

    .stat-card-blue .stat-icon {
        background: rgba(52, 152, 219, 0.1);
        color: #3498db;
    }

    .stat-card-green .stat-icon {
        background: rgba(39, 174, 96, 0.1);
        color: #27ae60;
    }

    .stat-card-orange .stat-icon {
        background: rgba(230, 126, 34, 0.1);
        color: #e67e22;
    }

    .stat-content h6 {
        color: #6c757d;
        font-size: 12px;
        margin-bottom: 5px;
    }

    .stat-content h2 {
        font-size: 28px;
        font-weight: 700;
        margin-bottom: 0;
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    new Chart(document.getElementById('ownerChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($monthlyLabels) ?>,
            datasets: [{
                label: 'Revenue',
                data: <?= json_encode($monthlyRevenue) ?>,
                backgroundColor: 'rgba(52, 152, 219, 0.8)',
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: v => '₦' + v.toLocaleString()
                    }
                }
            }
        }
    });
</script>
<script>
    const recipientOption = document.getElementById('recipientOption');
    const companySelectGroup = document.getElementById('companySelectGroup');

    function toggleCompanySelect() {
        companySelectGroup.style.display = recipientOption.value === 'company' ? 'block' : 'none';
    }

    if (recipientOption) {
        recipientOption.addEventListener('change', toggleCompanySelect);
        toggleCompanySelect();
    }
</script>