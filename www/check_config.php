<?php
try {
    $db = new SQLite3(__DIR__ . '/data/cndq.db');
    $result = $db->query("SELECT * FROM config");
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        print_r($row);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
