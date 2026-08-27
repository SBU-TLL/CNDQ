<?php
/**
 * Admin Reset Game API (SQLite version)
 *
 * POST: Reset all game data while keeping ability for players to start fresh
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../userData.php';
require_once __DIR__ . '/../../lib/SessionManager.php';
require_once __DIR__ . '/../../lib/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// ADMIN ONLY
if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Admin privileges required']);
    exit;
}

try {
    $db = Database::getInstance();
    $errors = [];

    // Capture current settings before wipe
    $sessionManager = new SessionManager();
    $currentState = $sessionManager->getState();
    $preservedTradingDuration = $currentState['tradingDuration'] ?? 120;

    // Delete all data from database tables
    $db->beginTransaction();
    try {
        // Temporarily disable foreign keys for cascading deletes
        $db->getPdo()->exec('PRAGMA foreign_keys = OFF');

        // Clear all team data (in correct order to respect foreign keys)
        $db->execute('DELETE FROM team_state_cache');
        $db->execute('DELETE FROM team_snapshots');
        $teamsDeleted = $db->execute('DELETE FROM team_events');

        // Clear marketplace data
        $db->execute('DELETE FROM marketplace_events');
        $db->execute('UPDATE marketplace_snapshot SET offers = ?, buy_orders = ?, ads = ?, updated_at = ? WHERE id = 1',
            ['[]', '[]', '[]', time()]);

        // Clear negotiations (offers first due to foreign key)
        $db->execute('DELETE FROM negotiation_offers');
        $db->execute('DELETE FROM negotiations');

        // Clear config (except keep admin config)
        $db->execute('DELETE FROM config WHERE key != ?', ['admin_config']);

        // Re-enable foreign keys
        $db->getPdo()->exec('PRAGMA foreign_keys = ON');

        // Reset NPC configuration
        $defaultNPCConfig = [
            'enabled' => true,
            'npcs' => []
        ];
        $db->execute(
            'INSERT OR REPLACE INTO config (key, value, updated_at) VALUES (?, ?, ?)',
            ['npc_config', json_encode($defaultNPCConfig), time()]
        );

        $db->commit();

    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }

    // Vacuum database to reclaim space
    $db->vacuum();

    // Reset session to session 1, trading phase (preserving duration)
    // $sessionManager is already instantiated above
    $newState = $sessionManager->reset($preservedTradingDuration);
    
    error_log("Game Reset Complete. State: " . ($newState['gameStopped'] ? "STOPPED" : "RUNNING") . ", Duration: $preservedTradingDuration");

    echo json_encode([
        'success' => true,
        'message' => 'Game data completely reset! Players will get new teams when they login.',
        'teamsReset' => $teamsDeleted,
        'sessionState' => $newState,
        'errors' => $errors
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
