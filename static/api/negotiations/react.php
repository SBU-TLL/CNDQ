<?php
/**
 * Negotiation Reaction API
 * POST: Record player sentiment/reaction to an offer
 * Body: { negotiationId: "neg_...", level: 0-100 }
 */

require_once __DIR__ . '/../../lib/TeamStorage.php';
require_once __DIR__ . '/../../userData.php';

header('Content-Type: application/json');

$currentUserEmail = getCurrentUserEmail();
if (!$currentUserEmail) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$negotiationId = $input['negotiationId'] ?? null;
$level = $input['level'] ?? 0;

if (!$negotiationId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing negotiation ID']);
    exit;
}

try {
    $storage = new TeamStorage($currentUserEmail);
    $storage->emitEvent('add_reaction', [
        'negotiationId' => $negotiationId,
        'level' => (int)$level
    ]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
