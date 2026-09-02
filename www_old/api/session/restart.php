<?php
/**
 * Public Restart Game API
 * POST: Restarts the game with existing NPC count
 * Allowed when: game is finished AND autoAdvance is enabled, or user is admin
 */

require_once __DIR__ . '/../../lib/SessionManager.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $sessionManager = new SessionManager();
    $state = $sessionManager->getState();

    // Safety: Allow restart only when autoAdvance (24/7 mode) is enabled
    // AND the game has finished (Simulation Complete screen)
    // This allows unattended operation where users can restart themselves
    require_once __DIR__ . '/../../userData.php';
    $isAutoAdvance = $state['autoAdvance'] ?? false;
    $isGameFinished = $state['gameFinished'] ?? false;

    // Non-admins can only restart when:
    // 1. autoAdvance (24/7 mode) is enabled, AND
    // 2. Game is finished (Simulation Complete screen showing)
    if (!isAdmin()) {
        if (!$isAutoAdvance) {
            http_response_code(403);
            echo json_encode(['error' => 'Restart not available. Admin must enable Auto-Cycle (24/7 Mode) first.']);
            exit;
        }
        if (!$isGameFinished) {
            http_response_code(403);
            echo json_encode(['error' => 'Game is still in progress. Wait for the simulation to complete.']);
            exit;
        }
    }

    $sessionManager->restartGame();

    // Auto-start the game so users don't have to wait for admin
    $newState = $sessionManager->toggleGameStop(false);

    echo json_encode([
        'success' => true,
        'message' => 'Game restarted and started!',
        'session' => $newState
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
