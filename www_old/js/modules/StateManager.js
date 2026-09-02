/**
 * StateManager
 * Handles application state, data loading, and event-based updates.
 */
import { api } from '../api.js';

export class StateManager extends EventTarget {
    constructor() {
        super();
        this.state = {
            profile: null,
            inventory: { C: 0, N: 0, D: 0, Q: 0 },
            shadowPrices: { C: 0, N: 0, D: 0, Q: 0, maxProfit: 0 },
            ranges: {},
            constraints: [],
            listings: { C: { buy: [], sell: [] }, N: { buy: [], sell: [] }, D: { buy: [], sell: [] }, Q: { buy: [], sell: [] } },
            myNegotiations: [],
            transactions: [],
            notifications: [],
            settings: { showTradingHints: false },
            sessionPhase: null,
            gameStopped: true,
            lastStalenessLevel: 'fresh',
            lastStalenessCount: 0
        };
    }

    /**
     * Dispatch a state update event
     * @param {string} type - Event type
     * @param {Object} data - Updated data
     */
    notify(type, data) {
        this.dispatchEvent(new CustomEvent(type, { detail: data }));
        this.dispatchEvent(new CustomEvent('stateChanged', { detail: { type, data, state: this.state } }));
    }

    /**
     * Load team profile and inventory
     */
    async loadProfile() {
        const data = await api.team.getProfile();
        this.state.profile = data.profile;
        this.state.inventory = data.inventory;
        this.state.lastStalenessLevel = data.inventory.stalenessLevel;
        this.state.lastStalenessCount = data.inventory.transactionsSinceLastShadowCalc;

        this.notify('profileUpdated', {
            profile: this.state.profile,
            inventory: this.state.inventory,
            staleness: {
                level: this.state.lastStalenessLevel,
                count: this.state.lastStalenessCount
            }
        });
        return data;
    }

    /**
     * Load shadow prices (read-only, does NOT recalculate)
     * Auto-recalculates if:
     * - Shadow prices are all zeros but inventory isn't
     * - Ranges are missing (not stored, only calculated by LP solver)
     */
    async loadShadowPrices() {
        try {
            const data = await api.production.readShadowPrices();
            if (data && data.shadowPrices) {
                // Check if shadow prices are all zeros but inventory isn't
                const allShadowZero = Object.values(data.shadowPrices).every(v => v === 0);
                const hasInventory = data.inventory && Object.values(data.inventory).some(v => v > 0);

                // Check if ranges are missing (ranges aren't stored, only from recalculate)
                const rangesMissing = !this.state.ranges || Object.keys(this.state.ranges).length === 0;

                if ((allShadowZero && hasInventory) || (rangesMissing && hasInventory)) {
                    console.log('StateManager: Auto-recalculating (shadow prices zero or ranges missing)');
                    await this.recalculateShadowPrices();
                    return; // recalculateShadowPrices already notifies
                }

                this.state.shadowPrices = {
                    ...data.shadowPrices,
                    maxProfit: this.state.shadowPrices?.maxProfit || 0
                };
                // Store optimal production mix (Deicer/Solvent quantities)
                if (data.optimalMix) {
                    this.state.optimalMix = data.optimalMix;
                }
                // Store constraints if available
                if (data.constraints) {
                    this.state.constraints = data.constraints;
                }
                // Update staleness from read endpoint
                if (data.staleness) {
                    this.state.lastStalenessLevel = data.staleness.level;
                    this.state.lastStalenessCount = data.staleness.count;
                }

                this.notify('shadowPricesUpdated', {
                    shadowPrices: this.state.shadowPrices,
                    ranges: this.state.ranges,
                    constraints: this.state.constraints,
                    optimalMix: this.state.optimalMix,
                    staleness: data.staleness
                });
            }
        } catch (error) {
            console.error('StateManager: Failed to load shadow prices:', error);
        }
    }

    /**
     * Recalculate shadow prices (triggers LP solver, resets staleness)
     */
    async recalculateShadowPrices() {
        const data = await api.production.recalculateShadowPrices();
        this.state.shadowPrices = {
            ...data.shadowPrices,
            maxProfit: data.maxProfit || 0
        };
        this.state.ranges = data.ranges || {};
        this.state.constraints = data.constraints || [];
        this.state.optimalMix = data.optimalMix || { deicer: 0, solvent: 0 };
        this.state.lastStalenessLevel = 'fresh';
        this.state.lastStalenessCount = 0;

        this.notify('shadowPricesUpdated', {
            shadowPrices: this.state.shadowPrices,
            ranges: this.state.ranges,
            constraints: this.state.constraints,
            optimalMix: this.state.optimalMix,
            staleness: { level: 'fresh', count: 0 }
        });

        await this.loadProfile();
    }

    /**
     * Load listings
     */
    async loadListings() {
        try {
            const data = await api.listings.list();
            this.state.listings = data.listings;
            this.notify('listingsUpdated', this.state.listings);
        } catch (error) {
            console.error('StateManager: Failed to load listings:', error);
        }
    }

    /**
     * Load negotiations
     */
    async loadNegotiations() {
        try {
            const data = await api.negotiations.list();
            this.state.myNegotiations = data.negotiations || [];
            this.notify('negotiationsUpdated', this.state.myNegotiations);
        } catch (error) {
            console.error('StateManager: Failed to load negotiations:', error);
        }
    }

    /**
     * Load transactions
     */
    async loadTransactions() {
        try {
            const data = await api.get('api/trades/history.php');
            if (data.success) {
                this.state.transactions = data.transactions || [];
                this.notify('transactionsUpdated', this.state.transactions);
            }
        } catch (error) {
            console.error('StateManager: Failed to load transactions:', error);
        }
    }

    /**
     * Update settings
     */
    updateSettings(newSettings) {
        this.state.settings = { ...this.state.settings, ...newSettings };
        this.notify('settingsUpdated', this.state.settings);
        localStorage.setItem('cndq_settings', JSON.stringify(this.state.settings));
    }

    /**
     * Load settings from localStorage
     */
    loadSettings() {
        const saved = localStorage.getItem('cndq_settings');
        if (saved) {
            try {
                this.state.settings = { ...this.state.settings, ...JSON.parse(saved) };
                this.notify('settingsUpdated', this.state.settings);
            } catch (e) {
                console.error('StateManager: Failed to parse saved settings');
            }
        }
    }
}

export const stateManager = new StateManager();
