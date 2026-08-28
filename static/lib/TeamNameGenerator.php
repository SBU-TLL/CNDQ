<?php
/**
 * Team Name Generator
 * Generates fun, anonymous team names using adjective-animal pairs
 * e.g., "Swift Penguin", "Bold Tiger", "Clever Fox"
 */

class TeamNameGenerator {
    private static $adjectives = [
        'Swift', 'Bold', 'Clever', 'Mighty', 'Brave', 'Quick', 'Silent', 'Wise',
        'Fierce', 'Noble', 'Rapid', 'Sharp', 'Bright', 'Keen', 'Agile', 'Strong',
        'Steady', 'Calm', 'Daring', 'Expert', 'Focused', 'Gifted', 'Hardy', 'Loyal',
        'Alert', 'Astute', 'Crafty', 'Driven', 'Epic', 'Fair', 'Grand', 'Iron',
        'Lucky', 'Nimble', 'Proud', 'Royal', 'Solid', 'True', 'Vital', 'Wild',
        'Ace', 'Cool', 'Elite', 'Prime', 'Slick', 'Smooth', 'Snappy', 'Stellar'
    ];

    private static $animals = [
        'Penguin', 'Tiger', 'Fox', 'Eagle', 'Wolf', 'Bear', 'Falcon', 'Hawk',
        'Lion', 'Panther', 'Raven', 'Shark', 'Dragon', 'Phoenix', 'Cobra', 'Lynx',
        'Otter', 'Panda', 'Raccoon', 'Badger', 'Beaver', 'Cheetah', 'Cougar', 'Jaguar',
        'Leopard', 'Mongoose', 'Wolverine', 'Dolphin', 'Owl', 'Sparrow', 'Viper', 'Python',
        'Gecko', 'Koala', 'Lemur', 'Meerkat', 'Narwhal', 'Octopus', 'Platypus', 'Quokka',
        'Rhino', 'Seal', 'Tortoise', 'Unicorn', 'Walrus', 'Yak', 'Zebra', 'Alpaca'
    ];

    // NPC-specific adjectives by skill level
    private static $npcAdjectives = [
        'beginner' => [
            'Bumbling', 'Clumsy', 'Confused', 'Dizzy', 'Erratic', 'Fumbling',
            'Goofy', 'Hapless', 'Awkward', 'Wobbly', 'Shaky', 'Nervous'
        ],
        'novice' => [
            'Careful', 'Cautious', 'Steady', 'Methodical', 'Prudent', 'Sensible',
            'Thoughtful', 'Measured', 'Balanced', 'Diligent', 'Practical', 'Reliable'
        ],
        'expert' => [
            'Cunning', 'Shrewd', 'Astute', 'Brilliant', 'Elite', 'Master',
            'Prime', 'Legendary', 'Supreme', 'Tactical', 'Strategic', 'Savvy'
        ]
    ];

    // NPC-specific animals by skill level
    private static $npcAnimals = [
        'beginner' => [
            'Sloth', 'Turtle', 'Snail', 'Penguin', 'Platypus', 'Koala',
            'Panda', 'Walrus', 'Manatee', 'Mole', 'Tortoise', 'Capybara'
        ],
        'novice' => [
            'Owl', 'Beaver', 'Fox', 'Raccoon', 'Meerkat', 'Badger',
            'Otter', 'Squirrel', 'Deer', 'Rabbit', 'Chipmunk', 'Lemur'
        ],
        'expert' => [
            'Eagle', 'Falcon', 'Hawk', 'Wolf', 'Tiger', 'Dragon',
            'Phoenix', 'Panther', 'Jaguar', 'Viper', 'Cobra', 'Shark'
        ]
    ];

    /**
     * Generate a unique team name based on a seed (user ID)
     * Same seed always produces same name (deterministic)
     *
     * @param string $seed User identifier (email, eppn, persistent-id, etc.)
     * @return string Team name like "Swift Penguin"
     */
    public static function generate($seed) {
        // Use hash for deterministic random selection
        $hash = md5($seed);

        // Convert hash portions to integers for indexing
        $adjectiveIndex = hexdec(substr($hash, 0, 8)) % count(self::$adjectives);
        $animalIndex = hexdec(substr($hash, 8, 8)) % count(self::$animals);

        return self::$adjectives[$adjectiveIndex] . ' ' . self::$animals[$animalIndex];
    }

    /**
     * Generate a team name with optional uniqueness check
     * If the generated name already exists, append a number
     *
     * @param string $seed User identifier
     * @param array $existingNames Array of already-taken team names
     * @param string|null $skillLevel For NPCs: 'beginner', 'novice', or 'expert'
     * @return string Unique team name
     */
    public static function generateUnique($seed, $existingNames = [], $skillLevel = null) {
        if ($skillLevel !== null) {
            $baseName = self::generateNPCName($skillLevel);
        } else {
            $baseName = self::generate($seed);
        }

        // If no collision, return as-is
        if (!in_array($baseName, $existingNames)) {
            return $baseName;
        }

        // Add number suffix if collision occurs
        $counter = 2;
        while (in_array($baseName . ' ' . $counter, $existingNames)) {
            $counter++;
        }

        return $baseName . ' ' . $counter;
    }

    /**
     * Generate an NPC name based on skill level
     * Uses skill-themed adjectives and animals
     *
     * @param string $skillLevel 'beginner', 'novice', or 'expert'
     * @return string NPC team name
     */
    public static function generateNPCName($skillLevel) {
        if (!isset(self::$npcAdjectives[$skillLevel])) {
            $skillLevel = 'beginner'; // Default to beginner if invalid
        }

        $adjectives = self::$npcAdjectives[$skillLevel];
        $animals = self::$npcAnimals[$skillLevel];

        $adjIndex = array_rand($adjectives);
        $animIndex = array_rand($animals);

        return $adjectives[$adjIndex] . ' ' . $animals[$animIndex];
    }

    /**
     * Get all possible combinations count
     * @return int Total possible team names
     */
    public static function getTotalCombinations() {
        return count(self::$adjectives) * count(self::$animals);
    }
}
