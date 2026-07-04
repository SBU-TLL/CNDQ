<?php
// Configuration for Google Sheets integration
$sheetId     = getenv('CNDQ_SHEET_ID')   ?: "1YvSAxbFty76hR1_mnKVzuEnEYc33xuaHvfW6Tsr2rso";
$sheetName   = getenv('CNDQ_SHEET_NAME') ?: "Groups";
// Google service-account credentials: runtime env override (mounted secret) with
// the in-repo path as the default. The file itself is never committed/baked.
$credentials = getenv('GOOGLE_APPLICATION_CREDENTIALS') ?: (__DIR__ . "/credentials.json");
// $headerRange= $spreadsheet->getRange("$sheetName!A1:1");
// $header        = preg_split("/,/",trim ($headerRange));
?>
