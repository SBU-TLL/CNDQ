<?php
/**
 * Admin Transaction Report API
 * GET: Returns all transactions with quality evaluation.
 * Params: ?format=csv (optional) for download
 */

require_once __DIR__ . '/../../../userData.php';
require_once __DIR__ . '/../../../lib/MarketplaceAggregator.php';

// Ensure Admin
if (!isAdmin()) {
    http_response_code(403);
    echo "Access Denied";
    exit;
}

try {
    $aggregator = new MarketplaceAggregator();
    $transactions = $aggregator->getAllTransactions();

    // Enrich/Format Data
    $report = [];
    foreach ($transactions as $txn) {
        $heat = $txn['heat'] ?? [];
        $quality = 'Neutral';
        if (($heat['isHot'] ?? false)) {
            $quality = 'Excellent (Mutually Beneficial)';
        } elseif (($heat['total'] ?? 0) > 0) {
            $quality = 'Good (Net Positive)';
        } elseif (($heat['total'] ?? 0) < 0) {
            $quality = 'Poor (Value Destroyed)';
        }

        $report[] = [
            'Time' => date('Y-m-d H:i:s', $txn['timestamp'] ?? time()),
            'Buyer' => $txn['buyerName'],
            'Seller' => $txn['sellerName'],
            'Chemical' => $txn['chemical'],
            'Quantity' => $txn['quantity'],
            'Price' => ($txn['pricePerGallon'] < 0 ? '-$' : '$') . number_format(abs($txn['pricePerGallon']), 2),
            'Total' => ($txn['totalAmount'] < 0 ? '-$' : '$') . number_format(abs($txn['totalAmount']), 2),
            'Value Created' => (($heat['total'] ?? 0) < 0 ? '-$' : '$') . number_format(abs($heat['total'] ?? 0), 2),
            'Quality' => $quality,
            'Buyer Gain' => (($heat['buyerGain'] ?? 0) < 0 ? '-$' : '$') . number_format(abs($heat['buyerGain'] ?? 0), 2),
            'Seller Gain' => (($heat['sellerGain'] ?? 0) < 0 ? '-$' : '$') . number_format(abs($heat['sellerGain'] ?? 0), 2)
        ];
    }

    // Handle CSV Export
    if (isset($_GET['format']) && $_GET['format'] === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="cndq_transactions_report_' . date('Ymd_His') . '.csv"');

        $fp = fopen('php://output', 'w');
        
        if (!empty($report)) {
            // Header
            fputcsv($fp, array_keys($report[0]), ",", "\"", "\\");
            
            // Rows
            foreach ($report as $row) {
                fputcsv($fp, $row, ",", "\"", "\\");
            }
        } else {
            fputcsv($fp, ['No transactions found'], ",", "\"", "\\");
        }
        
        fclose($fp);
        exit;
    }

    // Default JSON Response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'count' => count($report),
        'transactions' => $report
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
