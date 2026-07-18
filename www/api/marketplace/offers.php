<?php
/**
 * Marketplace Offers API
 * GET: Returns all active offers, optionally filtered by chemical
 * Query params: ?chemical=C or ?chemical=N,D etc.
 */

require_once __DIR__ . '/../../lib/MarketplaceAggregator.php';
require_once __DIR__ . '/../../userData.php';

header('Content-Type: application/json');

$currentUserEmail = getCurrentUserEmail();

if (!$currentUserEmail || $currentUserEmail === 'dev_user') {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

try {
    $aggregator = new MarketplaceAggregator();

    // Check if we have a recent cache (within 3 seconds)
    $cachedData = $aggregator->getCachedMarketplaceData(3);

    if ($cachedData) {
        $offersByChemical = $cachedData['offersByChemical'];
        $buyOrdersByChemical = $cachedData['buyOrdersByChemical'] ?? [];
        $recentTrades = $cachedData['recentTrades'] ?? [];
        $summary = $cachedData['summary'];
    } else {
        // Regenerate cache
        $offersByChemical = $aggregator->getOffersByChemical();
        $buyOrdersByChemical = $aggregator->getBuyOrdersByChemical();
        $allMarket = $aggregator->getAggregatedFromEvents();
        $recentTrades = $allMarket['recentTrades'] ?? [];
        $summary = $aggregator->getMarketplaceSummary();

        // Cache for next request
        $aggregator->cacheMarketplaceData();
    }

    // Filter by chemical if requested
    $chemicalFilter = $_GET['chemical'] ?? null;
    if ($chemicalFilter) {
        $chemicals = explode(',', $chemicalFilter);
        $filteredOffers = [];
        $filteredBuyOrders = [];
        foreach ($chemicals as $chem) {
            $chem = trim($chem);
            if (isset($offersByChemical[$chem])) {
                $filteredOffers[$chem] = $offersByChemical[$chem];
            }
            if (isset($buyOrdersByChemical[$chem])) {
                $filteredBuyOrders[$chem] = $buyOrdersByChemical[$chem];
            }
        }
        $offersByChemical = $filteredOffers;
        $buyOrdersByChemical = $filteredBuyOrders;
    }

    // Remove offers from the current user (can't buy from yourself)
    foreach ($offersByChemical as $chemical => &$offers) {
        $offers = array_values(array_filter($offers, function($offer) use ($currentUserEmail) {
            return ($offer['teamId'] ?? $offer['sellerId'] ?? '') !== $currentUserEmail;
        }));
    }

    // Remove buy orders from the current user (can't sell to yourself)
    foreach ($buyOrdersByChemical as $chemical => &$buyOrders) {
        $buyOrders = array_values(array_filter($buyOrders, function($buyOrder) use ($currentUserEmail) {
            return ($buyOrder['teamId'] ?? $buyOrder['buyerId'] ?? '') !== $currentUserEmail;
        }));
    }

    echo json_encode([
        'success' => true,
        'offersByChemical' => $offersByChemical,
        'buyOrdersByChemical' => $buyOrdersByChemical,
        'recentTrades' => $recentTrades,
        'summary' => $summary,
        'timestamp' => time()
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
