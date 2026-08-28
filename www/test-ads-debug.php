<?php
/**
 * Debug script to check advertisements vs buy orders
 */

require_once __DIR__ . '/lib/Database.php';
require_once __DIR__ . '/lib/ListingManager.php';
require_once __DIR__ . '/lib/MarketplaceAggregator.php';

echo "=== LISTING SYSTEM DEBUG ===\n\n";

$db = Database::getInstance();

// 1. Check 'add_ad' events
$adEvents = $db->query(
    "SELECT team_email, team_name, payload, timestamp
     FROM marketplace_events
     WHERE event_type = 'add_ad'
     ORDER BY timestamp DESC
     LIMIT 10"
);

echo "Recent 'add_ad' (listing) events in database: " . count($adEvents) . "\n";
foreach ($adEvents as $event) {
    $payload = json_decode($event['payload'], true);
    echo "  - {$event['team_name']}: {$payload['chemical']} ({$payload['type']})\n";
}
echo "\n";

// 2. Check what ListingManager returns
$adsByChemical = ListingManager::getListingsByChemical();

echo "Listings from ListingManager:\n";
$totalAds = 0;
foreach (['C', 'N', 'D', 'Q'] as $chemical) {
    $buyAds = $adsByChemical[$chemical]['buy'] ?? [];
    $totalAds += count($buyAds);
    echo "  Chemical {$chemical}: " . count($buyAds) . " buy listings\n";
    foreach ($buyAds as $ad) {
        echo "    - {$ad['teamName']}: {$ad['chemical']}\n";
    }
}
echo "Total buy listings: {$totalAds}\n\n";

// 3. Compare with buy orders
$aggregator = new MarketplaceAggregator();
$buyOrdersByChemical = $aggregator->getBuyOrdersByChemical();

echo "Buy Orders from MarketplaceAggregator:\n";
$totalOrders = 0;
foreach (['C', 'N', 'D', 'Q'] as $chemical) {
    $orders = $buyOrdersByChemical[$chemical] ?? [];
    $totalOrders += count($orders);
    echo "  Chemical {$chemical}: " . count($orders) . " buy orders\n";
}
echo "Total buy orders: {$totalOrders}\n\n";

echo "=== ISSUE DIAGNOSIS ===\n";
if ($totalAds === 0 && $totalOrders > 0) {
    echo "❌ PROBLEM FOUND: NPCs are creating buy_orders but NOT listings!\n";
    echo "   The frontend loads listings, not buy orders.\n";
    echo "   Check NPCManager::executeCreateBuyOrder() - it should call postListing()\n";
} elseif ($totalAds > 0) {
    echo "✓ Listings exist: {$totalAds}\n";
    echo "  These should be visible in the frontend.\n";
} else {
    echo "❌ No listings or buy orders found\n";
    echo "   NPCs may not have run their trading cycle yet\n";
}
echo "\n";
