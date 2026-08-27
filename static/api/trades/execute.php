<?php
/**
 * Execute Trade API
 * POST: Execute a trade by accepting an offer
 * Body: { offerId: "offer_xyz", quantity: 100 }
 */

require_once __DIR__ . '/../../lib/TradeExecutor.php';
require_once __DIR__ . '/../../lib/MarketplaceAggregator.php';
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

// Check if trading is allowed (session phase check)
$sessionManager = new SessionManager();
if (!$sessionManager->isTradingAllowed()) {
    $state = $sessionManager->getState();
    http_response_code(403);
    echo json_encode([
        'error' => 'Trading not allowed',
        'message' => 'Market is currently ' . $state['phase'] . '. Wait for trading phase to open.',
        'currentPhase' => $state['phase'],
        'currentSession' => $state['currentSession']
    ]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$offerId = $input['offerId'] ?? null;
$quantity = $input['quantity'] ?? null;

if (!$offerId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing offerId']);
    exit;
}

try {
    $aggregator = new MarketplaceAggregator();

    // Determine if this is a sell offer or buy order
    $isBuyOrder = strpos($offerId, 'buy_') === 0;

    if ($isBuyOrder) {
        // Accepting a buy order (I am selling to them)
        $buyOrder = $aggregator->getBuyOrderById($offerId);

        if (!$buyOrder) {
            http_response_code(404);
            echo json_encode(['error' => 'Buy order not found or no longer available']);
            exit;
        }

        // Prevent self-trading
        if ($buyOrder['buyerId'] === $currentUserEmail) {
            http_response_code(400);
            echo json_encode(['error' => 'Cannot trade with yourself']);
            exit;
        }

        // Use buy order quantity if not specified
        $tradeQuantity = $quantity ?? $buyOrder['quantity'];

        // Can't trade more than requested
        if ($tradeQuantity > $buyOrder['quantity']) {
            http_response_code(400);
            echo json_encode([
                'error' => 'Requested quantity exceeds buy order',
                'requested_by_buyer' => $buyOrder['quantity'],
                'requested_by_you' => $tradeQuantity
            ]);
            exit;
        }

        // Execute trade (I am seller, they are buyer)
        $executor = new TradeExecutor();
        $result = $executor->executeTrade(
            $currentUserEmail,          // seller (current user)
            $buyOrder['buyerId'],       // buyer
            $buyOrder['chemical'],
            $tradeQuantity,
            $buyOrder['maxPrice'],      // price
            $offerId
        );

    } else {
        // Accepting a sell offer (I am buying from them)
        $offer = $aggregator->getOfferById($offerId);

        if (!$offer) {
            http_response_code(404);
            echo json_encode(['error' => 'Offer not found or no longer available']);
            exit;
        }

        // Prevent self-trading
        if ($offer['sellerId'] === $currentUserEmail) {
            http_response_code(400);
            echo json_encode(['error' => 'Cannot trade with yourself']);
            exit;
        }

        // Use offer quantity if not specified
        $tradeQuantity = $quantity ?? $offer['quantity'];

        // Can't trade more than offered
        if ($tradeQuantity > $offer['quantity']) {
            http_response_code(400);
            echo json_encode([
                'error' => 'Requested quantity exceeds offer',
                'offered' => $offer['quantity'],
                'requested' => $tradeQuantity
            ]);
            exit;
        }

        // Execute trade (they are seller, I am buyer)
        $executor = new TradeExecutor();
        $result = $executor->executeTrade(
            $offer['sellerId'],         // seller
            $currentUserEmail,          // buyer (current user)
            $offer['chemical'],
            $tradeQuantity,
            $offer['minPrice'] ?? $offer['price'],
            $offerId
        );
    }

    if (!$result['success']) {
        http_response_code(400);
        echo json_encode($result);
        exit;
    }

    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
