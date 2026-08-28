<?php
/**
 * Admin Endpoint: List All Teams
 *
 * ADMIN-ONLY: Returns overview of all teams in the system
 *
 * Response:
 * {
 *   "teams": [
 *     {
 *       "email": "team1@example.com",
 *       "teamName": "Alpha Team",
 *       "funds": 10000,
 *       "inventory": { "C": 850, "N": 740, "D": 630, "Q": 520 },
 *       "activeOffers": 2,
 *       "totalTrades": 5
 *     }
 *   ]
 * }
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../userData.php';
require_once __DIR__ . '/../../lib/MarketplaceAggregator.php';

// SECURITY: Admin-only endpoint
if (!isAdmin()) {
    http_response_code(403);
    echo json_encode([
        'error' => 'Forbidden',
        'message' => 'This endpoint requires admin privileges'
    ]);
    exit;
}

try {
    $aggregator = new MarketplaceAggregator();
    $allTeams = $aggregator->getAllTeams();

    $teamSummaries = [];

    foreach ($allTeams as $teamInfo) {
        try {
            $storage = new TeamStorage($teamInfo['email']);
            $state = $storage->getState();
            
            $profile = $state['profile'] ?? [];
            $inventory = $state['inventory'] ?? [];
            $transactions = $state['transactions'] ?? [];
            $offers = $state['offers'] ?? [];

            // Calculate success metric: % improvement over initial production potential
            $initialPotential = $profile['initialProductionPotential'] ?? 0;

            // Check if production has run
            $hasProduction = isset($profile['productions']) && count($profile['productions']) > 0;

            // Current Profit Calculation:
            // During Trading: currentProfit = tradingNet + projected production revenue
            // After Production: currentProfit = currentFunds (actual production revenue realized)
            if ($hasProduction) {
                // Production has run: use final cash
                $currentProfit = $profile['currentFunds'] ?? 0;
            } else {
                // Still trading: calculate trading net + projected production revenue
                $currentCash = $profile['currentFunds'] ?? 0;

                // Calculate Potential Revenue from current inventory
                $inventoryRevenue = $state['shadowPrices']['maxProfit'] ?? 0;
                // If maxProfit isn't in shadowPrices, calculate it live
                if ($inventoryRevenue <= 0) {
                    require_once __DIR__ . '/../../lib/LPSolver.php';
                    $solver = new LPSolver();
                    $res = $solver->solve($inventory);
                    $inventoryRevenue = $res['maxProfit'];
                }
                $currentProfit = $currentCash + $inventoryRevenue;
            }

            // Calculate percentage improvement
            $percentImprovement = 0;
            if ($initialPotential > 0) {
                $percentImprovement = (($currentProfit - $initialPotential) / $initialPotential) * 100;
            }

            $teamSummaries[] = [
                'email' => $teamInfo['email'],
                'teamName' => $profile['teamName'] ?? $teamInfo['teamName'] ?? 'Unknown Team',
                'funds' => round($currentProfit, 2), // Current profit
                'initialPotential' => round($initialPotential, 2), // Initial production potential
                'percentImprovement' => round($percentImprovement, 2), // Success metric
                'inventory' => [
                    'C' => max(0, round($inventory['C'] ?? 0, 4)),
                    'N' => max(0, round($inventory['N'] ?? 0, 4)),
                    'D' => max(0, round($inventory['D'] ?? 0, 4)),
                    'Q' => max(0, round($inventory['Q'] ?? 0, 4))
                ],
                'activeOffers' => count($offers),
                'totalTrades' => count($transactions),
                'lastUpdated' => $inventory['updatedAt'] ?? null
            ];
        } catch (Exception $e) {
            continue;
        }
    }

    echo json_encode([
        'teams' => $teamSummaries,
        'totalTeams' => count($teamSummaries),
        'timestamp' => time()
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
