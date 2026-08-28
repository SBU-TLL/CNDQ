<?php
/**
 * Admin Session Control API
 *
 * GET: Get current session state
 * POST: Update session (finalize, startNew, setAutoCycle, toggleGameStop)
 */

// Prevent PHP errors/warnings from corrupting JSON output
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Start output buffering to catch any stray output
ob_start();

header('Content-Type: application/json');

// Register shutdown function to catch ALL errors including fatal ones
register_shutdown_function(function() {
    $error = error_get_last();
    $output = ob_get_clean();

    // If there was unexpected output (PHP warnings/errors), log it
    if (!empty($output) && strpos($output, '{') !== 0) {
        error_log("session.php unexpected output: " . substr($output, 0, 500));
        http_response_code(500);
        echo json_encode([
            'error' => 'Unexpected output',
            'debug' => substr($output, 0, 200)
        ]);
        return;
    }

    // If there was a fatal error
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Fatal error',
            'message' => $error['message'],
            'file' => basename($error['file']),
            'line' => $error['line']
        ]);
        return;
    }

    // Otherwise echo any buffered valid output
    echo $output;
});

try {
    require_once __DIR__ . '/../../userData.php';
    require_once __DIR__ . '/../../lib/SessionManager.php';

    $sessionManager = new SessionManager();
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get current session state (public - triggers auto-advance if enabled)
        // Tick the simulation forward based on this heartbeat
        $sessionManager->tick();

        $state = $sessionManager->getState();

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
            'session' => $state,
            'recentTrades' => $recentTrades
        ]);

    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // ADMIN ONLY for modifications
        if (!isAdmin()) {
            http_response_code(403);
            echo json_encode(['error' => 'Admin privileges required']);
            exit;
        }
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? null;

        switch ($action) {
            case 'finalize':
            case 'advance': // Backward compatibility
                // End the game (run final production)
                $state = $sessionManager->finalizeGame();
                echo json_encode([
                    'success' => true,
                    'message' => 'Market Closed. Final production run complete.',
                    'session' => $state
                ]);
                break;

            case 'setPhase':
                // Set phase directly
                $phase = $input['phase'] ?? null;
                if (!$phase) {
                    throw new Exception('Phase required');
                }
                $state = $sessionManager->setPhase($phase);
                echo json_encode([
                    'success' => true,
                    'message' => 'Phase set to ' . $phase,
                    'session' => $state
                ]);
                break;

            case 'setAutoCycle':
            case 'setAutoAdvance': // Backward compatibility
                // Toggle auto-cycle (24/7 mode)
                $enabled = $input['enabled'] ?? false;
                $state = $sessionManager->setAutoCycleMode($enabled);
                echo json_encode([
                    'success' => true,
                    'message' => 'Auto-Cycle (24/7 Mode) ' . ($enabled ? 'enabled' : 'disabled'),
                    'session' => $state
                ]);
                break;

            case 'setTradingDuration':
                // Set trading duration
                $seconds = $input['seconds'] ?? 600;
                $state = $sessionManager->setTradingDuration($seconds);
                echo json_encode([
                    'success' => true,
                    'message' => 'Market open duration set to ' . $seconds . ' seconds',
                    'session' => $state
                ]);
                break;

            case 'toggleGameStop':
            case 'start': // Alias
            case 'stop':  // Alias
                // Toggle game stopped state
                $stopped = $action === 'stop' ? true : ($action === 'start' ? false : ($input['stopped'] ?? false));
                $state = $sessionManager->toggleGameStop($stopped);
                echo json_encode([
                    'success' => true,
                    'message' => 'Game ' . ($stopped ? 'stopped' : 'started'),
                    'session' => $state
                ]);
                break;

            case 'startNew':
            case 'reset': // Backward compatibility
                // Start a fresh game (Hard Reset)
                $state = $sessionManager->startNewGame();
                echo json_encode([
                    'success' => true,
                    'message' => 'New Game Started (Previous data cleared)',
                    'session' => $state
                ]);
                break;

            default:
                throw new Exception('Invalid action');
        }

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