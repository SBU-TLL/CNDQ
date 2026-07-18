/**
 * CNDQ Marketplace API Client
 *
 * Centralized API abstraction layer for all backend communication.
 * Makes it easy to switch backends or modify request handling.
 *
 * Usage:
 *   import { api } from './api.js';
 *   const profile = await api.team.getProfile();
 *   await api.listings.post('C', 'sell');
 */

class ApiClient {
    constructor() {
        // Determine the base path relative to the current page
        const path = window.location.pathname;
        const search = window.location.search;

        // Robust path detection for subfolder installations
        // If we're in /admin/ or any subdirectory, use '../' prefix
        if (path.includes('/admin/')) {
            this.pathPrefix = '../';
        } else {
            this.pathPrefix = './';
        }

        console.group('🌐 API Client Initialized');
        console.log('Path:', path);
        console.log('Path Prefix:', this.pathPrefix);
        console.groupEnd();
    }

    /**
     * Generic fetch wrapper with error handling
     */
    async request(endpoint, options = {}) {
        // Remove leading slash if present for relative path construction
        const cleanEndpoint = endpoint.startsWith('/') ? endpoint.substring(1) : endpoint;

        // Construct relative URL
        const url = `${this.pathPrefix}${cleanEndpoint}`;

        if (window.APP_DEBUG) console.log(`[API] ${options.method || 'GET'} ${url}`);
        
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                ...options.headers
            }
        };

        const config = { ...defaultOptions, ...options };

        try {
            const response = await fetch(url, config);
            
            // specific handling for 204 No Content
            if (response.status === 204) {
                return null;
            }

            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                const data = await response.json();

                if (!response.ok) {
                    // Prefer detailed message over generic error label
                    const errorMsg = data.message || data.error || `HTTP ${response.status}`;
                    throw new Error(errorMsg);
                }

                return data;
            } else {
                // Handle non-JSON response
                const text = await response.text();
                const is404 = response.status === 404;
                
                const titleMatch = text.match(/<title>(.*?)<\/title>/i);
                const title = titleMatch ? titleMatch[1] : (is404 ? '404 Not Found' : 'Unknown Error');
                
                // Show the actual URL attempted
                const fullUrl = new URL(url, window.location.href).href;
                const errorMsg = `[${response.status}] ${title} at ${fullUrl}`;
                console.error('🚨 SERVER ERROR:', errorMsg);

                // CRITICAL: Log the body so Puppeteer can see the PHP error
                console.log('BODY_START:' + text + ':BODY_END');

                throw new Error(errorMsg);
            }
        } catch (error) {
            const msg = `API Error [${url}]: ${error.message}`;
            console.error(msg);
            throw error;
        }
    }

    /**
     * GET request
     */
    async get(endpoint) {
        return this.request(endpoint, { method: 'GET' });
    }

    /**
     * POST request
     */
    async post(endpoint, data) {
        return this.request(endpoint, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }

    /**
     * PUT request
     */
    async put(endpoint, data) {
        return this.request(endpoint, {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    }

    /**
     * DELETE request
     */
    async delete(endpoint) {
        return this.request(endpoint, { method: 'DELETE' });
    }

    // ==========================================
    // Team & Profile APIs
    // ==========================================

    team = {
        /**
         * Get team profile and inventory
         * @returns {Promise<{success: boolean, profile: Object, inventory: Object}>}
         */
        getProfile: async () => {
            return this.get('api/team/profile.php');
        },

        /**
         * Get team settings
         * @returns {Promise<{success: boolean, settings: Object}>}
         */
        getSettings: async () => {
            return this.get('api/team/settings.php');
        },

        /**
         * Update team settings
         * @param {Object} settings - Settings to update
         * @returns {Promise<{success: boolean, settings: Object}>}
         */
        updateSettings: async (settings) => {
            return this.post('api/team/settings.php', settings);
        }
    };

    // ==========================================
    // Production & Shadow Prices APIs
    // ==========================================

    production = {
        /**
         * Read stored shadow prices WITHOUT recalculating
         * @returns {Promise<{success: boolean, shadowPrices: Object, staleness: Object}>}
         */
        readShadowPrices: async () => {
            return this.get('api/production/shadow-prices-read.php');
        },

        /**
         * Recalculate shadow prices (resets staleness counter)
         * @returns {Promise<{success: boolean, shadowPrices: Object}>}
         */
        recalculateShadowPrices: async () => {
            return this.get('api/production/shadow-prices.php');
        }
    };

    // ==========================================
    // Listing APIs
    // ==========================================

    listings = {
        /**
         * List all marketplace listings
         * @returns {Promise<{success: boolean, listings: Object}>}
         */
        list: async () => {
            return this.get('api/listings/list.php');
        },

        /**
         * Post buy or sell interest
         * @param {string} chemical - Chemical type (C, N, D, Q)
         * @param {string} type - 'buy' or 'sell'
         * @returns {Promise<{success: boolean, message: string, listing: Object}>}
         */
        post: async (chemical, type) => {
            return this.post('api/listings/post.php', { chemical, type });
        },

        /**
         * Get my listings
         * @returns {Promise<{success: boolean, listings: Array}>}
         */
        getMyListings: async () => {
            return this.get('api/listings/my-listings.php');
        },

        /**
         * Cancel a listing (buy request)
         * @param {string} listingId - The listing ID to cancel
         * @returns {Promise<{success: boolean, message: string}>}
         */
        cancel: async (listingId) => {
            return this.post('api/listings/cancel.php', { listingId });
        }
    };

    // ==========================================
    // Offers APIs (Buy Requests)
    // ==========================================

    offers = {
        /**
         * Create a buy order (bid)
         * @param {string} chemical - Chemical type (C, N, D, Q)
         * @param {number} quantity - Quantity desired
         * @param {number} maxPrice - Maximum price willing to pay per gallon
         * @returns {Promise<{success: boolean, buyOrder: Object}>}
         */
        bid: async (chemical, quantity, maxPrice) => {
            return this.post('api/offers/bid.php', {
                chemical,
                quantity,
                maxPrice
            });
        }
    };

    // ==========================================
    // Negotiation APIs
    // ==========================================

    negotiations = {
        /**
         * List my negotiations
         * @returns {Promise<{success: boolean, negotiations: Array}>}
         */
        list: async () => {
            return this.get('api/negotiations/list.php');
        },

        /**
         * Initiate a new negotiation
         * @param {string} responderId - Other team's ID
         * @param {string} chemical - Chemical type
         * @param {number} quantity - Gallons
         * @param {number} price - Price per gallon
         * @param {string} type - 'buy' or 'sell' (initiator perspective)
         * @param {string} adId - (Optional) ID of listing being responded to
         * @returns {Promise<{success: boolean, negotiation: Object}>}
         */
        initiate: async (responderId, chemical, quantity, price, type = 'buy', adId = null) => {
            return this.post('api/negotiations/initiate.php', {
                responderId,
                chemical,
                quantity,
                price,
                type,
                adId
            });
        },

        /**
         * Send a counter-offer
         * @param {string} negotiationId - Negotiation ID
         * @param {number} quantity - Gallons
         * @param {number} price - Price per gallon
         * @returns {Promise<{success: boolean, negotiation: Object}>}
         */
        counter: async (negotiationId, quantity, price) => {
            return this.post('api/negotiations/counter.php', {
                negotiationId,
                quantity,
                price
            });
        },

        /**
         * Accept a negotiation offer
         * @param {string} negotiationId - Negotiation ID
         * @returns {Promise<{success: boolean, trade: Object}>}
         */
        accept: async (negotiationId) => {
            return this.post('api/negotiations/accept.php', { negotiationId });
        },

        /**
         * Reject or cancel a negotiation
         * @param {string} negotiationId - Negotiation ID
         * @returns {Promise<{success: boolean}>}
         */
        reject: async (negotiationId) => {
            return this.post('api/negotiations/reject.php', { negotiationId });
        }
    };

    // ==========================================
    // Notification APIs
    // ==========================================

    notifications = {
        /**
         * List notifications
         * @returns {Promise<{success: boolean, notifications: Array, unreadCount: number}>}
         */
        list: async () => {
            return this.get('api/notifications/list.php');
        }
    };

    // ==========================================
    // Report APIs
    // ==========================================

    reports = {
        /**
         * Get generated reports
         * @param {string} type - 'financials', 'transactions', 'optimization', or 'all'
         * @returns {Promise<{success: boolean, financials?: Object, transactions?: Array, optimization?: Object}>}
         */
        get: async (type = 'all') => {
            return this.get(`api/reports/index.php?type=${type}`);
        }
    };

    session = {
        /**
         * Get public session status (triggers auto-advance)
         */
        getStatus: async () => {
            return this.get('api/session/status.php');
        }
    };

    // ==========================================
    // Admin APIs
    // ==========================================

    admin = {
        /**
         * Get session state
         * @returns {Promise<{success: boolean, session: Object}>}
         */
        getSession: async () => {
            return this.get('api/admin/session.php');
        },

        /**
         * Update session (advance, set phase, etc.)
         * @param {string} action - Action to perform
         * @param {Object} params - Action parameters
         * @returns {Promise<{success: boolean, message: string, session: Object}>}
         */
        updateSession: async (action, params = {}) => {
            return this.post('api/admin/session.php', { action, ...params });
        },

        /**
         * Reset all game data
         * @returns {Promise<{success: boolean, teamsReset: number}>}
         */
        resetGame: async () => {
            return this.post('api/admin/reset-game.php');
        },

        /**
         * List all teams
         * @returns {Promise<{success: boolean, teams: Array}>}
         */
        listTeams: async () => {
            return this.get('api/admin/list-teams.php');
        }
    };

    // ==========================================
    // Leaderboard APIs
    // ==========================================

    leaderboard = {
        /**
         * Get leaderboard standings
         * @returns {Promise<{success: boolean, standings: Array, session: string, phase: string}>}
         */
        getStandings: async () => {
            return this.get('api/leaderboard/standings.php');
        }
    };
}

// Export singleton instance
export const api = new ApiClient();
