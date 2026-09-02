<?php
require_once __DIR__ . '/lib/TeamStorage.php';
require_once __DIR__ . '/lib/ListingManager.php';

$craftyEmail = 'test_mail1@stonybrook.edu';
$storage = new TeamStorage($craftyEmail);
$state = $storage->getState();
$ads = ListingManager::getListingsByChemical();

echo "=== CRAFTY OTTER STATUS ===\n\n";
echo "Chemical D:\n";
echo "  Inventory: " . ($state['inventory']['D'] ?? 0) . " gallons\n";
echo "  Buy Listings Available: " . count($ads['D']['buy'] ?? []) . "\n\n";

if (($state['inventory']['D'] ?? 0) > 0 && count($ads['D']['buy'] ?? []) > 0) {
    echo "✓ Should see " . count($ads['D']['buy']) . " 'Sell to' offers\n\n";
    foreach ($ads['D']['buy'] as $ad) {
        echo "  - Sell to: {$ad['teamName']}\n";
    }
} else {
    if (($state['inventory']['D'] ?? 0) <= 0) {
        echo "✗ No inventory - can't sell\n";
    }
    if (count($ads['D']['buy'] ?? []) <= 0) {
        echo "✗ No buy requests posted\n";
    }
}
?>
