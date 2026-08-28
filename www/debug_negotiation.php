<?php
require_once __DIR__ . '/lib/Database.php';

$db = Database::getInstance();

echo "DEBUG DIRECT NEGOTIATIONS TABLE\n";

$craftyEmail = 'test_mail1@stonybrook.edu';
$rhinoEmail = 'test_mail2@stonybrook.edu';

echo "Crafty: $craftyEmail\n";

// Query negotiations table directly
$negs = $db->query(
    "SELECT * FROM negotiations 
     WHERE (initiator_id = ? OR responder_id = ?)
     AND status = 'pending'",
    [$craftyEmail, $craftyEmail]
);

echo "Found " . count($negs) . " pending negotiations in DB for Crafty Otter.\n";

foreach ($negs as $n) {
    echo "------------------------------------------------\n";
    echo "ID: " . $n['id'] . "\n";
    echo "Initiator: " . $n['initiator_name'] . " (" . $n['initiator_id'] . ")\n";
    echo "Responder: " . $n['responder_name'] . " (" . $n['responder_id'] . ")\n";
    echo "Chemical: " . $n['chemical'] . "\n";
    echo "Type: " . $n['type'] . "\n";
    echo "Last Offer By: " . $n['last_offer_by'] . "\n";
    
    // Check if this is the one
    if (strpos($n['initiator_name'], 'Daring Rhino') !== false || strpos($n['responder_name'], 'Daring Rhino') !== false) {
        echo "!!! THIS IS THE DARING RHINO ONE !!!\n";
    }
}
echo "DEBUG END\n";
