<?php
/**
 * My Advertisements API
 * GET: Get current user's advertisements
 */

require_once __DIR__ . '/../../lib/ListingManager.php';
require_once __DIR__ . '/../../lib/TeamStorage.php';
require_once __DIR__ . '/../../userData.php';

header('Content-Type: application/json');

$currentUserEmail = getCurrentUserEmail();

if (!$currentUserEmail || $currentUserEmail === 'dev_user') {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

try {
    $storage = new TeamStorage($currentUserEmail);
    $profile = $storage->getProfile();

    $listingManager = new ListingManager($currentUserEmail, $profile['teamName']);
    $listings = $listingManager->getListings();

    echo json_encode([
        'success' => true,
        'listings' => $listings
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
