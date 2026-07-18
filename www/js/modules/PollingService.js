/**
 * PollingService
 * Handles game loop polling and timer management.
 */
import { api } from '../api.js';

export class PollingService {
    constructor(options = {}) {
        this.frequency = options.frequency || 3000;
        this.interval = null;
        this.timerInterval = null;
        this.onPoll = options.onPoll || (() => {});
        this.onTick = options.onTick || (() => {});
        
        this.state = {
            gameStopped: true,
            timeRemaining: 0,
            phase: 'UNKNOWN'
        };
    }

    /**
     * Start the polling loop
     */
    start() {
        if (this.interval) return;
        
        // Immediate first poll
        this.poll();
        
        this.interval = setInterval(() => this.poll(), this.frequency);
        
        // Start timer tick (every second)
        this.timerInterval = setInterval(() => this.tick(), 1000);
    }

    /**
     * Stop the polling loop
     */
    stop() {
        if (this.interval) {
            clearInterval(this.interval);
            this.interval = null;
        }
        if (this.timerInterval) {
            clearInterval(this.timerInterval);
            this.timerInterval = null;
        }
    }

    /**
     * Perform a single poll
     */
    async poll() {
        try {
            const data = await api.session.getStatus();
            
            this.state.gameStopped = data.stopped;
            this.state.timeRemaining = data.timeRemaining;
            this.state.phase = data.phase;
            
            if (this.onPoll) {
                this.onPoll(data);
            }
        } catch (error) {
            console.error('PollingService: Poll failed:', error);
        }
    }

    /**
     * Timer tick
     */
    tick() {
        if (!this.state.gameStopped && this.state.timeRemaining > 0) {
            this.state.timeRemaining--;
            if (this.onTick) {
                this.onTick(this.state.timeRemaining);
            }
        }
    }
}
