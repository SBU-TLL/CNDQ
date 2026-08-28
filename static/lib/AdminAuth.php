<?php
/**
 * Admin Authentication Manager
 *
 * Manages admin allowlist and permission checking
 */

class AdminAuth
{
    private $configFile;

    public function __construct()
    {
        $this->configFile = cndq_data_dir() . '/admin_config.json';
    }

    /**
     * Check if a user is an admin
     *
     * @param string $email User email to check
     * @return bool True if user is in admin allowlist
     */
    public function isAdmin($email)
    {
        if (empty($email)) {
            return false;
        }

        $config = $this->loadConfig();
        return in_array($email, $config['adminAllowlist'] ?? []);
    }

    /**
     * Require admin access (throws exception if not admin)
     *
     * @param string $email User email to check
     * @throws Exception if user is not an admin
     */
    public function requireAdmin($email)
    {
        if (!$this->isAdmin($email)) {
            error_log("Unauthorized admin access attempt by: $email");
            throw new Exception("Unauthorized: Admin access required");
        }
    }

    /**
     * Get list of all admins (count only, not actual emails for security)
     *
     * @return int Number of admins
     */
    public function getAdminCount()
    {
        $config = $this->loadConfig();
        return count($config['adminAllowlist'] ?? []);
    }

    // ============================================================
    // CLI-ONLY METHODS - Do not expose via web API
    // Use bin/manage_admins.php for adding/removing admins
    // ============================================================

    /**
     * Add user to admin allowlist (CLI ONLY - check isCLI before calling)
     *
     * @param string $email Email to add
     * @return bool Success
     */
    public function addAdminCLI($email)
    {
        if (!$this->isCLI()) {
            throw new Exception("This method can only be called from CLI");
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email address: $email");
        }

        $config = $this->loadConfig();

        if (!isset($config['adminAllowlist'])) {
            $config['adminAllowlist'] = [];
        }

        if (in_array($email, $config['adminAllowlist'])) {
            return true; // Already an admin
        }

        $config['adminAllowlist'][] = $email;
        $this->saveConfig($config);

        error_log("Admin added via CLI: $email");
        return true;
    }

    /**
     * Remove user from admin allowlist (CLI ONLY - check isCLI before calling)
     *
     * @param string $email Email to remove
     * @return bool Success
     */
    public function removeAdminCLI($email)
    {
        if (!$this->isCLI()) {
            throw new Exception("This method can only be called from CLI");
        }

        $config = $this->loadConfig();

        if (!isset($config['adminAllowlist'])) {
            return false;
        }

        $key = array_search($email, $config['adminAllowlist']);
        if ($key === false) {
            return false; // Not in allowlist
        }

        array_splice($config['adminAllowlist'], $key, 1);
        $this->saveConfig($config);

        error_log("Admin removed via CLI: $email");
        return true;
    }

    /**
     * List all admins (CLI ONLY)
     *
     * @return array List of admin emails
     */
    public function listAdminsCLI()
    {
        if (!$this->isCLI()) {
            throw new Exception("This method can only be called from CLI");
        }

        $config = $this->loadConfig();
        return $config['adminAllowlist'] ?? [];
    }

    /**
     * Check if running from command line
     *
     * @return bool True if CLI, false if web
     */
    private function isCLI()
    {
        return php_sapi_name() === 'cli' || php_sapi_name() === 'phpdbg';
    }

    /**
     * Load admin configuration
     *
     * @return array Configuration data
     */
    private function loadConfig()
    {
        if (!file_exists($this->configFile)) {
            return $this->getDefaultConfig();
        }

        $content = file_get_contents($this->configFile);
        $config = json_decode($content, true);

        if ($config === null) {
            error_log("Failed to parse admin config JSON");
            return $this->getDefaultConfig();
        }

        return $config;
    }

    /**
     * Save admin configuration with atomic write
     *
     * @param array $config Configuration to save
     * @return bool Success
     */
    private function saveConfig($config)
    {
        $dir = dirname($this->configFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $config['lastModified'] = time();
        $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        // Atomic write using temp file + rename
        $tempFile = $this->configFile . '.tmp';
        file_put_contents($tempFile, $json);
        rename($tempFile, $this->configFile);

        return true;
    }

    /**
     * Get default configuration structure
     *
     * @return array Default config
     */
    private function getDefaultConfig()
    {
        return [
            'adminAllowlist' => [
                'admin@stonybrook.edu' // Default admin
            ],
            'lastModified' => time()
        ];
    }

    /**
     * Send 403 Forbidden response and exit
     * Use this in API endpoints when admin check fails
     */
    public static function sendForbiddenResponse()
    {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Forbidden: Admin access required'
        ]);
        exit;
    }
}
