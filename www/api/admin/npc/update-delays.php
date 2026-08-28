<?php
/**
 * Update NPC Delays API
 * POST: Update global delay settings for NPCs
 */

require_once __DIR__ . '/../../../lib/NPCManager.php';
require_once __DIR__ . '/../../../userData.php';

header('Content-Type: application/json');

if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Admin privileges required']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $delays = $input['delays'] ?? null;

    if (!$delays || !is_array($delays)) {
        throw new Exception("Missing or invalid delays configuration");
    }

    $npcManager = new NPCManager();
    $config = $npcManager->loadConfig();
    
    // Merge or replace delays
    $config['delays'] = $delays;
    
    $npcManager->saveConfig($config);

    echo json_encode([
        'success' => true,
        'message' => 'NPC delays updated successfully',
        'delays' => $config['delays']
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
