<?php
/**
 * Reject/Cancel Negotiation API
 * POST: Reject the current offer or cancel the negotiation
 * Body: { negotiationId: "neg_..." }
 */

require_once __DIR__ . '/../../lib/NegotiationManager.php';
require_once __DIR__ . '/../../lib/SessionManager.php';
require_once __DIR__ . '/../../userData.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$currentUserEmail = getCurrentUserEmail();

if (!$currentUserEmail || $currentUserEmail === 'dev_user') {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$negotiationId = $input['negotiationId'] ?? null;

if (!$negotiationId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing negotiation ID']);
    exit;
}

try {
    $negotiationManager = new NegotiationManager();

    $negotiation = $negotiationManager->rejectNegotiation($negotiationId, $currentUserEmail);

    echo json_encode([
        'success' => true,
        'message' => 'Negotiation cancelled',
        'negotiation' => $negotiation
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Failed to cancel negotiation',
        'message' => $e->getMessage()
    ]);
}
