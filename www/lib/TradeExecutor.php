<?php
/**
 * TradeExecutor - Refactored for No-M (Filesystem-as-State)
 * 
 * Instead of direct file updates and locking, this class emits discrete events 
 * to both buyer and seller folders. The Aggregator handles the state reduction.
 */

require_once __DIR__ . '/TeamStorage.php';

class TradeExecutor {

    /**
     * Execute a trade - Refactored for No-M sovereignty.
     * Only writes to the directory of the team initiating the execution.
     */
    public function executeTrade($sellerId, $buyerId, $chemical, $quantity, $pricePerGallon, $offerId = null, $actingTeamId = null) {
        // If actingTeamId not provided, assume buyer (most common case: accepting an offer)
        if (!$actingTeamId) $actingTeamId = $buyerId;
        
        error_log("TradeExecutor: executeTrade called. Seller: $sellerId, Buyer: $buyerId, Actor: $actingTeamId, Chem: $chemical, Qty: $quantity");

        $isBuyerActing = ($actingTeamId === $buyerId);
        $counterpartyId = $isBuyerActing ? $sellerId : $buyerId;
        error_log("TradeExecutor: isBuyerActing: " . ($isBuyerActing ? 'YES' : 'NO') . ", Counterparty: $counterpartyId");

        if ($sellerId === $buyerId) return ['success' => false, 'message' => 'Cannot trade with yourself'];
        
        try {
            $actorStorage = new TeamStorage($actingTeamId);
            $counterpartyStorage = new TeamStorage($counterpartyId);
            
            // Validate Actor
            $totalCost = $quantity * $pricePerGallon;
            
            if ($isBuyerActing) {
                // Buyer Actor: needs funds - CHECK REMOVED (Infinite Capital)

                // Seller Counterparty: needs inventory
                $inv = $counterpartyStorage->getInventory()[$chemical] ?? 0;
                if ($inv < $quantity) {
                    throw new Exception("The seller no longer has enough inventory (needs {$quantity}, has {$inv}). They may have completed other trades.");
                }
            } else {
                // Seller Actor: needs inventory
                $inv = $actorStorage->getInventory()[$chemical] ?? 0;
                if ($inv < $quantity) {
                    throw new Exception("Insufficient inventory: you need {$quantity} gallons but only have {$inv}. Your inventory may have changed from other completed trades.");
                }
                // Buyer Counterparty: needs funds - CHECK REMOVED (Infinite Capital)
            }

            // Debug: Log pre-trade state
            $preInventory = $actorStorage->getInventory();
            $preFunds = $actorStorage->getProfile()['currentFunds'] ?? 0;
            error_log("TradeExecutor: Pre-Trade Inventory: " . json_encode($preInventory) . ", Funds: $preFunds");

            $totalCost = $quantity * $pricePerGallon;
            $transactionId = 'trade_' . time() . '_' . bin2hex(random_bytes(4));

            // Calculate Trade Heat (Mutually Beneficial vs Detrimental)
            $sellerStorage = new TeamStorage($sellerId);
            $buyerStorage = new TeamStorage($buyerId);
            $sellerShadow = $sellerStorage->getShadowPrices()[$chemical] ?? 0;
            $buyerShadow = $buyerStorage->getShadowPrices()[$chemical] ?? 0;
            
            $sellerGain = $pricePerGallon - $sellerShadow;
            $buyerGain = $buyerShadow - $pricePerGallon;
            $totalHeat = ($sellerGain + $buyerGain) * $quantity;
            $isHot = ($sellerGain > 0 && $buyerGain > 0);

            // Actor-specific events
            $counterpartyName = $counterpartyStorage->getTeamName();
            $microtime = microtime(true);

            // Update BOTH parties synchronously to ensure inventory conservation
            // (The eventual consistency model caused inventory duplication bugs)
            $actorName = $actorStorage->getTeamName();

            if ($isBuyerActing) {
                // === BUYER (Actor) ===
                $buyerInvBefore = $actorStorage->getInventory()[$chemical] ?? 0;
                $actorStorage->adjustChemical($chemical, $quantity);
                $buyerInvAfter = $actorStorage->getInventory()[$chemical] ?? 0;
                $actorStorage->updateFunds(-$totalCost);
                $actorStorage->addTransaction([
                    'transactionId' => $transactionId,
                    'role' => 'buyer',
                    'chemical' => $chemical,
                    'quantity' => $quantity,
                    'pricePerGallon' => $pricePerGallon,
                    'totalAmount' => $totalCost,
                    'counterparty' => $sellerId,
                    'counterpartyName' => $counterpartyName,
                    'offerId' => $offerId,
                    'timestamp' => $microtime,
                    'status' => 'accepted',
                    'inventoryBefore' => $buyerInvBefore,
                    'inventoryAfter' => $buyerInvAfter,
                    'heat' => [
                        'total' => $totalHeat,
                        'isHot' => $isHot,
                        'sellerGain' => $sellerGain,
                        'buyerGain' => $buyerGain
                    ]
                ]);

                // === SELLER (Counterparty) - Update immediately ===
                $sellerInvBefore = $counterpartyStorage->getInventory()[$chemical] ?? 0;
                $counterpartyStorage->adjustChemical($chemical, -$quantity);
                $sellerInvAfter = $counterpartyStorage->getInventory()[$chemical] ?? 0;
                $counterpartyStorage->updateFunds($totalCost);
                $counterpartyStorage->addTransaction([
                    'transactionId' => $transactionId,
                    'role' => 'seller',
                    'chemical' => $chemical,
                    'quantity' => $quantity,
                    'pricePerGallon' => $pricePerGallon,
                    'totalAmount' => $totalCost,
                    'counterparty' => $buyerId,
                    'counterpartyName' => $actorName,
                    'offerId' => $offerId,
                    'timestamp' => $microtime,
                    'status' => 'accepted',
                    'inventoryBefore' => $sellerInvBefore,
                    'inventoryAfter' => $sellerInvAfter,
                    'heat' => [
                        'total' => $totalHeat,
                        'isHot' => $isHot,
                        'sellerGain' => $sellerGain,
                        'buyerGain' => $buyerGain
                    ]
                ]);
            } else {
                // === SELLER (Actor) ===
                $sellerInvBefore = $actorStorage->getInventory()[$chemical] ?? 0;
                $actorStorage->adjustChemical($chemical, -$quantity);
                $sellerInvAfter = $actorStorage->getInventory()[$chemical] ?? 0;
                $actorStorage->updateFunds($totalCost);
                $actorStorage->addTransaction([
                    'transactionId' => $transactionId,
                    'role' => 'seller',
                    'chemical' => $chemical,
                    'quantity' => $quantity,
                    'pricePerGallon' => $pricePerGallon,
                    'totalAmount' => $totalCost,
                    'counterparty' => $buyerId,
                    'counterpartyName' => $counterpartyName,
                    'offerId' => $offerId,
                    'timestamp' => $microtime,
                    'status' => 'accepted',
                    'inventoryBefore' => $sellerInvBefore,
                    'inventoryAfter' => $sellerInvAfter,
                    'heat' => [
                        'total' => $totalHeat,
                        'isHot' => $isHot,
                        'sellerGain' => $sellerGain,
                        'buyerGain' => $buyerGain
                    ]
                ]);

                // === BUYER (Counterparty) - Update immediately ===
                $buyerInvBefore = $counterpartyStorage->getInventory()[$chemical] ?? 0;
                $counterpartyStorage->adjustChemical($chemical, $quantity);
                $buyerInvAfter = $counterpartyStorage->getInventory()[$chemical] ?? 0;
                $counterpartyStorage->updateFunds(-$totalCost);
                $counterpartyStorage->addTransaction([
                    'transactionId' => $transactionId,
                    'role' => 'buyer',
                    'chemical' => $chemical,
                    'quantity' => $quantity,
                    'pricePerGallon' => $pricePerGallon,
                    'totalAmount' => $totalCost,
                    'counterparty' => $sellerId,
                    'counterpartyName' => $actorName,
                    'offerId' => $offerId,
                    'timestamp' => $microtime,
                    'status' => 'accepted',
                    'inventoryBefore' => $buyerInvBefore,
                    'inventoryAfter' => $buyerInvAfter,
                    'heat' => [
                        'total' => $totalHeat,
                        'isHot' => $isHot,
                        'sellerGain' => $sellerGain,
                        'buyerGain' => $buyerGain
                    ]
                ]);
            }
            
            // GLOBAL MARKETPLACE EVENT: Record the trade for all to see
            // This enables global toasts for trades we're NOT part of
            // Includes complete inventory data for both parties for audit/reporting
            // $microtime is already defined above
            $globalEvent = [
                'transactionId' => $transactionId,
                'chemical' => $chemical,
                'quantity' => $quantity,
                'pricePerGallon' => $pricePerGallon,
                'totalAmount' => $totalCost,
                'sellerId' => $sellerId,
                'sellerName' => $sellerStorage->getTeamName(),
                'sellerInvBefore' => $sellerInvBefore,
                'sellerInvAfter' => $sellerInvAfter,
                'buyerId' => $buyerId,
                'buyerName' => $buyerStorage->getTeamName(),
                'buyerInvBefore' => $buyerInvBefore,
                'buyerInvAfter' => $buyerInvAfter,
                'timestamp' => $microtime,
                'heat' => [
                    'total' => $totalHeat,
                    'isHot' => $isHot,
                    'sellerGain' => $sellerGain,
                    'buyerGain' => $buyerGain
                ]
            ];
            
            $db = Database::getInstance();
            $db->insert(
                'INSERT INTO marketplace_events (team_email, team_name, event_type, payload, timestamp)
                 VALUES (?, ?, ?, ?, ?)',
                [
                    $actingTeamId,
                    $actorStorage->getTeamName(),
                    'add_transaction',
                    json_encode($globalEvent),
                    $microtime
                ]
            );

            // Debug: Log post-trade state
            // Force state refresh to verify write
            $postInventory = $actorStorage->getInventory();
            $postFunds = $actorStorage->getProfile()['currentFunds'] ?? 0;
            error_log("TradeExecutor: Post-Trade Inventory: " . json_encode($postInventory) . ", Funds: $postFunds");

            // ... cleanup ...

            return [
                'success' => true,
                'message' => 'Trade execution recorded. Counterparty update pending.',
                'transactionId' => $transactionId,
                'heat' => [
                    'total' => $totalHeat,
                    'isHot' => $isHot,
                    'sellerGain' => $sellerGain,
                    'buyerGain' => $buyerGain
                ]
            ];

        } catch (Exception $e) {
            error_log("TradeExecutor: Exception: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Minimal validation for the UI
     */
    public function validateTrade($sellerId, $buyerId, $chemical, $quantity) {
        $seller = new TeamStorage($sellerId);

        $hasInventory = ($seller->getInventory()[$chemical] ?? 0) >= $quantity;

        // Funds check removed for Infinite Capital model
        
                
        
                if (!$hasInventory) return ['valid' => false, 'reason' => 'Insufficient inventory'];
        
        
        
                return ['valid' => true];
        
            }
        
        }
        
        