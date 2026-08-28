<?php
/**
 * Production Results API
 * GET: Retrieve production results for current authenticated team
 *
 * Query params:
 *   ?session=X  - Get results for specific session (optional, defaults to most recent)
 *
 * Returns production data to display in post-production modal
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

// SECURITY CHECK: Reject any attempt to query other teams' production results
if (isset($_GET['team']) || isset($_GET['email']) || isset($_GET['user'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden - Can only access your own production results']);
    exit;
}

try {
    $storage = new TeamStorage($currentUserEmail);
    $productionHistory = $storage->getProductionHistory();

    // Get requested session (or most recent)
    $requestedSession = isset($_GET['session']) ? (int)$_GET['session'] : null;

    // Find the matching production event
    $productionResult = null;

    if ($requestedSession === null) {
        // Return most recent production (last in array)
        $productionResult = end($productionHistory);
    } else {
        // Find production for specific session
        foreach ($productionHistory as $prod) {
            if (isset($prod['sessionNumber']) && $prod['sessionNumber'] == $requestedSession) {
                $productionResult = $prod;
                break;
            }
        }
    }

    if (!$productionResult) {
        http_response_code(404);
        echo json_encode([
            'error' => 'No production results found',
            'requestedSession' => $requestedSession
        ]);
        exit;
    }

    // Return production results
    $profile = $storage->getProfile();
    echo json_encode([
        'success' => true,
        'sessionNumber' => $productionResult['sessionNumber'] ?? null,
        'type' => $productionResult['type'] ?? 'unknown',
        'production' => [
            'deicer' => $productionResult['deicer'] ?? 0,
            'solvent' => $productionResult['solvent'] ?? 0
        ],
        'revenue' => $productionResult['revenue'] ?? 0,
        'chemicalsConsumed' => $productionResult['chemicalsConsumed'] ?? [
            'C' => 0,
            'N' => 0,
            'D' => 0,
            'Q' => 0
        ],
        'constraints' => $productionResult['constraints'] ?? null,
        'shadowPrices' => $productionResult['shadowPrices'] ?? null,
        'ranges' => $productionResult['ranges'] ?? null,
        'note' => $productionResult['note'] ?? '',
        'timestamp' => $productionResult['timestamp'] ?? null,
        'currentInventory' => $storage->getInventory(),
        'currentFunds' => $profile['currentFunds']
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
