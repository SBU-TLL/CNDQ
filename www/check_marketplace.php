<?php
require_once __DIR__ . '/lib/Database.php';
$db = Database::getInstance();
$row = $db->queryOne('SELECT * FROM marketplace_snapshot WHERE id = 1');
if ($row) {
    echo "ADS: " . $row['ads'] . "\n";
    echo "OFFERS: " . $row['offers'] . "\n";
    echo "BUY ORDERS: " . $row['buy_orders'] . "\n";
} else {
    echo "No marketplace snapshot found\n";
}

