<?php
/**
 * NPCStrategyFactory - Factory for creating NPC trading strategies
 *
 * Maps skill levels to strategy implementations with configurable variability
 */

class NPCStrategyFactory
{
    // Strategy mapping (can be configured via admin)
    const STRATEGY_MAP = [
        'beginner' => 'ShadowPriceArbitrageStrategy',
        'novice' => 'BottleneckEliminationStrategy',
        'expert' => 'RecipeBalancingStrategy'
    ];

    // Global variability (0.0 to 1.0) - affects all NPCs
    private static $globalVariability = 0.5;

    /**
     * Set global variability for all NPCs
     *
     * @param float $variability Value between 0.0 and 1.0
     */
    public static function setGlobalVariability($variability)
    {
        self::$globalVariability = max(0.0, min(1.0, $variability));
    }

    /**
     * Get current global variability
     *
     * @return float
     */
    public static function getGlobalVariability()
    {
        return self::$globalVariability;
    }

    /**
     * Create strategy instance for an NPC
     *
     * @param TeamStorage $storage Team storage for this NPC
     * @param array $npc NPC configuration
     * @param NPCManager $npcManager Reference to NPC manager
     * @return NPCTradingStrategy Strategy instance
     * @throws Exception If strategy class not found
     */
    public static function createStrategy($storage, $npc, $npcManager)
    {
        $skillLevel = $npc['skillLevel'] ?? 'beginner';

        // Get strategy class name
        $strategyClass = self::STRATEGY_MAP[$skillLevel] ?? 'ShadowPriceArbitrageStrategy';

        // Load strategy file
        $strategyFile = __DIR__ . '/strategies/' . $strategyClass . '.php';

        if (!file_exists($strategyFile)) {
            error_log("Strategy file not found: $strategyFile, falling back to NPCTradingStrategy");

            // Fallback to old strategies if new ones don't exist
            $fallbackMap = [
                'beginner' => 'BeginnerStrategy',
                'novice' => 'NoviceStrategy',
                'expert' => 'ExpertStrategy'
            ];

            $strategyClass = $fallbackMap[$skillLevel] ?? 'BeginnerStrategy';
            $strategyFile = __DIR__ . '/strategies/' . $strategyClass . '.php';
        }

        require_once $strategyFile;

        // Add variability to NPC config (combination of global + individual randomness)
        $individualVariability = mt_rand() / mt_getrandmax(); // 0.0 to 1.0
        $combinedVariability = (self::$globalVariability + $individualVariability) / 2;

        // Inject variability into NPC config
        $npc['variability'] = $combinedVariability;

        error_log("NPCStrategyFactory: Creating {$strategyClass} for {$npc['teamName']} (variability={$combinedVariability})");

        // Instantiate strategy
        return new $strategyClass($storage, $npc, $npcManager);
    }

    /**
     * Get strategy name for a skill level
     *
     * @param string $skillLevel
     * @return string Strategy class name
     */
    public static function getStrategyName($skillLevel)
    {
        return self::STRATEGY_MAP[$skillLevel] ?? 'ShadowPriceArbitrageStrategy';
    }

    /**
     * Get all available strategies
     *
     * @return array Map of skill level => strategy class name
     */
    public static function getAllStrategies()
    {
        return self::STRATEGY_MAP;
    }

    /**
     * Load variability from config
     *
     * @param Database $db Database instance
     */
    public static function loadVariabilityFromConfig($db)
    {
        $row = $db->queryOne(
            'SELECT value FROM config WHERE key = ?',
            ['npc_variability']
        );

        if ($row) {
            $variability = (float)$row['value'];
            self::setGlobalVariability($variability);
            error_log("NPCStrategyFactory: Loaded global variability = {$variability}");
        } else {
            // Save default
            self::saveVariabilityToConfig($db, 0.5);
        }
    }

    /**
     * Save variability to config
     *
     * @param Database $db Database instance
     * @param float $variability Value to save
     */
    public static function saveVariabilityToConfig($db, $variability)
    {
        $db->execute(
            'INSERT OR REPLACE INTO config (key, value, updated_at) VALUES (?, ?, ?)',
            ['npc_variability', (string)$variability, time()]
        );

        self::setGlobalVariability($variability);
    }
}
