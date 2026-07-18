<?php
$db = new SQLite3(__DIR__ . '/data/cndq.db');
$res = $db->query("PRAGMA table_info(team_state_cache)");
while($row = $res->fetchArray(SQLITE3_ASSOC)) {
    echo $row['name'] . "\n";
}

