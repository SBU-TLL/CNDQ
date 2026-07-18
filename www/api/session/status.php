<?php
/**
 * Public Session Status API
 * GET: Returns current session number, phase, and time remaining.
 * Triggers auto-advance logic if enabled.
 */

// Prevent PHP errors/warnings from corrupting JSON output
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../../lib/SessionManager.php';
header('Content-Type: application/json');

try {
    $sessionManager = new SessionManager();

    // DRIVE THE WORLD: Tick the simulation forward based on this heartbeat
    $sessionManager->tick();

    // Handle POST requests to acknowledge production results
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (isset($input['acknowledgeProduction']) && $input['acknowledgeProduction'] === true) {
            // Clear the productionJustRan flag
            require_once __DIR__ . '/../../lib/SystemStorage.php';
            $storage = new SystemStorage();
            $storage->setSessionData(['productionJustRan' => null]);

            echo json_encode(['success' => true, 'message' => 'Production acknowledged']);
            exit;
        }
    }

    $state = $sessionManager->getState();

    // Auto-advance if time expired and auto-advance is enabled, AND game is not stopped
    if (($state['autoAdvance'] ?? false) && ($state['timeRemaining'] ?? 0) <= 0 && !($state['gameStopped'] ?? false)) {
        $state = $sessionManager->advanceSession();
    }

    // Get recent trades for global notifications
    require_once __DIR__ . '/../../lib/MarketplaceAggregator.php';
    $aggregator = new MarketplaceAggregator();
    $cached = $aggregator->getCachedMarketplaceData(5);
    $recentTrades = $cached['recentTrades'] ?? [];
    
    // If cache is stale/missing, get live (this also updates the snapshot)
    if (empty($recentTrades)) {
        $allMarket = $aggregator->getAggregatedFromEvents();
        $recentTrades = $allMarket['recentTrades'] ?? [];
    }

    echo json_encode([
        'success' => true,
        'session' => $state['currentSession'],
        'phase' => $state['phase'],
        'timeRemaining' => $state['timeRemaining'] ?? 0,
        'autoAdvance' => $state['autoAdvance'] ?? false,
        'productionJustRan' => $state['productionJustRan'] ?? null,
        'gameStopped' => $state['gameStopped'] ?? true,
        'gameFinished' => $state['gameFinished'] ?? false,
        'recentTrades' => $recentTrades
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
