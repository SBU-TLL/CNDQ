<?php
/**
 * Create Offer API
 * POST: Create a new sell offer
 * Body: { chemical: "C", quantity: 100, minPrice: 5.50 }
 */

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
        'message' => 'Market is currently ' . $state['phase'] . '. Cannot create offers now.',
        'currentPhase' => $state['phase']
    ]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$chemical = $input['chemical'] ?? null;
$quantity = $input['quantity'] ?? null;
$minPrice = $input['minPrice'] ?? null;

if (!$chemical || !$quantity || $minPrice === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

if (!in_array($chemical, ['C', 'N', 'D', 'Q'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid chemical']);
    exit;
}

if ($quantity <= 0 || $minPrice < 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid quantity or price']);
    exit;
}

try {
    $storage = new TeamStorage($currentUserEmail);
    $inventory = $storage->getInventory();

    // Check if seller has enough inventory
    if ($inventory[$chemical] < $quantity) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Insufficient inventory',
            'available' => $inventory[$chemical],
            'requested' => $quantity
        ]);
        exit;
    }

    // Create offer
    $offerData = [
        'chemical' => $chemical,
        'quantity' => $quantity,
        'minPrice' => $minPrice,
        'type' => 'sell'
    ];

    $result = $storage->addOffer($offerData);

    // Get the created offer (last one in array)
    $offers = $result['offers'];
    $createdOffer = end($offers);

    echo json_encode([
        'success' => true,
        'message' => 'Offer created successfully',
        'offer' => $createdOffer
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
