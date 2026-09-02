<?php
/**
 * Reports API
 * Generates data for Excel-style reporting:
 * - Financial Summary (Production, Sales, Purchases, Profit)
 * - Transaction History (Log of all trades)
 * - Optimization Reports (Answer & Sensitivity Reports via LP Solver)
 */

require_once __DIR__ . '/../../lib/TeamStorage.php';
require_once __DIR__ . '/../../lib/LPSolver.php';
require_once __DIR__ . '/../../userData.php';

header('Content-Type: application/json');

$currentUserEmail = getCurrentUserEmail();

if (!$currentUserEmail || trim($currentUserEmail) === '') {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

try {
    $storage = new TeamStorage($currentUserEmail);
    $type = $_GET['type'] ?? 'all'; // financials, transactions, optimization, or all

    $response = ['success' => true];

    // 1. Financial Summary
    if ($type === 'all' || $type === 'financials') {
        $transactions = $storage->getTransactions()['transactions'];

        // Production revenue = current optimal profit from LP solver (matches Excel's D2)
        // This updates dynamically as inventory changes from trades
        $inventory = $storage->getInventory();
        $solver = new LPSolver();
        $lpResult = $solver->solve($inventory);
        $productionRevenue = $lpResult['maxProfit'];

        $salesRevenue = 0;
        $purchaseCosts = 0;

        foreach ($transactions as $txn) {
            $amount = $txn['totalPrice'] ?? $txn['totalAmount'] ?? (($txn['quantity'] ?? 0) * ($txn['pricePerGallon'] ?? 0));
            // TradeExecutor stores 'role' (buyer/seller) not sellerId/buyerId
            $role = $txn['role'] ?? null;
            if ($role === 'seller') {
                $salesRevenue += $amount;
            } elseif ($role === 'buyer') {
                $purchaseCosts += $amount;
            }
        }

        $totalProfit = $productionRevenue + $salesRevenue - $purchaseCosts;
        // Adjust for starting funds/baseline if necessary, but this pure cash flow view is usually best.

        $response['financials'] = [
            'productionRevenue' => round($productionRevenue, 2),
            'salesRevenue' => round($salesRevenue, 2),
            'purchaseCosts' => round($purchaseCosts, 2),
            'totalProfit' => round($totalProfit, 2)
        ];
    }

    // 2. Transaction History
    if ($type === 'all' || $type === 'transactions') {
        $rawTransactions = $storage->getTransactions()['transactions'];
        $formattedTransactions = [];

        // Sort by timestamp desc
        usort($rawTransactions, function($a, $b) {
            return ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0);
        });

        foreach ($rawTransactions as $txn) {
            // TradeExecutor stores 'role' (buyer/seller) and 'counterparty'/'counterpartyName'
            $role = $txn['role'] ?? null;
            $isSeller = ($role === 'seller');

            // Use counterpartyName if available, fall back to counterparty ID
            $counterparty = $txn['counterpartyName'] ?? $txn['counterparty'] ?? 'Unknown';

            // Extract heat data if available
            $heat = $txn['heat'] ?? null;
            $heatInfo = null;
            if ($heat) {
                $heatInfo = [
                    'total' => $heat['total'] ?? 0,
                    'isHot' => $heat['isHot'] ?? false,
                    'yourGain' => $isSeller ? ($heat['sellerGain'] ?? 0) : ($heat['buyerGain'] ?? 0)
                ];
            }

            $formattedTransactions[] = [
                'id' => $txn['transactionId'] ?? $txn['id'] ?? uniqid(),
                'type' => $isSeller ? 'Sale' : 'Purchase',
                'chemical' => $txn['chemical'] ?? '?',
                'quantity' => $txn['quantity'] ?? 0,
                'pricePerGallon' => $txn['pricePerGallon'] ?? 0,
                'totalPrice' => $txn['totalPrice'] ?? $txn['totalAmount'] ?? (($txn['quantity'] ?? 0) * ($txn['pricePerGallon'] ?? 0)),
                'counterparty' => $counterparty,
                'timestamp' => $txn['timestamp'] ?? time(),
                'date' => date('Y-m-d H:i:s', floor($txn['timestamp'] ?? time())),
                // Expanded data for detailed view
                'inventoryBefore' => $txn['inventoryBefore'] ?? null,
                'inventoryAfter' => $txn['inventoryAfter'] ?? null,
                'heat' => $heatInfo
            ];
        }

        $response['transactions'] = $formattedTransactions;
    }

    // 3. Optimization Reports (Answer & Sensitivity)
    if ($type === 'all' || $type === 'optimization') {
        $fullState = $storage->getFullState();
        $finalProduction = null;
        
        // Find if there's a final production event
        foreach ($fullState['productions'] ?? [] as $prod) {
            if ($prod['type'] === 'final_production') {
                $finalProduction = $prod;
                break;
            }
        }

        $inventory = $storage->getInventory();
        
        if ($finalProduction) {
            // Use saved final results
            $result = [
                'maxProfit' => $finalProduction['revenue'],
                'deicer' => $finalProduction['deicer'],
                'solvent' => $finalProduction['solvent'],
                'constraints' => $finalProduction['constraints'],
                'shadowPrices' => $finalProduction['shadowPrices'],
                'ranges' => $finalProduction['ranges']
            ];
            
            // For constraints, we need to reconstruct the "Used" and "Available" values
            // Available = consumed + slack
            foreach ($result['constraints'] as $chem => &$data) {
                $consumed = $finalProduction['chemicalsConsumed'][$chem] ?? 0;
                $data['used'] = $consumed;
                $data['available'] = $consumed + ($data['slack'] ?? 0);
            }
            unset($data); // reference cleanup
            
            $isFinal = true;
        } else {
            // Live solve for mid-game view
            $solver = new LPSolver();
            $result = $solver->solve($inventory);
            
            foreach ($result['constraints'] as $chem => &$data) {
                $data['available'] = $inventory[$chem] ?? 0;
                $data['used'] = $data['available'] - ($data['slack'] ?? 0);
            }
            unset($data);
            
            $isFinal = false;
        }

        // --- ANSWER REPORT ---
        
        // 1. Target Cell (Objective)
        $objective = [
            'name' => $isFinal ? 'Final Production Revenue' : 'Total Profit (Projected)',
            'finalValue' => $result['maxProfit']
        ];

        // 2. Adjustable Cells (Decision Variables)
        $variables = [
            [
                'name' => 'Gallons Deicer',
                'finalValue' => round($result['deicer'], 2),
                'objectiveCoef' => 2,
                'type' => 'Production Mix'
            ],
            [
                'name' => 'Gallons Solvent',
                'finalValue' => round($result['solvent'], 2),
                'objectiveCoef' => 3,
                'type' => 'Production Mix'
            ]
        ];

        // 3. Constraints
        $constraints = [];
        foreach ($result['constraints'] as $chem => $data) {
            $slack = $data['slack'] ?? 0;
            $used = $data['used'] ?? 0;
            $available = $data['available'] ?? 0;

            $status = $data['status'] ?? ($slack < 0.001 ? 'Binding' : 'Not Binding');

            $constraints[] = [
                'name' => "Liquid $chem",
                'cellValue' => round($used, 2),
                'formula' => "Used <= Available",
                'status' => $status,
                'slack' => round($slack, 2),
                'used' => round($used, 2),
                'available' => round($available, 2)
            ];
        }

        $answerReport = [
            'objective' => $objective,
            'variables' => $variables,
            'constraints' => $constraints,
            'title' => $isFinal ? 'Final Answer Report' : 'Answer Report (Current Potential)'
        ];

        // --- SENSITIVITY REPORT ---
        
        $shadowPrices = [];

        foreach ($result['shadowPrices'] as $chem => $price) {
            $allowableIncrease = $result['ranges'][$chem]['allowableIncrease'] ?? 'INF';
            $allowableDecrease = $result['ranges'][$chem]['allowableDecrease'] ?? 0;
            $used = $result['constraints'][$chem]['used'] ?? 0;
            $available = $result['constraints'][$chem]['available'] ?? 0;

            if ((is_float($allowableIncrease) && is_infinite($allowableIncrease)) || $allowableIncrease >= 9999) {
                $allowableIncrease = '1E+30';
            }
            if (is_float($allowableDecrease) && is_infinite($allowableDecrease)) {
                $allowableDecrease = '1E+30';
            }

            $shadowPrices[] = [
                'chemical' => $chem,
                'name' => "Liquid $chem",
                'finalValue' => round($used, 2),
                'shadowPrice' => $price,
                'currentInventory' => round($available, 2),
                'constraintRHS' => $available,
                'allowableIncrease' => $allowableIncrease,
                'allowableDecrease' => $allowableDecrease
            ];
        }

        $sensitivityReport = [
            'shadowPrices' => $shadowPrices,
            'constraints' => $shadowPrices,
            'title' => $isFinal ? 'Final Sensitivity Report' : 'Sensitivity Report (Current)'
        ];

        $response['optimization'] = [
            'answerReport' => $answerReport,
            'sensitivityReport' => $sensitivityReport
        ];
    }

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
