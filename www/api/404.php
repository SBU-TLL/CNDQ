<?php
/**
 * JSON 404 Handler
 * Returns a clean JSON object instead of Herd's default HTML 404
 */
header('Content-Type: application/json');
http_response_code(404);

echo json_encode([
    'success' => false,
    'error' => '404 Not Found',
    'message' => 'The requested API endpoint does not exist.',
    'request_uri' => $_SERVER['REQUEST_URI'],
    'hint' => 'Verify the API endpoint path is correct',
    'server' => 'Herd/Nginx (JSON Fallback)'
], JSON_PRETTY_PRINT);
