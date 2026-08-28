<?php
/**
 * Toggle NPC System API
 * POST: Toggle global NPC system enabled/disabled
 * Body: { enabled: true }
 */

require_once __DIR__ . '/../../../lib/NPCManager.php';
require_once __DIR__ . '/../../../userData.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

$input = json_decode(file_get_contents('php://input'), true);

$enabled = $input['enabled'] ?? null;

if ($enabled === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing enabled']);
    exit;
}

try {
    $npcManager = new NPCManager();
    $result = $npcManager->toggleSystem($enabled);

    echo json_encode([
        'success' => true,
        'message' => 'NPC system ' . ($enabled ? 'enabled' : 'disabled'),
        'enabled' => (bool)$enabled
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to toggle NPC system',
        'message' => $e->getMessage()
    ]);
}
