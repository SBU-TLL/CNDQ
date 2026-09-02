<?php
/**
 * Create Buy Order API
 * POST: Create a new buy order (expressing interest to buy)
 * Body: { chemical: "C", quantity: 100, maxPrice: 5.50 }
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

if (!$currentUserEmail) {
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
        'message' => 'Market is currently ' . $state['phase'] . '. Cannot create buy orders now.',
        'currentPhase' => $state['phase']
    ]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$chemical = $input['chemical'] ?? null;
$quantity = $input['quantity'] ?? null;
$maxPrice = $input['maxPrice'] ?? null;

if (!$chemical || !$quantity || $maxPrice === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

if (!in_array($chemical, ['C', 'N', 'D', 'Q'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid chemical']);
    exit;
}

if ($quantity <= 0 || $maxPrice < 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid quantity or price']);
    exit;
}

try {
    $storage = new TeamStorage($currentUserEmail);
    $profile = $storage->getProfile();

    // Check if buyer has enough funds - REMOVED for Infinite Capital model
    // Players can spend into negative balance (debt)

    // Get current session number
    $sessionState = $sessionManager->getState();
    $currentSession = $sessionState['currentSession'];

    // Create buy order
    $buyOrderData = [
        'chemical' => $chemical,
        'quantity' => $quantity,
        'maxPrice' => $maxPrice,
        'sessionNumber' => $currentSession
    ];

    $result = $storage->addBuyOrder($buyOrderData);

    // ALSO post listing so it shows up in the public marketplace
    require_once __DIR__ . '/../../lib/ListingManager.php';
    $listingManager = new ListingManager($currentUserEmail, $profile['teamName'] ?? null);
    $listingManager->postListing($chemical, 'buy', [
        'quantity' => $quantity,
        'maxPrice' => $maxPrice
    ]);

    // Get the created buy order (last one in array)
    $buyOrders = $result['interests'];
    $createdOrder = end($buyOrders);

    echo json_encode([
        'success' => true,
        'message' => 'Buy order created successfully',
        'buyOrder' => $createdOrder
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
