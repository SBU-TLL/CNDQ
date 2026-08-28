<?php
/**
 * Simulate the API response that the frontend receives
 */

require_once __DIR__ . '/lib/ListingManager.php';
require_once __DIR__ . '/userData.php';

// Simulate being logged in as Crafty Otter
$_SERVER['HTTP_X_AUTH_EMAIL'] = 'test_mail1@stonybrook.edu';

echo "=== SIMULATING API CALL: /api/listings/list.php ===\n\n";

$currentUserEmail = getCurrentUserEmail();
echo "Current User: {$currentUserEmail}\n\n";

$listings = ListingManager::getListingsByChemical();

echo "API Response:\n";
echo json_encode([
    'success' => true,
    'listings' => $listings
], JSON_PRETTY_PRINT);

echo "\n\n=== ANALYSIS ===\n";

foreach (['C', 'N', 'D', 'Q'] as $chemical) {
    $buyAds = $listings[$chemical]['buy'] ?? [];
    echo "Chemical {$chemical}: " . count($buyAds) . " buy listings\n";
    foreach ($buyAds as $ad) {
        $teamId = $ad['teamId'] ?? 'unknown';
        $teamName = $ad['teamName'] ?? 'unknown';
        $isNPC = str_starts_with($teamId, 'npc_');
        echo "  - {$teamName} ({$teamId}) " . ($isNPC ? '[NPC]' : '[Human]') . "\n";
        echo "    listing data: " . json_encode($ad) . "\n";
    }
}

echo "\n=== WHAT THE FRONTEND SHOULD SEE ===\n";
echo "When Crafty Otter loads the page:\n";
echo "1. JavaScript calls: api.listings.list()\n";
echo "2. Backend returns the JSON above\n";
echo "3. marketplace.js sets: this.listings = data.listings\n";
echo "4. marketplace.js calls: this.renderListings()\n";
echo "5. For Chemical D: card.buyAds = [3 listings]\n";
echo "6. chemical-card component renders 3 <listing-item> elements\n";
echo "7. Each <listing-item> shows 'Sell to' button (since isMyAd=false)\n\n";

echo "If you don't see the 'Sell to' buttons:\n";
echo "- Check browser console for JavaScript errors\n";
echo "- Check if chemical-card web component is loaded\n";
echo "- Check if listing-item web component is loaded\n";
echo "- Check network tab to see if API is being called\n";
echo "- Check if listings are being filtered incorrectly\n";
echo "\n";
