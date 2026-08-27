<?php
/**
 * Notifications API
 * GET: List notifications
 * POST: Mark notifications as read
 */

require_once __DIR__ . '/../../lib/TeamStorage.php';
require_once __DIR__ . '/../../userData.php';

header('Content-Type: application/json');

$currentUserEmail = getCurrentUserEmail();

if (!$currentUserEmail || $currentUserEmail === 'dev_user') {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

try {
    $storage = new TeamStorage($currentUserEmail);

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $notifData = $storage->getNotifications();
        $notifications = $notifData['notifications'] ?? [];

        // Count unread
        $unreadCount = count(array_filter($notifications, function($n) {
            return !($n['read'] ?? false);
        }));

        // Sort by timestamp (newest first)
        usort($notifications, function($a, $b) {
            return ($b['timestamp'] ?? 0) - ($a['timestamp'] ?? 0);
        });

        echo json_encode([
            'success' => true,
            'notifications' => $notifications,
            'unreadCount' => $unreadCount
        ]);

    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $notificationIds = $input['notificationIds'] ?? null;

        // Mark as read
        $storage->markNotificationsRead($notificationIds);

        echo json_encode([
            'success' => true,
            'message' => 'Notifications marked as read'
        ]);

    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
