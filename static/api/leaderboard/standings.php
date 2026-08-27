<?php
/**
 * Leaderboard API
 * GET: Get team standings ranked by ROI percentage
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../userData.php';
require_once __DIR__ . '/../../lib/SessionManager.php';
require_once __DIR__ . '/../../lib/MarketplaceAggregator.php';

try {
    $sessionManager = new SessionManager();
    $sessionState = $sessionManager->getState();

    // Use MarketplaceAggregator to get team statistics from SQLite
    $marketplace = new MarketplaceAggregator();
    $stats = $marketplace->getTeamStatistics();

    $standings = [];

    foreach ($stats as $teamStat) {
        // Skip the system team
        if ($teamStat['email'] === 'system') continue;

        $inventory = $teamStat['inventory'];
        $standings[] = [
            'teamName' => $teamStat['teamName'],
            'teamId' => $teamStat['email'],
            'email' => $teamStat['email'], // Add email field for frontend comparison
            'startingFunds' => round($teamStat['startingFunds'], 2), // Initial production potential
            'currentFunds' => round($teamStat['currentFunds'], 2), // Current profit
            'profit' => round($teamStat['currentFunds'] - $teamStat['startingFunds'], 2), // Net improvement
            'roi' => $teamStat['percentChange'],
            'percentChange' => $teamStat['percentChange'],
            'inventory' => [
                'C' => max(0, round($inventory['C'] ?? 0, 4)),
                'N' => max(0, round($inventory['N'] ?? 0, 4)),
                'D' => max(0, round($inventory['D'] ?? 0, 4)),
                'Q' => max(0, round($inventory['Q'] ?? 0, 4))
            ],
            'totalTrades' => $teamStat['totalTrades']
        ];
    }

    // Sort by Success Score (percentChange) descending
    // This represents % improvement over initial production potential
    usort($standings, function($a, $b) {
        return $b['percentChange'] <=> $a['percentChange'];
    });

    // Add rank
    foreach ($standings as $index => &$team) {
        $team['rank'] = $index + 1;
    }

    echo json_encode([
        'success' => true,
        'session' => $sessionState['currentSession'],
        'phase' => $sessionState['phase'],
        'standings' => $standings,
        'totalTeams' => count($standings)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
