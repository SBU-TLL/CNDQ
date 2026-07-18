<?php
/**
 * Global Transaction History API
 * GET: Returns list of all marketplace transactions (visible to all teams)
 */

require_once __DIR__ . '/../../lib/Database.php';

header('Content-Type: application/json');

try {
    $db = Database::getInstance();

    // Get all trade transactions from marketplace_events
    // These are recorded by TradeExecutor with event_type = 'add_transaction'
    $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 100) : 50;

    $events = $db->query(
        "SELECT payload, timestamp, team_name
         FROM marketplace_events
         WHERE event_type = 'add_transaction'
         ORDER BY timestamp DESC
         LIMIT ?",
        [$limit]
    );

    $transactions = [];
    foreach ($events as $event) {
        $payload = json_decode($event['payload'], true);
        if ($payload) {
            $transactions[] = [
                'transactionId' => $payload['transactionId'] ?? null,
                'chemical' => $payload['chemical'] ?? null,
                'quantity' => $payload['quantity'] ?? 0,
                'pricePerGallon' => $payload['pricePerGallon'] ?? 0,
                'totalAmount' => $payload['totalAmount'] ?? 0,
                // Seller info
                'sellerName' => $payload['sellerName'] ?? 'Unknown',
                'sellerInvBefore' => $payload['sellerInvBefore'] ?? null,
                'sellerInvAfter' => $payload['sellerInvAfter'] ?? null,
                // Buyer info
                'buyerName' => $payload['buyerName'] ?? 'Unknown',
                'buyerInvBefore' => $payload['buyerInvBefore'] ?? null,
                'buyerInvAfter' => $payload['buyerInvAfter'] ?? null,
                // Metadata
                'timestamp' => $event['timestamp'],
                'heat' => $payload['heat'] ?? null
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'transactions' => $transactions,
        'count' => count($transactions)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
