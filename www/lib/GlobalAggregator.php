<?php
/**
 * GlobalAggregator - SQLite-based implementation for trade reflections.
 * This is the ACTIVE reflection logic used by the SessionManager.
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/TeamStorage.php';

class GlobalAggregator {
    private $db;

    public function __construct() {
        $this->db = \Database::getInstance();
    }

    /**
     * Process pending reflections (cross-team events)
     */
    public function processReflections() {
        // Get all teams that have ever had an event
        $teams = $this->db->query('SELECT DISTINCT team_email FROM team_events');
        $processedCount = 0;

        foreach ($teams as $row) {
            $teamId = $row['team_email'];
            if (!$teamId || $teamId === 'system') continue;

            try {
                $storage = new \TeamStorage($teamId);
                $state = $storage->getState();

                $transactions = $state['transactions'] ?? [];
                foreach ($transactions as $txn) {
                    if (!empty($txn['isPendingReflection'])) {
                        $this->reflectTransaction($teamId, $txn);
                        $processedCount++;
                    }
                }
            } catch (\Exception $e) {
                error_log("GlobalAggregator: Failed to process reflections for $teamId: " . $e->getMessage());
            }
        }
        return $processedCount;
    }

    /**
     * Reflect a transaction to the counterparty
     */
    private function reflectTransaction($actorId, $txn) {
        $counterpartyId = $txn['counterparty'];
        $transactionId = $txn['transactionId'];

        $counterpartyStorage = new \TeamStorage($counterpartyId);
        $actorStorage = new \TeamStorage($actorId);

        // 1. Verify it is STILL pending reflection on the actor (prevents race conditions)
        $actorState = $actorStorage->getState();
        $isStillPending = false;
        foreach ($actorState['transactions'] ?? [] as $actorTxn) {
            if (($actorTxn['transactionId'] ?? '') === $transactionId && !empty($actorTxn['isPendingReflection'])) {
                $isStillPending = true;
                break;
            }
        }

        if (!$isStillPending) {
            error_log("GlobalAggregator: Transaction $transactionId is no longer pending on $actorId. Skipping.");
            return;
        }

        // 2. Check if already reflected to counterparty to avoid duplicate inventory/funds adjustments
        $counterpartyState = $counterpartyStorage->getState();
        $alreadyReflected = false;
        foreach ($counterpartyState['transactions'] ?? [] as $cpTxn) {
            if (($cpTxn['transactionId'] ?? '') === $transactionId) {
                $alreadyReflected = true;
                break;
            }
        }

        if ($alreadyReflected) {
            // Already reflected - just mark as complete on actor side and return
            error_log("GlobalAggregator: Transaction $transactionId already exists on counterparty $counterpartyId. Marking as reflected without duplicate adjustment.");
            $actorStorage->emitEvent('mark_reflected', ['transactionId' => $transactionId]);
            return;
        }

        $role = ($txn['role'] === 'buyer') ? 'seller' : 'buyer';
        $quantity = $txn['quantity'];
        $chemical = $txn['chemical'];
        $totalAmount = $txn['totalAmount']; // Amount is always positive here

        // Get inventory BEFORE adjustment for transaction history
        $invBefore = $counterpartyStorage->getInventory()[$chemical] ?? 0;

        // Note: adjustChemical and updateFunds accept negative values for deductions
        if ($role === 'seller') {
            // Actor was buyer, so counterparty is seller
            // Seller loses chemicals, gains money
            $counterpartyStorage->adjustChemical($chemical, -$quantity);
            $counterpartyStorage->updateFunds($totalAmount);
        } else {
            // Actor was seller, so counterparty is buyer
            // Buyer gains chemicals, loses money
            $counterpartyStorage->adjustChemical($chemical, $quantity);
            $counterpartyStorage->updateFunds(-$totalAmount);
        }

        // Get inventory AFTER adjustment for transaction history
        $invAfter = $counterpartyStorage->getInventory()[$chemical] ?? 0;

        // Get actor's team name for counterpartyName field
        $actorTeamName = $actorStorage->getTeamName();

        $counterpartyStorage->addTransaction([
            'transactionId' => $transactionId,
            'role' => $role,
            'chemical' => $chemical,
            'quantity' => $quantity,
            'pricePerGallon' => $txn['pricePerGallon'],
            'totalAmount' => $totalAmount,
            'counterparty' => $actorId,
            'counterpartyName' => $actorTeamName,
            'offerId' => $txn['offerId'] ?? null,
            'timestamp' => $txn['timestamp'] ?? time(),
            'status' => 'accepted',
            'inventoryBefore' => $invBefore,
            'inventoryAfter' => $invAfter,
            'isReflection' => true,
            'heat' => $txn['heat'] ?? null
        ]);

        $actorName = $actorStorage->getTeamName();
        $counterpartyName = $counterpartyStorage->getTeamName();

        // Add notification to counterparty
        $counterpartyStorage->addNotification([
            'type' => 'trade_completed',
            'message' => ($role === 'seller' ? "Sold" : "Bought") . " $quantity gallons of $chemical " . ($role === 'seller' ? "to" : "from") . " $actorName for " . ($totalAmount < 0 ? '-$' : '$') . number_format(abs($totalAmount), 2)
        ]);

        // Add notification to actor (since TradeExecutor no longer does this)
        $actorRole = $txn['role']; // Actor's original role (opposite of counterparty role)
        $actorStorage->addNotification([
            'type' => 'trade_completed',
            'message' => ($actorRole === 'seller' ? "Sold" : "Bought") . " $quantity gallons of $chemical " . ($actorRole === 'seller' ? "to" : "from") . " $counterpartyName for " . ($totalAmount < 0 ? '-$' : '$') . number_format(abs($totalAmount), 2)
        ]);

        // 3. Mark as reflected on actor
        $actorStorage->emitEvent('mark_reflected', ['transactionId' => $transactionId]);
        
        error_log("GlobalAggregator: Reflected transaction $transactionId from $actorId to $counterpartyId");
    }
}
