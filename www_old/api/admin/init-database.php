<?php
/**
 * Admin Database Initialization Endpoint
 *
 * Initializes the database schema (admin only)
 * This endpoint allows the web server (apache) to initialize the DB with proper permissions
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../userData.php';
require_once __DIR__ . '/../../lib/Database.php';

// ADMIN ONLY
if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Admin privileges required']);
    exit;
}

try {
    $db = Database::getInstance();

    // Check if tables exist
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");

    if (empty($tables)) {
        // No tables - apply schema
        $schemaFile = __DIR__ . '/../../lib/schema.sql';

        if (!file_exists($schemaFile)) {
            throw new Exception("Schema file not found: $schemaFile");
        }

        $schema = file_get_contents($schemaFile);
        $db->getPdo()->exec($schema);

        // Re-check tables
        $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");

        echo json_encode([
            'success' => true,
            'message' => 'Database schema initialized successfully',
            'tables_created' => count($tables),
            'tables' => array_column($tables, 'name')
        ], JSON_PRETTY_PRINT);
    } else {
        // Tables already exist
        echo json_encode([
            'success' => true,
            'message' => 'Database already initialized',
            'tables_count' => count($tables),
            'tables' => array_column($tables, 'name')
        ], JSON_PRETTY_PRINT);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database initialization failed',
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
