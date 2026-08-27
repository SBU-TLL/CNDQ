<?php
/**
 * Cancel Listing API
 * POST: Cancel/remove a buy request listing
 * Body: { listingId: "ad_xxx" }
 *
 * Security: Only the listing owner can cancel their own listing
 */

require_once __DIR__ . '/../../lib/ListingManager.php';
require_once __DIR__ . '/../../lib/TeamStorage.php';
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

$input = json_decode(file_get_contents('php://input'), true);

$listingId = $input['listingId'] ?? null;

if (!$listingId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing listingId']);
    exit;
}

try {
    // Get the user's listings to verify ownership
    $storage = new TeamStorage($currentUserEmail);
    $profile = $storage->getProfile();

    $listingManager = new ListingManager($currentUserEmail, $profile['teamName']);
    $myListings = $listingManager->getListings();

    // Check if this listing belongs to the current user
    $listingFound = false;
    $listingChemical = null;

    $adsList = $myListings['ads'] ?? $myListings; // Handle both wrapped and unwrapped cases for safety
    if (!is_array($adsList)) $adsList = [];

    foreach ($adsList as $ad) {
        if (($ad['id'] ?? '') === $listingId) {
            $listingFound = true;
            $listingChemical = $ad['chemical'] ?? null;
            break;
        }
    }

    if (!$listingFound) {
        http_response_code(403);
        echo json_encode([
            'error' => 'Forbidden',
            'message' => 'You can only cancel your own listings'
        ]);
        exit;
    }

    // Remove the listing (snapshot is refreshed automatically in TeamStorage)
    $listingManager->removeListing($listingId);

    // Also remove the corresponding buy order for this chemical
    // (Listings and buy orders are separate - both must be removed)
    if ($listingChemical) {
        $buyOrdersData = $storage->getBuyOrders();
        $buyOrders = $buyOrdersData['interests'] ?? [];
        foreach ($buyOrders as $order) {
            if (($order['chemical'] ?? '') === $listingChemical) {
                $storage->removeBuyOrder($order['id']);
                break; // Remove one matching buy order
            }
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Listing cancelled successfully',
        'listingId' => $listingId,
        'chemical' => $listingChemical
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
