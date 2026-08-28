<?php
/**
 * Session Manager - Refactored for Single-Round "Infinite Capital" Model
 * 
 * Key Concepts:
 * - Single Round: The game consists of one trading period followed by a final production run.
 * - Auto-Cycle: Optional 24/7 mode where the game automatically restarts after finishing.
 * - Finalize Game: Closes the market and runs the final production.
 */

require_once __DIR__ . '/SystemStorage.php';
require_once __DIR__ . '/NPCManager.php';

class SessionManager {
    private $storage;

    public function __construct() {
        $this->storage = new SystemStorage();
    }

    /**
     * Get current session state
     */
    public function getState() {
        $data = $this->storage->getSystemState();
        
        // Ensure defaults if missing
        if (!isset($data['timeRemaining'])) {
            $data['timeRemaining'] = $data['tradingDuration'] ?? 300;
        }

        return $data;
    }

    /**
     * Heartbeat Tick - Drives the world forward based on client activity.
     * Called by status.php (polling).
     */
    public function tick() {
        $data = $this->storage->getSystemState();
        $updates = [];
        $now = time();
        $lastTick = $data['lastTick'] ?? $now;
        
        // RATE LIMIT: Only allow one tick update per second to prevent DB thrashing
        // and race conditions from multiple concurrent clients.
        if ($now - $lastTick < 1) {
            return;
        }
        
        // RATE LIMIT: Only allow one tick update per second to prevent DB thrashing
        // and race conditions from multiple concurrent clients.
        if ($now - $lastTick < 1) {
            return;
        }
        
        // 1. Time Management (Freeze Logic)
        // If the gap since last tick is small (< 10s), we are "live".
        // If the gap is large, we were "frozen", so we don't deduct the huge gap.
        $delta = $now - $lastTick;
        $heartbeatThreshold = 10; 
        
        // Initialize timeRemaining if not present
        // CRITICAL: Only use tradingDuration as fallback if timeRemaining has NEVER been set
        // Once set, always use the stored value to prevent reset bugs
        if (!isset($data['timeRemaining'])) {
            // First time initialization - use tradingDuration
            $timeRemaining = $data['tradingDuration'] ?? 300;
            // Force save this initialization
            $updates['timeRemaining'] = $timeRemaining;
        } else {
            $timeRemaining = $data['timeRemaining'];
        }
        $originalTimeRemaining = $timeRemaining;

        // Only advance time if game is not stopped and not finished
        if (!($data['gameStopped'] ?? true) && !($data['gameFinished'] ?? false)) {
            if ($delta > 0 && $delta < $heartbeatThreshold) {
                // We are live, time flows
                $timeRemaining = max(0, $timeRemaining - $delta);
            } elseif ($delta >= $heartbeatThreshold) {
                // We were frozen. Just resume.
                // Optionally deduct 1 second to acknowledge the tick
                // $timeRemaining = max(0, $timeRemaining - 1);
            }
        }

        $updates['timeRemaining'] = $timeRemaining;
        $updates['lastTick'] = $now;

        // 2. Marketplace Aggregation
        // Always run this to keep the market fresh for the active user
        try {
            require_once __DIR__ . '/MarketplaceAggregator.php';
            $aggregator = new MarketplaceAggregator();
            $aggregator->generateSnapshot();
        } catch (Exception $e) {
            error_log("SessionManager: Aggregation failed: " . $e->getMessage());
        }

        // 3. NPC Logic & Garbage Collection
        if (!($data['gameStopped'] ?? true) && !($data['gameFinished'] ?? false)) {
            // Periodic Garbage Collection (every 60 seconds)
            $lastGC = $data['lastGC'] ?? 0;
            if ($now - $lastGC >= 60) {
                try {
                    require_once __DIR__ . '/NegotiationManager.php';
                    (new NegotiationManager())->cleanupOldNegotiations(1); // Clean 1+ day old
                    $updates['lastGC'] = $now;
                } catch (Exception $e) {}
            }

            $npcSettings = (new NPCManager())->loadConfig();
            if (($npcSettings['enabled'] ?? false)) {
                $lastNpcRun = $data['npcLastRun'] ?? 0;
                
                // Run NPCs every 10 seconds
                if ($now - $lastNpcRun >= 10) {
                    try {
                        require_once __DIR__ . '/NPCManager.php';
                        $npcManager = new NPCManager();
                        
                        // Process Reflections (Pre-NPC)
                        require_once __DIR__ . '/GlobalAggregator.php';
                        $aggregator = new GlobalAggregator(); // Re-instantiate if needed
                        $aggregator->processReflections();

                        // Run Cycle
                        $npcManager->runTradingCycle($data['currentSession']);
                        
                        // Process Reflections (Post-NPC)
                        $aggregator->processReflections();

                        $updates['npcLastRun'] = $now;
                    } catch (Exception $e) {
                        error_log("SessionManager: NPC logic failed: " . $e->getMessage());
                    }
                }
            }
        }

        // 4. Auto-Finalize (Time Ran Out)
        if (($data['autoAdvance'] ?? false) && $timeRemaining <= 0 && !($data['gameStopped'] ?? true) && !($data['gameFinished'] ?? false)) {
            $this->finalizeGame();
            // finalizeGame updates state, so we should refresh or rely on its update.
            // But we might have pending updates in $updates array (like lastTick).
            // We should apply them carefully.
            // Actually, finalizeGame sets gameStopped=true.
            return; 
        }

        // 5. Auto-Restart removed - users must manually start new game from results screen
        // Previously: After 60 seconds, auto-started new game
        // Now: Game stays in finished state until user clicks "Restart Simulation" button

        // Persist updates ONLY if something changed (to save IO/Events)
        // We always update lastTick if delta > 0 to keep the heartbeat fresh
        if ($delta > 0 || $timeRemaining !== $originalTimeRemaining || isset($updates['npcLastRun'])) {
            $this->storage->setSessionData($updates);
        }
    }

    public function isTradingAllowed() {
        return true; // Always trading now - no production phase
    }

    /**
     * Finalize Game (formerly advanceSession)
     * Closes the market and runs the final production.
     */
    public function finalizeGame() {
        $data = $this->storage->getSystemState();
        
        // Stop the game when session ends
        $this->storage->setSessionData([
            'gameStopped' => true,
            'gameFinished' => true,
            'productionJustRan' => time()
        ]);

        // Run one final production for all teams (this time it IS final)
        $this->runFinalProductionForAllTeams($data['currentSession']);

        return $this->storage->getSystemState();
    }

    // Alias for backward compatibility
    public function advanceSession() { return $this->finalizeGame(); }

    /**
     * Run FINAL production for all teams (consumes inventory)
     */
    public function runFinalProductionForAllTeams($sessionNumber) {
        require_once __DIR__ . '/TeamStorage.php';
        require_once __DIR__ . '/LPSolver.php';
        require_once __DIR__ . '/Database.php';

        $db = Database::getInstance();
        $teams = $db->query('SELECT DISTINCT team_email FROM team_events');
        
        foreach ($teams as $teamRow) {
            $email = $teamRow['team_email'];
            if ($email === 'system') continue;

            try {
                $storage = new TeamStorage($email);
                $inventory = $storage->getInventory();
                
                $solver = new LPSolver();
                $result = $solver->solve($inventory);
                $revenue = $result['maxProfit'];

                // FINAL PRODUCTION: Consumes inventory and adds funds
                $consumed = [
                    'C' => $result['deicer'] * LPSolver::DEICER_C,
                    'N' => ($result['deicer'] * LPSolver::DEICER_N) + ($result['solvent'] * LPSolver::SOLVENT_N),
                    'D' => ($result['deicer'] * LPSolver::DEICER_D) + ($result['solvent'] * LPSolver::SOLVENT_D),
                    'Q' => $result['solvent'] * LPSolver::SOLVENT_Q
                ];
                
                foreach ($consumed as $chem => $amount) {
                    if ($amount > 0) $storage->adjustChemical($chem, -$amount);
                }

                $storage->updateFunds($revenue);

                $storage->addProduction([
                    'type' => 'final_production',
                    'sessionNumber' => $sessionNumber,
                    'deicer' => $result['deicer'],
                    'solvent' => $result['solvent'],
                    'revenue' => $revenue,
                    'chemicalsConsumed' => $consumed,
                    'constraints' => $result['constraints'],
                    'shadowPrices' => $result['shadowPrices'],
                    'ranges' => $result['ranges'],
                    'note' => "Final production run. Game over."
                ]);
            } catch (Exception $e) { continue; }
        }
    }

    /**
     * Update Projections (formerly runProductionForAllTeams)
     * Calculates projected profit without consuming inventory.
     */
    public function updateProjections($sessionNumber) {
        require_once __DIR__ . '/TeamStorage.php';
        require_once __DIR__ . '/LPSolver.php';
        require_once __DIR__ . '/Database.php';

        // Get all teams from database
        $db = Database::getInstance();
        $teams = $db->query('SELECT DISTINCT team_email FROM team_events');
        if (empty($teams)) return;

        foreach ($teams as $teamRow) {
            $email = $teamRow['team_email'];
            if ($email === 'system') continue;

            try {
                $storage = new TeamStorage($email);
                $inventory = $storage->getInventory();
                if (empty($inventory['C']) && empty($inventory['N'])) continue;

                $solver = new LPSolver();
                $result = $solver->solve($inventory);
                $revenue = $result['maxProfit'];

                // NEW MODEL: In-session production is just a projection. 
                // NO inventory is consumed and NO funds are credited until the final end of game.
                
                $storage->addProduction([
                    'type' => 'session_projection',
                    'sessionNumber' => $sessionNumber,
                    'deicer' => $result['deicer'],
                    'solvent' => $result['solvent'],
                    'revenue' => $revenue,
                    'constraints' => $result['constraints'],
                    'shadowPrices' => $result['shadowPrices'],
                    'ranges' => $result['ranges'],
                    'note' => "Projected potential for session $sessionNumber"
                ]);
            } catch (Exception $e) { continue; }
        }
    }

    // Alias for backward compatibility
    public function runProductionForAllTeams($sessionNumber) { return $this->updateProjections($sessionNumber); }

    public function setPhase($phase) {
        $phase = strtoupper($phase);
        $updates = ['phase' => $phase, 'phaseStartedAt' => time()];
        if ($phase === 'PRODUCTION') {
            $updates['productionRun'] = null;
        }
        $this->storage->setSessionData($updates);
        return $this->storage->getSystemState();
    }

    public function setAutoCycleMode($enabled) {
        $this->storage->setSessionData(['autoAdvance' => (bool)$enabled]);
        return $this->storage->getSystemState();
    }

    // Alias
    public function setAutoAdvance($enabled) { return $this->setAutoCycleMode($enabled); }

    public function setProductionDuration($seconds) {
        $this->storage->setSessionData(['productionDuration' => (int)$seconds]);
        return $this->storage->getSystemState();
    }

    public function setTradingDuration($seconds) {
        $this->storage->setSessionData([
            'tradingDuration' => (int)$seconds,
            'timeRemaining' => (int)$seconds,
            'lastTick' => time()
        ]);
        return $this->storage->getSystemState();
    }

    public function toggleGameStop($stopped) {
        $updates = ['gameStopped' => (bool)$stopped];
        
        // If starting the game, reset the tick timer
        if (!$stopped) {
            $updates['lastTick'] = time();
            $updates['gameFinished'] = false; // Ensure finished is false if manually started
        }
        
        $this->storage->setSessionData($updates);
        return $this->storage->getSystemState();
    }

    /**
     * Start New Game (formerly restartGame)
     * Resets the entire world and starts a fresh session.
     * Preserves NPC count and trading duration.
     */
    public function startNewGame() {
        require_once __DIR__ . '/NPCManager.php';
        require_once __DIR__ . '/Database.php';
        
        $npcManager = new NPCManager();
        $npcConfig = $npcManager->loadConfig();
        $npcSkillLevels = array_column($npcConfig['npcs'] ?? [], 'skillLevel');

        // Preserve current trading duration
        $currentState = $this->getState();
        $tradingDuration = $currentState['tradingDuration'] ?? 600;
        
        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            $db->getPdo()->exec('PRAGMA foreign_keys = OFF');
            $db->execute('DELETE FROM team_state_cache');
            $db->execute('DELETE FROM team_snapshots');
            $db->execute('DELETE FROM team_events');
            $db->execute('DELETE FROM marketplace_events');
            $db->execute('UPDATE marketplace_snapshot SET offers = ?, buy_orders = ?, ads = ?, recent_trades = ?, updated_at = ? WHERE id = 1',
                ['[]', '[]', '[]', '[]', time()]);
            $db->execute('DELETE FROM negotiation_offers');
            $db->execute('DELETE FROM negotiations');
            $db->execute('DELETE FROM config WHERE key != ?', ['admin_config']);
            $db->getPdo()->exec('PRAGMA foreign_keys = ON');
            $db->commit();
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }

        // Recreate NPCs
        $npcManager->toggleSystem(true);
        foreach ($npcSkillLevels as $level) {
            $npcManager->createNPCs($level, 1);
        }

        // Force marketplace snapshot to be empty (defensive)
        // This ensures no stale ads appear after reset
        require_once __DIR__ . '/MarketplaceAggregator.php';
        $aggregator = new MarketplaceAggregator();
        $aggregator->generateSnapshot();

        return $this->reset($tradingDuration);
    }

    // Alias for backward compatibility
    public function restartGame() { return $this->startNewGame(); }

    public function updateNpcLastRun() {
        $this->storage->setSessionData(['npcLastRun' => time()]);
        return $this->storage->getSystemState();
    }

    public function reset($tradingDuration = 600) {
        $this->storage->setSessionData([
            'currentSession' => 1,
            'phase' => 'TRADING',
            'autoAdvance' => true,
            'productionDuration' => 0, // Not used in new model but kept for compatibility
            'tradingDuration' => (int)$tradingDuration,
            'timeRemaining' => (int)$tradingDuration,
            'lastTick' => time(),
            'productionRun' => null,
            'productionJustRan' => null, // Don't show modal at start
            'gameStopped' => true, // Start STOPPED so admin can prepare
            'gameFinished' => false
        ]);
        return $this->storage->getSystemState();
    }
}
