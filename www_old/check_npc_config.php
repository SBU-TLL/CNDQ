<?php
require_once __DIR__ . '/lib/Database.php';
$db = Database::getInstance();
$row = $db->queryOne('SELECT value FROM config WHERE key = ?', ['npc_config']);
if ($row) {
    echo $row['value'] . "\n";
} else {
    echo "No NPC config found\n";
}

