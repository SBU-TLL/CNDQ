<?php
/**
 * NPCTradingStrategy - Abstract base class for NPC trading strategies
 *
 * Defines the interface for NPC trading logic and provides shared utilities
 */

require_once __DIR__ . '/TeamStorage.php';
require_once __DIR__ . '/MarketplaceAggregator.php';
require_once __DIR__ . '/TradeExecutor.php';
require_once __DIR__ . '/LPSolver.php';

abstract class NPCTradingStrategy
{
    protected $storage;
    protected $npc;
    protected $npcManager;
    protected $profile;
    protected $inventory;

    /**
     * Constructor
     *
     * @param TeamStorage $storage Team storage for this NPC
     * @param array $npc NPC configuration
     * @param NPCManager $npcManager Reference to NPC manager
     */
    public function __construct($storage, $npc, $npcManager)
    {
        $this->storage = $storage;
        $this->npc = $npc;
        $this->npcManager = $npcManager;
        $this->profile = $storage->getProfile();
        $this->inventory = $storage->getInventory();
    }

    /**
     * Decide what trade action to take
     * Must be implemented by concrete strategies
     *
     * @return array|null Trade action or null if no action
     */
    abstract public function decideTrade();

    /**
     * Respond to incoming negotiations
     * Must be implemented by concrete strategies
     *
     * @return array|null Negotiation response action or null
     */
    abstract public function respondToNegotiations();

    /**
     * Clear cached shadow prices to force recalculation
     */
    public function clearShadowPrices()
    {
        // Concrete strategies like ExpertStrategy use this to reset their internal cache
        if (property_exists($this, 'shadowPrices')) {
            $this->shadowPrices = null;
        }
        if (property_exists($this, 'ranges')) {
            $this->ranges = null;
        }
    }

    /**
     * Check if NPC has any trades pending reflection (unfinalized)
     * 
     * @return bool
     */
    protected function hasPendingReflections()
    {
        $state = $this->storage->getState();
        foreach ($state['transactions'] ?? [] as $txn) {
            if (!empty($txn['isPendingReflection'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if NPC should trade based on inventory levels
     *
     * @return bool
     */
    protected function shouldTradeBasedOnInventory()
    {
        $lowThreshold = $this->npc['tradeThresholds']['lowInventory'];
        $excessThreshold = $this->npc['tradeThresholds']['excessInventory'];

        foreach (['C', 'N', 'D', 'Q'] as $chemical) {
            $amount = $this->inventory[$chemical] ?? 0;

            if ($amount < $lowThreshold || $amount > $excessThreshold) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get current market offers for all chemicals
     *
     * @return array Offers grouped by chemical
     */
    protected function getMarketOffers()
    {
        $aggregator = new MarketplaceAggregator();
        $offersByChemical = $aggregator->getOffersByChemical();

        // Filter out this NPC's own offers
        $filteredOffers = [];
        foreach ($offersByChemical as $chemical => $offers) {
            $filteredOffers[$chemical] = array_filter($offers, function ($offer) {
                return $offer['sellerId'] !== $this->npc['email'];
            });
        }

        return $filteredOffers;
    }

    /**
     * Get current market buy orders for all chemicals
     *
     * @return array Buy orders grouped by chemical
     */
    protected function getMarketBuyOrders()
    {
        $aggregator = new MarketplaceAggregator();
        $buyOrdersByChemical = $aggregator->getBuyOrdersByChemical();

        // Filter out this NPC's own buy orders
        $filteredBuyOrders = [];
        foreach ($buyOrdersByChemical as $chemical => $orders) {
            $filteredBuyOrders[$chemical] = array_filter($orders, function ($order) {
                return $order['buyerId'] !== $this->npc['email'];
            });
        }

        return $filteredBuyOrders;
    }

    /**
     * Check if NPC has sufficient funds for a purchase
     *
     * @param float $totalCost Total cost of purchase
     * @return bool
     */
    protected function hasSufficientFunds($totalCost)
    {
        // Infinite Capital Model: Funds are unlimited, spending into debt is allowed.
        return true;
    }

    /**
     * Check if NPC has sufficient inventory to sell
     *
     * @param string $chemical Chemical type
     * @param float $quantity Quantity to sell
     * @return bool
     */
    protected function hasSufficientInventory($chemical, $quantity)
    {
        $current = $this->inventory[$chemical] ?? 0;
        return $current >= $quantity;
    }

    /**
     * Get chemical with lowest inventory
     *
     * @return string Chemical type (C, N, D, or Q)
     */
    protected function getLowestInventoryChemical()
    {
        $chemicals = ['C' => $this->inventory['C'],
                      'N' => $this->inventory['N'],
                      'D' => $this->inventory['D'],
                      'Q' => $this->inventory['Q']];

        asort($chemicals);
        return key($chemicals);
    }

    /**
     * Get chemical with highest inventory
     *
     * @return string Chemical type (C, N, D, or Q)
     */
    protected function getHighestInventoryChemical()
    {
        $chemicals = ['C' => $this->inventory['C'],
                      'N' => $this->inventory['N'],
                      'D' => $this->inventory['D'],
                      'Q' => $this->inventory['Q']];

        arsort($chemicals);
        return key($chemicals);
    }

    /**
     * Find cheapest offer for a chemical
     *
     * @param string $chemical Chemical type
     * @param array $offers Market offers
     * @return array|null Cheapest offer or null
     */
    protected function findCheapestOffer($chemical, $offers)
    {
        if (empty($offers[$chemical])) {
            return null;
        }

        $cheapest = null;
        foreach ($offers[$chemical] as $offer) {
            if ($cheapest === null || $offer['minPrice'] < $cheapest['minPrice']) {
                $cheapest = $offer;
            }
        }

        return $cheapest;
    }

    /**
     * Find highest buy order for a chemical
     *
     * @param string $chemical Chemical type
     * @param array $buyOrders Market buy orders
     * @return array|null Highest buy order or null
     */
    protected function findHighestBuyOrder($chemical, $buyOrders)
    {
        if (empty($buyOrders[$chemical])) {
            return null;
        }

        $highest = null;
        foreach ($buyOrders[$chemical] as $order) {
            // Already filtered NPCs in getMarketBuyOrders
            if ($highest === null || $order['maxPrice'] > $highest['maxPrice']) {
                $highest = $order;
            }
        }

        return $highest;
    }

    /**
     * Calculate shadow prices and ranges for current inventory
     *
     * @return array [shadowPrices, ranges]
     */
    protected function calculateShadowPrices()
    {
        $solver = new LPSolver();
        $result = $solver->getShadowPrices($this->inventory);

        // Check if shadow prices exist in result
        if (!isset($result['shadowPrices']) || !is_array($result['shadowPrices'])) {
            return null;
        }

        return [
            'shadowPrices' => [
                'C' => $result['shadowPrices']['C'] ?? 0,
                'N' => $result['shadowPrices']['N'] ?? 0,
                'D' => $result['shadowPrices']['D'] ?? 0,
                'Q' => $result['shadowPrices']['Q'] ?? 0
            ],
            'ranges' => $result['ranges'] ?? []
        ];
    }

    /**
     * Round a number to a "human-like" value (multiples of 5, 10, or 25)
     * Real traders rarely offer "53.27 gallons"
     */
    protected function roundToHuman($value, $type = 'quantity')
    {
        if ($type === 'quantity') {
            if ($value >= 100) return round($value / 10) * 10;
            if ($value >= 50) return round($value / 5) * 5;
            return round($value);
        } else {
            // Price: Round to nearest $0.05 or $0.25
            if ($value >= 10) return round($value * 4) / 4; // Nearest $0.25
            return round($value * 20) / 20; // Nearest $0.05
        }
    }

    /**
     * Apply a random "jitter" to a value to prevent robotic precision
     */
    protected function jitter($value, $percent = 0.02)
    {
        $factor = 1.0 + $this->randomFloat(-$percent, $percent);
        return $value * $factor;
    }

    /**
     * Generate random number within range
     */
    protected function randomFloat($min, $max)
    {
        return $min + mt_rand() / mt_getrandmax() * ($max - $min);
    }

    /**
     * Get random chemical
     *
     * @return string Chemical type
     */
    protected function randomChemical()
    {
        $chemicals = ['C', 'N', 'D', 'Q'];
        return $chemicals[array_rand($chemicals)];
    }

    /**
     * Check if a pending negotiation already exists with a specific team for a chemical
     *
     * @param string $teamId Counterparty email
     * @param string $chemical Chemical type
     * @return bool
     */
    protected function hasPendingNegotiationWith($teamId, $chemical)
    {
        $negotiationManager = $this->npcManager->getNegotiationManager();
        $allNegotiations = $negotiationManager->getTeamNegotiations($this->npc['email']);

        foreach ($allNegotiations as $neg) {
            $otherId = ($neg['initiatorId'] === $this->npc['email']) ? $neg['responderId'] : $neg['initiatorId'];
            if ($otherId === $teamId && $neg['chemical'] === $chemical && ($neg['status'] ?? 'pending') === 'pending') {
                return true;
            }
        }

        return false;
    }

    /**
     * Get pending negotiations where it is the NPC's turn to act
     *
     * @return array Pending negotiations
     */
    protected function getPendingNegotiations()
    {
        $negotiationManager = $this->npcManager->getNegotiationManager();
        $allNegotiations = $negotiationManager->getTeamNegotiations($this->npc['email']);

        // Filter to only those where it is MY turn (I did not make the last offer)
        return array_filter($allNegotiations, function($neg) {
            $isPending = ($neg['status'] ?? 'pending') === 'pending';
            $isMyTurn = ($neg['lastOfferBy'] !== $this->npc['email']);
            
            return $isPending && $isMyTurn;
        });
    }

    /**
     * Evaluate an offer based on shadow prices and "greed ratio"
     * 
     * @param string $chemical Chemical type
     * @param float $price Offered price
     * @param string $role NPC role: 'seller' or 'buyer'
     * @return array [
     *   'ratio' => float, 
     *   'quality' => 'steal'|'fair'|'greedy'|'robbery',
     *   'action' => 'accept'|'counter'|'reject'
     * ]
     */
    protected function evaluateOffer($chemical, $price, $role) {
        $shadowPrice = $this->calculateShadowPrices()[$chemical] ?? 2.0;
        
        // Greed ratio: How far is this from our internal value?
        // For seller NPC: higher ratio is better
        // For buyer NPC: lower ratio is better
        if ($role === 'seller') {
            $ratio = $price / max(0.01, $shadowPrice);
            
            if ($ratio >= 1.5) return ['ratio' => $ratio, 'quality' => 'excellent', 'action' => 'accept'];
            if ($ratio >= 1.0) return ['ratio' => $ratio, 'quality' => 'fair', 'action' => 'accept'];
            if ($ratio >= 0.8) return ['ratio' => $ratio, 'quality' => 'greedy', 'action' => 'counter'];
            return ['ratio' => $ratio, 'quality' => 'robbery', 'action' => 'reject'];
        } else {
            // NPC is buying
            $ratio = $price / max(0.01, $shadowPrice);
            
            if ($ratio <= 0.7) return ['ratio' => $ratio, 'quality' => 'excellent', 'action' => 'accept'];
            if ($ratio <= 1.0) return ['ratio' => $ratio, 'quality' => 'fair', 'action' => 'accept'];
            if ($ratio <= 1.2) return ['ratio' => $ratio, 'quality' => 'greedy', 'action' => 'counter'];
            return ['ratio' => $ratio, 'quality' => 'robbery', 'action' => 'reject'];
        }
    }
}
