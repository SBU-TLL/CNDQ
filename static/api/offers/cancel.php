<?php
/**
 * Cancel Offer API
 * POST: Cancel own offer
 * Body: { offerId: "offer_abc123" }
 */

require_once __DIR__ . '/../../lib/TeamStorage.php';
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
$offerId = $input['offerId'] ?? null;

if (!$offerId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing offerId']);
    exit;
}

try {
    $storage = new TeamStorage($currentUserEmail);

    // Determine if this is a sell offer or buy order based on ID prefix
    $isBuyOrder = strpos($offerId, 'buy_') === 0;

    if ($isBuyOrder) {
        // Handle buy order cancellation
        $buyOrders = $storage->getBuyOrders();
        $found = false;
        foreach ($buyOrders['interests'] as $buyOrder) {
            if ($buyOrder['id'] === $offerId) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            http_response_code(404);
            echo json_encode(['error' => 'Buy order not found or not owned by you']);
            exit;
        }

        // Remove buy order
        $storage->removeBuyOrder($offerId);

        echo json_encode([
            'success' => true,
            'message' => 'Buy order cancelled successfully'
        ]);
    } else {
        // Handle sell offer cancellation
        $offers = $storage->getOffersMade();
        $found = false;
        foreach ($offers['offers'] as $offer) {
            if ($offer['id'] === $offerId) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            http_response_code(404);
            echo json_encode(['error' => 'Offer not found or not owned by you']);
            exit;
        }

        // Remove offer
        $storage->removeOffer($offerId);

        echo json_encode([
            'success' => true,
            'message' => 'Offer cancelled successfully'
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
