# License Feature Gate Implementation Guide

## Overview

The `LicenseFeatureGate` system controls feature access based on user's license tier:

- **Starter (Free)**: Basic POS, Inventory, Customers
- **Professional ($99/mo)**: All Starter + Reports, Analytics, Multi-Branch, Expenses
- **Enterprise (Custom)**: All Professional + Custom Reports, API, SSO, Priority Support

## How It Works

### 1. Feature Protection in Pages

Add this at the TOP of any page that requires a paid feature:

```php
<?php
require_once(__DIR__ . '/../config/config.php');

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// ENFORCE LICENSE CHECK - add this for paid features
requireLicenseFeature('reports');  // or 'multi_branch', 'analytics', etc.

// Rest of your page code...
?>
```

### 2. Feature Requirements

Available features for licensing:

**Starter Features (Free):**

- `basic_pos` - Point of Sale System
- `inventory` - Inventory Management
- `customers` - Customer Database

**Professional Features:**

- `reports` - Sales Reports
- `analytics` - Analytics Dashboard
- `multi_branch` - Multi-Branch Support
- `expenses` - Expense Management

**Enterprise Features:**

- `custom_reports` - Custom Report Builder
- `api_access` - API Integration
- `sso_integration` - Single Sign-On

### 3. Example: Adding License Check to Reports Page

Current reports.php (WITHOUT license check):

```php
<?php
require_once 'config/config.php';
// page code...
?>
```

Updated reports.php (WITH license check):

```php
<?php
require_once 'config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

// Check if company has license for reports feature
requireLicenseFeature('reports');

// Rest of page...
$sales = getDB()->query("SELECT * FROM sales...");
?>
```

### 4. How Redirects Work

When `requireLicenseFeature('feature')` is called:

1. It checks if user's company has an active license
2. It checks if license tier includes the feature
3. **If allowed**: Page loads normally
4. **If NOT allowed**: Redirects to `feature_restricted.php` showing:
   - ❌ Lock icon and "Feature Restricted" message
   - 📋 Current license status
   - 📊 All 3 tier options with comparison
   - 🔗 Links to upgrade or activate license

### 5. Manual License Checking (Advanced)

If you need more control than `requireLicenseFeature()`:

```php
<?php
$gate = getLicenseFeatureGate();
$check = $gate->canAccessFeature('reports');

if ($check['allowed']) {
    // Show reports page
} else {
    // Show custom message
    echo "Error: " . $check['message'];
    echo "Required tier: " . $check['requiredTier'];
}
?>
```

### 6. Getting License Info in Pages

To display license status in a page:

```php
<?php
$currentUser = getCurrentUser();
$companyId = $currentUser['company_id'] ?? $_SESSION['company_id'];
$licenseManager = getLicenseManager();
$license = $licenseManager->getCompanyLicense($companyId);

if ($license) {
    echo "Plan: " . $license['tier'];
    echo "Expires: " . $license['expires_at'];
    echo "Max Users: " . $license['max_users'];
}
?>
```

### 7. Pages That Need License Checks

Add `requireLicenseFeature()` to these pages:

| Page                   | Feature          | Tier          |
| ---------------------- | ---------------- | ------------- |
| pages/reports.php      | `reports`        | Professional+ |
| pages/expenses.php     | `expenses`       | Professional+ |
| pages/invoices.php\*   | `multi_branch`   | Professional+ |
| Custom reports builder | `custom_reports` | Enterprise    |
| API endpoints          | `api_access`     | Enterprise    |

\*Only if multi-branch features are present

## Implementation Checklist

- [ ] Add `requireLicenseFeature('feature')` to top of feature pages
- [ ] Test with different license tiers (Starter, Professional, Enterprise)
- [ ] Test without license (should redirect to feature_restricted)
- [ ] Test with expired license (should be rejected)
- [ ] Test with suspended license (should be rejected)
- [ ] Verify feature_restricted page shows correct tier comparison

## Quick Start Examples

**For Reports Page (Professional feature):**

```php
<?php
require_once 'config/config.php';
if (!isLoggedIn()) redirect('login.php');
requireLicenseFeature('reports');
// ... rest of page
```

**For Expenses Page (Professional feature):**

```php
<?php
require_once 'config/config.php';
if (!isLoggedIn()) redirect('login.php');
requireLicenseFeature('expenses');
// ... rest of page
```

**For Multi-Branch Features (Professional feature):**

```php
<?php
require_once 'config/config.php';
if (!isLoggedIn()) redirect('login.php');
requireLicenseFeature('multi_branch');
// ... rest of page
```

## Troubleshooting

**User redirected to feature_restricted unexpectedly:**

- Check if license is active (not suspended/expired)
- Verify company_id is set in session
- Check feature name matches requirements

**"Feature not found" error:**

- Add the feature to `featureRequirements` in LicenseFeatureGate.php
- Make sure feature name matches exactly

**License checking not working:**

- Verify `requireLicenseFeature()` is called at page TOP (before any output)
- Check config.php includes LicenseFeatureGate.php

---

**Last Updated:** May 3, 2026
