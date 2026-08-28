<?php
/**
 * BottleneckEliminationStrategy - INTERMEDIATE Level
 *
 * Based on Strategy 2 from GAME-GUIDE.md:
 * "Identify and eliminate your production bottlenecks"
 *
 * Rules:
 * - Find chemical with HIGHEST shadow price (bottleneck)
 * - Aggressively acquire more of that chemical
 * - Sell chemicals with LOW shadow prices to fund purchases
 * - Recalculate and repeat
 *
 * This strategy directly attacks constraints and has high profit potential.
 *
 * Variability: Affects how aggressively we pursue bottlenecks
 */

require_once __DIR__ . '/../NPCTradingStrategy.php';

class BottleneckEliminationStrategy extends NPCTradingStrategy
{
    private $variability;

    // Thresholds for identifying bottlenecks
    const HIGH_VALUE_THRESHOLD = 2.0;  // Shadow price must be > $2 to be a bottleneck
    const LOW_VALUE_THRESHOLD = 1.0;   // Shadow price < $1 is "excess"
    const AGGRESSIVE_MULTIPLIER = 1.5; // Will pay up to 150% of shadow price

    const MIN_TRADE_QTY = 100;
    const MAX_TRADE_QTY = 500;

    public function __construct($storage, $npc, $npcManager)
    {
        parent::__construct($storage, $npc, $npcManager);
        $this->variability = $npc['variability'] ?? 0.5;
    }

    /**
     * Decide what trade action to take
     * Focus on eliminating highest bottleneck
     */
    public function decideTrade()
    {
        $shadowPrices = $this->calculateShadowPrices();
        if (!$shadowPrices) {
            error_log("NPC {$this->npc['teamName']}: Shadow prices unavailable");
            return null;
        }

        // 1. Identify bottleneck (highest shadow price)
        $bottleneck = $this->identifyBottleneck($shadowPrices);

        // 2. Identify excess (lowest shadow price)
        $excess = $this->identifyExcess($shadowPrices);

        error_log("NPC {$this->npc['teamName']}: Bottleneck={$bottleneck['chemical']} (${$bottleneck['price']}), Excess={$excess['chemical']} (${$excess['price']})");

        // 3. Try to acquire bottleneck chemical
        $acquireAction = $this->acquireBottleneck($bottleneck, $shadowPrices);
        if ($acquireAction) {
            return $acquireAction;
        }

        // 4. Try to sell excess chemical
        $sellAction = $this->sellExcess($excess, $shadowPrices);
        if ($sellAction) {
            return $sellAction;
        }

        // 5. If no immediate opportunities, post strategic offers
        if ($this->shouldPostStrategicOffer()) {
            return $this->postStrategicOffer($bottleneck, $excess, $shadowPrices);
        }

        return null;
    }

    /**
     * Identify the bottleneck chemical (highest shadow price)
     */
    private function identifyBottleneck($shadowPrices)
    {
        $highest = ['chemical' => 'C', 'price' => 0];

        foreach ($shadowPrices as $chem => $price) {
            if ($price > $highest['price']) {
                $highest = ['chemical' => $chem, 'price' => $price];
            }
        }

        return $highest;
    }

    /**
     * Identify excess chemical (lowest shadow price)
     */
    private function identifyExcess($shadowPrices)
    {
        $lowest = ['chemical' => 'C', 'price' => PHP_FLOAT_MAX];

        foreach ($shadowPrices as $chem => $price) {
            $available = $this->inventory[$chem] ?? 0;

            // Only consider selling if we have significant inventory
            if ($price < $lowest['price'] && $available > 200) {
                $lowest = ['chemical' => $chem, 'price' => $price];
            }
        }

        return $lowest;
    }

    /**
     * Aggressively acquire bottleneck chemical
     */
    private function acquireBottleneck($bottleneck, $shadowPrices)
    {
        $chemical = $bottleneck['chemical'];
        $shadowPrice = $bottleneck['price'];

        // Skip if not actually a bottleneck
        if ($shadowPrice < self::HIGH_VALUE_THRESHOLD) {
            return null;
        }

        // Calculate how aggressive we should be (variability affects this)
        $aggressiveness = self::AGGRESSIVE_MULTIPLIER * (1 + $this->variability * 0.5);
        $maxAcceptablePrice = $shadowPrice * $aggressiveness;

        // Look for offers in the market
        $offers = $this->getMarketOffers();
        $chemOffers = $offers[$chemical] ?? [];

        foreach ($chemOffers as $offer) {
            $marketPrice = $offer['minPrice'];

            if ($marketPrice <= $maxAcceptablePrice) {
                $quantity = min($offer['quantity'], $this->getTradeQuantity());

                error_log("NPC {$this->npc['teamName']}: ACQUIRE bottleneck {$chemical} @ ${marketPrice} (willing to pay up to ${maxAcceptablePrice})");

                return [
                    'type' => 'initiate_negotiation',
                    'responderId' => $offer['sellerId'],
                    'responderName' => $offer['sellerName'],
                    'chemical' => $chemical,
                    'quantity' => $quantity,
                    'price' => $marketPrice,
                    'adId' => $offer['id'] ?? null
                ];
            }
        }

        // Check buy orders (someone else wants it, compete?)
        $buyOrders = $this->getMarketBuyOrders();
        $chemBuyOrders = $buyOrders[$chemical] ?? [];

        // If there are competing buy orders, we might need to bid higher
        // For now, just post our own buy order

        return null;
    }

    /**
     * Sell excess chemical
     */
    private function sellExcess($excess, $shadowPrices)
    {
        $chemical = $excess['chemical'];
        $shadowPrice = $excess['price'];
        $available = $this->inventory[$chemical] ?? 0;

        // Don't sell if we don't have much
        if ($available < self::MIN_TRADE_QTY) {
            return null;
        }

        // We'll sell at ANY price above shadow price * (1 - variability)
        // More variability = more willing to accept lower prices
        $minAcceptablePrice = $shadowPrice * (1 - $this->variability * 0.3);

        // Look for buy orders
        $buyOrders = $this->getMarketBuyOrders();
        $chemBuyOrders = $buyOrders[$chemical] ?? [];

        foreach ($chemBuyOrders as $buyOrder) {
            $marketPrice = $buyOrder['maxPrice'];

            if ($marketPrice >= $minAcceptablePrice) {
                $quantity = min($buyOrder['quantity'], $available, $this->getTradeQuantity());

                error_log("NPC {$this->npc['teamName']}: SELL excess {$chemical} @ ${marketPrice} (shadow: ${shadowPrice})");

                return [
                    'type' => 'initiate_negotiation',
                    'responderId' => $buyOrder['buyerId'],
                    'responderName' => $buyOrder['buyerName'],
                    'chemical' => $chemical,
                    'quantity' => $quantity,
                    'price' => $marketPrice,
                    'adId' => $buyOrder['id'] ?? null
                ];
            }
        }

        return null;
    }

    /**
     * Should we post a strategic offer?
     */
    private function shouldPostStrategicOffer()
    {
        $postProbability = 0.4 * (1 - $this->variability * 0.5);
        return mt_rand() / mt_getrandmax() < $postProbability;
    }

    /**
     * Post strategic buy order for bottleneck
     */
    private function postStrategicOffer($bottleneck, $excess, $shadowPrices)
    {
        // Only post buy orders (players don't post sell advertisements)
        return $this->postBottleneckBuyOrder($bottleneck);
    }

    /**
     * Post buy order for bottleneck chemical
     */
    private function postBottleneckBuyOrder($bottleneck)
    {
        $chemical = $bottleneck['chemical'];
        $shadowPrice = $bottleneck['price'];

        if ($shadowPrice < self::HIGH_VALUE_THRESHOLD) {
            return null;
        }

        // Offer slightly below what we're willing to pay
        $offerPrice = $shadowPrice * (1.3 - $this->variability * 0.2);

        return [
            'type' => 'create_buy_order',
            'chemical' => $chemical,
            'quantity' => $this->getTradeQuantity(),
            'maxPrice' => round($offerPrice, 2)
        ];
    }

    /**
     * Get trade quantity with variability
     */
    private function getTradeQuantity()
    {
        $range = self::MAX_TRADE_QTY - self::MIN_TRADE_QTY;

        // Higher variability = smaller trades
        $qty = self::MIN_TRADE_QTY + ($range * (1 - $this->variability * 0.5) * mt_rand() / mt_getrandmax());

        return round($qty);
    }

    /**
     * Respond to incoming negotiations
     * Evaluate based on whether it helps eliminate bottlenecks
     */
    public function respondToNegotiations()
    {
        $pendingNegotiations = $this->getPendingNegotiations();
        if (empty($pendingNegotiations)) return null;

        $negotiation = array_values($pendingNegotiations)[0];
        $latestOffer = end($negotiation['offers']);

        $chemical = $negotiation['chemical'];
        $quantity = $latestOffer['quantity'];
        $price = $latestOffer['price'];
        $type = $negotiation['type'] ?? 'buy';

        $role = ($type === 'buy') ? 'seller' : 'buyer';

        // Check feasibility
        if ($role === 'seller' && !$this->hasSufficientInventory($chemical, $quantity)) {
            return [
                'type' => 'reject_negotiation',
                'negotiationId' => $negotiation['id']
            ];
        }

        // Get current shadow prices and bottleneck
        $shadowPrices = $this->calculateShadowPrices();
        $shadowPrice = $shadowPrices[$chemical] ?? 2.0;
        $bottleneck = $this->identifyBottleneck($shadowPrices);

        // Strategic decision based on whether this is bottleneck or excess
        $isBottleneck = ($chemical === $bottleneck['chemical']);

        if ($role === 'buyer') {
            // We're buying this chemical
            if ($isBottleneck) {
                // Buying our bottleneck - be aggressive
                $maxAcceptable = $shadowPrice * (1.5 + $this->variability * 0.5);

                if ($price <= $maxAcceptable) {
                    error_log("NPC {$this->npc['teamName']}: ACCEPT buy bottleneck {$chemical} @ ${price}");
                    return [
                        'type' => 'accept_negotiation',
                        'negotiationId' => $negotiation['id']
                    ];
                } else if ($price <= $maxAcceptable * 1.2) {
                    // Counter
                    $counterPrice = $shadowPrice * 1.3;
                    return [
                        'type' => 'counter_negotiation',
                        'negotiationId' => $negotiation['id'],
                        'quantity' => $quantity,
                        'price' => round($counterPrice, 2)
                    ];
                }
            } else {
                // Buying non-bottleneck - standard evaluation
                $maxAcceptable = $shadowPrice * (1.1 + $this->variability * 0.2);

                if ($price <= $maxAcceptable) {
                    return [
                        'type' => 'accept_negotiation',
                        'negotiationId' => $negotiation['id']
                    ];
                }
            }

            // Reject if too expensive
            return [
                'type' => 'reject_negotiation',
                'negotiationId' => $negotiation['id']
            ];

        } else {
            // We're selling this chemical
            if ($isBottleneck) {
                // Selling our bottleneck - be very reluctant
                $minAcceptable = $shadowPrice * (1.3 - $this->variability * 0.2);

                if ($price >= $minAcceptable) {
                    error_log("NPC {$this->npc['teamName']}: ACCEPT sell bottleneck {$chemical} @ ${price} (reluctantly)");
                    return [
                        'type' => 'accept_negotiation',
                        'negotiationId' => $negotiation['id']
                    ];
                } else {
                    // Counter with high price
                    $counterPrice = $shadowPrice * 1.5;
                    return [
                        'type' => 'counter_negotiation',
                        'negotiationId' => $negotiation['id'],
                        'quantity' => $quantity,
                        'price' => round($counterPrice, 2)
                    ];
                }
            } else {
                // Selling excess - be willing
                $minAcceptable = $shadowPrice * (0.9 - $this->variability * 0.2);

                if ($price >= $minAcceptable) {
                    error_log("NPC {$this->npc['teamName']}: ACCEPT sell excess {$chemical} @ ${price}");
                    return [
                        'type' => 'accept_negotiation',
                        'negotiationId' => $negotiation['id']
                    ];
                } else if ($price >= $minAcceptable * 0.8) {
                    $counterPrice = $shadowPrice * 1.0;
                    return [
                        'type' => 'counter_negotiation',
                        'negotiationId' => $negotiation['id'],
                        'quantity' => $quantity,
                        'price' => round($counterPrice, 2)
                    ];
                }
            }

            // Reject if too low
            return [
                'type' => 'reject_negotiation',
                'negotiationId' => $negotiation['id']
            ];
        }
    }
}
