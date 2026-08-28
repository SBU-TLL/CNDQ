<?php
/**
 * Fix and Test Script
 * 1. Trigger NPC cycle to ensure fresh ads
 * 2. Verify ads are created
 * 3. Show what Crafty Otter should see
 */

require_once __DIR__ . '/lib/NPCManager.php';
require_once __DIR__ . '/lib/TeamStorage.php';
require_once __DIR__ . '/lib/ListingManager.php';

echo "=== FIX AND TEST SCRIPT ===\n\n";

// Step 1: Trigger NPC cycle
echo "Step 1: Triggering NPC trading cycle...\n";
$npcMgr = new NPCManager();
try {
    $result = $npcMgr->runAllNPCs();
    echo "✓ NPC cycle complete\n";
    echo "  Actions: " . count($result['actions'] ?? []) . "\n\n";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n\n";
}

// Step 2: Check listings
echo "Step 2: Checking listings...\n";
$ads = ListingManager::getListingsByChemical();
$totalBuyAds = 0;
foreach (['C', 'N', 'D', 'Q'] as $chem) {
    $count = count($ads[$chem]['buy'] ?? []);
    $totalBuyAds += $count;
    if ($count > 0) {
        echo "  Chemical {$chem}: {$count} buy listings\n";
    }
}
echo "Total: {$totalBuyAds} buy listings\n\n";

// Step 3: Check Crafty Otter
echo "Step 3: What Crafty Otter should see...\n";
$craftyEmail = 'test_mail1@stonybrook.edu';
$storage = new TeamStorage($craftyEmail);
$state = $storage->getState();

foreach (['C', 'N', 'D', 'Q'] as $chem) {
    $myInventory = $state['inventory'][$chem] ?? 0;
    $buyAds = $ads[$chem]['buy'] ?? [];
    $visibleAds = $myInventory > 0 ? $buyAds : [];

    if (count($visibleAds) > 0) {
        echo "\n  Chemical {$chem}:";
        echo "\n    Your inventory: {$myInventory} gal\n";
        echo "    'Sell to' offers visible:\n";
        foreach ($visibleAds as $ad) {
            echo "      - {$ad['teamName']}\n";
        }
    }
}

echo "\n\n=== NEXT STEPS ===\n";
echo "1. Open: http://cndq.test/CNDQ/\n";
echo "2. Log in as: test_mail1@stonybrook.edu\n";
echo "3. Look at Chemical D card\n";
echo "4. You should see 'Sell to' buttons under 'Buy Requests' section\n";
echo "5. If not visible:\n";
echo "   - Press Ctrl+Shift+R to hard refresh (clear cache)\n";
echo "   - Open DevTools (F12) and check Console for errors\n";
echo "   - Check Network tab to see if /api/listings/list.php is called\n";
echo "\n";
?>
