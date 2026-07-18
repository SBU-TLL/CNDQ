<?php
/**
 * Negotiation Manager - SQLite-based negotiation storage
 *
 * Handles private negotiations between teams using database storage.
 * Migrated from file-based to SQLite for production scalability.
 */

require_once __DIR__ . '/Database.php';

class NegotiationManager {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Get all active negotiations for a team
     */
    public function getTeamNegotiations($teamEmail) {
        $negotiations = $this->db->query(
            'SELECT n.*, COUNT(o.id) as offer_count
             FROM negotiations n
             LEFT JOIN negotiation_offers o ON n.id = o.negotiation_id
             WHERE (n.initiator_id = ? OR n.responder_id = ?)
               AND n.status = ?
             GROUP BY n.id
             ORDER BY n.updated_at DESC',
            [$teamEmail, $teamEmail, 'pending']
        );

        $mappedNegotiations = [];
        foreach ($negotiations as $neg) {
            $neg['offers'] = $this->getNegotiationOffers($neg['id']);
            $mappedNegotiations[] = $this->mapNegotiation($neg);
        }

        return $mappedNegotiations;
    }

    /**
     * Get offers for a negotiation
     */
    private function getNegotiationOffers($negotiationId) {
        return $this->db->query(
            'SELECT from_team_id, from_team_name, quantity, price, heat_is_hot, heat_total, created_at
             FROM negotiation_offers
             WHERE negotiation_id = ?
             ORDER BY created_at ASC',
            [$negotiationId]
        );
    }

    /**
     * Helper to check if team has enough inventory
     */
    private function checkInventory($teamId, $chemical, $quantity) {
        require_once __DIR__ . '/TeamStorage.php';
        $storage = new TeamStorage($teamId);
        $inventory = $storage->getInventory();
        $current = $inventory[$chemical] ?? 0;
        
        if ($current < $quantity) {
            throw new Exception("Insufficient inventory. You have {$current} of Chemical {$chemical}, but tried to offer {$quantity}.");
        }
    }

    /**
     * Create new negotiation
     */
    public function createNegotiation($initiatorId, $initiatorName, $responderId, $responderName, $chemical, $initialOffer, $sessionNumber = null, $type = 'buy', $adId = null) {
        // CHOPSTICK CHECK: Prevent duplicate pending negotiations for the same chemical between same parties
        $existingNegotiation = $this->db->queryOne(
            'SELECT id FROM negotiations
             WHERE ((initiator_id = ? AND responder_id = ?) OR (initiator_id = ? AND responder_id = ?))
               AND chemical = ?
               AND status = ?',
            [$initiatorId, $responderId, $responderId, $initiatorId, $chemical, 'pending']
        );

        if ($existingNegotiation) {
            throw new Exception("You already have a pending negotiation for $chemical with this team");
        }

        // Validation: If selling, check inventory
        if ($type === 'sell') {
            $this->checkInventory($initiatorId, $chemical, $initialOffer['quantity']);
        }

        $negotiationId = 'neg_' . time() . '_' . bin2hex(random_bytes(4));

        $initiatorName = $initiatorName ?: $initiatorId;
        $responderName = $responderName ?: $responderId;

        // Calculate heat info
        $heatIsHot = 0;
        $heatTotal = 0;

        try {
            require_once __DIR__ . '/TeamStorage.php';
            $iStorage = new TeamStorage($initiatorId);
            $rStorage = new TeamStorage($responderId);
            $iShadow = $iStorage->getShadowPrices()[$chemical] ?? 0;
            $rShadow = $rStorage->getShadowPrices()[$chemical] ?? 0;

            // Determine who is buyer and who is seller
            $iIsBuyer = ($type === 'buy');
            $iGain = $iIsBuyer ? ($iShadow - $initialOffer['price']) : ($initialOffer['price'] - $iShadow);
            $rGain = $iIsBuyer ? ($initialOffer['price'] - $rShadow) : ($rShadow - $initialOffer['price']);

            $heatIsHot = ($iGain > 0 && $rGain > 0) ? 1 : 0;
            $heatTotal = ($iGain + $rGain) * $initialOffer['quantity'];
        } catch (Exception $e) {}

        // Start transaction
        $this->db->beginTransaction();
        try {
            // Insert negotiation
            $this->db->insert(
                'INSERT INTO negotiations
                 (id, chemical, type, initiator_id, initiator_name, responder_id, responder_name,
                  session_number, status, last_offer_by, ad_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $negotiationId,
                    $chemical,
                    $type,
                    $initiatorId,
                    $initiatorName,
                    $responderId,
                    $responderName,
                    $sessionNumber,
                    'pending',
                    $initiatorId,
                    $adId
                ]
            );

            // Insert first offer
            $this->db->insert(
                'INSERT INTO negotiation_offers
                 (negotiation_id, from_team_id, from_team_name, quantity, price, heat_is_hot, heat_total)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [
                    $negotiationId,
                    $initiatorId,
                    $initiatorName,
                    $initialOffer['quantity'],
                    $initialOffer['price'],
                    $heatIsHot,
                    $heatTotal
                ]
            );

            // If this negotiation is linked to a listing, remove it immediately
            // (prevents other teams from responding to the same listing)
            if ($adId) {
                try {
                    require_once __DIR__ . '/ListingManager.php';
                    // The listing belongs to the responder (they posted the buy request)
                    $listingManager = new ListingManager($responderId);

                    // Check if listing still exists before trying to remove
                    $existingListings = $listingManager->getListings();
                    $listingExists = false;
                    foreach ($existingListings['ads'] ?? [] as $ad) {
                        if ($ad['id'] === $adId) {
                            $listingExists = true;
                            break;
                        }
                    }

                    if ($listingExists) {
                        $listingManager->removeListing($adId);
                        error_log("NegotiationManager: Removed listing {$adId} after negotiation initiation.");
                    } else {
                        error_log("NegotiationManager: Listing {$adId} already removed (race condition).");
                    }
                } catch (Exception $e) {
                    error_log("NegotiationManager: Failed to remove listing {$adId}: " . $e->getMessage());
                    // Don't throw - listing removal failure shouldn't block negotiation creation
                }
            }

            // Emit events to both parties
            try {
                require_once __DIR__ . '/TeamStorage.php';
                $iStorage = new TeamStorage($initiatorId);
                $rStorage = new TeamStorage($responderId);

                $iStorage->emitEvent('initiate_negotiation', [
                    'negotiationId' => $negotiationId,
                    'chemical' => $chemical,
                    'counterparty' => $responderId,
                    'role' => 'initiator'
                ]);
                $rStorage->emitEvent('initiate_negotiation', [
                    'negotiationId' => $negotiationId,
                    'chemical' => $chemical,
                    'counterparty' => $initiatorId,
                    'role' => 'responder'
                ]);

                // Add notifications
                $iStorage->addNotification([
                    'type' => 'negotiation_started',
                    'message' => "Started a negotiation with $responderName for Chemical $chemical."
                ]);
                $rStorage->addNotification([
                    'type' => 'negotiation_started',
                    'message' => "$initiatorName started a negotiation with you for Chemical $chemical."
                ]);
            } catch (Exception $e) {
                error_log("NegotiationManager: Failed to emit events: " . $e->getMessage());
            }

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }

        return $this->getNegotiation($negotiationId);
    }

    /**
     * Get negotiation by ID
     */
    public function getNegotiation($negotiationId) {
        $negotiation = $this->db->queryOne(
            'SELECT * FROM negotiations WHERE id = ?',
            [$negotiationId]
        );

        if (!$negotiation) {
            return null;
        }

        // Fetch offers
        $negotiation['offers'] = $this->getNegotiationOffers($negotiationId);

        return $this->mapNegotiation($negotiation);
    }

    /**
     * Add counter-offer to negotiation
     */
    public function addCounterOffer($negotiationId, $fromTeamId, $fromTeamName, $quantity, $price) {
        $negotiation = $this->getNegotiation($negotiationId);

        if (!$negotiation) {
            throw new Exception('Negotiation not found');
        }

        // Verify team is part of this negotiation
        if ($fromTeamId !== $negotiation['initiatorId'] && $fromTeamId !== $negotiation['responderId']) {
            throw new Exception('Unauthorized');
        }

        // Verify it's their turn (can't counter your own offer)
        if ($negotiation['lastOfferBy'] === $fromTeamId) {
            throw new Exception('Wait for other team to respond');
        }

        // Validation: If selling, check inventory
        // If type='buy', Initiator is Buyer, Responder is Seller.
        // If type='sell', Initiator is Seller, Responder is Buyer.
        $isSeller = ($negotiation['type'] === 'buy' && $fromTeamId === $negotiation['responderId']) ||
                    ($negotiation['type'] === 'sell' && $fromTeamId === $negotiation['initiatorId']);

        if ($isSeller) {
            try {
                $this->checkInventory($fromTeamId, $negotiation['chemical'], $quantity);
            } catch (Exception $e) {
                // Auto-reject the negotiation with the inventory error as reason
                $this->rejectNegotiation($negotiationId, 'system', $e->getMessage());
                throw new Exception("Trade invalidated: " . $e->getMessage());
            }
        }

        // Calculate heat info
        $heatIsHot = 0;
        $heatTotal = 0;

        try {
            require_once __DIR__ . '/TeamStorage.php';
            $iStorage = new TeamStorage($negotiation['initiatorId']);
            $rStorage = new TeamStorage($negotiation['responderId']);
            $iShadow = $iStorage->getShadowPrices()[$negotiation['chemical']] ?? 0;
            $rShadow = $rStorage->getShadowPrices()[$negotiation['chemical']] ?? 0;

            $iIsBuyer = ($negotiation['type'] === 'buy');
            $iGain = $iIsBuyer ? ($iShadow - $price) : ($price - $iShadow);
            $rGain = $iIsBuyer ? ($price - $rShadow) : ($rShadow - $price);

            $heatIsHot = ($iGain > 0 && $rGain > 0) ? 1 : 0;
            $heatTotal = ($iGain + $rGain) * $quantity;
        } catch (Exception $e) {}

        // Start transaction
        $this->db->beginTransaction();
        try {
            // Insert offer
            $this->db->insert(
                'INSERT INTO negotiation_offers
                 (negotiation_id, from_team_id, from_team_name, quantity, price, heat_is_hot, heat_total)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [
                    $negotiationId,
                    $fromTeamId,
                    $fromTeamName,
                    $quantity,
                    $price,
                    $heatIsHot,
                    $heatTotal
                ]
            );

            // Update negotiation
            $this->db->execute(
                'UPDATE negotiations
                 SET last_offer_by = ?, updated_at = ?
                 WHERE id = ?',
                [$fromTeamId, time(), $negotiationId]
            );

            // Emit events to both parties
            try {
                $otherTeamId = ($fromTeamId === $negotiation['initiatorId']) ? $negotiation['responderId'] : $negotiation['initiatorId'];
                $fromStorage = new TeamStorage($fromTeamId);
                $otherStorage = new TeamStorage($otherTeamId);

                $offerData = [
                    'from_team_id' => $fromTeamId,
                    'from_team_name' => $fromTeamName,
                    'quantity' => $quantity,
                    'price' => $price,
                    'heat' => [
                        'isHot' => (bool)$heatIsHot,
                        'total' => $heatTotal
                    ]
                ];

                $fromStorage->emitEvent('add_counter_offer', [
                    'negotiationId' => $negotiationId,
                    'isFromMe' => true,
                    'offer' => $offerData
                ]);
                $otherStorage->emitEvent('add_counter_offer', [
                    'negotiationId' => $negotiationId,
                    'isFromMe' => false,
                    'offer' => $offerData
                ]);

                // Add notifications
                $fromStorage->addNotification([
                    'type' => 'negotiation_counter',
                    'message' => "You sent a counter-offer to " . ($fromTeamId === $negotiation['initiatorId'] ? $negotiation['responderName'] : $negotiation['initiatorName']) . " for Chemical {$negotiation['chemical']}."
                ]);
                $otherStorage->addNotification([
                    'type' => 'negotiation_counter',
                    'message' => "$fromTeamName sent you a counter-offer for Chemical {$negotiation['chemical']}."
                ]);
            } catch (Exception $e) {
                error_log("NegotiationManager: Failed to emit counter-offer events: " . $e->getMessage());
            }

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }

        return $this->getNegotiation($negotiationId);
    }

    /**
     * Accept negotiation (execute trade)
     */
    public function acceptNegotiation($negotiationId, $acceptingTeamId) {
        // DEBUG: Check if transaction already active
        if ($this->db->inTransaction()) {
            error_log("WARNING: acceptNegotiation called while transaction already active!");
            throw new Exception("Cannot accept negotiation: transaction already in progress");
        }

        // Start transaction immediately to lock the negotiation
        $this->db->beginTransaction();
        error_log("DEBUG: Transaction started in acceptNegotiation for $negotiationId");

        try {
            // Get negotiation with row-level lock (SELECT FOR UPDATE)
            $negotiation = $this->db->queryOne(
                'SELECT * FROM negotiations WHERE id = ?',
                [$negotiationId]
            );

            if (!$negotiation) {
                throw new Exception('Negotiation not found');
            }

            // CRITICAL: Check if already accepted (prevents double-execution)
            if ($negotiation['status'] === 'accepted') {
                throw new Exception('This negotiation has already been accepted');
            }

            if ($negotiation['status'] === 'rejected') {
                throw new Exception('This negotiation has been rejected');
            }

            // Verify team is part of this negotiation (use database column names with underscores)
            if ($acceptingTeamId !== $negotiation['initiator_id'] && $acceptingTeamId !== $negotiation['responder_id']) {
                throw new Exception('Unauthorized');
            }

            // Verify it's their turn (can only accept other team's offer)
            if ($negotiation['last_offer_by'] === $acceptingTeamId) {
                throw new Exception('Cannot accept your own offer');
            }

            // Convert array result from database columns to expected format
            $negotiation = [
                'id' => $negotiation['id'],
                'chemical' => $negotiation['chemical'],
                'type' => $negotiation['type'],
                'initiatorId' => $negotiation['initiator_id'],
                'initiatorName' => $negotiation['initiator_name'],
                'responderId' => $negotiation['responder_id'],
                'responderName' => $negotiation['responder_name'],
                'sessionNumber' => $negotiation['session_number'],
                'status' => $negotiation['status'],
                'lastOfferBy' => $negotiation['last_offer_by'],
                'adId' => $negotiation['ad_id'],
                'offers' => $this->getNegotiationOffers($negotiationId)
            ];

        // Validation: If I am accepting a 'Buy' offer (meaning I am selling), check MY inventory.
        // If the other person offered to BUY, they are the Buyer, I am the Seller.
        // If the other person offered to SELL, they are the Seller, I am the Buyer.
        
        // Who made the last offer? The OTHER person.
        // If negotiation['type'] is 'buy':
        //    - Initiator is Buyer. Responder is Seller.
        //    - If I am Responder (accepting Initiator's Buy Offer), I am Seller. Check My Inv.
        //    - If I am Initiator (accepting Responder's Sell Counter), I am Buyer.
        // If negotiation['type'] is 'sell':
        //    - Initiator is Seller. Responder is Buyer.
        //    - If I am Responder (accepting Initiator's Sell Offer), I am Buyer.
        //    - If I am Initiator (accepting Responder's Buy Counter), I am Seller. Check My Inv.

            $isSeller = ($negotiation['type'] === 'buy' && $acceptingTeamId === $negotiation['responderId']) ||
                        ($negotiation['type'] === 'sell' && $acceptingTeamId === $negotiation['initiatorId']);

            if ($isSeller) {
                // Get the last offer to know quantity
                $lastOffer = end($negotiation['offers']);
                try {
                    $this->checkInventory($acceptingTeamId, $negotiation['chemical'], $lastOffer['quantity']);
                } catch (Exception $e) {
                    // Rollback current transaction first
                    $this->db->rollback();
                    // Then reject the negotiation with the reason
                    $this->rejectNegotiation($negotiationId, 'system', $e->getMessage());
                    // Provide helpful message - inventory may have changed due to other trades
                    throw new Exception("Trade cancelled: " . $e->getMessage() . " (Your inventory may have changed from other completed trades.)");
                }
            }

            // Update negotiation status to 'accepted' - marks it as consumed
            // The WHERE clause includes status='pending' to ensure atomicity
            $rowsAffected = $this->db->execute(
                'UPDATE negotiations
                 SET status = ?, accepted_by = ?, accepted_at = ?, updated_at = ?
                 WHERE id = ? AND status = ?',
                ['accepted', $acceptingTeamId, time(), time(), $negotiationId, 'pending']
            );

            // Double-check that the update succeeded (another transaction might have beaten us)
            if ($rowsAffected === 0) {
                $this->db->rollback();
                throw new Exception('This negotiation was already accepted by someone else');
            }

            // Emit events to both parties
            // NOTE: Trade execution is handled by api/negotiations/accept.php AFTER this method returns
            try {
                $otherTeamId = ($acceptingTeamId === $negotiation['initiatorId']) ? $negotiation['responderId'] : $negotiation['initiatorId'];
                $otherTeamName = ($acceptingTeamId === $negotiation['initiatorId']) ? $negotiation['responderName'] : $negotiation['initiatorName'];
                $aStorage = new TeamStorage($acceptingTeamId);
                $oStorage = new TeamStorage($otherTeamId);

                $aStorage->emitEvent('close_negotiation', ['negotiationId' => $negotiationId, 'status' => 'accepted']);
                $oStorage->emitEvent('close_negotiation', ['negotiationId' => $negotiationId, 'status' => 'accepted']);

                // Add notifications for history
                $aStorage->addNotification([
                    'type' => 'trade_completed',
                    'message' => "Trade accepted! Negotiation with $otherTeamName for Chemical {$negotiation['chemical']} complete."
                ]);
                // Counterparty (oStorage) notification is handled by GlobalAggregator reflection
            } catch (Exception $e) {
                error_log("NegotiationManager: Failed to emit accept events: " . $e->getMessage());
            }

            // Remove associated listing and buy order if they exist
            if (!empty($negotiation['adId'])) {
                try {
                    require_once __DIR__ . '/ListingManager.php';
                    // Determine who owned the listing
                    // If type was 'buy' from initiator, initiator posted it.
                    // If it was 'sell' from initiator, they were responding to a 'buy' listing from responder.
                    $adOwner = (($negotiation['type'] ?? 'buy') === 'buy') ? $negotiation['initiatorId'] : $negotiation['responderId'];

                    $listingManager = new ListingManager($adOwner);
                    
                    // 1. Remove the public listing event
                    $listingManager->removeListing($negotiation['adId']);
                    error_log("NegotiationManager: Automatically removed listing {$negotiation['adId']} after acceptance.");

                    // 2. ALSO remove the corresponding buy order event for this chemical
                    try {
                        $ownerStorage = new TeamStorage($adOwner);
                        $buyOrdersData = $ownerStorage->getBuyOrders();
                        $buyOrders = $buyOrdersData['interests'] ?? [];

                        foreach ($buyOrders as $order) {
                            if ($order['chemical'] === $negotiation['chemical']) {
                                $ownerStorage->removeBuyOrder($order['id']);
                                error_log("NegotiationManager: Automatically removed buy order {$order['id']} for {$negotiation['chemical']} belonging to $adOwner.");
                                // We stop after removing one matching buy order
                                break; 
                            }
                        }
                    } catch (Exception $e) {
                        error_log("NegotiationManager: Failed to remove associated buy order: " . $e->getMessage());
                    }
                } catch (Exception $e) {
                    error_log("NegotiationManager: Failed to cleanup linked listing/buy-order: " . $e->getMessage());
                }
            }

            error_log("DEBUG: About to commit transaction in acceptNegotiation for $negotiationId");
            if (!$this->db->inTransaction()) {
                error_log("ERROR: No transaction active when trying to commit! This should never happen.");
                throw new Exception("Transaction lost before commit");
            }
            $this->db->commit();
            error_log("DEBUG: Transaction committed successfully for $negotiationId");
        } catch (Exception $e) {
            error_log("DEBUG: Exception in acceptNegotiation: " . $e->getMessage());
            if ($this->db->inTransaction()) {
                error_log("DEBUG: Rolling back transaction");
                $this->db->rollback();
            } else {
                error_log("ERROR: Cannot rollback - no transaction active!");
            }
            throw $e;
        }

        return $this->getNegotiation($negotiationId);
    }

    /**
     * Reject/Cancel negotiation
     * @param string $negotiationId
     * @param string $rejectingTeamId - Team rejecting, or 'system' for auto-rejection
     * @param string|null $reason - Optional reason for rejection (e.g., "Insufficient inventory")
     */
    public function rejectNegotiation($negotiationId, $rejectingTeamId, $reason = null) {
        $negotiation = $this->getNegotiation($negotiationId);

        if (!$negotiation) {
            throw new Exception('Negotiation not found');
        }

        // Verify team is part of this negotiation (unless system rejection)
        if ($rejectingTeamId !== 'system' &&
            $rejectingTeamId !== $negotiation['initiatorId'] &&
            $rejectingTeamId !== $negotiation['responderId']) {
            throw new Exception('Unauthorized');
        }

        // Start transaction
        $this->db->beginTransaction();
        try {
            // Update negotiation with optional reason
            $this->db->execute(
                'UPDATE negotiations
                 SET status = ?, rejected_by = ?, rejected_at = ?, rejection_reason = ?, updated_at = ?
                 WHERE id = ?',
                ['rejected', $rejectingTeamId, time(), $reason, time(), $negotiationId]
            );

            // Emit events to both parties
            try {
                $isSystemRejection = ($rejectingTeamId === 'system');

                if ($isSystemRejection) {
                    // System rejection - notify both parties
                    $iStorage = new TeamStorage($negotiation['initiatorId']);
                    $rStorage = new TeamStorage($negotiation['responderId']);

                    $iStorage->emitEvent('close_negotiation', ['negotiationId' => $negotiationId, 'status' => 'rejected', 'reason' => $reason]);
                    $rStorage->emitEvent('close_negotiation', ['negotiationId' => $negotiationId, 'status' => 'rejected', 'reason' => $reason]);

                    $reasonMsg = $reason ? " Reason: $reason" : "";
                    $iStorage->addNotification([
                        'type' => 'negotiation_rejected',
                        'message' => "The negotiation for Chemical {$negotiation['chemical']} was cancelled due to validation failure.$reasonMsg"
                    ]);
                    $rStorage->addNotification([
                        'type' => 'negotiation_rejected',
                        'message' => "The negotiation for Chemical {$negotiation['chemical']} was cancelled due to validation failure.$reasonMsg"
                    ]);
                } else {
                    // Normal rejection by a team member
                    $otherTeamId = ($rejectingTeamId === $negotiation['initiatorId']) ? $negotiation['responderId'] : $negotiation['initiatorId'];
                    $otherTeamName = ($rejectingTeamId === $negotiation['initiatorId']) ? $negotiation['responderName'] : $negotiation['initiatorName'];
                    $rejectingTeamName = ($rejectingTeamId === $negotiation['initiatorId']) ? $negotiation['initiatorName'] : $negotiation['responderName'];

                    $rejStorage = new TeamStorage($rejectingTeamId);
                    $oStorage = new TeamStorage($otherTeamId);

                    $rejStorage->emitEvent('close_negotiation', ['negotiationId' => $negotiationId, 'status' => 'rejected']);
                    $oStorage->emitEvent('close_negotiation', ['negotiationId' => $negotiationId, 'status' => 'rejected']);

                    // Add notifications for history
                    $rejStorage->addNotification([
                        'type' => 'negotiation_rejected',
                        'message' => "You cancelled the negotiation with $otherTeamName for Chemical {$negotiation['chemical']}."
                    ]);
                    $oStorage->addNotification([
                        'type' => 'negotiation_rejected',
                        'message' => "The negotiation for Chemical {$negotiation['chemical']} was cancelled by $rejectingTeamName."
                    ]);
                }
            } catch (Exception $e) {
                error_log("NegotiationManager: Failed to emit reject events: " . $e->getMessage());
            }

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }

        return $this->getNegotiation($negotiationId);
    }

    /**
     * Clean up old negotiations (optional - for maintenance)
     */
    public function cleanupOldNegotiations($olderThanDays = 7) {
        $cutoffTime = time() - ($olderThanDays * 24 * 60 * 60);

        $deleted = $this->db->execute(
            'DELETE FROM negotiations
             WHERE updated_at < ?
               AND status IN (?, ?)',
            [$cutoffTime, 'accepted', 'rejected']
        );

        return $deleted;
    }

    /**
     * Map raw database row to frontend-friendly camelCase format
     */
    public function mapNegotiation($neg) {
        if (!$neg) return null;

        $mapped = [
            'id' => $neg['id'],
            'chemical' => $neg['chemical'],
            'type' => $neg['type'],
            'initiatorId' => $neg['initiator_id'] ?? null,
            'initiatorName' => $neg['initiator_name'] ?? null,
            'responderId' => $neg['responder_id'] ?? null,
            'responderName' => $neg['responder_name'] ?? null,
            'sessionNumber' => $neg['session_number'] ?? null,
            'status' => $neg['status'] ?? 'pending',
            'lastOfferBy' => $neg['last_offer_by'] ?? null,
            'rejectionReason' => $neg['rejection_reason'] ?? null,
            'adId' => $neg['ad_id'] ?? null,
            'createdAt' => $neg['created_at'] ?? null,
            'updatedAt' => $neg['updated_at'] ?? null,
            'offers' => []
        ];

        if (!empty($neg['offers'])) {
            foreach ($neg['offers'] as $offer) {
                $mapped['offers'][] = [
                    'fromTeamId' => $offer['from_team_id'] ?? null,
                    'fromTeamName' => $offer['from_team_name'] ?? null,
                    'quantity' => $offer['quantity'] ?? 0,
                    'price' => $offer['price'] ?? 0,
                    'createdAt' => $offer['created_at'] ?? null
                ];
            }
        }

        return $mapped;
    }
}
