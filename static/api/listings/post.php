<?php
/**
 * Post Advertisement API
 * POST: Post interest to buy or sell (no price)
 * Body: { chemical: "C", type: "buy" | "sell" }
 */

require_once __DIR__ . '/../../lib/ListingManager.php';
require_once __DIR__ . '/../../lib/SessionManager.php';
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

// Check if trading is allowed
$sessionManager = new SessionManager();
if (!$sessionManager->isTradingAllowed()) {
    $state = $sessionManager->getState();
    http_response_code(403);
    echo json_encode([
        'error' => 'Trading not allowed',
        'message' => 'Market is currently ' . $state['phase'] . '. Cannot post listings now.',
        'currentPhase' => $state['phase']
    ]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$chemical = $input['chemical'] ?? null;
$type = $input['type'] ?? null;

if (!$chemical || !$type) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

if (!in_array($chemical, ['C', 'N', 'D', 'Q'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid chemical']);
    exit;
}

if (!in_array($type, ['buy', 'sell'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid type']);
    exit;
}

try {
    // Get team name
    $storage = new TeamStorage($currentUserEmail);
    $profile = $storage->getProfile();

    $listingManager = new ListingManager($currentUserEmail, $profile['teamName']);
    $listing = $listingManager->postListing($chemical, $type);

    echo json_encode([
        'success' => true,
        'message' => 'Listing posted successfully',
        'listing' => $listing
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
