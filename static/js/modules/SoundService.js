/**
 * SoundService
 * Handles audio cues (earcons) for accessibility and engagement.
 * Uses Web Audio API to synthesize sounds without external assets.
 */

export class SoundService {
    constructor() {
        this.ctx = null;
        this.volume = 0.5;
        this.muted = false;
        
        // Load initial settings from localStorage
        this.loadSettings();
    }

    /**
     * Initialize AudioContext on first user interaction
     */
    init() {
        if (!this.ctx) {
            this.ctx = new (window.AudioContext || window.webkitAudioContext)();
        }
        if (this.ctx.state === 'suspended') {
            this.ctx.resume();
        }
    }

    loadSettings() {
        const saved = localStorage.getItem('cndq_audio_settings');
        if (saved) {
            try {
                const settings = JSON.parse(saved);
                this.volume = settings.volume ?? 0.5;
                this.muted = settings.muted ?? false;
            } catch (e) {
                console.error('Failed to load audio settings');
            }
        }
    }

    saveSettings() {
        localStorage.setItem('cndq_audio_settings', JSON.stringify({
            volume: this.volume,
            muted: this.muted
        }));
    }

    setVolume(val) {
        this.volume = parseFloat(val);
        this.saveSettings();
    }

    setMuted(val) {
        this.muted = !!val;
        this.saveSettings();
    }

    /**
     * Generic oscillator player
     */
    playTone(freq, type, duration, volumeMultiplier = 1) {
        if (this.muted || this.volume <= 0) return;
        this.init();

        const osc = this.ctx.createOscillator();
        const gain = this.ctx.createGain();

        osc.type = type;
        osc.frequency.setValueAtTime(freq, this.ctx.currentTime);

        const finalVolume = this.volume * volumeMultiplier;
        gain.gain.setValueAtTime(finalVolume, this.ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, this.ctx.currentTime + duration);

        osc.connect(gain);
        gain.connect(this.ctx.destination);

        osc.start();
        osc.stop(this.ctx.currentTime + duration);
    }

    /**
     * SUCCESS - Crisp upward chime
     */
    playSuccess() {
        setTimeout(() => this.playTone(523.25, 'sine', 0.2, 0.5), 0); // C5
        setTimeout(() => this.playTone(659.25, 'sine', 0.3, 0.5), 100); // E5
        setTimeout(() => this.playTone(783.99, 'sine', 0.4, 0.5), 200); // G5
    }

    /**
     * ERROR / WARNING - Low thud
     */
    playError() {
        this.playTone(150, 'triangle', 0.5, 0.8);
        this.playTone(100, 'sine', 0.5, 0.8);
    }

    /**
     * NOTIFICATION - Friendly alert
     */
    playNotification() {
        this.playTone(440, 'sine', 0.1, 0.3);
        setTimeout(() => this.playTone(880, 'sine', 0.2, 0.3), 100);
    }

    /**
     * PATIENCE TICK - Percussive click
     */
    playTick(isUrgent = false) {
        this.playTone(isUrgent ? 800 : 400, 'square', 0.05, 0.1);
    }

    /**
     * MONEY / TRADE - High frequency glissando
     */
    playTrade() {
        if (this.muted || this.volume <= 0) return;
        this.init();
        const osc = this.ctx.createOscillator();
        const gain = this.ctx.createGain();
        
        osc.frequency.setValueAtTime(800, this.ctx.currentTime);
        osc.frequency.exponentialRampToValueAtTime(1200, this.ctx.currentTime + 0.1);
        
        gain.gain.setValueAtTime(this.volume * 0.3, this.ctx.currentTime);
        gain.gain.linearRampToValueAtTime(0, this.ctx.currentTime + 0.2);
        
        osc.connect(gain);
        gain.connect(this.ctx.destination);
        osc.start();
        osc.stop(this.ctx.currentTime + 0.2);
    }
}

export const sounds = new SoundService();
