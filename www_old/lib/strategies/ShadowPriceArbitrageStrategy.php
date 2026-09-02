<?php
/**
 * ShadowPriceArbitrageStrategy - BEGINNER Level
 *
 * Based on Strategy 1 from GAME-GUIDE.md:
 * "Trade chemicals where your valuation differs from market prices"
 *
 * Rules:
 * - BUY when market price < shadow price (gain production value)
 * - SELL when market price > shadow price (get more than it's worth)
 * - Recalculate shadow prices after trades
 * - Simple, reliable, economically sound
 *
 * Variability: Affects margin thresholds and decision randomness
 */

require_once __DIR__ . '/../NPCTradingStrategy.php';

class ShadowPriceArbitrageStrategy extends NPCTradingStrategy
{
    private $variability; // 0.0 to 1.0, affects decision randomness

    // Base thresholds (modified by variability)
    const BASE_BUY_MARGIN = 0.10;   // Buy if price 10% below shadow price
    const BASE_SELL_MARGIN = 0.10;  // Sell if price 10% above shadow price
    const MIN_TRADE_QTY = 50;
    const MAX_TRADE_QTY = 300;

    public function __construct($storage, $npc, $npcManager)
    {
        parent::__construct($storage, $npc, $npcManager);

        // Get global variability from NPC config or default to 0.5
        $this->variability = $npc['variability'] ?? 0.5;
    }

    /**
     * Decide what trade action to take
     * Scan market for arbitrage opportunities
     */
    public function decideTrade()
    {
        $shadowPrices = $this->calculateShadowPrices();
        if (!$shadowPrices) {
            error_log("NPC {$this->npc['teamName']}: Shadow prices unavailable");
            return null;
        }

        // Adjust margins based on variability
        // Higher variability = wider margins = fewer trades
        $buyMargin = self::BASE_BUY_MARGIN * (1 + $this->variability);
        $sellMargin = self::BASE_SELL_MARGIN * (1 + $this->variability);

        // 1. Look for buying opportunities (market price < shadow price)
        $buyOpportunity = $this->findBuyOpportunity($shadowPrices, $buyMargin);
        if ($buyOpportunity) {
            error_log("NPC {$this->npc['teamName']}: BUY opportunity for {$buyOpportunity['chemical']} @ {$buyOpportunity['price']} (shadow: {$shadowPrices[$buyOpportunity['chemical']]})");
            return $buyOpportunity;
        }

        // 2. Look for selling opportunities (market price > shadow price)
        $sellOpportunity = $this->findSellOpportunity($shadowPrices, $sellMargin);
        if ($sellOpportunity) {
            error_log("NPC {$this->npc['teamName']}: SELL opportunity for {$sellOpportunity['chemical']} @ {$sellOpportunity['price']} (shadow: {$shadowPrices[$sellOpportunity['chemical']]})");
            return $sellOpportunity;
        }

        // 3. If no perfect opportunities, occasionally post offers (variability-based)
        if ($this->shouldPostOffer()) {
            return $this->postMarketOffer($shadowPrices);
        }

        return null;
    }

    /**
     * Find a buying opportunity where market price < shadow price
     */
    private function findBuyOpportunity($shadowPrices, $margin)
    {
        $offers = $this->getMarketOffers();

        $opportunities = [];

        foreach (['C', 'N', 'D', 'Q'] as $chemical) {
            $shadowPrice = $shadowPrices[$chemical];

            // Skip if shadow price is too low (non-binding constraint)
            if ($shadowPrice < 0.5) continue;

            $chemOffers = $offers[$chemical] ?? [];

            foreach ($chemOffers as $offer) {
                $marketPrice = $offer['minPrice'];

                // BUY RULE: Buy if market price < (shadow price - margin)
                $maxAcceptablePrice = $shadowPrice * (1 - $margin);

                if ($marketPrice <= $maxAcceptablePrice) {
                    $gain = $shadowPrice - $marketPrice;

                    $opportunities[] = [
                        'type' => 'initiate_negotiation',
                        'responderId' => $offer['sellerId'],
                        'responderName' => $offer['sellerName'],
                        'chemical' => $chemical,
                        'quantity' => min($offer['quantity'], $this->getTradeQuantity()),
                        'price' => $marketPrice,
                        'adId' => $offer['id'] ?? null,
                        'expectedGain' => $gain
                    ];
                }
            }
        }

        // Return best opportunity (highest gain)
        if (!empty($opportunities)) {
            usort($opportunities, function($a, $b) {
                return $b['expectedGain'] <=> $a['expectedGain'];
            });

            return $opportunities[0];
        }

        return null;
    }

    /**
     * Find a selling opportunity where market price > shadow price
     */
    private function findSellOpportunity($shadowPrices, $margin)
    {
        $buyOrders = $this->getMarketBuyOrders();

        $opportunities = [];

        foreach (['C', 'N', 'D', 'Q'] as $chemical) {
            $shadowPrice = $shadowPrices[$chemical];
            $available = $this->inventory[$chemical] ?? 0;

            // Skip if we don't have enough inventory
            if ($available < self::MIN_TRADE_QTY) continue;

            $chemBuyOrders = $buyOrders[$chemical] ?? [];

            foreach ($chemBuyOrders as $buyOrder) {
                $marketPrice = $buyOrder['maxPrice'];

                // SELL RULE: Sell if market price > (shadow price + margin)
                $minAcceptablePrice = $shadowPrice * (1 + $margin);

                if ($marketPrice >= $minAcceptablePrice) {
                    $gain = $marketPrice - $shadowPrice;

                    $opportunities[] = [
                        'type' => 'initiate_negotiation',
                        'responderId' => $buyOrder['buyerId'],
                        'responderName' => $buyOrder['buyerName'],
                        'chemical' => $chemical,
                        'quantity' => min($buyOrder['quantity'], $available, $this->getTradeQuantity()),
                        'price' => $marketPrice,
                        'adId' => $buyOrder['id'] ?? null,
                        'expectedGain' => $gain
                    ];
                }
            }
        }

        // Return best opportunity (highest gain)
        if (!empty($opportunities)) {
            usort($opportunities, function($a, $b) {
                return $b['expectedGain'] <=> $a['expectedGain'];
            });

            return $opportunities[0];
        }

        return null;
    }

    /**
     * Decide whether to post a market offer (variability-based)
     */
    private function shouldPostOffer()
    {
        // Lower variability = more active posting
        $postProbability = 0.3 * (1 - $this->variability);
        return mt_rand() / mt_getrandmax() < $postProbability;
    }

    /**
     * Post a buy order based on shadow prices
     */
    private function postMarketOffer($shadowPrices)
    {
        // Only post buy orders (players don't post sell advertisements)
        return $this->postBuyOrder($shadowPrices);
    }

    /**
     * Post a buy order at slightly below shadow price
     */
    private function postBuyOrder($shadowPrices)
    {
        // Choose chemical with highest shadow price (most valuable to us)
        $chemicalPrices = $shadowPrices;
        arsort($chemicalPrices);

        foreach ($chemicalPrices as $chemical => $shadowPrice) {
            if ($shadowPrice > 1.0) {
                // Offer slightly below shadow price
                $offerPrice = $shadowPrice * (0.95 - $this->variability * 0.1);

                return [
                    'type' => 'create_buy_order',
                    'chemical' => $chemical,
                    'quantity' => $this->getTradeQuantity(),
                    'maxPrice' => round($offerPrice, 2)
                ];
            }
        }

        return null;
    }

    /**
     * Get trade quantity with variability
     */
    private function getTradeQuantity()
    {
        $range = self::MAX_TRADE_QTY - self::MIN_TRADE_QTY;
        $qty = self::MIN_TRADE_QTY + ($range * (1 - $this->variability) * mt_rand() / mt_getrandmax());

        return round($qty);
    }

    /**
     * Respond to incoming negotiations
     * Accept/reject based on shadow price arbitrage
     */
    public function respondToNegotiations()
    {
        $pendingNegotiations = $this->getPendingNegotiations();
        if (empty($pendingNegotiations)) return null;

        // Handle first pending negotiation
        $negotiation = array_values($pendingNegotiations)[0];
        $latestOffer = end($negotiation['offers']);
        $offerCount = count($negotiation['offers']);

        $chemical = $negotiation['chemical'];
        $quantity = $latestOffer['quantity'];
        $price = $latestOffer['price'];
        $type = $negotiation['type'] ?? 'buy';

        // Determine our role
        $role = ($type === 'buy') ? 'seller' : 'buyer';

        // Check feasibility
        if ($role === 'seller' && !$this->hasSufficientInventory($chemical, $quantity)) {
            return [
                'type' => 'reject_negotiation',
                'negotiationId' => $negotiation['id']
            ];
        }

        // Get shadow price
        $shadowPrices = $this->calculateShadowPrices();
        $shadowPrice = $shadowPrices[$chemical] ?? 2.0;

        // Apply variability to acceptance thresholds
        $acceptMargin = 0.05 * (1 + $this->variability);

        // Decide action based on role
        if ($role === 'seller') {
            // We're selling: accept if price >= shadow price (with small margin)
            $minAcceptable = $shadowPrice * (1 - $acceptMargin);

            if ($price >= $minAcceptable) {
                error_log("NPC {$this->npc['teamName']}: ACCEPT sell at ${price} (shadow: ${shadowPrice})");
                return [
                    'type' => 'accept_negotiation',
                    'negotiationId' => $negotiation['id']
                ];
            } else if ($price >= $minAcceptable * 0.8) {
                // Counter with higher price
                $counterPrice = $shadowPrice * (1 + 0.05);
                error_log("NPC {$this->npc['teamName']}: COUNTER sell from ${price} to ${counterPrice}");

                return [
                    'type' => 'counter_negotiation',
                    'negotiationId' => $negotiation['id'],
                    'quantity' => $quantity,
                    'price' => round($counterPrice, 2)
                ];
            } else {
                // Too low, reject
                return [
                    'type' => 'reject_negotiation',
                    'negotiationId' => $negotiation['id']
                ];
            }
        } else {
            // We're buying: accept if price <= shadow price (with small margin)
            $maxAcceptable = $shadowPrice * (1 + $acceptMargin);

            if ($price <= $maxAcceptable) {
                error_log("NPC {$this->npc['teamName']}: ACCEPT buy at ${price} (shadow: ${shadowPrice})");
                return [
                    'type' => 'accept_negotiation',
                    'negotiationId' => $negotiation['id']
                ];
            } else if ($price <= $maxAcceptable * 1.2) {
                // Counter with lower price
                $counterPrice = $shadowPrice * (1 - 0.05);
                error_log("NPC {$this->npc['teamName']}: COUNTER buy from ${price} to ${counterPrice}");

                return [
                    'type' => 'counter_negotiation',
                    'negotiationId' => $negotiation['id'],
                    'quantity' => $quantity,
                    'price' => round($counterPrice, 2)
                ];
            } else {
                // Too high, reject
                return [
                    'type' => 'reject_negotiation',
                    'negotiationId' => $negotiation['id']
                ];
            }
        }
    }
}
