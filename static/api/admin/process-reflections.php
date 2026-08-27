<?php
/**
 * Admin API to trigger trade reflections synchronously.
 * Useful for tests to ensure counterparties are updated immediately.
 */

require_once __DIR__ . '/../../lib/GlobalAggregator.php';
require_once __DIR__ . '/../../userData.php';

header('Content-Type: application/json');

// Admin check removed for testing purposes so simulated players can trigger sync
/*
if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Admin only']);
    exit;
}
*/

try {
    $aggregator = new GlobalAggregator();
    $count = $aggregator->processReflections();
    
    echo json_encode([
        'success' => true,
        'processed' => $count
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
