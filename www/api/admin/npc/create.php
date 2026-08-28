<?php
/**
 * Create NPC API
 * POST: Create new NPC(s)
 * Body: { skillLevel: "beginner"|"novice"|"expert", count: 1 }
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

$skillLevel = $input['skillLevel'] ?? null;
$count = $input['count'] ?? 1;

if (!$skillLevel) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing skillLevel']);
    exit;
}

if (!in_array($skillLevel, ['beginner', 'novice', 'expert'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid skill level. Must be: beginner, novice, or expert']);
    exit;
}

if ($count < 1 || $count > 10) {
    http_response_code(400);
    echo json_encode(['error' => 'Count must be between 1 and 10']);
    exit;
}

try {
    $npcManager = new NPCManager();
    $createdIds = $npcManager->createNPCs($skillLevel, $count);

    // Get details of created NPCs
    $npcList = $npcManager->listNPCs();
    $createdNPCs = array_filter($npcList['npcs'], function($npc) use ($createdIds) {
        return in_array($npc['id'], $createdIds);
    });

    echo json_encode([
        'success' => true,
        'message' => "Created $count $skillLevel NPC(s)",
        'npcIds' => $createdIds,
        'npcs' => array_values($createdNPCs)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to create NPC',
        'message' => $e->getMessage()
    ]);
}
