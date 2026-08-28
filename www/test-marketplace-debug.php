<?php
/**
 * Debug script to check marketplace buy orders for "Crafty Otter" test
 */

require_once __DIR__ . '/lib/Database.php';
require_once __DIR__ . '/lib/MarketplaceAggregator.php';
require_once __DIR__ . '/lib/TeamStorage.php';
require_once __DIR__ . '/lib/NPCManager.php';

echo "=== MARKETPLACE DEBUG TOOL ===\n\n";

// 1. Check if there are any NPCs
$npcMgr = new NPCManager();
$npcs = $npcMgr->listNPCs();
echo "NPCs in system: " . count($npcs['npcs']) . "\n";
foreach ($npcs['npcs'] as $npc) {
    echo "  - {$npc['teamName']} ({$npc['email']}) - " . ($npc['active'] ? 'ACTIVE' : 'INACTIVE') . "\n";
}
echo "\n";

// 2. Check marketplace_events for buy orders
$db = Database::getInstance();
$buyOrderEvents = $db->query(
    "SELECT team_email, team_name, payload, timestamp
     FROM marketplace_events
     WHERE event_type = 'add_buy_order'
     ORDER BY timestamp DESC
     LIMIT 10"
);

echo "Recent 'add_buy_order' events in database: " . count($buyOrderEvents) . "\n";
foreach ($buyOrderEvents as $event) {
    $payload = json_decode($event['payload'], true);
    echo "  - {$event['team_name']}: {$payload['chemical']} @ \${$payload['maxPrice']} (qty: {$payload['quantity']})\n";
}
echo "\n";

// 3. Check marketplace aggregator
$aggregator = new MarketplaceAggregator();
$buyOrdersByChemical = $aggregator->getBuyOrdersByChemical();

echo "Active Buy Orders from MarketplaceAggregator:\n";
$totalBuyOrders = 0;
foreach (['C', 'N', 'D', 'Q'] as $chemical) {
    $orders = $buyOrdersByChemical[$chemical] ?? [];
    $totalBuyOrders += count($orders);
    echo "  Chemical {$chemical}: " . count($orders) . " orders\n";
    foreach ($orders as $order) {
        echo "    - {$order['buyerName']}: \${$order['maxPrice']} for {$order['quantity']} gallons\n";
    }
}
echo "Total: {$totalBuyOrders} buy orders\n\n";

// 4. Check what "Crafty Otter" should see
echo "=== WHAT 'CRAFTY OTTER' SHOULD SEE ===\n";
$craftyOtterEmail = null;

// Find Crafty Otter's email
foreach ($npcs['npcs'] as $npc) {
    if (stripos($npc['teamName'], 'Crafty Otter') !== false ||
        stripos($npc['teamName'], 'CraftyOtter') !== false) {
        $craftyOtterEmail = $npc['email'];
        echo "Found Crafty Otter: {$craftyOtterEmail}\n";
        break;
    }
}

if (!$craftyOtterEmail) {
    echo "ERROR: Crafty Otter not found in system!\n";
    echo "Trying to find any test user...\n";
    // Look for any non-NPC user
    $allTeams = $aggregator->getAllTeams();
    foreach ($allTeams as $team) {
        if (!str_starts_with($team['email'], 'npc_')) {
            $craftyOtterEmail = $team['email'];
            echo "Using test user: {$team['teamName']} ({$craftyOtterEmail})\n";
            break;
        }
    }
}

if ($craftyOtterEmail) {
    // Simulate API call filtering out Crafty Otter's own orders
    $visibleBuyOrders = [];
    foreach ($buyOrdersByChemical as $chemical => $orders) {
        $filtered = array_filter($orders, function($order) use ($craftyOtterEmail) {
            return ($order['teamId'] ?? $order['buyerId'] ?? '') !== $craftyOtterEmail;
        });
        if (!empty($filtered)) {
            $visibleBuyOrders[$chemical] = array_values($filtered);
        }
    }

    echo "\nBuy Orders visible to Crafty Otter:\n";
    $visibleCount = 0;
    foreach (['C', 'N', 'D', 'Q'] as $chemical) {
        $orders = $visibleBuyOrders[$chemical] ?? [];
        $visibleCount += count($orders);
        if (!empty($orders)) {
            echo "  Chemical {$chemical}: " . count($orders) . " 'Sell to' offers\n";
            foreach ($orders as $order) {
                echo "    - {$order['buyerName']}: \${$order['maxPrice']} for {$order['quantity']} gal\n";
            }
        }
    }

    if ($visibleCount === 0) {
        echo "  *** NO BUY ORDERS VISIBLE! This is the problem! ***\n";
    } else {
        echo "Total: {$visibleCount} 'Sell to' offers should be visible\n";
    }
} else {
    echo "Could not find a test user to check visibility\n";
}

echo "\n=== DIAGNOSIS ===\n";
if (count($npcs['npcs']) === 0) {
    echo "❌ No NPCs exist in the system\n";
    echo "   Solution: Create NPCs via admin panel or API\n";
} else {
    $activeNPCs = array_filter($npcs['npcs'], fn($n) => $n['active']);
    if (empty($activeNPCs)) {
        echo "❌ NPCs exist but are INACTIVE\n";
        echo "   Solution: Activate NPCs via admin panel\n";
    } else {
        echo "✓ Active NPCs: " . count($activeNPCs) . "\n";
    }
}

if (empty($buyOrderEvents)) {
    echo "❌ No buy_order events in database\n";
    echo "   Solution: NPCs haven't run their trading cycle yet\n";
    echo "   Try: POST /api/admin/npc/trigger-cycle\n";
} else {
    echo "✓ Buy order events exist: " . count($buyOrderEvents) . "\n";
}

if ($totalBuyOrders === 0) {
    echo "❌ MarketplaceAggregator returns NO buy orders\n";
    echo "   This suggests events aren't being aggregated correctly\n";
} else {
    echo "✓ Aggregator finds buy orders: {$totalBuyOrders}\n";
}

echo "\n";
