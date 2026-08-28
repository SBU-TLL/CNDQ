<?php
require_once __DIR__ . '/lib/Database.php';

try {
    $db = Database::getInstance();

    echo "=== Negotiation Status Counts ===\n";
    $statusCounts = $db->query("SELECT status, COUNT(*) as count FROM negotiations GROUP BY status");
    foreach ($statusCounts as $row) {
        echo "{$row['status']}: {$row['count']}\n";
    }
    echo "\n";

    echo "=== Rejected Negotiations (Last 5) ===\n";
    $rejected = $db->query("SELECT id, initiator_name, responder_name, chemical, type, last_offer_by, updated_at FROM negotiations WHERE status = 'rejected' ORDER BY updated_at DESC LIMIT 5");
    
    if (empty($rejected)) {
        echo "No rejected negotiations found.\n";
    }

    foreach ($rejected as $row) {
        echo "ID: {$row['id']}\n";
        echo "  Initiator: {$row['initiator_name']} ({$row['type']})\n";
        echo "  Responder: {$row['responder_name']}\n";
        echo "  Chemical: {$row['chemical']}\n";
        echo "  Last Offer By: {$row['last_offer_by']}\n";
        echo "  Time: " . date('Y-m-d H:i:s', $row['updated_at']) . "\n";
        
        // Get offers for this negotiation
        $offers = $db->query("SELECT from_team_name, price, quantity, heat_total, created_at FROM negotiation_offers WHERE negotiation_id = ? ORDER BY created_at ASC", [$row['id']]);
        echo "  Offers:\n";
        foreach ($offers as $offer) {
            echo "    - {$offer['from_team_name']}: {$offer['quantity']} @ {$offer['price']} (Heat: {$offer['heat_total']})\n";
        }
        echo "\n";
    }

    echo "=== NPC Configuration ===\n";
    $configs = $db->query("SELECT key, value FROM config WHERE key LIKE '%npc%'");
    foreach ($configs as $row) {
        // Truncate value if too long
        $val = $row['value'];
        if (strlen($val) > 100) $val = substr($val, 0, 100) . "...";
        echo "{$row['key']}: $val\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
