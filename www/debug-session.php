<?php
require_once __DIR__ . '/lib/SystemStorage.php';

$storage = new SystemStorage();
$data = $storage->getSystemState();

echo json_encode([
    'timeRemaining' => $data['timeRemaining'] ?? 'NOT SET',
    'tradingDuration' => $data['tradingDuration'] ?? 'NOT SET',
    'gameStopped' => $data['gameStopped'] ?? 'NOT SET',
    'gameFinished' => $data['gameFinished'] ?? 'NOT SET',
    'lastTick' => $data['lastTick'] ?? 'NOT SET',
    'npcLastRun' => $data['npcLastRun'] ?? 'NOT SET'
], JSON_PRETTY_PRINT);
