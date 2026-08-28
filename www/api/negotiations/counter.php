<?php
/**
 * Counter Offer API
 * POST: Make a counter-offer in an existing negotiation
 * Body: { negotiationId: "neg_...", quantity: 100, price: 6.00 }
 */

require_once __DIR__ . '/../../lib/NegotiationManager.php';
require_once __DIR__ . '/../../lib/TeamStorage.php';
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

// Check if trading is allowed
$sessionManager = new SessionManager();
if (!$sessionManager->isTradingAllowed()) {
    $state = $sessionManager->getState();
    http_response_code(403);
    echo json_encode([
        'error' => 'Trading not allowed',
        'message' => 'Market is currently ' . $state['phase'] . '.',
        'currentPhase' => $state['phase']
    ]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$negotiationId = $input['negotiationId'] ?? null;
$quantity = $input['quantity'] ?? null;
$price = $input['price'] ?? null;

if (!$negotiationId || !$quantity || $price === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

if ($quantity <= 0 || $price < 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid quantity or price']);
    exit;
}

try {
    $storage = new TeamStorage($currentUserEmail);
    $profile = $storage->getProfile();

    $negotiationManager = new NegotiationManager();

    $negotiation = $negotiationManager->addCounterOffer(
        $negotiationId,
        $currentUserEmail,
        $profile['teamName'],
        $quantity,
        $price
    );

    echo json_encode([
        'success' => true,
        'message' => 'Counter-offer sent',
        'negotiation' => $negotiation
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Failed to send counter-offer',
        'message' => $e->getMessage()
    ]);
}
