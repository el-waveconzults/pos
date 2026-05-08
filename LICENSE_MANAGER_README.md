# License Manager System for POS

A comprehensive license management system for the POS application with support for tier-based features, license key generation, validation, and enforcement.

## Features

- **License Key Generation**: Automatic generation of unique license keys in format `POS-XXXX-XXXX-XXXX-XXXX`
- **Tier Management**: Three license tiers - Starter, Professional, and Enterprise
- **Feature Control**: Different features unlocked per tier
- **User/Branch Limits**: Enforce user and branch limits based on license tier
- **License Expiration**: Automatic tracking and suspension of expired licenses
- **Admin Dashboard**: Full license administration interface
- **Audit Logging**: Track all license changes and events
- **User-Facing Manager**: Let users activate their own licenses

## Installation

### 1. Create Database Tables

Run the migration script once:

```php
require 'config/config.php';
require 'migrations/create_licenses_table.php';
```

Or navigate to: `http://localhost/pos/migrations/create_licenses_table.php`

### 2. Files Added

- `config/LicenseManager.php` - Core license management class
- `config/LicenseMiddleware.php` - License validation middleware
- `pages/license_manager.php` - User license activation page
- `pages/admin_licenses.php` - Admin license management page
- `migrations/create_licenses_table.php` - Database migration

## License Tiers

### Starter

- Max Users: 5
- Max Branches: 1
- Features:
  - Basic POS
  - Inventory Management
  - Customer Management

### Professional

- Max Users: 20
- Max Branches: 10
- Features:
  - All Starter features
  - Reports
  - Multi-Branch Support
  - Advanced Analytics

### Enterprise

- Max Users: Unlimited
- Max Branches: Unlimited
- Features:
  - All Professional features
  - Custom Reports
  - API Access
  - SSO Integration
  - Priority Support

## Usage

### For Super Admins

#### Generate a License

1. Go to: `http://localhost/pos/pages/admin_licenses.php`
2. Fill in the tier, optional company assignment, and expiration date
3. Click "Generate License"
4. License key will be displayed

#### Manage Licenses

- **Suspend**: Disable a license temporarily
- **Reactivate**: Re-enable a suspended license
- **Extend**: Add more days to the expiration date
- View statistics on total, active, suspended, and expired licenses

### For Users/Companies

#### Activate a License

1. Go to: `http://localhost/pos/pages/license_manager.php`
2. Enter the license key in the provided field
3. Click "Activate License"
4. License features become available immediately

#### View License Status

- See current license tier
- Check maximum users and branches
- View expiration date
- Get warnings if license is expiring soon

## API Reference

### LicenseManager Class

```php
$licenseManager = getLicenseManager();

// Generate a new license
$result = $licenseManager->createLicense([
    'tier' => 'professional',
    'company_id' => 1,
    'expires_at' => '2025-05-03'
]);

// Validate a license
$validation = $licenseManager->validateLicense('POS-XXXX-XXXX-XXXX-XXXX');

// Get company's license
$license = $licenseManager->getCompanyLicense($companyId);

// Check if feature is available
$hasReports = $licenseManager->hasFeature($companyId, 'reports');

// Get tier features
$features = $licenseManager->getTierFeatures('professional');

// Suspend/Reactivate
$licenseManager->suspendLicense($licenseKey);
$licenseManager->reactivateLicense($licenseKey);

// Extend expiration
$licenseManager->extendLicense($licenseKey, 365); // Add 365 days
```

### LicenseMiddleware Class

```php
$middleware = getLicenseMiddleware();

// Validate company license
$isValid = $middleware->validateCompanyLicense($companyId);

// Check user limit
$check = $middleware->checkUserLimit($companyId);
if (!$check['allowed']) {
    echo $check['message']; // "User limit reached (5/5)"
}

// Check branch limit
$check = $middleware->checkBranchLimit($companyId);

// Get license info for display
$info = $middleware->getLicenseInfo($companyId);
echo "Days left: " . $info['daysLeft'];
echo "Expiring soon: " . ($info['isExpiringSoon'] ? 'Yes' : 'No');

// Enforce restrictions
$result = $middleware->enforceRestrictions($companyId, 'user');
```

## Integration Examples

### Check License Before Allowing Feature

```php
<?php
require 'config/config.php';

$middleware = getLicenseMiddleware();
$companyId = getCurrentUser()['company_id'];

// Check if user has this feature
if (!getLicenseManager()->hasFeature($companyId, 'reports')) {
    die('Reports feature not available in your plan.');
}

// Continue with reports functionality...
?>
```

### Enforce User Limit on New User Creation

```php
<?php
$middleware = getLicenseMiddleware();
$companyId = $_POST['company_id'];

$check = $middleware->checkUserLimit($companyId);
if (!$check['allowed']) {
    // User limit reached
    jsonResponse(['error' => $check['message']], 400);
}

// Create the user...
?>
```

### Display License Status Banner

```php
<?php
$middleware = getLicenseMiddleware();
$licenseInfo = $middleware->getLicenseInfo($_SESSION['company_id']);

if ($licenseInfo) {
    if ($licenseInfo['isExpiringSoon']) {
        echo "<div class='alert alert-warning'>";
        echo "Your license expires in {$licenseInfo['daysLeft']} days!";
        echo "</div>";
    }
}
?>
```

## Database Schema

### licenses table

- `id` - Primary key
- `license_key` - Unique license key (e.g., POS-XXXX-XXXX-XXXX-XXXX)
- `company_id` - Associated company (NULL if unassigned)
- `tier` - License tier (starter, professional, enterprise)
- `max_users` - Maximum allowed users
- `max_branches` - Maximum allowed branches
- `activated_at` - When license was activated
- `expires_at` - License expiration date
- `last_verified_at` - Last verification timestamp
- `status` - active, suspended, or expired
- `notes` - Optional notes

### license_audit_log table

- `id` - Primary key
- `license_id` - Reference to license
- `action` - Action performed (created, extended, suspended, etc.)
- `old_value` - Previous value
- `new_value` - New value
- `user_id` - User who performed action
- `ip_address` - IP address
- `user_agent` - Browser/client info
- `created_at` - Timestamp

## Security Considerations

1. **License Keys**: Generated using cryptographically secure random bytes
2. **Validation**: License format and database consistency verified
3. **Audit Trail**: All license changes are logged
4. **Expiration Checks**: Automatic suspension of expired licenses
5. **Database**: Foreign key constraints and proper indexing

## Troubleshooting

### License Not Found

- Verify license key format: `POS-XXXX-XXXX-XXXX-XXXX`
- Ensure license status is 'active'
- Check if license has expired

### User Limit Reached

- Upgrade to a higher tier
- Deactivate unused user accounts
- Contact support to extend limits

### Feature Not Available

- Check license tier and included features
- Upgrade to Professional or Enterprise for more features
- Verify license is properly activated

## Future Enhancements

- [ ] License renewal/subscription automation
- [ ] Trial period support
- [ ] Feature-specific license keys
- [ ] License reselling/distribution
- [ ] Usage analytics and reporting
- [ ] Payment gateway integration
- [ ] License transfer between companies
