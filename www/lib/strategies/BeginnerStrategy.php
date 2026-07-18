<?php
/**
 * BeginnerStrategy - Random trading logic for beginner NPCs
 *
 * Trading behavior:
 * - 30% chance to trade each cycle
 * - Random buy/sell decisions
 * - Safety limits: Max 30% funds per trade, max 40% inventory
 * - Random prices between $1.50-$5.00/gal
 */

require_once __DIR__ . '/../NPCTradingStrategy.php';
require_once __DIR__ . '/../MarketplaceAggregator.php';

class BeginnerStrategy extends NPCTradingStrategy
{
    const TRADE_PROBABILITY = 1.0;      // 100% chance to trade in simulation
    const MAX_FUNDS_PERCENT = 0.8;      // Aggressive
    const MAX_INVENTORY_PERCENT = 0.8;  // Aggressive
    const MIN_PRICE = 0.50;
    const MAX_PRICE = 20.00;

    /**
     * Decide what trade action to take
     * Beginners trade randomly with safety limits
     *
     * @return array|null Trade action or null
     */
    public function decideTrade()
    {
        // 1. Try to respond to market ads first (Sell to Players)
        $adResponse = $this->respondToMarketAds();
        if ($adResponse) {
            return $adResponse;
        }

        // 2. If no ads to respond to, create a "Bad" Buy Order (Buy from Players at HIGH price)
        // 50% chance to post a buy order if we have funds
        if (mt_rand(0, 100) < 50) {
            return $this->createBadBuyOrder();
        }

        return null;
    }

    /**
     * Create a buy order with a HIGH price (easy profit for players)
     */
    private function createBadBuyOrder()
    {
        $chemicals = ['C', 'N', 'D', 'Q'];
        $chemical = $chemicals[array_rand($chemicals)];
        
        $quantity = mt_rand(50, 200);
        $shadowPrices = $this->calculateShadowPrices();
        $basePrice = $shadowPrices[$chemical] ?? 5.0;
        
        // "Bad" Strategy: Offer to buy at 150% - 200% of value!
        // If shadow price is 0 (degenerate), assume a base value of $5
        if ($basePrice <= 0.1) $basePrice = 5.0;
        
        $maxPrice = round($basePrice * (mt_rand(150, 200) / 100), 2);
        
        // Ensure we can afford it (infinite capital model allows debt, but let's be somewhat realistic? No, infinite capital!)
        // Actually, TradeExecutor checks funds? No, I removed that.
        // But Strategy hasSufficientFunds check?
        // Let's bypass hasSufficientFunds for Beginner since they are meant to be liquidity providers.
        
        return [
            'type' => 'create_buy_order',
            'chemical' => $chemical,
            'quantity' => $quantity,
            'maxPrice' => $maxPrice
        ];
    }

    /**
     * Scan marketplace for human ads and initiate negotiation
     */
    private function respondToMarketAds()
    {
        $aggregator = new MarketplaceAggregator();
        $buyAds = $aggregator->getActiveBuyOrders();

        if (empty($buyAds)) {
            return null;
        }

        foreach ($buyAds as $ad) {
            // Check if already negotiating with this team for this chemical
            if ($this->hasPendingNegotiationWith($ad['buyerId'], $ad['chemical'])) {
                continue;
            }

            // Don't trade with yourself
            if ($ad['buyerId'] === $this->npc['email']) {
                continue;
            }

            $chemical = $ad['chemical'];
            $available = $this->inventory[$chemical] ?? 0;

            // Always respond if we have anything (or even if we don't, we can go negative? No, inventory check remains)
            // But let's assume we have some.
            // If we don't have enough, we can't sell.
            if ($available < 10) continue;

            // "Bad" Seller Strategy: Offer to sell at LOW price!
            // 80% of what they are asking, or 50% of shadow price, whichever is LOWER.
            $shadowPrices = $this->calculateShadowPrices();
            $shadowPrice = $shadowPrices[$chemical] ?? 5.0;
            if ($shadowPrice <= 0.1) $shadowPrice = 2.0;

            $theirMax = $ad['maxPrice'];
            
            // We undercut ourselves significantly
            $offerPrice = round(min($theirMax * 0.8, $shadowPrice * 0.8), 2);
            $offerQty = min($ad['quantity'], $available);

            if ($offerQty < 1) continue;

            error_log("NPC {$this->npc['teamName']} responding to buy ad for $chemical from {$ad['buyerName']} with LOW price $offerPrice");

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

        return null;
    }

    /**
     * Respond to incoming negotiations
     * For simulation, accept if we have enough resources
     */
    public function respondToNegotiations()
    {
        $pendingNegotiations = $this->getPendingNegotiations();

        // Filter out negotiations where NPC made the last offer (cannot accept own offer)
        $respondableNegotiations = array_filter($pendingNegotiations, function($neg) {
            return $neg['lastOfferBy'] !== $this->npc['email'];
        });

        if (empty($respondableNegotiations)) return null;

        $negotiation = array_values($respondableNegotiations)[0];
        $latestOffer = end($negotiation['offers']);
        $offerCount = count($negotiation['offers']);
        
        $chemical = $negotiation['chemical'];
        $quantity = $latestOffer['quantity'];
        $price = $latestOffer['price'];
        $type = $negotiation['type'] ?? 'buy';

        // Check feasibility
        if ($type === 'buy') {
            // Player wants to buy from NPC (NPC is seller)
            if (!$this->hasSufficientInventory($chemical, $quantity)) {
                return [
                    'type' => 'reject_negotiation',
                    'negotiationId' => $negotiation['id']
                ];
            }
        } else {
            // Player wants to sell to NPC (NPC is buyer)
            // Infinite Capital Model: NPCs also have infinite credit lines
            /*
            if (!$this->hasSufficientFunds($quantity * $price)) {
                return [
                    'type' => 'reject_negotiation',
                    'negotiationId' => $negotiation['id']
                ];
            }
            */
        }
        
        // Determine role
        $role = ($type === 'buy') ? 'seller' : 'buyer';
        
        $shadowPrices = $this->calculateShadowPrices();
        $shadowPrice = $shadowPrices[$chemical] ?? 0;
        if ($shadowPrice <= 0.1) $shadowPrice = 3.0; // Default base value

        // FORCE HAGGLE TESTING:
        // If this is the very first offer from the player AND NOT from an ad, ALWAYS counter.
        // This ensures the "Haggle" UI is triggered for testing purposes.
        if ($offerCount === 1 && empty($negotiation['adId'])) {
             $counterPrice = ($role === 'seller') ? ($price * 1.2) : ($price * 0.8);
             // Ensure counter is reasonable
             $counterPrice = round($counterPrice, 2);
             
             error_log("NPC FORCE COUNTER for testing: $price -> $counterPrice");
             
             return [
                'type' => 'counter_negotiation',
                'negotiationId' => $negotiation['id'],
                'quantity' => $quantity,
                'price' => $counterPrice
             ];
        }

        // If negotiation is from an Ad, we should generally accept it if it matches our request
        if (!empty($negotiation['adId'])) {
             // We (NPC) posted the ad.
             // If we posted a Buy Ad (type='sell' from user), we wanted to BUY at specific price.
             // If we posted a Sell Ad (type='buy' from user), we wanted to SELL at specific price.
             
             // Beginner Strategy: Just accept it if it's from an ad. We are liquidity providers.
             return [
                'type' => 'accept_negotiation',
                'negotiationId' => $negotiation['id']
            ];
        }

        // "Bad" Strategy Evaluation: Be very generous to players
        $action = 'reject';
        
        if ($role === 'seller') {
            // NPC is selling. 
            // Accept anything > 50% of value (Player gets cheap goods)
            if ($price >= $shadowPrice * 0.5) {
                $action = 'accept';
            } else {
                $action = 'counter'; // Too low even for beginner
            }
        } else {
            // NPC is buying.
            // Accept anything < 150% of value (Player sells for high profit)
            if ($price <= $shadowPrice * 1.5) {
                $action = 'accept';
            } else {
                $action = 'counter'; // Too high
            }
        }
        
        if ($action === 'accept') {
             return [
                'type' => 'accept_negotiation',
                'negotiationId' => $negotiation['id']
            ];
        } elseif ($action === 'counter') {
             // Counter with a still-generous offer
             $counterPrice = ($role === 'seller') ? ($shadowPrice * 0.6) : ($shadowPrice * 1.3);
             
             return [
                'type' => 'counter_negotiation',
                'negotiationId' => $negotiation['id'],
                'quantity' => $quantity,
                'price' => round($counterPrice, 2)
             ];
        } else {
             return [
                'type' => 'reject_negotiation',
                'negotiationId' => $negotiation['id']
            ];
        }
    }
}
