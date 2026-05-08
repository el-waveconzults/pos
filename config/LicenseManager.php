<?php

/**
 * License Manager Class
 * Handles license generation, validation, and feature checking
 */
class LicenseManager
{
    private $conn;
    private $appId = 'POS_SYSTEM';

    public function __construct($dbConnection)
    {
        $this->conn = $dbConnection;
    }

    /**
     * Generate a new license key
     * Format: POS-XXXX-XXXX-XXXX-XXXX (base36)
     */
    public function generateLicenseKey()
    {
        $segments = [];
        for ($i = 0; $i < 4; $i++) {
            $random = mt_rand(0, 1679615); // 36^4 - 1
            $segments[] = strtoupper(base_convert($random, 10, 36));
        }
        return $this->appId . '-' . implode('-', $segments);
    }

    /**
     * Create a new license
     */
    public function createLicense($data)
    {
        $licenseKey = $this->generateLicenseKey();
        $tier = $data['tier'] ?? 'starter'; // starter, professional, enterprise
        $expiresAt = $data['expires_at'] ?? date('Y-m-d H:i:s', strtotime('+1 year'));
        $companyId = $data['company_id'] ?? null;
        $maxUsers = $this->getMaxUsers($tier);
        $maxBranches = $this->getMaxBranches($tier);

        $stmt = $this->conn->prepare(
            "INSERT INTO licenses (license_key, company_id, tier, max_users, max_branches, activated_at, expires_at, status) 
             VALUES (?, ?, ?, ?, ?, NOW(), ?, 'active')"
        );
        $stmt->bind_param("sisiis", $licenseKey, $companyId, $tier, $maxUsers, $maxBranches, $expiresAt);

        if ($stmt->execute()) {
            return [
                'success' => true,
                'license_key' => $licenseKey,
                'message' => 'License created successfully'
            ];
        }
        return ['success' => false, 'message' => $this->conn->error];
    }

    /**
     * Validate a license key format and existence
     */
    public function validateLicense($licenseKey)
    {
        // Format validation: POS-XXXX-XXXX-XXXX-XXXX
        if (!preg_match('/^POS-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $licenseKey)) {
            return ['valid' => false, 'message' => 'Invalid license key format'];
        }

        $stmt = $this->conn->prepare(
            "SELECT * FROM licenses WHERE license_key = ? AND status = 'active'"
        );
        $stmt->bind_param("s", $licenseKey);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            return ['valid' => false, 'message' => 'License not found or inactive'];
        }

        $license = $result->fetch_assoc();

        // Check expiration
        if (strtotime($license['expires_at']) < time()) {
            $this->suspendLicense($licenseKey);
            return ['valid' => false, 'message' => 'License has expired'];
        }

        return [
            'valid' => true,
            'license' => $license,
            'message' => 'License is valid'
        ];
    }

    /**
     * Get current license for a company
     */
    public function getCompanyLicense($companyId)
    {
        // Return null if table doesn't exist
        if (!$this->tableExists()) {
            return null;
        }

        $stmt = $this->conn->prepare(
            "SELECT * FROM licenses WHERE company_id = ? AND status = 'active' ORDER BY activated_at DESC LIMIT 1"
        );
        $stmt->bind_param("i", $companyId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        return null;
    }

    /**
     * Check if feature is available for company
     */
    public function hasFeature($companyId, $feature)
    {
        $license = $this->getCompanyLicense($companyId);
        if (!$license) {
            return false;
        }

        $features = $this->getTierFeatures($license['tier']);
        return in_array($feature, $features);
    }

    /**
     * Get maximum users allowed for tier
     */
    private function getMaxUsers($tier)
    {
        $tiers = [
            'starter' => 5,
            'professional' => 20,
            'enterprise' => 999
        ];
        return $tiers[$tier] ?? 5;
    }

    /**
     * Get maximum branches allowed for tier
     */
    private function getMaxBranches($tier)
    {
        $tiers = [
            'starter' => 1,
            'professional' => 10,
            'enterprise' => 999
        ];
        return $tiers[$tier] ?? 1;
    }

    /**
     * Get available features for tier
     */
    public function getTierFeatures($tier)
    {
        $features = [
            'starter' => [
                'basic_pos',
                'inventory',
                'customers'
            ],
            'professional' => [
                'basic_pos',
                'inventory',
                'customers',
                'reports',
                'multi_branch',
                'advanced_analytics'
            ],
            'enterprise' => [
                'basic_pos',
                'inventory',
                'customers',
                'reports',
                'multi_branch',
                'advanced_analytics',
                'custom_reports',
                'api_access',
                'sso_integration',
                'priority_support'
            ]
        ];
        return $features[$tier] ?? [];
    }

    /**
     * Suspend a license
     */
    public function suspendLicense($licenseKey)
    {
        $stmt = $this->conn->prepare("UPDATE licenses SET status = 'suspended' WHERE license_key = ?");
        $stmt->bind_param("s", $licenseKey);
        return $stmt->execute();
    }

    /**
     * Reactivate a suspended license
     */
    public function reactivateLicense($licenseKey)
    {
        $stmt = $this->conn->prepare("UPDATE licenses SET status = 'active' WHERE license_key = ?");
        $stmt->bind_param("s", $licenseKey);
        return $stmt->execute();
    }

    /**
     * Extend license expiration
     */
    public function extendLicense($licenseKey, $days = 365)
    {
        $newExpiry = date('Y-m-d H:i:s', strtotime("+$days days"));
        $stmt = $this->conn->prepare("UPDATE licenses SET expires_at = ? WHERE license_key = ?");
        $stmt->bind_param("ss", $newExpiry, $licenseKey);
        return $stmt->execute();
    }

    /**
     * Delete a license
     */
    public function deleteLicense($licenseKey)
    {
        $stmt = $this->conn->prepare("DELETE FROM licenses WHERE license_key = ?");
        $stmt->bind_param("s", $licenseKey);
        return $stmt->execute();
    }

    /**
     * Check if licenses table exists
     */
    private function tableExists()
    {
        $result = $this->conn->query("SHOW TABLES LIKE 'licenses'");
        return $result && $result->num_rows > 0;
    }

    /**
     * Get all licenses with company information (for owners)
     */
    public function getAllLicenses()
    {
        if (!$this->tableExists()) {
            return [];
        }

        $query = "SELECT l.*, c.name as company_name 
                 FROM licenses l 
                 LEFT JOIN companies c ON l.company_id = c.id 
                 ORDER BY l.created_at DESC";
        $result = $this->conn->query($query);

        $licenses = [];
        while ($row = $result->fetch_assoc()) {
            $licenses[] = $row;
        }
        return $licenses;
    }

    /**
     * Get license statistics
     */
    public function getLicenseStats($companyId = null)
    {
        // Return empty stats if table doesn't exist
        if (!$this->tableExists()) {
            return [
                'total' => 0,
                'active' => 0,
                'suspended' => 0,
                'expired' => 0
            ];
        }

        if ($companyId) {
            $query = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended,
                SUM(CASE WHEN expires_at < NOW() THEN 1 ELSE 0 END) as expired
            FROM licenses WHERE company_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("i", $companyId);
        } else {
            $query = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended,
                SUM(CASE WHEN expires_at < NOW() THEN 1 ELSE 0 END) as expired
            FROM licenses";
            $stmt = $this->conn->prepare($query);
        }

        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
}
