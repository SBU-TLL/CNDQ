<?php
/**
 * ExpertStrategy - Shadow price-based trading logic for expert NPCs
 *
 * Trading behavior:
 * - Uses LP solver to calculate shadow prices
 * - Buy when market price < shadow price × 0.95 (5% margin)
 * - Sell when market price > shadow price × 1.05 (5% margin)
 * - Recalculates shadow prices every 2 trades
 * - Trades in larger quantities with aggressive pricing
 */

require_once __DIR__ . '/../NPCTradingStrategy.php';
require_once __DIR__ . '/../MarketplaceAggregator.php';

class ExpertStrategy extends NPCTradingStrategy
{
    const BUY_MARGIN = 0.95;            // Buy at 95% of shadow price
    const SELL_MARGIN = 1.05;           // Sell at 105% of shadow price
    const MIN_QUANTITY = 50;            // Minimum trade quantity
    const MAX_QUANTITY = 500;           // Maximum trade quantity
    const RECALC_INTERVAL = 1;          // Recalculate shadow prices after EVERY trade

    protected $shadowPrices = null;
    protected $ranges = null;
    private $tradesSinceRecalc = 0;

    /**
     * Decide what trade action to take
     * Experts use shadow price analysis to identify profitable opportunities
     *
     * @return array|null Trade action or null
     */
    public function decideTrade()
    {
        // Recalculate shadow prices if needed
        if ($this->shadowPrices === null || $this->tradesSinceRecalc >= self::RECALC_INTERVAL) {
            $result = $this->calculateShadowPrices();
            $this->shadowPrices = $result['shadowPrices'] ?? null;
            $this->ranges = $result['ranges'] ?? null;
            $this->tradesSinceRecalc = 0;

            if (!$this->shadowPrices) {
                // If LP solver fails, Expert cannot operate
                return null;
            }
        }

        // 1. Look for human buy requests (advertisements) to sell into
        $adAction = $this->respondToMarketAds();
        if ($adAction) {
            $this->tradesSinceRecalc++;
            return $adAction;
        }

        // 2. Look for opportunities to buy needed chemicals (high shadow price)
        $buyAction = $this->tryToBuy();
        if ($buyAction) {
            $this->tradesSinceRecalc++;
            return $buyAction;
        }

        // 3. Look for opportunities to sell excess chemicals (low shadow price)
        $sellAction = $this->tryToSell();
        if ($sellAction) {
            $this->tradesSinceRecalc++;
            return $sellAction;
        }

        return null; // No profitable opportunities
    }

    /**
     * Scan the marketplace for human buy requests and initiate negotiations
     */
    private function respondToMarketAds()
    {
        $aggregator = new MarketplaceAggregator();
        $buyOrders = $aggregator->getActiveBuyOrders(); 

        // Ensure we have shadow prices
        if ($this->shadowPrices === null) {
            $result = $this->calculateShadowPrices();
            $this->shadowPrices = $result['shadowPrices'] ?? null;
            $this->ranges = $result['ranges'] ?? null;
        }

        if (!$this->shadowPrices) {
            return null;
        }

        foreach ($buyOrders as $ad) {
            // Check if already negotiating
            if ($this->hasPendingNegotiationWith($ad['buyerId'], $ad['chemical'])) {
                continue;
            }

            // Don't trade with yourself
            if ($ad['buyerId'] === $this->npc['email']) {
                continue;
            }

            $chemical = $ad['chemical'];
            $shadowPrice = $this->shadowPrices[$chemical] ?? 0;
            
            // Experts only sell if price > shadow price and have sufficient inventory
            $minSellPrice = $shadowPrice * self::SELL_MARGIN;
            $targetPrice = $ad['maxPrice'] ?? 0;

            if ($targetPrice >= $minSellPrice && $this->hasSufficientInventory($chemical, self::MIN_QUANTITY)) {
                // Found a good buyer! Initiate negotiation
                // EXPERT LOGIC: Only sell what is within the ALLOWABLE DECREASE range
                $allowableDecrease = $this->ranges[$chemical]['allowableDecrease'] ?? 0;
                if ($allowableDecrease < 1) continue;

                // Ensure we never offer for less than a reasonable floor (e.g. $0.50) even if shadow price is 0
                // JITTER: NPCs shouldn't always aim for the exact same margin
                $targetMargin = $this->jitter(self::SELL_MARGIN, 0.03); 
                $offerPrice = $this->roundToHuman(max($shadowPrice * $targetMargin, $targetPrice * 0.98, 0.50), 'price');
                
                // Limit quantity to requested, NPC logic, and sensitivity range
                $offerQty = min(
                    $ad['quantity'], 
                    $this->calculateSellQuantity($chemical, $this->inventory[$chemical]),
                    $allowableDecrease
                );
                $offerQty = $this->roundToHuman($offerQty, 'quantity');

                if ($offerQty >= 1) {
                    return [
                        'type' => 'initiate_negotiation',
                        'responderId' => $ad['buyerId'],
                        'responderName' => $ad['buyerName'],
                        'chemical' => $chemical,
                        'quantity' => $offerQty,
                        'price' => $offerPrice,
                        'adId' => $ad['id']
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Try to buy a chemical when shadow price is high
     * NPC posts a BUY REQUEST to the market
     */
    private function tryToBuy() {
        if ($this->shadowPrices === null) {
            $result = $this->calculateShadowPrices();
            $this->shadowPrices = $result['shadowPrices'] ?? null;
            $this->ranges = $result['ranges'] ?? null;
        }
        if (!$this->shadowPrices) {
            return null;
        }

        foreach (['C', 'N', 'D', 'Q'] as $chemical) {
            $shadowPrice = $this->shadowPrices[$chemical] ?? 0;
            $currentAmount = $this->inventory[$chemical] ?? 0;

            // Only post buy request if shadow price is high and inventory is low
            if ($shadowPrice > 1.0 && $currentAmount < 2000) { 
                // JITTER: Use slightly different margins each time
                $targetMargin = $this->jitter(self::BUY_MARGIN, 0.03);
                $maxBuyPrice = $this->roundToHuman($shadowPrice * $targetMargin, 'price');
                
                // EXPERT LOGIC: Only buy what is within the ALLOWABLE INCREASE range
                $allowableIncrease = $this->ranges[$chemical]['allowableIncrease'] ?? 0;
                if ($allowableIncrease < 1) continue;

                $quantity = min(
                    $this->calculateBuyQuantity($chemical, $currentAmount),
                    $allowableIncrease
                );
                $quantity = $this->roundToHuman($quantity, 'quantity');

                if ($quantity >= 1 && $this->hasSufficientFunds($quantity * $maxBuyPrice)) {
                    return [
                        'type' => 'create_buy_order',
                        'chemical' => $chemical,
                        'quantity' => $quantity,
                        'maxPrice' => $maxBuyPrice
                    ];
                }
            }
        }
        return null;
    }

    /**
     * Try to sell a chemical when it is a remainder
     * Expert NPCs sell anything NOT used in the current optimal production mix
     */
    private function tryToSell() {
        if ($this->shadowPrices === null) {
            $result = $this->calculateShadowPrices();
            $this->shadowPrices = $result['shadowPrices'] ?? null;
            $this->ranges = $result['ranges'] ?? null;
        }
        
        // Use the LP Solver result from calculating shadow prices
        $inventory = $this->inventory;
        $solver = new LPSolver();
        $result = $solver->solve($inventory);
        
        $deicer = $result['deicer'];
        $solvent = $result['solvent'];
        
        // Calculate what will be consumed
        $willConsume = [
            'C' => ($deicer * LPSolver::DEICER_C),
            'N' => ($deicer * LPSolver::DEICER_N) + ($solvent * LPSolver::SOLVENT_N),
            'D' => ($deicer * LPSolver::DEICER_D) + ($solvent * LPSolver::SOLVENT_D),
            'Q' => ($solvent * LPSolver::SOLVENT_Q)
        ];

        foreach (['C', 'N', 'D', 'Q'] as $chemical) {
            $currentAmount = $inventory[$chemical] ?? 0;
            $neededForProduction = $willConsume[$chemical] ?? 0;
            $surplus = $currentAmount - $neededForProduction;

            // If we have more than 1 gallon of leftovers, sell it!
            if ($surplus > 1) {
                // Look for buyers (buy orders)
                $buyOrders = $this->getMarketBuyOrders();
                $highestBuyOrder = $this->findHighestBuyOrder($chemical, $buyOrders);

                if ($highestBuyOrder) {
                    // Don't trade with yourself
                    if ($highestBuyOrder['buyerId'] === $this->npc['email']) {
                        continue;
                    }
                    
                    // EXPERT LOGIC: Respect sensitivity range for surplus dumping
                    $allowableDecrease = $this->ranges[$chemical]['allowableDecrease'] ?? 0;

                    $quantity = min($surplus, $highestBuyOrder['quantity'], $allowableDecrease);
                    if ($quantity >= 1 && $this->hasSufficientInventory($chemical, $quantity)) {
                        return [
                            'type' => 'accept_buy_order',
                            'buyOrderId' => $highestBuyOrder['id'],
                            'buyerId' => $highestBuyOrder['buyerId'],
                            'chemical' => $chemical,
                            'quantity' => $quantity,
                            'price' => $highestBuyOrder['maxPrice']
                        ];
                    }
                }
            }
        }
        return null;
    }


    /**
     * Calculate optimal buy quantity based on shadow price and inventory
     */
    private function calculateBuyQuantity($chemical, $currentAmount)
    {
        // Target inventory level based on shadow price
        // Higher shadow price = more valuable = buy more
        $shadowPrice = $this->shadowPrices[$chemical] ?? 3.0;

        // Calculate target based on value
        $avgShadowPrice = array_sum($this->shadowPrices) / 4;
        $relativeValue = ($avgShadowPrice > 0) ? ($shadowPrice / $avgShadowPrice) : 1.0;

        // More valuable chemicals get higher target inventory
        $targetInventory = 1000 + ($relativeValue * 500);

        $desiredQuantity = max(
            self::MIN_QUANTITY,
            min(self::MAX_QUANTITY, $targetInventory - $currentAmount)
        );

        return floor($desiredQuantity);
    }

    /**
     * Calculate optimal sell quantity based on shadow price and inventory
     */
    private function calculateSellQuantity($chemical, $currentAmount)
    {
        // Sell more of less valuable chemicals
        $shadowPrice = $this->shadowPrices[$chemical] ?? 3.0;

        $avgShadowPrice = array_sum($this->shadowPrices) / 4;
        $relativeValue = ($avgShadowPrice > 0) ? ($shadowPrice / $avgShadowPrice) : 1.0;

        // Less valuable chemicals can be sold more aggressively
        $sellPercent = min(0.5, 0.3 / max(0.1, $relativeValue));

        $desiredQuantity = max(
            self::MIN_QUANTITY,
            min(self::MAX_QUANTITY, $currentAmount * $sellPercent)
        );

        return floor($desiredQuantity);
    }

    /**
     * Respond to incoming negotiations
     * Experts use shadow price analysis and iterative haggling
     */
    public function respondToNegotiations()
    {
        $pendingNegotiations = $this->getPendingNegotiations();

        // Filter out negotiations where NPC made the last offer (cannot accept own offer)
        $respondableNegotiations = array_filter($pendingNegotiations, function($neg) {
            return $neg['lastOfferBy'] !== $this->npc['email'];
        });

        if (empty($respondableNegotiations)) {
            return null;
        }

        // Get time remaining for pragmatic decision making
        require_once __DIR__ . '/../SessionManager.php';
        $sessionState = (new SessionManager())->getState();
        $timeRemaining = $sessionState['timeRemaining'] ?? 300;
        $isTimePressure = ($timeRemaining < 45); // Pragmatic mode: < 45 seconds left

        // Recalculate shadow prices if needed
        if ($this->shadowPrices === null) {
            $result = $this->calculateShadowPrices();
            $this->shadowPrices = $result['shadowPrices'] ?? null;
            $this->ranges = $result['ranges'] ?? null;
            if (!$this->shadowPrices) return null;
        }

        // Only respond to the first respondable negotiation
        $negotiation = array_values($respondableNegotiations)[0];
        $latestOffer = end($negotiation['offers']);

        $chemical = $negotiation['chemical'];
        $quantity = $latestOffer['quantity'];
        $playerPrice = $latestOffer['price'];
        $type = $negotiation['type'] ?? 'buy'; // From initiator's perspective
        $offerCount = count($negotiation['offers'] ?? []);

        $shadowPrice = $this->shadowPrices[$chemical] ?? 0;
        
        // Determine if NPC is buyer or seller
        $npcIsSeller = ($type === 'buy') || ($negotiation['initiatorId'] === $this->npc['email'] && $type === 'sell');

        // Check if the trade is profitable at all
        $isProfitable = false;
        if ($npcIsSeller) {
            $isProfitable = ($playerPrice > $shadowPrice);
        } else {
            $isProfitable = ($playerPrice < $shadowPrice);
        }

        // EXPERT CHECK: Is the quantity within our ALLOWABLE RANGE?
        $range = $this->ranges[$chemical] ?? null;
        $withinRange = true;
        if ($range) {
            if ($npcIsSeller && $quantity > $range['allowableDecrease']) $withinRange = false;
            if (!$npcIsSeller && $quantity > $range['allowableIncrease']) $withinRange = false;
        }

        // 1. PRAGMATIC CLOSING: If time is running out, accept ANY profitable deal within range
        if ($isTimePressure && $isProfitable && $withinRange) {
            error_log("NPC {$this->npc['teamName']} PRAGMATIC ACCEPT: Time low ({$timeRemaining}s), securing profit.");
            return [
                'type' => 'accept_negotiation',
                'negotiationId' => $negotiation['id']
            ];
        }

        // Check inventory for sellers
        if ($npcIsSeller && !$this->hasSufficientInventory($chemical, $quantity)) {
            return [
                'type' => 'reject_negotiation',
                'negotiationId' => $negotiation['id']
            ];
        }

        // Calculate target price ranges
        $calcShadowPrice = max(0.01, $shadowPrice);
        if ($npcIsSeller) {
            $optimalPrice = $calcShadowPrice * self::SELL_MARGIN;
            $absoluteMinPrice = $shadowPrice + 0.05; // Absolute floor is shadow + 5 cents
        } else {
            $optimalPrice = $calcShadowPrice * self::BUY_MARGIN;
            $absoluteMaxPrice = max(0.01, $shadowPrice - 0.05); // Absolute ceiling is shadow - 5 cents
        }

        // 2. ADVERTISEMENT PRIORITY:
        if (!empty($negotiation['adId'])) {
            $isGoodDeal = false;
            if ($npcIsSeller) {
                 if ($playerPrice >= $optimalPrice * 0.95 && $withinRange) $isGoodDeal = true;
            } else {
                 if ($playerPrice <= $optimalPrice * 1.05 && $withinRange) $isGoodDeal = true;
            }

            if ($isGoodDeal) {
                return [
                    'type' => 'accept_negotiation',
                    'negotiationId' => $negotiation['id']
                ];
            }
        }

        // 3. ITERATIVE HAGGLING: 
        $maxRounds = 5; 
        $currentRound = min($offerCount, $maxRounds);
        $compromiseFactor = ($currentRound - 1) / ($maxRounds - 1);

        if ($npcIsSeller) {
            $counterPrice = $optimalPrice - ($compromiseFactor * ($optimalPrice - $playerPrice));
            $counterPrice = max($absoluteMinPrice, min($optimalPrice, $counterPrice));
            // Jitter the counter price slightly and round
            $counterPrice = $this->roundToHuman($this->jitter($counterPrice, 0.01), 'price');

            // Accept if player's price is good enough AND within range
            if ($playerPrice >= $optimalPrice * 0.98 && $withinRange) {
                return ['type' => 'accept_negotiation', 'negotiationId' => $negotiation['id']];
            }

            // If deal is NOT profitable OR out of range, and we've reached max rounds, reject
            if ((!$isProfitable || !$withinRange) && $offerCount >= $maxRounds) {
                return ['type' => 'reject_negotiation', 'negotiationId' => $negotiation['id']];
            }
        } else {
            $counterPrice = $optimalPrice + ($compromiseFactor * ($playerPrice - $optimalPrice));
            $counterPrice = max($optimalPrice, min($absoluteMaxPrice, $counterPrice));
            // Jitter the counter price slightly and round
            $counterPrice = $this->roundToHuman($this->jitter($counterPrice, 0.01), 'price');

            // Accept if player's price is good enough AND within range
            if ($playerPrice <= $optimalPrice * 1.02 && $withinRange) {
                return ['type' => 'accept_negotiation', 'negotiationId' => $negotiation['id']];
            }

            // If deal is NOT profitable OR out of range, and we've reached max rounds, reject
            if ((!$isProfitable || !$withinRange) && $offerCount >= $maxRounds) {
                return ['type' => 'reject_negotiation', 'negotiationId' => $negotiation['id']];
            }
        }

        // Correct quantity if it was out of range for the counter-offer
        $targetQuantity = $quantity;
        if (!$withinRange) {
            if ($npcIsSeller) $targetQuantity = floor($range['allowableDecrease']);
            else $targetQuantity = floor($range['allowableIncrease']);

            $targetQuantity = $this->roundToHuman($targetQuantity, 'quantity');

            // Reject if corrected quantity is trivially small (avoid 1-gallon counter offers)
            if ($targetQuantity < 10) {
                error_log("NPC {$this->npc['teamName']}: REJECT - quantity too small after range correction ({$targetQuantity} gal)");
                return ['type' => 'reject_negotiation', 'negotiationId' => $negotiation['id']];
            }
        }

        // Expert NPCs don't get 'annoyed', but we keep a 0 reaction level to avoid UI issues
        $this->npcManager->runTradingCycleAction($this->npc, [
            'type' => 'add_reaction',
            'negotiationId' => $negotiation['id'],
            'level' => 0
        ]);

        return [
            'type' => 'counter_negotiation',
            'negotiationId' => $negotiation['id'],
            'quantity' => $targetQuantity,
            'price' => $counterPrice
        ];
    }
}
