<?php
/**
 * List Negotiations API
 * GET: Get current user's active negotiations
 */

require_once __DIR__ . '/../../lib/NegotiationManager.php';
require_once __DIR__ . '/../../lib/TeamStorage.php';
require_once __DIR__ . '/../../userData.php';

header('Content-Type: application/json');

$currentUserEmail = getCurrentUserEmail();

if (!$currentUserEmail) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

try {
    $storage = new TeamStorage($currentUserEmail);
    $state = $storage->getState();
    $negManager = new NegotiationManager();
    
    $negotiations = [];
    $activeNegStates = $state['negotiationStates'] ?? [];
    $now = time();
    $recentThreshold = 60; // 60 seconds
    
    foreach ($activeNegStates as $negId => $negState) {
        $status = $negState['status'] ?? 'pending';
        
        // Show if pending OR if finished very recently
        $isFinishedRecently = false;
        $fullNeg = $negManager->getNegotiation($negId);
        
        if ($fullNeg) {
            if ($status !== 'pending') {
                $updatedAt = $fullNeg['updatedAt'] ?? 0;
                if (($now - $updatedAt) < $recentThreshold) {
                    $isFinishedRecently = true;
                }
            }

            if ($status === 'pending' || $isFinishedRecently) {
                // Ensure patience from local state is included
                $fullNeg['patience'] = $negState['patience'] ?? 100;
                $negotiations[] = $fullNeg;
            }
        }
    }

    echo json_encode([
        'success' => true,
        'negotiations' => $negotiations
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
