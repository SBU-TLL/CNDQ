<?php
/**
 * Shadow Prices API - CRITICAL SECURITY
 * GET: Calculate shadow prices for CURRENT AUTHENTICATED TEAM ONLY
 *
 * SECURITY: This endpoint MUST NEVER expose other teams' shadow prices.
 * Shadow prices are the core secret of each team's strategy.
 */

require_once __DIR__ . '/../../lib/TeamStorage.php';
require_once __DIR__ . '/../../lib/LPSolver.php';
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
    $inventory = $storage->getInventory();

    // Use actual LP solver to calculate shadow prices
    $solver = new LPSolver();
    $result = $solver->getShadowPrices($inventory);

    $shadowPrices = $result['shadowPrices'];
    $optimalMix = $result['optimalMix'];
    $maxProfit = $result['maxProfit'];
    $rawConstraints = $result['constraints'];

    // Format constraints for frontend (array of objects with 'name')
    $constraints = [];
    foreach ($rawConstraints as $chem => $data) {
        $constraints[] = [
            'name' => $chem,
            'slack' => $data['slack'],
            'status' => $data['status']
        ];
    }

    // Save new shadow prices to team storage
    $storage->updateShadowPrices($shadowPrices);

    // Reset transaction counter (prices are now fresh)
    $storage->resetShadowCalcCounter();

    echo json_encode([
        'success' => true,
        'shadowPrices' => [
            'C' => $shadowPrices['C'],
            'N' => $shadowPrices['N'],
            'D' => $shadowPrices['D'],
            'Q' => $shadowPrices['Q']
        ],
        'ranges' => $result['ranges'], // Include ranges for sensitivity analysis UI
        'constraints' => $constraints, // NEW: Include constraints for tutorial
        'optimalMix' => [
            'deicer' => $optimalMix['deicer'],
            'solvent' => $optimalMix['solvent']
        ],
        'maxProfit' => $maxProfit,
        'calculatedAt' => time(),
        'inventory' => [
            'C' => max(0, round($inventory['C'], 4)),
            'N' => max(0, round($inventory['N'], 4)),
            'D' => max(0, round($inventory['D'], 4)),
            'Q' => max(0, round($inventory['Q'], 4))
        ],
        'note' => 'These shadow prices are PRIVATE to your team and calculated using Linear Programming'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
