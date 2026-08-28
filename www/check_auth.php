<?php
require_once 'userData.php';

header('Content-Type: text/plain');

echo "✅ Authenticated as: " . getCurrentUserEmail() . "\n";
echo "----------------------------------------\n";
echo "Debug Information:\n";
echo "Environment: " . ($_SERVER['environment'] ?? 'production') . "\n";
echo "Cookie (mock_mail): " . ($_COOKIE['mock_mail'] ?? 'not set') . "\n";
echo "Server (mail): " . ($_SERVER['mail'] ?? 'not set') . "\n";
echo "Server (email): " . ($_SERVER['email'] ?? 'not set') . "\n";

