<?php
try {
    $db = new SQLite3(__DIR__ . '/data/cndq.db');
    $result = $db->query("SELECT name FROM sqlite_master WHERE type='table';");
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        echo $row['name'] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

