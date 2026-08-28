<?php
require_once 'userData.php';

header('Content-Type: application/json');

// Ensure we only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Read the raw POST data
$input = file_get_contents('php://input');

// Validate JSON
$data = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$filePath = getUserDataFilePath();

// Save the data to the user's JSON file
if (file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT))) {
    echo json_encode(['status' => 'success']);
} else {
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Failed to save data']);
}
