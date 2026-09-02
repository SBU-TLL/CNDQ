<?php
/**
 * Initiate Negotiation API
 * POST: Start a private negotiation with another team
 * Body: { responderId: "team@email.com", chemical: "C", quantity: 100, price: 5.50 }
 */

require_once __DIR__ . '/../../lib/NegotiationManager.php';
require_once __DIR__ . '/../../lib/TeamStorage.php';
require_once __DIR__ . '/../../lib/SessionManager.php';
require_once __DIR__ . '/../../userData.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$currentUserEmail = getCurrentUserEmail();

if (!$currentUserEmail || $currentUserEmail === 'dev_user') {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

// Check if trading is allowed
$sessionManager = new SessionManager();
$sessionState = $sessionManager->getState();

if (!$sessionManager->isTradingAllowed()) {
    http_response_code(403);
    echo json_encode([
        'error' => 'Trading not allowed',
        'message' => 'Market is currently ' . ($sessionState['phase'] ?? 'UNKNOWN') . '.',
        'currentPhase' => ($sessionState['phase'] ?? 'UNKNOWN')
    ]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$responderId = $input['responderId'] ?? null;
$adId = $input['adId'] ?? null;
$chemical = $input['chemical'] ?? null;
$quantity = $input['quantity'] ?? null;
$price = $input['price'] ?? null;
$type = $input['type'] ?? 'buy'; // 'buy' or 'sell' from initiator perspective

if (!$responderId || !$chemical || !$quantity || $price === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

if (!in_array($type, ['buy', 'sell'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid type']);
    exit;
}

if (!in_array($chemical, ['C', 'N', 'D', 'Q'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid chemical']);
    exit;
}

if ($quantity <= 0 || $price < 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid quantity or price']);
    exit;
}

if ($responderId === $currentUserEmail) {
    http_response_code(400);
    echo json_encode(['error' => 'Cannot negotiate with yourself']);
    exit;
}

try {
    // Get team names
    $initiatorStorage = new TeamStorage($currentUserEmail);
    $initiatorProfile = $initiatorStorage->getProfile();

    $responderStorage = new TeamStorage($responderId);
    $responderProfile = $responderStorage->getProfile();

    // Get current session number
    $sessionState = $sessionManager->getState();
    $currentSession = $sessionState['currentSession'];

    $negotiationManager = new NegotiationManager();

    $negotiation = $negotiationManager->createNegotiation(
        $currentUserEmail,
        $initiatorProfile['teamName'] ?? $currentUserEmail,
        $responderId,
        $responderProfile['teamName'] ?? $responderId,
        $chemical,
        [
            'quantity' => $quantity,
            'price' => $price
        ],
        $currentSession,
        $type,
        $adId
    );

    echo json_encode([
        'success' => true,
        'message' => 'Negotiation initiated',
        'negotiation' => $negotiation
    ]);

} catch (Exception $e) {
    // Log the full error for debugging
    error_log("Negotiation initiate error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());

    $message = $e->getMessage();
    $code = 500;

    // Map known exceptions to appropriate HTTP codes
    if (strpos($message, 'already have a pending negotiation') !== false) {
        $code = 409; // Conflict
    } elseif (strpos($message, 'Insufficient inventory') !== false) {
        $code = 400; // Bad Request
    } elseif (strpos($message, 'Cannot negotiate with yourself') !== false) {
        $code = 400;
    }

    http_response_code($code);
    echo json_encode([
        'error' => $message, // Client uses 'error' field for display
        'message' => $message,
        'debug' => $e->getFile() . ':' . $e->getLine()
    ]);
}
