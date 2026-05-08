<?php

/**
 * License Feature Enforcement Middleware
 * Checks if user's license allows access to a specific feature
 */
class LicenseFeatureGate
{
    private $licenseManager;
    private $currentUser;

    // Feature to tier mapping
    private $featureRequirements = [
        // Free/Starter features
        'basic_pos' => 'starter',
        'inventory' => 'starter',
        'customers' => 'starter',

        // Professional features
        'reports' => 'professional',
        'analytics' => 'professional',
        'multi_branch' => 'professional',
        'expenses' => 'professional',

        // Enterprise features
        'custom_reports' => 'enterprise',
        'api_access' => 'enterprise',
        'sso_integration' => 'enterprise'
    ];

    public function __construct($licenseManager, $currentUser)
    {
        $this->licenseManager = $licenseManager;
        $this->currentUser = $currentUser;
    }

    /**
     * Check if user can access a feature
     * Returns: ['allowed' => bool, 'message' => string, 'requiredTier' => string]
     */
    public function canAccessFeature($feature)
    {
        // Owners have unrestricted access to all features
        if ($this->currentUser['role'] === 'owner') {
            return ['allowed' => true, 'message' => ''];
        }

        // If no feature requirement defined, allow access (free features)
        if (!isset($this->featureRequirements[$feature])) {
            return ['allowed' => true, 'message' => ''];
        }

        $requiredTier = $this->featureRequirements[$feature];
        $companyId = $this->currentUser['company_id'] ?? $_SESSION['company_id'] ?? null;

        if (!$companyId) {
            return [
                'allowed' => false,
                'message' => 'Company not found in session',
                'requiredTier' => $requiredTier
            ];
        }

        // Get company's active license
        $license = $this->licenseManager->getCompanyLicense($companyId);

        if (!$license) {
            return [
                'allowed' => false,
                'message' => "This feature requires a {$requiredTier} license. Please purchase a plan to access this feature.",
                'requiredTier' => $requiredTier,
                'featureName' => ucfirst($feature)
            ];
        }

        // Check if license is active and not expired
        if ($license['status'] !== 'active') {
            return [
                'allowed' => false,
                'message' => 'Your license is ' . $license['status'] . '. Please contact support or reactivate your license.',
                'requiredTier' => $requiredTier
            ];
        }

        if (strtotime($license['expires_at']) < time()) {
            return [
                'allowed' => false,
                'message' => 'Your license has expired. Please renew to continue using this feature.',
                'requiredTier' => $requiredTier
            ];
        }

        // Get tier hierarchy
        $tierHierarchy = ['starter' => 1, 'professional' => 2, 'enterprise' => 3];
        $userTierLevel = $tierHierarchy[$license['tier']] ?? 0;
        $requiredTierLevel = $tierHierarchy[$requiredTier] ?? 0;

        if ($userTierLevel < $requiredTierLevel) {
            $tierNames = [
                'starter' => 'Starter',
                'professional' => 'Professional',
                'enterprise' => 'Enterprise'
            ];
            return [
                'allowed' => false,
                'message' => "This feature is only available in the {$tierNames[$requiredTier]} plan and above. Your current plan is {$tierNames[$license['tier']]}.",
                'requiredTier' => $requiredTier,
                'currentTier' => $license['tier']
            ];
        }

        return ['allowed' => true, 'message' => ''];
    }

    /**
     * Check and redirect if not allowed
     * Usage: LicenseFeatureGate::enforceAccess($feature)
     */
    public function enforceAccess($feature)
    {
        $result = $this->canAccessFeature($feature);

        if (!$result['allowed']) {
            redirect('license_manager.php?error=' . urlencode($result['message']));
        }
    }

    /**
     * Get feature requirements
     */
    public function getFeatureRequirements($feature = null)
    {
        if ($feature) {
            return $this->featureRequirements[$feature] ?? null;
        }
        return $this->featureRequirements;
    }

    /**
     * Get all features by tier
     */
    public static function getFeaturesByTier($tier)
    {
        $features = [
            'starter' => [
                'basic_pos' => 'Point of Sale System',
                'inventory' => 'Inventory Management',
                'customers' => 'Customer Database'
            ],
            'professional' => [
                'basic_pos' => 'Point of Sale System',
                'inventory' => 'Inventory Management',
                'customers' => 'Customer Database',
                'reports' => 'Sales Reports',
                'analytics' => 'Analytics Dashboard',
                'multi_branch' => 'Multi-Branch Support',
                'expenses' => 'Expense Management'
            ],
            'enterprise' => [
                'basic_pos' => 'Point of Sale System',
                'inventory' => 'Inventory Management',
                'customers' => 'Customer Database',
                'reports' => 'Sales Reports',
                'analytics' => 'Analytics Dashboard',
                'multi_branch' => 'Multi-Branch Support',
                'expenses' => 'Expense Management',
                'custom_reports' => 'Custom Reports',
                'api_access' => 'API Access',
                'sso_integration' => 'SSO Integration'
            ]
        ];

        return $features[$tier] ?? [];
    }
}
