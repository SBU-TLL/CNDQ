<?php
/**
 * Toggle NPC API
 * POST: Toggle individual NPC active state
 * Body: { npcId: "npc_xyz", active: true }
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

$npcId = $input['npcId'] ?? null;
$active = $input['active'] ?? null;

if (!$npcId || $active === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing npcId or active']);
    exit;
}

try {
    $npcManager = new NPCManager();
    $result = $npcManager->toggleNPC($npcId, $active);

    echo json_encode([
        'success' => true,
        'message' => 'NPC ' . ($active ? 'activated' : 'deactivated'),
        'npcId' => $npcId,
        'active' => (bool)$active
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to toggle NPC',
        'message' => $e->getMessage()
    ]);
}
