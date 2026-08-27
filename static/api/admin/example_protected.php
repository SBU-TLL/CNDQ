<?php
/**
 * Example Protected Admin Endpoint
 *
 * Shows how to use AdminAuth to protect admin-only API endpoints
 */

require_once __DIR__ . '/../../lib/AdminAuth.php';
require_once __DIR__ . '/../../userData.php';

header('Content-Type: application/json');

// Get current user's email (from session/auth)
$userEmail = $_SESSION['user_email'] ?? null;

// Check admin access
$adminAuth = new AdminAuth();

if (!$adminAuth->isAdmin($userEmail)) {
    // User is not an admin - deny access
    AdminAuth::sendForbiddenResponse();
    // Script exits here
}

// If we get here, user is an admin - proceed with admin operations

// Example: Get sensitive admin data
$result = [
    'success' => true,
    'message' => 'Admin access granted',
    'data' => [
        'totalAdmins' => $adminAuth->getAdminCount(),
        'requestedBy' => $userEmail
    ]
];

echo json_encode($result, JSON_PRETTY_PRINT);
