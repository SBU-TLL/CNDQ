<?php
/**
 * List Advertisements API
 * GET: Get all active advertisements grouped by chemical
 */

require_once __DIR__ . '/../../lib/ListingManager.php';
require_once __DIR__ . '/../../userData.php';

header('Content-Type: application/json');

$currentUserEmail = getCurrentUserEmail();

if (!$currentUserEmail) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

try {
    $listings = ListingManager::getListingsByChemical();

    // Hide quantity and maxPrice from non-owners
    // Only the listing owner should see their own quantity/price details
    foreach (['C', 'N', 'D', 'Q'] as $chemical) {
        foreach (['buy', 'sell'] as $type) {
            if (!isset($listings[$chemical][$type])) continue;

            foreach ($listings[$chemical][$type] as $key => $ad) {
                // Check if this ad belongs to the current user
                $isOwner = ($ad['teamId'] ?? $ad['team_id'] ?? null) === $currentUserEmail;

                if (!$isOwner) {
                    // Strip sensitive data for non-owners
                    // They can see that a buy request exists, but not the details
                    unset($listings[$chemical][$type][$key]['quantity']);
                    unset($listings[$chemical][$type][$key]['maxPrice']);
                    unset($listings[$chemical][$type][$key]['minPrice']);
                }
            }
            // Re-index array after potential unsets
            $listings[$chemical][$type] = array_values($listings[$chemical][$type]);
        }
    }

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
