<?php
/**
 * SystemStorage - Specialized storage for system-wide state.
 * Treats the 'system' as a special user for event-sourced session data.
 */

require_once __DIR__ . '/TeamStorage.php';

class SystemStorage extends TeamStorage {
    public function __construct() {
        parent::__construct('system');
    }

    /**
     * Get system state with default values
     */
    public function getSystemState() {
        $state = $this->getState();

        // If no session events yet, provide sensible defaults
        // This ensures the first call to getState() returns a valid object
        // that SessionManager can then use or auto-reset.
        $session = $state['session'] ?? [];

        return [
            'currentSession' => $session['currentSession'] ?? 1,
            'phase' => $session['phase'] ?? 'PRODUCTION',
            'autoAdvance' => $session['autoAdvance'] ?? true,
            'productionDuration' => $session['productionDuration'] ?? 2,
            'tradingDuration' => $session['tradingDuration'] ?? 300,
            'timeRemaining' => $session['timeRemaining'] ?? null,
            'lastTick' => $session['lastTick'] ?? null,
            'phaseStartedAt' => $session['phaseStartedAt'] ?? time(),
            'npcLastRun' => $session['npcLastRun'] ?? 0,
            'productionRun' => $session['productionRun'] ?? null,
            'initialProductionRun' => $session['initialProductionRun'] ?? null,
            'productionJustRan' => $session['productionJustRan'] ?? null,
            'gameStopped' => $session['gameStopped'] ?? true,
            'gameFinished' => $session['gameFinished'] ?? false
        ];
    }

    public function setSessionData($data) {
        $this->emitEvent('update_session', $data);
    }
}
