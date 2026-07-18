<?php
require_once __DIR__ . '/lib/Database.php';
$db = Database::getInstance();

$accepted = $db->query('SELECT COUNT(*) as count FROM negotiations WHERE status = "accepted"')[0]['count'];
$pending = $db->query('SELECT COUNT(*) as count FROM negotiations WHERE status = "pending"')[0]['count'];
$rejected = $db->query('SELECT COUNT(*) as count FROM negotiations WHERE status = "rejected"')[0]['count'];

echo "Negotiations Status:\n";
echo "Accepted: $accepted\n";
echo "Pending: $pending\n";
echo "Rejected: $rejected\n";

$negotiations = $db->query('SELECT * FROM negotiations ORDER BY created_at DESC LIMIT 10');
if ($negotiations) {
    echo "\nRecent Negotiations:\n";
    foreach ($negotiations as $neg) {
        echo "ID: {$neg['id']}, Status: {$neg['status']}, Chem: {$neg['chemical']}, Initiator: {$neg['initiator_name']}, Responder: {$neg['responder_name']}\n";
    }
}