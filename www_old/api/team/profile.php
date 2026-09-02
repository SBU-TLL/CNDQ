<?php
/**
 * Team Profile API
 * GET: Returns current team's profile and inventory
 * POST: Update team profile (limited fields)
 */

require_once __DIR__ . '/../../lib/TeamStorage.php';
require_once __DIR__ . '/../../userData.php';

header('Content-Type: application/json');

$currentUserEmail = getCurrentUserEmail();

// Reject only truly empty/invalid emails
// Note: dev_user@localhost is allowed for local development
if (!$currentUserEmail || trim($currentUserEmail) === '') {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

try {
    $storage = new TeamStorage($currentUserEmail);

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get full profile
        $profile = $storage->getProfile();
        $inventory = $storage->getInventory();

        // Calculate staleness indicator
        $transactionsSinceCalc = $inventory['transactionsSinceLastShadowCalc'] ?? 0;
        $stalenessLevel = 'fresh';
        if ($transactionsSinceCalc >= 2) {
            $stalenessLevel = 'stale';
        } elseif ($transactionsSinceCalc === 1) {
            $stalenessLevel = 'warning';
        }

        echo json_encode([
            'success' => true,
            'profile' => [
                'email' => $profile['email'],
                'teamName' => $profile['teamName'],
                'currentFunds' => round($profile['currentFunds'], 2),
                'startingFunds' => round($profile['startingFunds'], 2),
                'initialProductionPotential' => round($profile['initialProductionPotential'] ?? 0, 2),
                'settings' => $profile['settings'] ?? []
            ],
            'inventory' => [
                'C' => max(0, round($inventory['C'], 4)),
                'N' => max(0, round($inventory['N'], 4)),
                'D' => max(0, round($inventory['D'], 4)),
                'Q' => max(0, round($inventory['Q'], 4)),
                'transactionsSinceLastShadowCalc' => $transactionsSinceCalc,
                'stalenessLevel' => $stalenessLevel
            ]
        ]);

    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Update profile (limited fields)
        $input = json_decode(file_get_contents('php://input'), true);

        if (isset($input['teamName'])) {
            $storage->setTeamName($input['teamName']);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Profile updated'
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
