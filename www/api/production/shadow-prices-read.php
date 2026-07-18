<?php
/**
 * Shadow Prices Read API - READ ONLY
 * GET: Returns stored shadow prices WITHOUT recalculating
 *
 * Use this endpoint for polling/refreshing UI state.
 * Use /shadow-prices.php to recalculate shadow prices.
 */

require_once __DIR__ . '/../../lib/TeamStorage.php';
require_once __DIR__ . '/../../userData.php';

header('Content-Type: application/json');

$currentUserEmail = getCurrentUserEmail();

if (!$currentUserEmail) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

// SECURITY CHECK: Reject any attempt to query other teams' shadow prices
if (isset($_GET['team']) || isset($_GET['email']) || isset($_GET['user'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden - Can only access your own shadow prices']);
    exit;
}

try {
    $storage = new TeamStorage($currentUserEmail);
    $state = $storage->getState();

    $shadowPrices = $state['shadowPrices'] ?? ['C' => 0, 'N' => 0, 'D' => 0, 'Q' => 0];
    $inventory = $state['inventory'] ?? [];
    $stalenessCount = $inventory['transactionsSinceLastShadowCalc'] ?? 0;
    $stalenessLevel = $inventory['stalenessLevel'] ?? 'fresh';

    echo json_encode([
        'success' => true,
        'shadowPrices' => [
            'C' => $shadowPrices['C'] ?? 0,
            'N' => $shadowPrices['N'] ?? 0,
            'D' => $shadowPrices['D'] ?? 0,
            'Q' => $shadowPrices['Q'] ?? 0
        ],
        'staleness' => [
            'count' => $stalenessCount,
            'level' => $stalenessLevel
        ],
        'inventory' => [
            'C' => max(0, round($inventory['C'] ?? 0, 4)),
            'N' => max(0, round($inventory['N'] ?? 0, 4)),
            'D' => max(0, round($inventory['D'] ?? 0, 4)),
            'Q' => max(0, round($inventory['Q'] ?? 0, 4))
        ],
        'note' => 'Read-only - use /shadow-prices.php to recalculate'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
