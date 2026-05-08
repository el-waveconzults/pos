<?php

/**
 * License Verification Middleware
 * Include this in config.php or at the start of protected pages
 */

require_once(__DIR__ . '/LicenseManager.php');

class LicenseMiddleware
{
    private $licenseManager;
    private $conn;

    public function __construct($dbConnection)
    {
        $this->conn = $dbConnection;
        $this->licenseManager = new LicenseManager($dbConnection);
    }

    /**
     * Check if company has valid license
     * Returns false if no license or expired
     */
    public function validateCompanyLicense($companyId)
    {
        $license = $this->licenseManager->getCompanyLicense($companyId);

        if (!$license) {
            return false;
        }

        // License must be active
        if ($license['status'] !== 'active') {
            return false;
        }

        // Check expiration
        if (strtotime($license['expires_at']) < time()) {
            return false;
        }

        return true;
    }

    /**
     * Check if user count exceeds license limit
     */
    public function checkUserLimit($companyId)
    {
        $license = $this->licenseManager->getCompanyLicense($companyId);
        if (!$license) {
            return ['allowed' => false, 'message' => 'No active license'];
        }

        $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM users WHERE company_id = ?");
        $stmt->bind_param("i", $companyId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $userCount = $result['count'];

        if ($userCount >= $license['max_users']) {
            return [
                'allowed' => false,
                'message' => "User limit reached ({$userCount}/{$license['max_users']})"
            ];
        }

        return [
            'allowed' => true,
            'current' => $userCount,
            'limit' => $license['max_users']
        ];
    }

    /**
     * Check if branch count exceeds license limit
     */
    public function checkBranchLimit($companyId)
    {
        $license = $this->licenseManager->getCompanyLicense($companyId);
        if (!$license) {
            return ['allowed' => false, 'message' => 'No active license'];
        }

        $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM branches WHERE company_id = ?");
        $stmt->bind_param("i", $companyId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $branchCount = $result['count'];

        if ($branchCount >= $license['max_branches']) {
            return [
                'allowed' => false,
                'message' => "Branch limit reached ({$branchCount}/{$license['max_branches']})"
            ];
        }

        return [
            'allowed' => true,
            'current' => $branchCount,
            'limit' => $license['max_branches']
        ];
    }

    /**
     * Enforce license restrictions
     * Should be called when creating new users or branches
     */
    public function enforceRestrictions($companyId, $action = 'user')
    {
        if ($action === 'user') {
            return $this->checkUserLimit($companyId);
        } elseif ($action === 'branch') {
            return $this->checkBranchLimit($companyId);
        }
        return ['allowed' => true];
    }

    /**
     * Get license info for display
     */
    public function getLicenseInfo($companyId)
    {
        $license = $this->licenseManager->getCompanyLicense($companyId);
        if (!$license) {
            return null;
        }

        $daysLeft = (strtotime($license['expires_at']) - time()) / 86400;

        return [
            'tier' => $license['tier'],
            'status' => $license['status'],
            'expiresAt' => $license['expires_at'],
            'daysLeft' => ceil($daysLeft),
            'isExpired' => $daysLeft <= 0,
            'isExpiringSoon' => $daysLeft <= 10,
            'maxUsers' => $license['max_users'],
            'maxBranches' => $license['max_branches']
        ];
    }
}
