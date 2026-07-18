<?php
/**
 * Quick check script to verify NPCs are only posting buy requests
 * Run this while the game is running to see NPC advertisement behavior
 */

require_once __DIR__ . '/lib/Database.php';

$db = Database::getInstance();

echo "=== NPC Advertisement Check ===\n\n";

// Get all NPCs
$npcs = $db->query("SELECT email, teamName FROM teams WHERE email LIKE 'npc_%'");

if (empty($npcs)) {
    echo "No NPCs found in the database.\n";
    exit;
}

echo "Found " . count($npcs) . " NPCs:\n";
foreach ($npcs as $npc) {
    echo "  - {$npc['teamName']} ({$npc['email']})\n";
}
echo "\n";

// Check listings by type
$adStats = $db->query("
    SELECT event_type as type, COUNT(*) as count 
    FROM marketplace_events 
    WHERE event_type IN ('add_ad', 'add_listing')
    GROUP BY event_type
");

echo "=== Listings by Event Type ===\n";
if (empty($adStats)) {
    echo "No listings in database yet.\n";
} else {
    foreach ($adStats as $stat) {
        echo "  {$stat['type']}: {$stat['count']} listings\n";
    }
}
echo "\n";

// Check recent NPC listings
$npcAds = $db->query("
    SELECT payload, team_name, timestamp
    FROM marketplace_events
    WHERE team_email LIKE 'npc_%' AND event_type IN ('add_ad', 'add_listing')
    ORDER BY timestamp DESC
    LIMIT 10
");

echo "=== Recent NPC Listings (Last 10) ===\n";
if (empty($npcAds)) {
    echo "No NPC listings found.\n";
} else {
    foreach ($npcAds as $ad) {
        $payload = json_decode($ad['payload'], true);
        $time = date('H:i:s', (int)$ad['timestamp']);
        echo "  [{$time}] {$ad['team_name']}: {$payload['type']} Chemical {$payload['chemical']}\n";
    }
}
echo "\n";

// Check for any 'sell' type listings from NPCs
$npcSellAds = $db->query("
    SELECT payload
    FROM marketplace_events
    WHERE team_email LIKE 'npc_%' AND event_type IN ('add_ad', 'add_listing')
");

$sellCount = 0;
foreach ($npcSellAds as $ad) {
    $payload = json_decode($ad['payload'], true);
    if (($payload['type'] ?? '') === 'sell') $sellCount++;
}

echo "=== VERIFICATION ===\n";
if ($sellCount > 0) {
    echo "❌ PROBLEM: Found {$sellCount} SELL listings from NPCs!\n";
} else {
    echo "✅ GOOD: No sell listings from NPCs\n";
    echo "   NPCs are correctly only posting buy requests\n";
}
echo "\n";

// Check recent negotiations initiated by NPCs
$npcNegs = $db->query("
    SELECT n.*, t.teamName
    FROM negotiations n
    JOIN teams t ON n.initiatorId = t.email
    WHERE t.email LIKE 'npc_%'
    ORDER BY n.createdAt DESC
    LIMIT 5
");

echo "=== Recent NPC-Initiated Negotiations ===\n";
if (empty($npcNegs)) {
    echo "No NPC negotiations found.\n";
} else {
    foreach ($npcNegs as $neg) {
        $time = date('H:i:s', $neg['createdAt']);
        $type = $neg['type'] ?? 'buy';
        echo "  [{$time}] {$neg['teamName']}: {$type} negotiation for Chemical {$neg['chemical']} (status: {$neg['status']})\n";
    }
}

echo "\n=== Summary ===\n";
echo "NPCs should:\n";
echo "  ✓ Post BUY advertisements when they want to acquire chemicals\n";
echo "  ✓ Initiate negotiations to SELL (responding to others' buy requests)\n";
echo "  ✗ NEVER post SELL advertisements\n";
