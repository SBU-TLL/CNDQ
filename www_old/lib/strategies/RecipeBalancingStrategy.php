<?php
/**
 * RecipeBalancingStrategy - EXPERT Level
 *
 * Based on Strategy 3 + 4 from GAME-GUIDE.md:
 * "Reshape inventory to match optimal production ratios"
 * + "Aggressive Haggling"
 *
 * Rules:
 * - Choose Solvent-Heavy or Deicer-Heavy specialization
 * - Target specific chemical ratios for chosen product
 * - Aggressively haggle for best prices (never accept first offer)
 * - Walk away if deal isn't favorable
 *
 * Solvent Recipe: 0.25N + 0.35D + 0.4Q (profit: $3/gal)
 * Deicer Recipe: 0.5C + 0.3N + 0.2D (profit: $2/gal)
 *
 * Variability: Affects specialization choice and haggling aggressiveness
 */

require_once __DIR__ . '/../NPCTradingStrategy.php';

class RecipeBalancingStrategy extends NPCTradingStrategy
{
    private $variability;
    private $specialization; // 'solvent' or 'deicer'

    // Target ratios for Solvent (N:D:Q = 0.25:0.35:0.4 ≈ 5:7:8)
    const SOLVENT_RATIOS = ['N' => 5, 'D' => 7, 'Q' => 8];

    // Target ratios for Deicer (C:N:D = 0.5:0.3:0.2 ≈ 5:3:2)
    const DEICER_RATIOS = ['C' => 5, 'N' => 3, 'D' => 2];

    const MIN_TRADE_QTY = 100;
    const MAX_TRADE_QTY = 400;

    // Haggling parameters
    const INITIAL_LOWBALL_PERCENT = 0.20; // Start 20% below asking
    const INITIAL_HIGHBALL_PERCENT = 0.20; // Start 20% above shadow price
    const MAX_HAGGLE_ROUNDS = 4;

    public function __construct($storage, $npc, $npcManager)
    {
        parent::__construct($storage, $npc, $npcManager);
        $this->variability = $npc['variability'] ?? 0.5;

        // Choose specialization based on current inventory + variability
        $this->specialization = $this->chooseSpecialization();

        error_log("NPC {$this->npc['teamName']}: Specializing in {$this->specialization}");
    }

    /**
     * Choose specialization (Solvent vs Deicer)
     * Based on current inventory and variability
     */
    private function chooseSpecialization()
    {
        // Calculate which product we're naturally better at
        $solventScore = $this->calculateProductScore(self::SOLVENT_RATIOS, ['N', 'D', 'Q']);
        $deicerScore = $this->calculateProductScore(self::DEICER_RATIOS, ['C', 'N', 'D']);

        // Add some randomness based on variability
        $randomFactor = ($this->variability * mt_rand() / mt_getrandmax()) - ($this->variability / 2);

        $adjustedSolventScore = $solventScore * (1 + $randomFactor);
        $adjustedDeicerScore = $deicerScore * (1 - $randomFactor);

        // Prefer Solvent (higher margin) unless Deicer is clearly better
        if ($adjustedSolventScore >= $adjustedDeicerScore * 0.8) {
            return 'solvent';
        } else {
            return 'deicer';
        }
    }

    /**
     * Calculate how well our current inventory matches a product recipe
     */
    private function calculateProductScore($ratios, $chemicals)
    {
        $score = PHP_FLOAT_MAX;

        foreach ($chemicals as $chem) {
            $available = $this->inventory[$chem] ?? 0;
            $ratio = $ratios[$chem];

            // How many units of product can we make with this chemical?
            $unitsFromThisChem = $available / ($ratio / 10); // Normalized

            $score = min($score, $unitsFromThisChem);
        }

        return $score;
    }

    /**
     * Decide what trade action to take
     * Focus on achieving target ratios
     */
    public function decideTrade()
    {
        $shadowPrices = $this->calculateShadowPrices();
        if (!$shadowPrices) {
            error_log("NPC {$this->npc['teamName']}: Shadow prices unavailable");
            return null;
        }

        // Analyze current inventory balance
        $analysis = $this->analyzeInventoryBalance();

        error_log("NPC {$this->npc['teamName']}: Deficit={$analysis['deficit']['chemical']}, Excess={$analysis['excess']['chemical']}");

        // Try to acquire deficit chemical
        $acquireAction = $this->acquireDeficitChemical($analysis['deficit'], $shadowPrices);
        if ($acquireAction) {
            return $acquireAction;
        }

        // Try to sell excess chemical
        $sellAction = $this->sellExcessChemical($analysis['excess'], $shadowPrices);
        if ($sellAction) {
            return $sellAction;
        }

        // Post strategic offers
        if ($this->shouldPostOffer()) {
            return $this->postStrategicOffer($analysis, $shadowPrices);
        }

        return null;
    }

    /**
     * Analyze inventory balance relative to target ratios
     */
    private function analyzeInventoryBalance()
    {
        $targetRatios = ($this->specialization === 'solvent') ? self::SOLVENT_RATIOS : self::DEICER_RATIOS;
        $relevantChems = array_keys($targetRatios);

        // Calculate deviation from target ratios
        $deviations = [];

        foreach ($relevantChems as $chem) {
            $actual = $this->inventory[$chem] ?? 0;
            $targetRatio = $targetRatios[$chem];

            // Normalized deviation
            $deviation = $actual / max(1, $targetRatio);

            $deviations[$chem] = [
                'chemical' => $chem,
                'actual' => $actual,
                'target' => $targetRatio,
                'deviation' => $deviation
            ];
        }

        // Sort to find deficit (lowest deviation) and excess (highest deviation)
        uasort($deviations, function($a, $b) {
            return $a['deviation'] <=> $b['deviation'];
        });

        $deficit = reset($deviations); // Lowest deviation = deficit
        $excess = end($deviations);    // Highest deviation = excess

        return [
            'deficit' => $deficit,
            'excess' => $excess,
            'all' => $deviations
        ];
    }

    /**
     * Acquire chemical we're deficit in
     */
    private function acquireDeficitChemical($deficit, $shadowPrices)
    {
        $chemical = $deficit['chemical'];
        $shadowPrice = $shadowPrices[$chemical] ?? 2.0;

        // Be willing to pay above shadow price for deficit chemicals
        $maxAcceptablePrice = $shadowPrice * (1.3 + $this->variability * 0.3);

        // Look for market offers
        $offers = $this->getMarketOffers();
        $chemOffers = $offers[$chemical] ?? [];

        foreach ($chemOffers as $offer) {
            // Check if already negotiating
            if ($this->hasPendingNegotiationWith($offer['sellerId'], $chemical)) {
                continue;
            }

            $marketPrice = $offer['minPrice'];

            if ($marketPrice <= $maxAcceptablePrice) {
                // Use aggressive haggling - start with lowball
                $initialOffer = $marketPrice * (1 - self::INITIAL_LOWBALL_PERCENT);

                error_log("NPC {$this->npc['teamName']}: ACQUIRE deficit {$chemical}, lowball \${$initialOffer} (asking \${$marketPrice})");

                return [
                    'type' => 'initiate_negotiation',
                    'responderId' => $offer['sellerId'],
                    'responderName' => $offer['sellerName'],
                    'chemical' => $chemical,
                    'quantity' => min($offer['quantity'], $this->getTradeQuantity()),
                    'price' => round($initialOffer, 2),
                    'adId' => $offer['id'] ?? null
                ];
            }
        }

        return null;
    }

    /**
     * Sell chemical we have excess of
     */
    private function sellExcessChemical($excess, $shadowPrices)
    {
        $chemical = $excess['chemical'];
        $shadowPrice = $shadowPrices[$chemical] ?? 2.0;
        $available = $this->inventory[$chemical] ?? 0;

        if ($available < self::MIN_TRADE_QTY) {
            return null;
        }

        // Accept prices above shadow price
        $minAcceptablePrice = $shadowPrice * (1.0 - $this->variability * 0.1);

        // Look for buy orders
        $buyOrders = $this->getMarketBuyOrders();
        $chemBuyOrders = $buyOrders[$chemical] ?? [];

        foreach ($chemBuyOrders as $buyOrder) {
            // Check if already negotiating
            if ($this->hasPendingNegotiationWith($buyOrder['buyerId'], $chemical)) {
                continue;
            }

            $marketPrice = $buyOrder['maxPrice'];

            if ($marketPrice >= $minAcceptablePrice) {
                // Start with highball
                $initialAsk = max($marketPrice, $shadowPrice * (1 + self::INITIAL_HIGHBALL_PERCENT));

                error_log("NPC {$this->npc['teamName']}: SELL excess {$chemical}, highball \${$initialAsk} (bid \${$marketPrice})");

                return [
                    'type' => 'initiate_negotiation',
                    'responderId' => $buyOrder['buyerId'],
                    'responderName' => $buyOrder['buyerName'],
                    'chemical' => $chemical,
                    'quantity' => min($buyOrder['quantity'], $available, $this->getTradeQuantity()),
                    'price' => round($initialAsk, 2),
                    'adId' => $buyOrder['id'] ?? null
                ];
            }
        }

        return null;
    }

    /**
     * Should we post an offer?
     */
    private function shouldPostOffer()
    {
        $postProbability = 0.5 * (1 - $this->variability * 0.3);
        return mt_rand() / mt_getrandmax() < $postProbability;
    }

    /**
     * Post strategic offer based on deficit/excess analysis
     */
    private function postStrategicOffer($analysis, $shadowPrices)
    {
        // Only post buy orders (players don't post sell advertisements)
        return $this->postDeficitBuyOrder($analysis['deficit'], $shadowPrices);
    }

    /**
     * Post buy order for deficit chemical
     */
    private function postDeficitBuyOrder($deficit, $shadowPrices)
    {
        $chemical = $deficit['chemical'];
        $shadowPrice = $shadowPrices[$chemical] ?? 2.0;

        // Start with lowball bid
        $bidPrice = $shadowPrice * (1 - self::INITIAL_LOWBALL_PERCENT + $this->variability * 0.1);

        return [
            'type' => 'create_buy_order',
            'chemical' => $chemical,
            'quantity' => $this->getTradeQuantity(),
            'maxPrice' => round($bidPrice, 2)
        ];
    }

    /**
     * Get trade quantity
     */
    private function getTradeQuantity()
    {
        $range = self::MAX_TRADE_QTY - self::MIN_TRADE_QTY;
        $qty = self::MIN_TRADE_QTY + ($range * (1 - $this->variability * 0.3) * mt_rand() / mt_getrandmax());
        return round($qty);
    }

    /**
     * Respond to incoming negotiations with AGGRESSIVE HAGGLING
     * Never accept first offer - always counter at least once
     */
    public function respondToNegotiations()
    {
        $pendingNegotiations = $this->getPendingNegotiations();
        if (empty($pendingNegotiations)) return null;

        $negotiation = array_values($pendingNegotiations)[0];
        $latestOffer = end($negotiation['offers']);
        $offerCount = count($negotiation['offers']);

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

        // Get shadow price and inventory analysis
        $shadowPrices = $this->calculateShadowPrices();
        $shadowPrice = $shadowPrices[$chemical] ?? 2.0;
        $analysis = $this->analyzeInventoryBalance();

        $isDeficit = ($chemical === $analysis['deficit']['chemical']);
        $isExcess = ($chemical === $analysis['excess']['chemical']);

        // RULE: Never accept first offer (aggressive haggling)
        if ($offerCount === 1) {
            error_log("NPC {$this->npc['teamName']}: NEVER accept first offer! Countering...");

            if ($role === 'seller') {
                // Counter with higher price
                $counterPrice = $price * 1.15;
                return [
                    'type' => 'counter_negotiation',
                    'negotiationId' => $negotiation['id'],
                    'quantity' => $quantity,
                    'price' => round($counterPrice, 2)
                ];
            } else {
                // Counter with lower price
                $counterPrice = $price * 0.85;
                return [
                    'type' => 'counter_negotiation',
                    'negotiationId' => $negotiation['id'],
                    'quantity' => $quantity,
                    'price' => round($counterPrice, 2)
                ];
            }
        }

        // After haggling, evaluate strategically
        if ($role === 'buyer') {
            // We're buying
            if ($isDeficit) {
                // Buying deficit chemical - be willing to pay premium
                $maxAcceptable = $shadowPrice * (1.4 + $this->variability * 0.2);

                if ($price <= $maxAcceptable) {
                    error_log("NPC {$this->npc['teamName']}: ACCEPT buy deficit {$chemical} @ \${$price}");
                    return [
                        'type' => 'accept_negotiation',
                        'negotiationId' => $negotiation['id']
                    ];
                } else if ($offerCount < self::MAX_HAGGLE_ROUNDS) {
                    // Continue haggling
                    $counterPrice = ($price + $shadowPrice * 1.2) / 2;
                    return [
                        'type' => 'counter_negotiation',
                        'negotiationId' => $negotiation['id'],
                        'quantity' => $quantity,
                        'price' => round($counterPrice, 2)
                    ];
                }
            } else {
                // Buying non-deficit chemical
                $maxAcceptable = $shadowPrice * (1.1 + $this->variability * 0.1);

                if ($price <= $maxAcceptable) {
                    return [
                        'type' => 'accept_negotiation',
                        'negotiationId' => $negotiation['id']
                    ];
                } else if ($offerCount < self::MAX_HAGGLE_ROUNDS) {
                    $counterPrice = $shadowPrice * 1.0;
                    return [
                        'type' => 'counter_negotiation',
                        'negotiationId' => $negotiation['id'],
                        'quantity' => $quantity,
                        'price' => round($counterPrice, 2)
                    ];
                }
            }

            // Walk away if too expensive
            error_log("NPC {$this->npc['teamName']}: REJECT - too expensive, walking away");
            return [
                'type' => 'reject_negotiation',
                'negotiationId' => $negotiation['id']
            ];

        } else {
            // We're selling
            if ($isExcess) {
                // Selling excess - be willing
                $minAcceptable = $shadowPrice * (0.9 - $this->variability * 0.1);

                if ($price >= $minAcceptable) {
                    error_log("NPC {$this->npc['teamName']}: ACCEPT sell excess {$chemical} @ \${$price}");
                    return [
                        'type' => 'accept_negotiation',
                        'negotiationId' => $negotiation['id']
                    ];
                } else if ($offerCount < self::MAX_HAGGLE_ROUNDS) {
                    $counterPrice = ($price + $shadowPrice) / 2;
                    return [
                        'type' => 'counter_negotiation',
                        'negotiationId' => $negotiation['id'],
                        'quantity' => $quantity,
                        'price' => round($counterPrice, 2)
                    ];
                }
            } else {
                // Selling deficit - very reluctant
                $minAcceptable = $shadowPrice * (1.5 - $this->variability * 0.2);

                if ($price >= $minAcceptable) {
                    error_log("NPC {$this->npc['teamName']}: ACCEPT sell (reluctantly) {$chemical} @ \${$price}");
                    return [
                        'type' => 'accept_negotiation',
                        'negotiationId' => $negotiation['id']
                    ];
                } else if ($offerCount < self::MAX_HAGGLE_ROUNDS) {
                    $counterPrice = $shadowPrice * 1.5;
                    return [
                        'type' => 'counter_negotiation',
                        'negotiationId' => $negotiation['id'],
                        'quantity' => $quantity,
                        'price' => round($counterPrice, 2)
                    ];
                }
            }

            // Walk away if too low
            error_log("NPC {$this->npc['teamName']}: REJECT - too low, walking away");
            return [
                'type' => 'reject_negotiation',
                'negotiationId' => $negotiation['id']
            ];
        }
    }
}
