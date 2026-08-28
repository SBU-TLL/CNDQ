<?php
/**
 * MarketplaceAggregator - SQLite-based marketplace aggregation
 *
 * Reads from marketplace_events table and builds aggregated marketplace view.
 * Migrated from file-based to SQLite for production scalability.
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/TeamStorage.php';

class MarketplaceAggregator {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * GENERATES the snapshot - called by World Turner (cron/admin)
     */
    public function generateSnapshot() {
        $data = $this->getAggregatedFromEvents();

        // Update singleton snapshot record
        $this->db->execute(
            'UPDATE marketplace_snapshot
             SET offers = ?, buy_orders = ?, ads = ?, recent_trades = ?, updated_at = ?
             WHERE id = 1',
            [
                json_encode($data['offers']),
                json_encode($data['buyOrders']),
                json_encode($data['ads']),
                json_encode($data['recentTrades']),
                time()
            ]
        );

        return $data;
    }

    /**
     * Reads the snapshot (Fast read from database)
     * Falls back to live aggregation if snapshot is missing
     */
    private function getSnapshot() {
        $snapshot = $this->db->queryOne('SELECT offers, buy_orders, ads, recent_trades FROM marketplace_snapshot WHERE id = 1');

        if ($snapshot) {
            return [
                'offers' => json_decode($snapshot['offers'], true) ?? [],
                'buyOrders' => json_decode($snapshot['buy_orders'], true) ?? [],
                'ads' => json_decode($snapshot['ads'], true) ?? [],
                'recentTrades' => json_decode($snapshot['recent_trades'] ?? '[]', true) ?? []
            ];
        }

        // Fallback for dev/testing
        return $this->getAggregatedFromEvents();
    }

    /**
     * Get list of all team emails (from team_events table)
     */
    public function getAllTeams() {
        $teams = [];

        // Get distinct teams from team_events
        $teamEmails = $this->db->query(
            'SELECT DISTINCT team_email, team_name
             FROM team_events
             WHERE event_type = "init"
             ORDER BY created_at DESC'
        );

        foreach ($teamEmails as $row) {
            try {
                $storage = new TeamStorage($row['team_email']);
                $profile = $storage->getProfile();

                $teams[] = [
                    'email' => $row['team_email'],
                    'teamName' => $profile['teamName'] ?? $row['team_name'],
                    'lastActive' => $profile['lastActive'] ?? 0
                ];
            } catch (Exception $e) {
                continue;
            }
        }

        usort($teams, fn($a, $b) => $b['lastActive'] - $a['lastActive']);
        return $teams;
    }

    /**
     * Get all active offers (Fast read from snapshot)
     */
    public function getActiveOffers($chemical = null) {
        $data = $this->getSnapshot();
        $offers = $data['offers'] ?? [];

        if ($chemical) {
            $offers = array_values(array_filter($offers, fn($o) => ($o['chemical'] ?? '') === $chemical));
        }

        return $offers;
    }

    /**
     * Get all active buy orders (Fast read from snapshot)
     */
    public function getActiveBuyOrders($chemical = null) {
        $data = $this->getSnapshot();
        $buyOrders = $data['buyOrders'] ?? [];

        if ($chemical) {
            $buyOrders = array_values(array_filter($buyOrders, fn($o) => ($o['chemical'] ?? '') === $chemical));
        }

        return $buyOrders;
    }

    public function getOffersByChemical() {
        $grouped = ['C' => [], 'N' => [], 'D' => [], 'Q' => []];

        foreach ($this->getActiveOffers() as $offer) {
            $chem = $offer['chemical'] ?? null;
            if ($chem && isset($grouped[$chem])) $grouped[$chem][] = $offer;
        }

        foreach ($grouped as &$offers) {
            usort($offers, function($a, $b) {
                return ($a['minPrice'] ?? $a['price'] ?? 0) <=> ($b['minPrice'] ?? $b['price'] ?? 0);
            });
        }

        return $grouped;
    }

    public function getBuyOrdersByChemical() {
        $grouped = ['C' => [], 'N' => [], 'D' => [], 'Q' => []];

        foreach ($this->getActiveBuyOrders() as $order) {
            $chem = $order['chemical'] ?? null;
            if ($chem && isset($grouped[$chem])) $grouped[$chem][] = $order;
        }

        foreach ($grouped as &$orders) {
            usort($orders, function($a, $b) {
                return ($b['maxPrice'] ?? $b['price'] ?? 0) <=> ($a['maxPrice'] ?? $a['price'] ?? 0);
            });
        }

        return $grouped;
    }

    public function getOfferById($offerId) {
        foreach ($this->getActiveOffers() as $offer) {
            if ($offer['id'] === $offerId) return $offer;
        }
        return null;
    }

    public function getBuyOrderById($buyOrderId) {
        foreach ($this->getActiveBuyOrders() as $order) {
            if ($order['id'] === $buyOrderId) return $order;
        }
        return null;
    }

    /**
     * Aggregates marketplace from events table (replaces shared log file scanning)
     */
    public function getAggregatedFromEvents() {
        // Get all marketplace events ordered by timestamp
        $events = $this->db->query(
            'SELECT team_email, team_name, event_type, payload, timestamp
             FROM marketplace_events
             ORDER BY timestamp ASC'
        );

        $offers = [];
        $buyOrders = [];
        $ads = [];
        $recentTrades = [];

        foreach ($events as $event) {
            $type = $event['event_type'];
            $payload = json_decode($event['payload'], true);
            $teamId = $event['team_email'];
            $teamName = $event['team_name'] ?? $teamId;

            switch ($type) {
                case 'team_joined':
                case 'add_transaction':
                    // Payload already contains full details
                    // For join: {eventId, teamName, type: 'join'}
                    // For trade: {transactionId, chemical, ...}
                    array_unshift($recentTrades, $payload);
                    // Keep only last 20 activities
                    if (count($recentTrades) > 20) {
                        array_pop($recentTrades);
                    }
                    break;

                case 'add_offer':
                    $payload['teamId'] = $teamId;
                    $payload['sellerName'] = $teamName;
                    $offers[$payload['id']] = $payload;
                    break;

                case 'remove_offer':
                    unset($offers[$payload['id']]);
                    break;

                case 'update_offer':
                    if (isset($offers[$payload['id']])) {
                        $offers[$payload['id']] = array_merge($offers[$payload['id']], $payload['updates']);
                    }
                    break;

                case 'add_buy_order':
                    $payload['teamId'] = $teamId;
                    $payload['buyerName'] = $teamName;
                    // Deduplication: Only keep latest buy order per team/chemical
                    foreach ($buyOrders as $id => $order) {
                        if ($order['teamId'] === $teamId && $order['chemical'] === $payload['chemical']) {
                            unset($buyOrders[$id]);
                        }
                    }
                    $buyOrders[$payload['id']] = $payload;
                    break;

                case 'remove_buy_order':
                    unset($buyOrders[$payload['id']]);
                    break;

                case 'update_buy_order':
                    if (isset($buyOrders[$payload['id']])) {
                        $buyOrders[$payload['id']] = array_merge($buyOrders[$payload['id']], $payload['updates']);
                    }
                    break;

                case 'add_ad':
                case 'add_listing':
                    $payload['teamId'] = $teamId;
                    $payload['teamName'] = $teamName;
                    // Deduplication: Only keep latest listing per team/chemical
                    foreach ($ads as $id => $ad) {
                        if ($ad['teamId'] === $teamId && $ad['chemical'] === $payload['chemical']) {
                            unset($ads[$id]);
                        }
                    }
                    $ads[$payload['id']] = $payload;
                    break;

                case 'remove_ad':
                case 'remove_listing':
                    unset($ads[$payload['id']]);
                    break;
            }
        }

        return [
            'offers' => array_values($offers),
            'buyOrders' => array_values($buyOrders),
            'ads' => array_values($ads),
            'recentTrades' => $recentTrades
        ];
    }

    /**
     * Get team statistics (optimized with SQL aggregation)
     */
    public function getTeamStatistics() {
        $stats = [];
        $teams = $this->getAllTeams();

        foreach ($teams as $teamInfo) {
            try {
                $storage = new TeamStorage($teamInfo['email']);
                $state = $storage->getState();

                // Success Metric: % improvement over initial production potential
                $initialPotential = $state['profile']['initialProductionPotential'] ?? 0;

                // Get game state to check if production already ran
                require_once __DIR__ . '/SystemStorage.php';
                $system = new SystemStorage();
                $gameStopped = $system->getSystemState()['gameStopped'] ?? true;

                // Current Profit Calculation:
                // During Trading: currentProfit = tradingNet (projected production)
                // After Production: currentProfit = currentFunds (actual production revenue realized)
                $hasProduction = isset($state['profile']['productions']) && count($state['profile']['productions']) > 0;

                if ($hasProduction) {
                    // Production has run: use final cash
                    $currentProfit = $state['profile']['currentFunds'] ?? 0;
                } else {
                    // Still trading: calculate trading net + projected production revenue
                    $currentCash = $state['profile']['currentFunds'] ?? 0;

                    // Calculate Potential Revenue from current inventory
                    $inventoryRevenue = $state['shadowPrices']['maxProfit'] ?? 0;
                    // If maxProfit isn't in shadowPrices, calculate it live
                    if ($inventoryRevenue <= 0) {
                        require_once __DIR__ . '/LPSolver.php';
                        $solver = new LPSolver();
                        $res = $solver->solve($state['inventory']);
                        $inventoryRevenue = $res['maxProfit'];
                    }
                    $currentProfit = $currentCash + $inventoryRevenue;
                }

                $stats[] = [
                    'email' => $teamInfo['email'],
                    'teamName' => $state['profile']['teamName'] ?? $teamInfo['teamName'],
                    'startingFunds' => $initialPotential, // Now represents initial potential
                    'currentFunds' => $currentProfit, // Current profit (trading net + projected or actual production)
                    'percentChange' => $this->calculatePercentChange($initialPotential, $currentProfit),
                    'totalTrades' => count($state['transactions'] ?? []),
                    'inventory' => $state['inventory']
                ];
            } catch (Exception $e) {
                continue;
            }
        }

        usort($stats, fn($a, $b) => $b['percentChange'] <=> $a['percentChange']);
        return $stats;
    }

    private function calculatePercentChange($starting, $current) {
        // If starting is 0, can't calculate percentage
        if ($starting == 0) {
            return 0;
        }
        return (($current - $starting) / $starting) * 100;
    }

    public function getRecentTrades($limit = 20) {
        $allTrades = [];
        $teams = $this->getAllTeams();

        foreach ($teams as $teamInfo) {
            try {
                $storage = new TeamStorage($teamInfo['email']);
                $txns = $storage->getTransactions()['transactions'] ?? [];

                foreach ($txns as $txn) {
                    $txn['teamEmail'] = $teamInfo['email'];
                    $txn['teamName'] = $teamInfo['teamName'];
                    $allTrades[] = $txn;
                }
            } catch (Exception $e) {
                continue;
            }
        }

        usort($allTrades, fn($a, $b) => ($b['timestamp'] ?? 0) - ($a['timestamp'] ?? 0));
        return array_slice($allTrades, 0, $limit);
    }

    /**
     * Get ALL transactions for reporting
     */
    public function getAllTransactions() {
        $allTrades = [];
        $teams = $this->getAllTeams();

        foreach ($teams as $teamInfo) {
            try {
                $storage = new TeamStorage($teamInfo['email']);
                // We only need to check one side of the trade to avoid duplicates if we iterate all teams.
                // However, the event is stored in BOTH teams' logs.
                // Filter: Only include if current team is the 'buyer' (or just use transactionId to dedupe).
                $txns = $storage->getTransactions()['transactions'] ?? [];

                foreach ($txns as $txn) {
                    // Only process if this team was the BUYER to avoid duplicates
                    // (Every trade has 1 buyer and 1 seller)
                    if (($txn['role'] ?? '') === 'buyer') {
                        $txn['buyerName'] = $teamInfo['teamName'];
                        $txn['buyerEmail'] = $teamInfo['email'];
                        
                        // We need to fetch seller name if not in txn
                        // Usually txn has 'counterparty' ID.
                        if (!isset($txn['sellerName'])) {
                            try {
                                $sellerStorage = new TeamStorage($txn['counterparty']);
                                $txn['sellerName'] = $sellerStorage->getTeamName();
                                $txn['sellerEmail'] = $txn['counterparty'];
                            } catch (Exception $e) {
                                $txn['sellerName'] = 'Unknown';
                            }
                        }
                        
                        $allTrades[] = $txn;
                    }
                }
            } catch (Exception $e) {
                continue;
            }
        }

        // Sort by timestamp descending
        usort($allTrades, fn($a, $b) => ($b['timestamp'] ?? 0) - ($a['timestamp'] ?? 0));
        return $allTrades;
    }

    /**
     * Get cached marketplace data if it exists and is recent
     * @param int $maxAgeSeconds Maximum age of cache in seconds
     * @return array|null Cached data or null if not available/stale
     */
    public function getCachedMarketplaceData($maxAgeSeconds = 3) {
        $snapshot = $this->getSnapshot();
        $cacheAge = time() - ($snapshot['updated_at'] ?? 0);

        if ($cacheAge <= $maxAgeSeconds) {
            return [
                'offersByChemical' => $this->getOffersByChemical(),
                'buyOrdersByChemical' => $this->getBuyOrdersByChemical(),
                'recentTrades' => $snapshot['recentTrades'] ?? [],
                'summary' => $this->getMarketplaceSummary()
            ];
        }

        return null;
    }

    /**
     * Cache marketplace data (snapshot is automatically updated)
     */
    public function cacheMarketplaceData() {
        // The snapshot is automatically maintained by generateSnapshot()
        // which is called periodically. No additional caching needed.
        $this->generateSnapshot();
    }

    /**
     * Get marketplace summary statistics
     */
    public function getMarketplaceSummary() {
        $data = $this->getAggregatedFromEvents();

        return [
            'totalOffers' => count($data['offers']),
            'totalBuyOrders' => count($data['buyOrders']),
            'totalAds' => count($data['ads'])
        ];
    }
}
