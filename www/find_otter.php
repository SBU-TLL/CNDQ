<?php
try {
    $db = new SQLite3(__DIR__ . '/data/cndq.db');
    $result = $db->query("SELECT team_email, state FROM team_state_cache");
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $state = json_decode($row['state'], true);
        if (isset($state['profile']['teamName']) && stripos($state['profile']['teamName'], 'Otter') !== false) {
            echo "Team: " . $state['profile']['teamName'] . " | Email: " . $row['team_email'] . "\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}