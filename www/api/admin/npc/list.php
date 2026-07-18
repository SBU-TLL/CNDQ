<?php
/**
 * List NPCs API
 * GET: List all NPCs with their stats and current state
 */

require_once __DIR__ . '/../../../lib/NPCManager.php';
require_once __DIR__ . '/../../../userData.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$currentUserEmail = getCurrentUserEmail();

// Admin check (you may want to add proper admin authentication)
if (!$currentUserEmail || $currentUserEmail === 'dev_user') {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

try {
    $npcManager = new NPCManager();
    $result = $npcManager->listNPCs();

    echo json_encode([
        'success' => true,
        'enabled' => $result['enabled'],
        'npcs' => $result['npcs'],
        'count' => count($result['npcs'])
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
