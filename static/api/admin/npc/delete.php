<?php
/**
 * Delete NPC API
 * POST: Delete an NPC
 * Body: { npcId: "npc_xyz", deleteTeamData: false }
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
$deleteTeamData = $input['deleteTeamData'] ?? false;

if (!$npcId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing npcId']);
    exit;
}

try {
    $npcManager = new NPCManager();
    $result = $npcManager->deleteNPC($npcId, $deleteTeamData);

    echo json_encode([
        'success' => true,
        'message' => 'NPC deleted successfully',
        'npcId' => $npcId,
        'teamDataDeleted' => $deleteTeamData
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to delete NPC',
        'message' => $e->getMessage()
    ]);
}
