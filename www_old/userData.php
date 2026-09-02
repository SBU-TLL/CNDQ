<?php

function isAdmin() {
    // Admin allowlist - real users with special privileges (apply in ALL environments)
    $adminEmails = [
        'rvonrauchhau@stonybrook.edu',
        'hlewis@stonybrook.edu',
        'tsexton@stonybrook.edu',
        'test.admin@stonybrook.edu'
        
    ];

    // Dev/test accounts are granted admin ONLY in local development — never in
    // production. This neutralizes the privilege-escalation risk if the no-attributes
    // fallback (dev_user@localhost) is ever reached on a misconfigured prod server.
    if (isLocalDev()) {
        $adminEmails = array_merge($adminEmails, [
            'test.admin@stonybrook.edu', // Test user
        ]);
    }

    $currentEmail = getCurrentUserEmail();

    return in_array($currentEmail, $adminEmails, true);
}

function isLocalDev() {
    // Hard production kill-switch: when the environment is explicitly flagged as
    // production (server-set APP_ENV, e.g. via Apache SetEnv / Ansible), the dev
    // bypass is OFF regardless of any stray .env file that may have been deployed.
    $env = getenv('APP_ENV') ?: ($_SERVER['APP_ENV'] ?? '');
    if (strtolower($env) === 'production') {
        return false;
    }
    // Otherwise local development is signalled by the presence of a .env file.
    return file_exists(__DIR__ . '/.env') || file_exists(dirname(__DIR__) . '/.env');
}

// Load environment variables for local development (Herd/PHP-FPM doesn't support .htaccess SetEnv)
if (isLocalDev()) {
    $envPath = file_exists(__DIR__ . '/.env') ? __DIR__ . '/.env' : dirname(__DIR__) . '/.env';
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0 || trim($line) === '') continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = strtolower(trim($key));
            $value = trim($value);
            $_SERVER[$key] = $value;
            putenv("$key=$value");
        }
    }
}

function getCurrentUserEmail() {
    // Check for cookie override (Restricted to local dev with .env file)
    // This prevents production users from being hijacked if they accidentally hit dev.php
    if (isLocalDev() && isset($_COOKIE['mock_mail']) && !empty($_COOKIE['mock_mail'])) {
        return $_COOKIE['mock_mail'];
    }

    // Prefer non-identifying Shibboleth attributes for privacy
    // persistent-id is a random opaque identifier that doesn't reveal identity
    $persistentId = $_SERVER['persistent-id'] ?? getenv('persistent-id') ?? null;
    if (!empty($persistentId)) return $persistentId;

    // eppn (eduPersonPrincipalName) is less identifying than email
    $eppn = $_SERVER['eppn'] ?? getenv('eppn') ?? null;
    if (!empty($eppn)) return $eppn;

    // Fallback to email for backwards compatibility
    $mail = $_SERVER['mail'] ?? getenv('mail') ?? null;
    if (!empty($mail)) return $mail;

    $email = $_SERVER['email'] ?? getenv('email') ?? null;
    if (!empty($email)) return $email;

    // Default for local development (Herd/non-Shibboleth)
    // Log a warning in production if we reach here
    if (!isLocalDev()) {
        error_log("AUTH WARNING: No Shibboleth attributes found in production. Falling back to dev_user@localhost. Headers: " . json_encode(array_keys($_SERVER)));
    }
    return 'dev_user@localhost';
}


/**
 * @deprecated Legacy function for file-based storage. Use TeamStorage class instead.
 */
/*
function getUserDataFilePath() {
    $email = getCurrentUserEmail();

    // Sanitize email for filename to prevent directory traversal or invalid chars
    $safeEmail = preg_replace('/[^a-zA-Z0-9_\-@.]/', '_', $email);

    $dataDir = __DIR__ . '/data';

    if (!file_exists($dataDir)) {
        if (!mkdir($dataDir, 0755, true)) {
            error_log("Failed to create data directory: $dataDir");
        }
    }

    return $dataDir . '/user_' . $safeEmail . '.json';
}
*/
