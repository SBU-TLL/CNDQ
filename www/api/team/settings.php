<?php
/**
 * Team Settings API
 * GET: Get team settings
 * POST: Update team settings
 */

require_once __DIR__ . '/../../lib/TeamStorage.php';
require_once __DIR__ . '/../../userData.php';

header('Content-Type: application/json');

$currentUserEmail = getCurrentUserEmail();

if (!$currentUserEmail || $currentUserEmail === 'dev_user') {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

try {
    $storage = new TeamStorage($currentUserEmail);

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $settings = $storage->getSettings();

        echo json_encode([
            'success' => true,
            'settings' => $settings
        ]);

    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        // Allowed settings to update
        $allowedSettings = ['pollingInterval', 'notifications'];
        $updates = [];

        foreach ($allowedSettings as $key) {
            if (isset($input[$key])) {
                $updates[$key] = $input[$key];
            }
        }

        if (!empty($updates)) {
            $storage->updateSettings($updates);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Settings updated',
            'settings' => $storage->getSettings()
        ]);

    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
