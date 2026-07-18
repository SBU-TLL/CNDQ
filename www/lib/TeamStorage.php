<?php
/**
 * TeamStorage - SQLite-based storage for team events and state
 *
 * Migrated from file-based to SQLite for production scalability.
 * Maintains same public API for backward compatibility.
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/TeamNameGenerator.php';

class TeamStorage {
    private $teamEmail;
    private $safeEmail;
    private $teamName;
    private $db;

    /**
     * Initialize storage for a specific team
     * @param string $email Team's email address
     */
    public function __construct($email) {
        $this->teamEmail = $email;
        $this->safeEmail = $this->sanitizeEmail($email);
        $this->db = Database::getInstance();
        $this->ensureTeamInitialized();
    }

    /**
     * Get the team name (with caching)
     */
    public function getTeamName() {
        if ($this->teamName) return $this->teamName;
        $state = $this->getState();
        $this->teamName = $state['profile']['teamName'] ?? $this->teamEmail;
        return $this->teamName;
    }

    /**
     * Sanitize email for safe usage
     */
    public static function sanitizeEmail($email) {
        return preg_replace('/[^a-zA-Z0-9_\-@.]/', '_', $email);
    }

    /**
     * Check if team exists, initialize if new
     */
    private function ensureTeamInitialized() {
        // Check if team has any events
        $existing = $this->db->queryOne(
            'SELECT id FROM team_events WHERE team_email = ? LIMIT 1',
            [$this->teamEmail]
        );

        if (!$existing) {
            $this->initializeNewTeam();
        }
    }

    /**
     * Initialize a new team with starting inventory
     */
    private function initializeNewTeam() {
        // Get all existing team names to prevent collisions
        $existingNames = $this->db->query(
            'SELECT DISTINCT team_name FROM team_events WHERE event_type = "init"'
        );
        $existingNamesList = array_column($existingNames, 'team_name');

        // Generate unique name (appends number if collision)
        $this->teamName = TeamNameGenerator::generateUnique($this->teamEmail, $existingNamesList);

        $timestamp = microtime(true);

        // Generate random starting inventory
        $startingInventory = [
            'C' => round(rand(500, 2000), 4),
            'N' => round(rand(500, 2000), 4),
            'D' => round(rand(500, 2000), 4),
            'Q' => round(rand(500, 2000), 4)
        ];

        // Calculate initial production potential (baseline for success measurement)
        // This is what the team COULD make with their starting inventory if they don't trade
        require_once __DIR__ . '/LPSolver.php';
        $solver = new LPSolver();
        $result = $solver->solve($startingInventory);
        $initialProductionPotential = $result['maxProfit'];

        $event = [
            'type' => 'init',
            'payload' => [
                'profile' => [
                    'email' => $this->teamEmail,
                    'teamName' => $this->teamName,
                    'createdAt' => time(),
                    'currentFunds' => 0,
                    'startingFunds' => 0,
                    'initialProductionPotential' => $initialProductionPotential  // NEW: baseline for % improvement
                ],
                'inventory' => $startingInventory,
                'shadowPrices' => [
                    'C' => 0, 'N' => 0, 'D' => 0, 'Q' => 0
                ]
            ],
            'timestamp' => $timestamp,
            'teamId' => $this->teamEmail,
            'teamName' => $this->teamName
        ];

        $this->db->insert(
            'INSERT INTO team_events (team_email, team_name, event_type, payload, timestamp)
             VALUES (?, ?, ?, ?, ?)',
            [
                $this->teamEmail,
                $this->teamName,
                'init',
                json_encode($event['payload']),
                $timestamp
            ]
        );

        // Emit Global 'Team Joined' Event
        $this->db->insert(
            'INSERT INTO marketplace_events (team_email, team_name, event_type, payload, timestamp)
             VALUES (?, ?, ?, ?, ?)',
            [
                $this->teamEmail,
                $this->teamName,
                'team_joined',
                json_encode([
                    'eventId' => 'join_' . time() . '_' . bin2hex(random_bytes(2)),
                    'teamName' => $this->teamName,
                    'joinedAt' => $timestamp,
                    'type' => 'join'
                ]),
                $timestamp
            ]
        );

        // NOTE: Production is NOT run at initialization.
        // Production only runs at the end of the game to calculate final scores.
        // Players start with random inventory and $0 funds, then trade to maximize value.
    }

    /**
     * Emit an event - core write operation
     */
    public function emitEvent(string $type, array $payload) {
        // Small delay to ensure unique timestamps
        usleep(50000);

        $timestamp = microtime(true);
        $teamName = $this->teamName ?? $this->getTeamName();

        $event = [
            'type' => $type,
            'payload' => $payload,
            'timestamp' => $timestamp,
            'teamId' => $this->teamEmail,
            'teamName' => $teamName
        ];

        // Handle nested transactions: Only begin if not already in one
        $useTransaction = !$this->db->inTransaction();
        if ($useTransaction) {
            $this->db->beginTransaction();
        }

        try {
            // Insert team event
            $this->db->insert(
                'INSERT INTO team_events (team_email, team_name, event_type, payload, timestamp)
                 VALUES (?, ?, ?, ?, ?)',
                [
                    $this->teamEmail,
                    $teamName,
                    $type,
                    json_encode($payload),
                    $timestamp
                ]
            );

            // If this is a marketplace event, also insert into marketplace_events
            $marketplaceTypes = ['add_offer', 'remove_offer', 'update_offer',
                               'add_buy_order', 'remove_buy_order', 'update_buy_order',
                               'add_listing', 'remove_listing'];

            if (in_array($type, $marketplaceTypes)) {
                // Ensure teamName is in payload for marketplace
                $event['payload']['teamName'] = $teamName;

                $this->db->insert(
                    'INSERT INTO marketplace_events (team_email, team_name, event_type, payload, timestamp)
                     VALUES (?, ?, ?, ?, ?)',
                    [
                        $this->teamEmail,
                        $teamName,
                        $type,
                        json_encode($event['payload']),
                        $timestamp
                    ]
                );
            }

            // Invalidate cache
            $this->db->execute(
                'DELETE FROM team_state_cache WHERE team_email = ?',
                [$this->teamEmail]
            );

            if ($useTransaction) {
                $this->db->commit();
            }
        } catch (Exception $e) {
            if ($useTransaction) {
                $this->db->rollback();
            }
            error_log("TeamStorage: Failed to emit event '$type' for {$this->teamEmail}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get the current state (Aggregated and Cached)
     */
    public function getState(): array {
        // Try to get cached state
        $cached = $this->db->queryOne(
            'SELECT state, last_event_id, events_processed FROM team_state_cache WHERE team_email = ?',
            [$this->teamEmail]
        );

        // Check if cache is still valid
        if ($cached) {
            $lastEventInDb = $this->db->queryOne(
                'SELECT id FROM team_events WHERE team_email = ? ORDER BY id DESC LIMIT 1',
                [$this->teamEmail]
            );

            if ($lastEventInDb && $lastEventInDb['id'] == $cached['last_event_id']) {
                // Cache is valid
                $state = json_decode($cached['state'], true);
                $state['eventsProcessed'] = $cached['events_processed'];
                return $state;
            }
        }

        // Cache miss or invalid - aggregate from events
        $state = $this->aggregateStateFromEvents();

        // Calculate staleness level
        $count = $state['inventory']['transactionsSinceLastShadowCalc'] ?? 0;
        if ($count === 0) {
            $state['inventory']['stalenessLevel'] = 'fresh';
        } elseif ($count === 1) {
            $state['inventory']['stalenessLevel'] = 'warning';
        } else {
            $state['inventory']['stalenessLevel'] = 'stale';
        }

        // Get last event ID for cache validation
        $lastEvent = $this->db->queryOne(
            'SELECT id FROM team_events WHERE team_email = ? ORDER BY id DESC LIMIT 1',
            [$this->teamEmail]
        );

        // Save to cache
        $this->db->execute(
            'INSERT OR REPLACE INTO team_state_cache (team_email, state, last_event_id, events_processed, updated_at)
             VALUES (?, ?, ?, ?, ?)',
            [
                $this->teamEmail,
                json_encode($state),
                $lastEvent['id'] ?? null,
                $state['eventsProcessed'],
                time()
            ]
        );

        // Check if we should create a snapshot (every 50 events)
        $eventsSinceSnapshot = $state['eventsProcessed'] - ($state['snapshotEvents'] ?? 0);
        if ($eventsSinceSnapshot >= 50) {
            $this->createSnapshot($state);
        }

        return $state;
    }

    /**
     * Aggregate state from all events
     */
    private function aggregateStateFromEvents(): array {
        // Check for latest snapshot to start from
        $snapshot = $this->db->queryOne(
            'SELECT state, event_count FROM team_snapshots
             WHERE team_email = ?
             ORDER BY created_at DESC LIMIT 1',
            [$this->teamEmail]
        );

        if ($snapshot) {
            $state = json_decode($snapshot['state'], true);
            $state['snapshotEvents'] = $snapshot['event_count'];
            $startEventId = $snapshot['event_count'];
        } else {
            // Start with empty state
            $state = [
                'profile' => [
                    'email' => $this->teamEmail,
                    'teamName' => $this->teamEmail,
                    'currentFunds' => 0,
                    'startingFunds' => 0,
                    'settings' => []
                ],
                'inventory' => ['C' => 0, 'N' => 0, 'D' => 0, 'Q' => 0],
                'shadowPrices' => ['C' => 0, 'N' => 0, 'D' => 0, 'Q' => 0],
                'offers' => [],
                'buyOrders' => [],
                'transactions' => [],
                'notifications' => [],
                'ads' => [],
                'productions' => [],
                'session' => [],  // For system team
                'snapshotEvents' => 0
            ];
            $startEventId = 0;
        }

        // Get all events since snapshot (or all if no snapshot)
        $sql = 'SELECT event_type, payload, timestamp
             FROM team_events
             WHERE team_email = ?';
        
        if ($snapshot) {
            $sql .= ' ORDER BY timestamp ASC LIMIT -1 OFFSET ' . (int)$snapshot['event_count'];
        } else {
            $sql .= ' ORDER BY timestamp ASC';
        }

        $events = $this->db->query($sql, [$this->teamEmail]);

        $state['eventsProcessed'] = ($snapshot ? $snapshot['event_count'] : 0) + count($events);

        // Apply events to build the current state
        foreach ($events as $event) {
            $state = $this->applyEvent($state, $event['event_type'], json_decode($event['payload'], true));
        }

        return $state;
    }

    /**
     * Apply a single event to state
     */
    private function applyEvent(array $state, string $type, array $payload): array {
        switch ($type) {
            case 'init':
                if (isset($payload['profile'])) $state['profile'] = array_merge($state['profile'], $payload['profile']);
                if (isset($payload['inventory'])) $state['inventory'] = $payload['inventory'];
                if (isset($payload['shadowPrices'])) $state['shadowPrices'] = $payload['shadowPrices'];
                break;

            case 'update_profile':
                $state['profile'] = array_merge($state['profile'], $payload);
                break;

            case 'set_funds':
                $state['profile']['currentFunds'] = $payload['amount'];
                if ($payload['is_starting'] ?? false) {
                    $state['profile']['startingFunds'] = $payload['amount'];
                }
                break;

            case 'adjust_funds':
                // Round to 2 decimal places to prevent floating-point accumulation errors
                $state['profile']['currentFunds'] = round(($state['profile']['currentFunds'] ?? 0) + $payload['amount'], 2);
                break;

            case 'adjust_chemical':
                $chem = $payload['chemical'];
                // Round to 4 decimal places to prevent floating-point accumulation errors
                $state['inventory'][$chem] = round(($state['inventory'][$chem] ?? 0) + $payload['amount'], 4);
                // Increment staleness counter - shadow prices need recalculation after inventory change
                $state['inventory']['transactionsSinceLastShadowCalc'] = ($state['inventory']['transactionsSinceLastShadowCalc'] ?? 0) + 1;
                break;

            case 'update_shadow_prices':
                $state['shadowPrices'] = $payload;
                $state['inventory']['transactionsSinceLastShadowCalc'] = 0;
                break;

            case 'add_offer':
                $state['offers'][] = $payload;
                break;

            case 'remove_offer':
                $state['offers'] = array_values(array_filter($state['offers'], fn($o) => $o['id'] !== $payload['id']));
                break;

            case 'update_offer':
                foreach ($state['offers'] as &$offer) {
                    if ($offer['id'] === $payload['id']) {
                        $offer = array_merge($offer, $payload['updates']);
                    }
                }
                break;

            case 'add_buy_order':
                $state['buyOrders'][] = $payload;
                break;

            case 'remove_buy_order':
                $state['buyOrders'] = array_values(array_filter($state['buyOrders'], fn($o) => $o['id'] !== $payload['id']));
                break;

            case 'update_buy_order':
                foreach ($state['buyOrders'] as &$order) {
                    if ($order['id'] === $payload['id']) {
                        $order = array_merge($order, $payload['updates']);
                    }
                }
                break;

            case 'add_transaction':
                $state['transactions'][] = $payload;
                break;

            case 'add_notification':
                $state['notifications'][] = $payload;
                break;

            case 'mark_notifications_read':
                if ($payload['ids'] === null) {
                    foreach ($state['notifications'] as &$notif) {
                        $notif['read'] = true;
                    }
                } else {
                    foreach ($state['notifications'] as &$notif) {
                        if (in_array($notif['id'], $payload['ids'])) {
                            $notif['read'] = true;
                        }
                    }
                }
                break;

            case 'add_ad':
            case 'add_listing':
                $state['ads'][] = $payload;
                break;

            case 'remove_ad':
            case 'remove_listing':
                $state['ads'] = array_values(array_filter($state['ads'], fn($a) => $a['id'] !== $payload['id']));
                break;

            case 'add_production':
                $state['productions'][] = $payload;
                break;

            case 'initiate_negotiation':
                $negId = $payload['negotiationId'];
                if (!isset($state['negotiationStates'][$negId])) {
                    $state['negotiationStates'][$negId] = [
                        'id' => $negId,
                        'patience' => 100,
                        'lastReaction' => 0,
                        'chemical' => $payload['chemical'],
                        'role' => $payload['role'],
                        'counterparty' => $payload['counterparty'],
                        'status' => 'pending'
                    ];
                }
                break;

            case 'add_counter_offer':
                $negId = $payload['negotiationId'];
                if (!isset($state['negotiationStates'][$negId])) {
                    $state['negotiationStates'][$negId] = ['patience' => 100, 'lastReaction' => 0];
                }
                $state['negotiationStates'][$negId]['id'] = $negId;
                if (!($payload['isFromMe'] ?? false)) {
                    $state['negotiationStates'][$negId]['patience'] = max(0, ($state['negotiationStates'][$negId]['patience'] ?? 100) - 10);
                }
                break;

            case 'close_negotiation':
                $negId = $payload['negotiationId'];
                if (isset($state['negotiationStates'][$negId])) {
                    $state['negotiationStates'][$negId]['status'] = $payload['status'];
                }
                break;

            case 'add_reaction':
                $negId = $payload['negotiationId'];
                if (!isset($state['negotiationStates'][$negId])) {
                    $state['negotiationStates'][$negId] = ['patience' => 100, 'lastReaction' => 0];
                }
                $state['negotiationStates'][$negId]['lastReaction'] = $payload['level'];
                break;

            case 'mark_reflected':
                foreach ($state['transactions'] as &$txn) {
                    if (($txn['transactionId'] ?? '') === $payload['transactionId']) {
                        unset($txn['isPendingReflection']);
                    }
                }
                break;

            case 'update_session':
                // Merge session data (for system team)
                $state['session'] = array_merge($state['session'] ?? [], $payload);
                break;
        }

        return $state;
    }

    /**
     * Create a baseline snapshot of the current state
     */
    private function createSnapshot(array $state) {
        $state['snapshotEvents'] = $state['eventsProcessed'];
        $state['snapshotAt'] = time();

        $this->db->insert(
            'INSERT INTO team_snapshots (team_email, state, event_count)
             VALUES (?, ?, ?)',
            [
                $this->teamEmail,
                json_encode($state),
                $state['eventsProcessed']
            ]
        );
    }

    /**
     * Automatically run first production for new teams to establish baseline
     */
    private function runAutomaticFirstProduction() {
        require_once __DIR__ . '/LPSolver.php';

        $inventory = $this->getInventory();
        $solver = new LPSolver();
        $result = $solver->solve($inventory);

        $revenue = $result['maxProfit'];

        // NEW MODEL: Inventory is NOT consumed during the session.
        // We only record the initial potential profit as the baseline.
        
        // Baseline funds for ROI calculation (starting funds = baseline profit)
        // Actual current funds start at $0 (Infinite Capital model)
        $this->emitEvent('set_funds', ['amount' => $revenue, 'is_starting' => true]);
        $this->emitEvent('set_funds', ['amount' => 0, 'is_starting' => false]);

        $this->addProduction([
            'type' => 'baseline_potential',
            'sessionNumber' => 0, 
            'deicer' => $result['deicer'],
            'solvent' => $result['solvent'],
            'revenue' => $revenue,
            'note' => 'Initial baseline potential established. No inventory consumed.'
        ]);
    }

    // ==================== Profile Methods ====================

    public function getProfile() { return $this->getState()['profile']; }

    public function updateProfile($callback) {
        $state = $this->getState();
        $newProfile = $callback($state['profile']);
        $this->emitEvent('update_profile', $newProfile);
        return $newProfile;
    }

    public function setTeamName($name) {
        $this->updateProfile(function($p) use ($name) {
            $p['teamName'] = $name;
            return $p;
        });
    }

    public function updateFunds($amount) {
        $this->emitEvent('adjust_funds', ['amount' => $amount]);
    }

    public function setFunds($amount, $isStarting = false) {
        $this->emitEvent('set_funds', ['amount' => $amount, 'is_starting' => $isStarting]);
    }

    // ==================== Inventory Methods ====================

    public function getInventory() { return $this->getState()['inventory']; }

    public function updateInventory($callback) {
        $state = $this->getState();
        $newInventory = $callback($state['inventory']);
        $this->emitEvent('init', ['inventory' => $newInventory]);
        return $newInventory;
    }

    public function adjustChemical($chemical, $amount) {
        $this->emitEvent('adjust_chemical', ['chemical' => $chemical, 'amount' => $amount]);
    }

    public function resetShadowCalcCounter() {
        $this->emitEvent('update_shadow_prices', $this->getShadowPrices());
    }

    // ==================== Shadow Prices Methods ====================

    public function getShadowPrices() { return $this->getState()['shadowPrices']; }

    public function updateShadowPrices($prices) {
        $this->emitEvent('update_shadow_prices', $prices);
    }

    // ==================== Offers Methods ====================

    public function getOffersMade() { return ['offers' => $this->getState()['offers']]; }

    public function addOffer($data) {
        $data['id'] = 'offer_' . time() . '_' . bin2hex(random_bytes(4));
        $data['sellerId'] = $this->teamEmail;
        $data['createdAt'] = time();
        $data['status'] = 'active';
        $this->emitEvent('add_offer', $data);
        return ['offers' => $this->getOffersMade()['offers']];
    }

    public function updateOffer($id, $updates) {
        $this->emitEvent('update_offer', ['id' => $id, 'updates' => $updates]);
    }

    public function removeOffer($id) {
        $this->emitEvent('remove_offer', ['id' => $id]);
    }

    // ==================== Buy Orders Methods ====================

    public function getBuyOrders() { return ['interests' => $this->getState()['buyOrders']]; }

    public function addBuyOrder($data) {
        // Garbage Collection: Remove existing active orders for this chemical
        $existingOrders = $this->getBuyOrders()['interests'];
        foreach ($existingOrders as $existingOrder) {
            if (($existingOrder['chemical'] ?? '') === ($data['chemical'] ?? '')) {
                $this->removeBuyOrder($existingOrder['id']);
            }
        }

        $data['id'] = 'buy_' . time() . '_' . bin2hex(random_bytes(4));
        $data['buyerId'] = $this->teamEmail;
        $data['createdAt'] = time();
        $data['status'] = 'active';
        $this->emitEvent('add_buy_order', $data);
        return ['interests' => $this->getBuyOrders()['interests']];
    }

    public function updateBuyOrder($id, $updates) {
        $this->emitEvent('update_buy_order', ['id' => $id, 'updates' => $updates]);
    }

    public function removeBuyOrder($id) {
        $this->emitEvent('remove_buy_order', ['id' => $id]);

        // Immediately refresh the marketplace snapshot so NPCs see the updated state
        require_once __DIR__ . '/MarketplaceAggregator.php';
        $aggregator = new MarketplaceAggregator();
        $aggregator->generateSnapshot();
    }

    // ==================== Transactions Methods ====================

    public function getTransactions() { return ['transactions' => $this->getState()['transactions']]; }

    public function addTransaction($data) {
        $data['id'] = 'txn_' . time() . '_' . bin2hex(random_bytes(4));
        $this->emitEvent('add_transaction', $data);
    }

    // ==================== Notifications Methods ====================

    public function getNotifications() { return ['notifications' => $this->getState()['notifications']]; }

    public function addNotification($data) {
        $data['id'] = 'notif_' . time() . '_' . bin2hex(random_bytes(4));
        $this->emitEvent('add_notification', $data);
        return $data;
    }

    public function markNotificationsRead($ids = null) {
        $this->emitEvent('mark_notifications_read', ['ids' => $ids]);
    }

    // ==================== Listing Methods ====================

    public function getListings() { return $this->getState()['ads']; }

    public function addListing($chemical, $type, $extraData = []) {
        // Garbage Collection: Remove existing active listings for this chemical/type
        $existingAds = $this->getListings();
        foreach ($existingAds as $existingAd) {
            if ($existingAd['chemical'] === $chemical && $existingAd['type'] === $type) {
                $this->removeListing($existingAd['id']);
            }
        }

        $ad = array_merge([
            'id' => 'ad_' . time() . '_' . bin2hex(random_bytes(4)),
            'teamId' => $this->teamEmail,
            'chemical' => $chemical,
            'type' => $type,
            'status' => 'active',
            'createdAt' => time()
        ], $extraData);
        
        $this->emitEvent('add_listing', $ad);
        return ['ads' => $this->getListings()];
    }

    public function removeListing($adId) {
        $this->emitEvent('remove_listing', ['id' => $adId]);

        // Immediately refresh the marketplace snapshot so NPCs see the updated state
        // This prevents stale canceled listings from appearing to newly created NPCs
        require_once __DIR__ . '/MarketplaceAggregator.php';
        $aggregator = new MarketplaceAggregator();
        $aggregator->generateSnapshot();
    }

    // ==================== Production Methods ====================

    public function addProduction($data) { $this->emitEvent('add_production', $data); }
    public function getProductionHistory() { return $this->getState()['productions']; }

    // ==================== Settings Methods ====================

    public function getSettings() { return $this->getState()['profile']['settings'] ?? []; }

    public function updateSettings($settings) {
        $this->updateProfile(function($p) use ($settings) {
            $p['settings'] = array_merge($p['settings'] ?? [], $settings);
            return $p;
        });
    }

    // ==================== Utility Methods ====================

    public function getTeamEmail() { return $this->teamEmail; }
    public function getTeamDirectory() {
        // For backward compatibility - returns legacy directory path
        return cndq_data_dir() . '/teams/' . $this->safeEmail;
    }
    public function getFullState() { return $this->getState(); }
}
