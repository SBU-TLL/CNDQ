<?php
try {
    $db = new SQLite3(__DIR__ . '/data/cndq.db');
    $stmt = $db->prepare("SELECT email FROM teams WHERE name = :name");
    $stmt->bindValue(':name', 'Crafty Otter', SQLITE3_TEXT);
    $result = $stmt->execute();
    
    if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        echo $row['email'];
    } else {
        echo "User not found";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
