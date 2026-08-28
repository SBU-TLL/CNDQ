<?php
/**
 * NoviceStrategy - Threshold-based trading logic for novice NPCs
 *
 * Trading behavior:
 * - Trades when inventory is low (<300 gal) or excess (>1800 gal)
 * - Buy threshold: $2.50/gal max
 * - Sell threshold: $3.00/gal min
 * - Methodical approach with fixed price limits
 */

require_once __DIR__ . '/../NPCTradingStrategy.php';
require_once __DIR__ . '/../MarketplaceAggregator.php';

class NoviceStrategy extends NPCTradingStrategy
{
    const BUY_THRESHOLD = 5.00;         // Max price willing to pay
    const SELL_THRESHOLD = 2.00;        // Min price to accept
    const LOW_INVENTORY = 500;          // Trade when inventory below this
    const EXCESS_INVENTORY = 1200;      // Trade when inventory above this
    const BUY_QUANTITY_MIN = 200;       // Minimum buy quantity
    const BUY_QUANTITY_MAX = 400;       // Maximum buy quantity
    const SELL_QUANTITY_MIN = 200;      // Minimum sell quantity
    const SELL_QUANTITY_MAX = 500;      // Maximum sell quantity

    /**
     * Decide what trade action to take
     * Novices trade based on inventory levels and price thresholds
     *
     * @return array|null Trade action or null
     */
    public function decideTrade()
    {
        // First, look for human buy requests (advertisements) to sell into
        $adAction = $this->respondToMarketAds();
        if ($adAction) {
            return $adAction;
        }

        // Check each chemical for any amount above 50 gallons to sell
        foreach (['C', 'N', 'D', 'Q'] as $chemical) {
            $amount = $this->inventory[$chemical] ?? 0;

            if ($amount > 50) {
                // Try to sell remainders
                $action = $this->tryToSell($chemical);
                if ($action) {
                    return $action;
                }
            }
            
            // If inventory is low, try to buy
            if ($amount < self::LOW_INVENTORY) {
                $action = $this->tryToBuy($chemical);
                if ($action) {
                    return $action;
                }
            }
        }

        return null; // No action needed
    }

    /**
     * Try to buy a chemical when inventory is low
     */
    private function tryToBuy($chemical) {
        // Post a buy order with a maximum price
        $quantity = mt_rand(self::BUY_QUANTITY_MIN, self::BUY_QUANTITY_MAX);
        $maxPrice = self::BUY_THRESHOLD;

        if ($this->hasSufficientFunds($quantity * $maxPrice)) {
            return [
                'type' => 'create_buy_order',
                'chemical' => $chemical,
                'quantity' => $quantity,
                'maxPrice' => $maxPrice
            ];
        }
        return null;
    }

    /**
     * Scan the marketplace for human buy requests and initiate negotiations
     */
    private function respondToMarketAds()
    {
        $aggregator = new MarketplaceAggregator();
        $buyAds = $aggregator->getActiveBuyOrders();

        foreach ($buyAds as $ad) {
            // Check if already negotiating
            if ($this->hasPendingNegotiationWith($ad['buyerId'], $ad['chemical'])) {
                continue;
            }

            // Don't trade with yourself
            if ($ad['buyerId'] === $this->npc['email']) {
                continue;
            }

            $chemical = $ad['chemical'];
            $amount = $this->inventory[$chemical] ?? 0;

            // Sell if we have a reasonable remainder (> 50)
            if ($amount > 50) {
                $targetPrice = $ad['maxPrice'] ?? 0;
                
                if ($targetPrice >= self::SELL_THRESHOLD) {
                    // Initiate negotiation
                    $qty = min($ad['quantity'], $amount - 10); // Sell down to 10
                    $qty = max(1, min(self::SELL_QUANTITY_MAX, $qty));
                    
                    if ($qty > 0 && $this->hasSufficientInventory($chemical, $qty)) {
                        return [
                            'type' => 'initiate_negotiation',
                            'responderId' => $ad['buyerId'],
                            'responderName' => $ad['buyerName'],
                            'chemical' => $chemical,
                            'quantity' => $qty,
                            'price' => self::SELL_THRESHOLD,
                            'adId' => $ad['id']
                        ];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Try to sell a chemical remainder
     */
    private function tryToSell($chemical)
    {
        $buyOrders = $this->getMarketBuyOrders();
        $highestBuyOrder = $this->findHighestBuyOrder($chemical, $buyOrders);

        if ($highestBuyOrder && $highestBuyOrder['maxPrice'] >= self::SELL_THRESHOLD) {
            // Don't trade with yourself
            if ($highestBuyOrder['buyerId'] === $this->npc['email']) {
                return null;
            }

            $currentAmount = $this->inventory[$chemical] ?? 0;
            $quantity = min($currentAmount - 10, $highestBuyOrder['quantity']);

            if ($quantity > 0 && $this->hasSufficientInventory($chemical, $quantity)) {
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

        return null;
    }

    /**
     * Respond to incoming negotiations
     * Novices respond based on inventory levels and price thresholds
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

        $negotiation = array_values($respondableNegotiations)[0];
        $latestOffer = end($negotiation['offers']);

        $chemical = $negotiation['chemical'];
        $quantity = $latestOffer['quantity'];
        $price = $latestOffer['price'];
        $currentInventory = $this->inventory[$chemical] ?? 0;
        $type = $negotiation['type'] ?? 'buy';

        // NPC is the initiator of a sell negotiation (RPC posted buy ad, NPC responded with sell offer)
        if ($negotiation['initiatorId'] === $this->npc['email'] && $type === 'sell') {
            // NPC is seller, RPC is buyer. RPC countered our sell offer.
            if ($price >= self::SELL_THRESHOLD * 0.9) { // Accept if price is close to our threshold
                if ($this->hasSufficientInventory($chemical, $quantity)) {
                    return [
                        'type' => 'accept_negotiation',
                        'negotiationId' => $negotiation['id']
                    ];
                }
            }
            // Counter if still have excess and price is below threshold
            if ($currentInventory > self::EXCESS_INVENTORY && $price < self::SELL_THRESHOLD) {
                 $reaction = rand(40, 70); // Displeased
                 $this->npcManager->runTradingCycleAction($this->npc, [
                    'type' => 'add_reaction',
                    'negotiationId' => $negotiation['id'],
                    'level' => $reaction
                 ]);

                 return [
                    'type' => 'counter_negotiation',
                    'negotiationId' => $negotiation['id'],
                    'quantity' => $quantity,
                    'price' => self::SELL_THRESHOLD
                ];
            }

        }
        // NPC is the responder in a negotiation where RPC is offering to sell to NPC or buy from NPC
        else {
            // RPC wants to buy from NPC (NPC is seller)
            if ($type === 'buy') {
                if ($this->hasSufficientInventory($chemical, $quantity) && $price >= self::SELL_THRESHOLD) {
                    return [
                        'type' => 'accept_negotiation',
                        'negotiationId' => $negotiation['id']
                    ];
                }
                // Counter if we have excess and price is too low, but within a reasonable range
                if ($currentInventory > self::EXCESS_INVENTORY && $price >= self::SELL_THRESHOLD * 0.8) {
                    $reaction = rand(30, 60);
                    $this->npcManager->runTradingCycleAction($this->npc, [
                        'type' => 'add_reaction',
                        'negotiationId' => $negotiation['id'],
                        'level' => $reaction
                    ]);

                    return [
                        'type' => 'counter_negotiation',
                        'negotiationId' => $negotiation['id'],
                        'quantity' => $quantity,
                        'price' => self::SELL_THRESHOLD
                    ];
                }
            }
            // RPC wants to sell to NPC (NPC is buyer)
            else {
                // If this is a response to our ad, accept it if price is within our threshold
                // We bypass the inventory check because we explicitly asked for this via an Ad
                if (!empty($negotiation['adId']) && $price <= self::BUY_THRESHOLD) {
                    return [
                        'type' => 'accept_negotiation',
                        'negotiationId' => $negotiation['id']
                    ];
                }

                // Infinite Capital: NPC can buy even with 0 funds (debt)
                /*
                if ($this->hasSufficientFunds($quantity * $price) && $currentInventory < self::LOW_INVENTORY && $price <= self::BUY_THRESHOLD) {
                */
                if ($currentInventory < self::LOW_INVENTORY && $price <= self::BUY_THRESHOLD) {
                    return [
                        'type' => 'accept_negotiation',
                        'negotiationId' => $negotiation['id']
                    ];
                }
                // Counter if we need it but price is a bit high
                // Removed fund check
                if ($currentInventory < self::LOW_INVENTORY && $price <= self::BUY_THRESHOLD * 1.2) {
                    $reaction = rand(30, 60);
                    $this->npcManager->runTradingCycleAction($this->npc, [
                        'type' => 'add_reaction',
                        'negotiationId' => $negotiation['id'],
                        'level' => $reaction
                    ]);

                    return [
                        'type' => 'counter_negotiation',
                        'negotiationId' => $negotiation['id'],
                        'quantity' => $quantity,
                        'price' => self::BUY_THRESHOLD
                    ];
                }
            }
        }
        
        // If we reach here, the previous conditions for acceptance or specific countering weren't met.
        // Instead of flat rejection, try one last counter-offer at our threshold if we still have interest.
        
        if ($type === 'buy') {
            // NPC is seller. If inventory is okay, counter with SELL_THRESHOLD
            if ($this->hasSufficientInventory($chemical, $quantity)) {
                return [
                    'type' => 'counter_negotiation',
                    'negotiationId' => $negotiation['id'],
                    'quantity' => $quantity,
                    'price' => self::SELL_THRESHOLD
                ];
            }
        } else {
            // NPC is buyer. If funds are okay, counter with BUY_THRESHOLD
            // Infinite Capital: NPC can buy even with 0 funds
            /*
            if ($this->hasSufficientFunds($quantity * self::BUY_THRESHOLD)) {
            */
            return [
                'type' => 'counter_negotiation',
                'negotiationId' => $negotiation['id'],
                'quantity' => $quantity,
                'price' => self::BUY_THRESHOLD
            ];
        }

        // Default to reject only if we can't even afford/provide the threshold
        return [
            'type' => 'reject_negotiation',
            'negotiationId' => $negotiation['id']
        ];
    }
}
