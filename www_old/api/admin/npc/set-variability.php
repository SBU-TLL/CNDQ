<?php
/**
 * Set Global NPC Variability API
 * POST: Set global variability for all NPCs
 * Body: { variability: 0.0 to 1.0 }
 */

require_once __DIR__ . '/../../../lib/NPCStrategyFactory.php';
require_once __DIR__ . '/../../../lib/Database.php';
require_once __DIR__ . '/../../../userData.php';

header('Content-Type: application/json');

if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Admin privileges required']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $variability = isset($input['variability']) ? (float)$input['variability'] : 0.5;

    $db = Database::getInstance();
    NPCStrategyFactory::saveVariabilityToConfig($db, $variability);

    echo json_encode([
        'success' => true,
        'message' => 'Global NPC variability set to ' . $variability,
        'variability' => $variability
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
