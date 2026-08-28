<?php
/**
 * Listing Manager - Refactored to use No-M TeamStorage
 */

require_once __DIR__ . '/TeamStorage.php';

class ListingManager {
    private $storage;

    public function __construct($teamEmail, $teamName = null) {
        $this->storage = new TeamStorage($teamEmail);
        if ($teamName) {
            $this->storage->setTeamName($teamName);
        }
    }

    public function getListings() {
        return $this->storage->getListings();
    }

    public function postListing($chemical, $type, $extraData = []) {
        return $this->storage->addListing($chemical, $type, $extraData);
    }

    public function removeListing($adId) {
        $this->storage->removeListing($adId);
        return true;
    }

    public static function getAllListings() {
        require_once __DIR__ . '/MarketplaceAggregator.php';
        $aggregator = new MarketplaceAggregator();
        $data = $aggregator->getAggregatedFromEvents(); // Updated for SQLite

        $allAds = ['buy' => [], 'sell' => []];

        // Map ads to the expected format
        foreach ($data['ads'] as $ad) {
            $type = $ad['type'] ?? 'buy';
            if (isset($allAds[$type])) {
                $allAds[$type][] = $ad;
            }
        }

        return $allAds;
    }

    public static function getListingsByChemical() {
        $allAds = self::getAllListings();

        $byChemical = [
            'C' => ['buy' => [], 'sell' => []],
            'N' => ['buy' => [], 'sell' => []],
            'D' => ['buy' => [], 'sell' => []],
            'Q' => ['buy' => [], 'sell' => []]
        ];

        foreach (['buy', 'sell'] as $type) {
            foreach ($allAds[$type] as $ad) {
                $chemical = $ad['chemical'];
                if (isset($byChemical[$chemical])) {
                    $byChemical[$chemical][$type][] = $ad;
                }
            }
        }

        return $byChemical;
    }
}