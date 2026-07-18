<?php
/**
 * Debug Crafty Otter's inventory to understand filtering
 */

require_once __DIR__ . '/lib/TeamStorage.php';
require_once __DIR__ . '/lib/NPCManager.php';
require_once __DIR__ . '/lib/MarketplaceAggregator.php';

echo "=== CRAFTY OTTER INVENTORY CHECK ===\n\n";

// Find Crafty Otter
$aggregator = new MarketplaceAggregator();
$allTeams = $aggregator->getAllTeams();

$craftyOtterEmail = null;
foreach ($allTeams as $team) {
    if (!str_starts_with($team['email'], 'npc_')) {
        $craftyOtterEmail = $team['email'];
        echo "Found test user: {$team['teamName']} ({$craftyOtterEmail})\n\n";
        break;
    }
}

if (!$craftyOtterEmail) {
    echo "No test user found!\n";
    exit;
}

$storage = new TeamStorage($craftyOtterEmail);
$state = $storage->getState();

echo "Inventory:\n";
foreach (['C', 'N', 'D', 'Q'] as $chemical) {
    $qty = $state['inventory'][$chemical] ?? 0;
    echo "  Chemical {$chemical}: {$qty} gallons\n";
}

echo "\nShadow Prices:\n";
foreach (['C', 'N', 'D', 'Q'] as $chemical) {
    $sp = $state['shadowPrices'][$chemical] ?? 0;
    echo "  Chemical {$chemical}: \${$sp}\n";
}

echo "\n=== FRONTEND FILTERING LOGIC ===\n";
echo "The frontend code (marketplace.js:613) filters buy ads like this:\n";
echo "  const buyAds = myInventory > 0 ? allBuyAds : [];\n\n";

echo "This means: If you have 0 inventory of Chemical D, you won't see ANY buy requests for D\n";
echo "even though someone wants to buy it from you.\n\n";

echo "Testing for each chemical:\n";
require_once __DIR__ . '/lib/ListingManager.php';
$adsByChemical = ListingManager::getListingsByChemical();
foreach (['C', 'N', 'D', 'Q'] as $chemical) {
    $myInventory = $state['inventory'][$chemical] ?? 0;
    $allBuyAds = $adsByChemical[$chemical]['buy'] ?? [];
    $visibleBuyAds = $myInventory > 0 ? $allBuyAds : [];

    echo "  Chemical {$chemical}:\n";
    echo "    - Your inventory: {$myInventory} gallons\n";
    echo "    - Total buy ads: " . count($allBuyAds) . "\n";
    echo "    - Visible to you: " . count($visibleBuyAds) . " ads\n";

    if ($myInventory === 0 && count($allBuyAds) > 0) {
        echo "    ⚠️ HIDDEN due to zero inventory!\n";
    }
    echo "\n";
}

echo "=== DIAGNOSIS ===\n";
$hasZeroInventory = false;
$hasBuyRequests = false;

foreach (['C', 'N', 'D', 'Q'] as $chemical) {
    $myInventory = $state['inventory'][$chemical] ?? 0;
    $allBuyAds = $adsByChemical[$chemical]['buy'] ?? [];

    if ($myInventory === 0 && count($allBuyAds) > 0) {
        $hasZeroInventory = true;
        $hasBuyRequests = true;
        echo "❌ Chemical {$chemical}: You have 0 inventory but there are " . count($allBuyAds) . " buy requests!\n";
        echo "   These 'Sell to' offers are being hidden by the frontend filter.\n";
    }
}

if ($hasZeroInventory && $hasBuyRequests) {
    echo "\n🐛 BUG CONFIRMED: The inventory filter is hiding legitimate sell opportunities!\n";
    echo "   Fix: Remove or modify the zero-inventory filter in marketplace.js line 613\n";
} else {
    echo "\n✓ No issues found with inventory filtering\n";
}
echo "\n";
