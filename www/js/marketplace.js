/**
 * CNDQ Marketplace SPA
 * Single Page Application for Chemical Trading
 */

// Import web components (cache-busting v4)
import './components/chemical-card.js?v=8';
import './components/listing-item.js?v=7';
import './components/negotiation-card.js';
import './components/offer-bubble.js';
import './components/notification-manager.js';
import './components/leaderboard-modal.js';
import './components/buy-request-card.js';
import './components/report-viewer.js';

// Import API client
import { api } from './api.js';

// Import Services
import { notifications } from './modules/NotificationService.js';
import { stateManager } from './modules/StateManager.js';
import { PollingService } from './modules/PollingService.js';
import { modalManager } from './components/ModalManager.js';
import { sounds } from './modules/SoundService.js';
import { financialRenderer } from './modules/FinancialRenderer.js';

class MarketplaceApp {
    constructor() {
        // State
        this.currentUser = null;
        this.profile = null;
        this.inventory = { C: 0, N: 0, D: 0, Q: 0 };
        this.shadowPrices = { C: 0, N: 0, D: 0, Q: 0 };
        this.constraints = [];
        this.listings = { C: { buy: [], sell: [] }, N: { buy: [], sell: [] }, D: { buy: [], sell: [] }, Q: { buy: [], sell: [] } };
        this.myNegotiations = [];
        this.notifications = [];
        this.settings = { showTradingHints: false };
        this.productionResultsShown = false; // Flag to prevent showing modal multiple times

        // Tutorial State
        this.tutorialStep = 0;
        this.tutorialSteps = [];

        // Global reference for tests
        window.marketplaceApp = this;

        // Polling (Delegated)
        this.pollingService = new PollingService({
            frequency: 3000,
            onPoll: (data) => this.handlePoll(data),
            onTick: (time) => this.updateTimerUI(time)
        });

        // Track when page loaded to filter out old toasts
        this.pageLoadTime = Date.now() / 1000; // Unix timestamp in seconds
        this.lastServerTimeRemaining = 0;
        this.gameStopped = true;
        this.wasGameStopped = false; // Track previous game stopped state for reload trigger
        this.gameFinished = false;
        this.autoAdvance = false; // Track if 24/7 mode is enabled (controls restart button visibility)

        // Modal state
        this.currentModal = null;

        // Track pending ad posts to prevent race conditions
        this.pendingListingPosts = new Set();

        // Track seen global trades to avoid duplicate toasts
        this.processedGlobalTrades = new Set();
        this.seenCompletedNegotiations = new Set();

        // Bind StateManager events
        this.bindStateEvents();
    }

    bindStateEvents() {
        stateManager.addEventListener('profileUpdated', (e) => {
            const { profile, inventory, staleness } = e.detail;
            this.profile = profile;
            this.inventory = inventory;
            this.currentUser = profile.email;
            this.updateUIProfile();
            this.updateStalenessIndicator(staleness.level, staleness.count);
            this.refreshRankBadge();
        });

        stateManager.addEventListener('shadowPricesUpdated', (e) => {
            const { shadowPrices, ranges, constraints, optimalMix, staleness } = e.detail;
            this.shadowPrices = shadowPrices;
            this.ranges = ranges;
            this.constraints = constraints || [];
            this.optimalMix = optimalMix || { deicer: 0, solvent: 0 };
            this.updateShadowPricesUI();
            this.renderFinancialSummary();
            // Update staleness indicator if staleness info is included
            if (staleness) {
                this.updateStalenessIndicator(staleness.level, staleness.count);
            }
        });

        stateManager.addEventListener('listingsUpdated', (e) => {
            this.listings = e.detail;
            this.renderListings();
        });

        stateManager.addEventListener('negotiationsUpdated', (e) => {
            const oldNegotiations = this.myNegotiations || [];
            this.myNegotiations = e.detail || [];
            this.renderNegotiations();

            // Also update modal view if open
            this.renderNegotiationsInModal();

            // Check if currently viewed negotiation changed status (counterparty accepted/rejected)
            if (this.currentNegotiation && this.currentNegotiation.status === 'pending') {
                const updated = this.myNegotiations.find(n => n.id === this.currentNegotiation.id);
                if (updated && updated.status !== 'pending') {
                    // Negotiation was completed by counterparty! Update the view
                    this.currentNegotiation = updated;

                    // Show toast notification
                    if (updated.status === 'accepted') {
                        const otherParty = updated.initiatorId === this.currentUser ? updated.responderName : updated.initiatorName;
                        notifications.showToast(`${otherParty} accepted your offer!`, 'success');
                    } else {
                        notifications.showToast('Negotiation was cancelled', 'info');
                    }

                    // Close modal - synopsis card will show on main page
                    this.closeNegotiationModal();
                    stateManager.loadProfile();
                    stateManager.loadShadowPrices();
                }
            }
        });

        stateManager.addEventListener('transactionsUpdated', (e) => {
            this.transactions = e.detail || [];
            this.renderFinancialSummary();
        });
    }

    /**
     * Initialize the application
     */
    async init() {
        try {
            console.log('Initializing marketplace...');

            // 1. Setup UI event listeners immediately (responsive UI first)
            console.log('Setting up UI event listeners...');
            this.setupEventListeners();
            console.log('✓ UI listeners setup');

            // 2. Wait for custom elements to be defined
            await Promise.all([
                customElements.whenDefined('chemical-card'),
                customElements.whenDefined('listing-item'),
                customElements.whenDefined('negotiation-card'),
                customElements.whenDefined('offer-bubble')
            ]);
            console.log('✓ Web components defined');

            // 3. Load data (async chain)
            console.log('Loading initial data...');
            await Promise.all([
                stateManager.loadProfile(), // Use StateManager
                stateManager.loadShadowPrices(), // Use StateManager
                stateManager.loadListings(),
                stateManager.loadNegotiations(),
                stateManager.loadTransactions(),
                this.loadNotifications(),
                this.loadSettings()
            ]);
            console.log('✓ Initial data loaded');

            // Load saved theme
            this.loadSavedTheme();

            // Check for production results
            await this.checkSessionPhase();

            // Start polling
            this.startPolling();
            console.log('✓ Polling started');

            // Initialize audio service
            sounds.init();

            // Hide loading, show app
            const overlay = document.getElementById('loading-overlay');
            if (overlay) overlay?.classList.add('hidden');
            console.log('✓ Marketplace initialized successfully');

            // Check if first visit tutorial should show
            this.checkFirstVisitTutorial();

        } catch (error) {
            console.error('Failed to initialize marketplace:', error);
            // Hide loading even on error so user can at least see what's wrong
            const overlay = document.getElementById('loading-overlay');
            if (overlay) overlay?.classList.add('hidden');
            
            notifications.showToast('Initialization error: ' + error.message, 'error');
        }
    }

    /**
     * Show custom confirmation dialog (non-blocking)
     * Returns a Promise that resolves to true/false
     */
    showConfirm(message, title = 'Confirm Action') {
        return new Promise((resolve) => {
            const dialog = document.getElementById('confirm-dialog');
            const titleEl = document.getElementById('confirm-title');
            const messageEl = document.getElementById('confirm-message');
            const okBtn = document.getElementById('confirm-ok');
            const cancelBtn = document.getElementById('confirm-cancel');

            // Store previous focus to restore later
            const previousFocus = document.activeElement;

            titleEl.textContent = title;
            messageEl.textContent = message;
            dialog?.classList.remove('hidden');
            dialog.setAttribute('role', 'alertdialog');
            dialog.setAttribute('aria-modal', 'true');
            dialog.setAttribute('aria-labelledby', 'confirm-title');
            dialog.setAttribute('aria-describedby', 'confirm-message');

            // Focus the cancel button initially for safety
            setTimeout(() => cancelBtn.focus(), 100);

            const cleanup = () => {
                dialog?.classList.add('hidden');
                dialog.removeAttribute('role');
                dialog.removeAttribute('aria-modal');
                dialog.removeAttribute('aria-labelledby');
                dialog.removeAttribute('aria-describedby');
                okBtn.removeEventListener('click', handleOk);
                cancelBtn.removeEventListener('click', handleCancel);
                dialog.removeEventListener('click', handleBackdrop);
                document.removeEventListener('keydown', handleKeydown);

                // Restore focus
                if (previousFocus) {
                    previousFocus.focus();
                }
            };

            const handleOk = () => {
                cleanup();
                resolve(true);
            };

            const handleCancel = () => {
                cleanup();
                resolve(false);
            };

            const handleBackdrop = (e) => {
                if (e.target === dialog) {
                    cleanup();
                    resolve(false);
                }
            };

            const handleKeydown = (e) => {
                // Esc key closes dialog
                if (e.key === 'Escape') {
                    e.preventDefault();
                    handleCancel();
                }
                // Enter key confirms (if not focused on cancel button)
                else if (e.key === 'Enter' && document.activeElement !== cancelBtn) {
                    e.preventDefault();
                    handleOk();
                }
                // Tab key - trap focus within dialog
                else if (e.key === 'Tab') {
                    const focusableElements = [cancelBtn, okBtn];
                    const firstElement = focusableElements[0];
                    const lastElement = focusableElements[focusableElements.length - 1];

                    if (e.shiftKey && document.activeElement === firstElement) {
                        e.preventDefault();
                        lastElement.focus();
                    } else if (!e.shiftKey && document.activeElement === lastElement) {
                        e.preventDefault();
                        firstElement.focus();
                    }
                }
            };

            okBtn.addEventListener('click', handleOk);
            cancelBtn.addEventListener('click', handleCancel);
            dialog.addEventListener('click', handleBackdrop);
            document.addEventListener('keydown', handleKeydown);
        });
    }

    /**
     * Update UI with profile and inventory data
     */
    updateUIProfile() {
        if (!this.profile) return;

        // Update UI
        document.getElementById('team-name').textContent = this.profile.teamName || this.profile.email;
        this.renderFunds();

        // Update inventory on chemical-card components
        ['C', 'N', 'D', 'Q'].forEach(chem => {
            const card = document.querySelector(`chemical-card[chemical="${chem}"]`);
            if (card) {
                card.inventory = this.inventory[chem];
            }
        });

        this.updateScreenReaderSummary();
    }

    /**
     * Update the hidden screen reader summary with current game state
     */
    updateScreenReaderSummary() {
        if (!this.profile) return;

        const funds = this.formatCurrency(this.profile.currentFunds);
        
        // Calculate total inventory
        const totalGallons = Object.values(this.inventory).reduce((a, b) => a + b, 0);
        
        // Identify lowest inventory (bottleneck candidate) if production enabled
        // Simple heuristic: lowest absolute amount
        let bottleneck = 'None';
        let minAmount = Infinity;
        for (const [chem, amount] of Object.entries(this.inventory)) {
            if (amount < minAmount) {
                minAmount = amount;
                bottleneck = `Chemical ${chem}`;
            }
        }

        // Profit/Improvement
        // Calculate improvement %
        const initialPotential = this.profile.initialProductionPotential || 0;
        const totalValue = (this.profile.currentFunds || 0) + (this.shadowPrices?.maxProfit || 0);
        let improvement = 0;
        if (initialPotential > 0) {
            improvement = ((totalValue - initialPotential) / initialPotential) * 100;
        }
        const trend = improvement >= 0 ? "up" : "down";

        const summary = `Game State Update: You have ${funds}. Total inventory is ${Math.round(totalGallons)} gallons. Lowest stock is ${bottleneck}. Your profit is ${trend} ${Math.abs(improvement).toFixed(1)}%.`;

        const el = document.getElementById('sr-game-summary');
        if (el) el.textContent = summary;
    }



    /**
     * Update shadow prices in UI
     */
    updateShadowPricesUI() {
        ['C', 'N', 'D', 'Q'].forEach(chem => {
            const price = this.shadowPrices[chem] || 0;
            // Update header shadow prices
            const shadowEl = document.getElementById(`shadow-${chem}`);
            if (shadowEl) shadowEl.textContent = this.formatCurrency(price);

            // Update chemical card shadow prices via component properties
            const card = document.querySelector(`chemical-card[chemical="${chem}"]`);
            if (card) {
                card.shadowPrice = price;
                if (this.ranges && this.ranges[chem]) {
                    card.ranges = this.ranges[chem];
                }
                
                // Pass slack from constraints
                if (this.constraints) {
                    const constraint = this.constraints.find(c => c.name === chem);
                    if (constraint) {
                        card.slack = constraint.slack;
                    }
                }
            }
        });
    }

    /**
     * Update staleness indicator
     */
    updateStalenessIndicator(level, count) {
        const indicator = document.getElementById('staleness-indicator');
        const warning = document.getElementById('staleness-warning');
        const recalcBtn = document.getElementById('recalc-shadow-btn');

        // Store for theme changes
        this.lastStalenessLevel = level;
        this.lastStalenessCount = count;

        if (level === 'fresh') {
            if (indicator) indicator.innerHTML = '<span class="staleness-fresh">✓ Fresh</span>';
            if (warning) warning?.classList.add('hidden');
            // Disable and grey out button when fresh
            if (recalcBtn) {
                recalcBtn.disabled = true;
                recalcBtn.classList.remove('bg-green-600', 'hover:bg-green-700');
                recalcBtn.classList.add('bg-gray-600', 'cursor-not-allowed', 'opacity-50');
            }
        } else if (level === 'warning') {
            if (indicator) indicator.innerHTML = '<span class="staleness-warning">⚠ Stale (1 trade ago)</span>';
            if (warning) {
                warning?.classList.remove('hidden');
                warning.className = 'mt-3 p-3 rounded text-sm badge-warning';
                warning.textContent = '💡 Tip: Your inventory changed! Shadow prices may be outdated. Click [Recalculate] to update them.';
            }
            // Enable button when stale
            if (recalcBtn) {
                recalcBtn.disabled = false;
                recalcBtn.classList.remove('bg-gray-600', 'cursor-not-allowed', 'opacity-50');
                recalcBtn.classList.add('bg-green-600', 'hover:bg-green-700');
            }
        } else if (level === 'stale') {
            if (indicator) indicator.innerHTML = `<span class="staleness-stale">✗ Very Stale (${count} trades ago)</span>`;
            if (warning) {
                warning?.classList.remove('hidden');
                warning.className = 'mt-3 p-3 rounded text-sm badge-error';
                warning.textContent = `⚠️ Warning: Shadow prices are very stale (last calculated before ${count} transactions). Your valuations may be inaccurate!`;
            }
            // Enable button when very stale
            if (recalcBtn) {
                recalcBtn.disabled = false;
                recalcBtn.classList.remove('bg-gray-600', 'cursor-not-allowed', 'opacity-50');
                recalcBtn.classList.add('bg-green-600', 'hover:bg-green-700');
            }
        }
    }

    /**
     * Recalculate shadow prices
     */
    async recalculateShadowPrices() {
        const btn = document.getElementById('recalc-shadow-btn');
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Calculating...';
        }

        try {
            await stateManager.recalculateShadowPrices();
            notifications.showToast('Shadow prices updated successfully', 'success');
            sounds.playNotification();
        } catch (error) {
            console.error('Failed to recalculate shadow prices:', error);
            notifications.showToast('Failed to recalculate shadow prices', 'error');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Recalculate Shadow Prices';
            }
        }
    }

    /**
     * Load marketplace offers
     */


    /**
     * Update the rank badge in the Net Profit card.
     * Throttled to at most once every 30 seconds to avoid hammering the API.
     */
    async refreshRankBadge() {
        const now = Date.now();
        if (this._lastRankFetch && now - this._lastRankFetch < 30000) return;
        this._lastRankFetch = now;

        try {
            const data = await api.leaderboard.getStandings();
            if (!data.success || !data.standings) return;
            const standings = data.standings;
            const idx = standings.findIndex(t => t.email === this.currentUser || t.teamId === this.currentUser);
            const rankEl = document.getElementById('fin-rank');
            if (!rankEl) return;
            if (idx >= 0) {
                const rank = idx + 1;
                const total = standings.length;
                const medal = rank === 1 ? '🥇' : rank === 2 ? '🥈' : rank === 3 ? '🥉' : `#${rank}`;
                rankEl.textContent = `${medal} of ${total} teams`;
            } else {
                rankEl.textContent = '';
            }
        } catch (e) { /* non-fatal — rank is display-only */ }
    }

    /**
     * Render Financial Summary Panel (delegated to FinancialRenderer module)
     */
    renderFinancialSummary() {
        financialRenderer.renderFinancialSummary({
            transactions: this.transactions,
            profile: this.profile,
            shadowPrices: this.shadowPrices,
            optimalMix: this.optimalMix,
            staleness: this.lastStalenessLevel
        });
    }

    /**
     * Render Transaction History Table (delegated to FinancialRenderer module)
     */
    renderTransactionHistoryTable() {
        financialRenderer.renderTransactionHistoryTable(this.transactions);
    }

    openTransactionHistory() {
        this.renderTransactionHistoryTable();
        modalManager.open('history-modal');
    }

    closeTransactionHistory() {
        modalManager.close('history-modal');
    }

    /**
     * Load and render global market transaction history
     */
    async loadGlobalTransactions() {
        const tbody = document.getElementById('global-history-table-body');
        const emptyMsg = document.getElementById('global-history-empty-msg');
        const loadingMsg = document.getElementById('global-history-loading');

        if (!tbody) return;

        try {
            loadingMsg?.classList.remove('hidden');
            emptyMsg?.classList.add('hidden');

            const response = await fetch('/CNDQ/api/trades/global.php');
            const data = await response.json();

            loadingMsg?.classList.add('hidden');

            if (!data.success || !data.transactions || data.transactions.length === 0) {
                emptyMsg?.classList.remove('hidden');
                tbody.innerHTML = '';
                return;
            }

            this.renderGlobalTransactionHistory(data.transactions);
        } catch (error) {
            console.error('Failed to load global transactions:', error);
            loadingMsg?.classList.add('hidden');
            emptyMsg?.classList.remove('hidden');
            tbody.innerHTML = '';
        }
    }

    /**
     * Render Global Transaction History Table
     * Shows complete audit trail with both parties' inventory
     */
    renderGlobalTransactionHistory(transactions) {
        const tbody = document.getElementById('global-history-table-body');
        const emptyMsg = document.getElementById('global-history-empty-msg');

        if (!tbody) return;

        tbody.innerHTML = '';

        if (!transactions || transactions.length === 0) {
            emptyMsg?.classList.remove('hidden');
            return;
        }

        emptyMsg?.classList.add('hidden');

        transactions.forEach(t => {
            const row = document.createElement('tr');
            row.className = 'hover:bg-gray-700/50 transition text-sm';

            let timeStr = 'Unknown';
            if (t.timestamp) {
                const date = new Date(t.timestamp * 1000);
                timeStr = date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            }

            // Hot trade indicator
            const isHot = t.heat?.isHot;
            const heatLabel = isHot ? '🔥 Hot' : t.heat?.total > 0 ? '✓ Good' : '—';
            const heatBgClass = isHot ? 'bg-red-500/20 text-red-400' : t.heat?.total > 0 ? 'bg-green-500/20 text-green-400' : 'text-gray-500';

            // Format inventory values (handle null for old transactions)
            const formatInv = (val) => val !== null && val !== undefined ? val.toFixed(1) : '—';

            row.innerHTML = `
                <td class="py-2 px-2 font-mono text-gray-400 whitespace-nowrap">${timeStr}</td>
                <td class="py-2 px-2 font-bold" style="color: var(--color-chemical-${t.chemical?.toLowerCase() || 'c'})">${t.chemical}</td>
                <td class="py-2 px-2 text-right font-mono">${this.formatNumber(t.quantity)}</td>
                <td class="py-2 px-2 text-right font-mono">${this.formatCurrency(t.pricePerGallon)}</td>
                <td class="py-2 px-2 text-right font-mono font-bold text-white">${this.formatCurrency(t.totalAmount)}</td>
                <td class="py-2 px-2 text-green-400 border-l border-gray-700">${t.sellerName}</td>
                <td class="py-2 px-2 text-right font-mono text-gray-400">${formatInv(t.sellerInvBefore)}</td>
                <td class="py-2 px-2 text-right font-mono text-gray-300">${formatInv(t.sellerInvAfter)}</td>
                <td class="py-2 px-2 text-blue-400 border-l border-gray-700">${t.buyerName}</td>
                <td class="py-2 px-2 text-right font-mono text-gray-400">${formatInv(t.buyerInvBefore)}</td>
                <td class="py-2 px-2 text-right font-mono text-gray-300">${formatInv(t.buyerInvAfter)}</td>
                <td class="py-2 px-2 text-center border-l border-gray-700">
                    <span class="px-2 py-0.5 rounded text-xs font-semibold ${heatBgClass}">${heatLabel}</span>
                </td>
            `;

            tbody.appendChild(row);
        });
    }

    /**
     * Render listing board
     * Updates chemical cards with current listings
     */
    renderListings() {
        if (!this.listings) return;

        ['C', 'N', 'D', 'Q'].forEach(chemical => {
            const card = document.querySelector(`chemical-card[chemical="${chemical}"]`);
            if (card) {
                card.currentUserId = this.currentUser;
                card.inventory = this.inventory[chemical];
                card.shadowPrice = this.shadowPrices[chemical];
                
                card.ranges = this.ranges?.[chemical] || { allowableIncrease: 0, allowableDecrease: 0 };

                // Check for active negotiations for this chemical
                card.hasActiveNegotiation = this.myNegotiations.some(n => 
                    n.chemical === chemical && n.status === 'pending'
                );

                // Filter listings - only show what they can sell into (human buy requests)
                // Filter rule: If they have 0 inventory, hide all buy requests for that chemical
                // (Can't sell what you don't have)
                const allBuyListings = this.listings[chemical]?.buy || [];
                const myInventory = this.inventory[chemical] || 0;
                
                const buyListings = myInventory > 0 ? allBuyListings : [];

                card.buyListings = buyListings;
            }
        });
    }

    /**
     * Render negotiations summary using web components
     * Includes pending buy requests (Phase 0) at the top, followed by active negotiations
     */
    renderNegotiations() {
        const container = document.getElementById('my-negotiations');

        // Collect user's own pending buy requests (Phase 0 - before anyone responds)
        const myBuyRequests = [];
        for (const chemical of ['C', 'N', 'D', 'Q']) {
            const listings = this.listings[chemical]?.buy || [];
            const myListing = listings.find(l => l.teamId === this.currentUser);
            if (myListing) {
                myBuyRequests.push({ ...myListing, chemical });
            }
        }

        // Show pending negotiations OR newly completed ones that haven't been dismissed
        const pendingOrNew = this.myNegotiations.filter(n =>
            n.status === 'pending' ||
            (n.status !== 'pending' && !this.seenCompletedNegotiations.has(n.id))
        ).slice(0, 5);

        // Check if there's anything to show
        const hasContent = myBuyRequests.length > 0 || pendingOrNew.length > 0;

        if (!hasContent) {
            container.innerHTML = '<p class="text-gray-300 text-center py-8">You have no active negotiations</p>';
            return;
        }

        // Remove the "No pending" or "No active" message if it exists (check first child)
        if (container.firstElementChild && container.firstElementChild.tagName === 'P') {
            container.innerHTML = '';
        }

        // Map existing cards by ID (for both buy-request-cards and negotiation-cards)
        const existingBuyRequestMap = new Map();
        const existingNegotiationMap = new Map();
        Array.from(container.children).forEach(child => {
            if (child.tagName === 'BUY-REQUEST-CARD') {
                const id = child.getAttribute('listing-id') || (child.listing && child.listing.id);
                if (id) existingBuyRequestMap.set(String(id), child);
            } else if (child.tagName === 'NEGOTIATION-CARD') {
                const id = child.getAttribute('negotiation-id') || (child.negotiation && child.negotiation.id);
                if (id) existingNegotiationMap.set(String(id), child);
            } else {
                child.remove(); // Remove non-card elements
            }
        });

        // Render buy request cards first (Phase 0)
        myBuyRequests.forEach(listing => {
            let card = existingBuyRequestMap.get(String(listing.id));

            if (card) {
                // Update existing card
                card.listing = listing;
                existingBuyRequestMap.delete(String(listing.id));
            } else {
                // Create new card
                card = document.createElement('buy-request-card');
                card.listing = listing;
                card.currentUserId = this.currentUser;
            }

            // Insert at top (before negotiations)
            container.appendChild(card);
        });

        // Render negotiation cards
        pendingOrNew.forEach(neg => {
            let card = existingNegotiationMap.get(String(neg.id));

            if (card) {
                // Update existing card
                card.negotiation = neg;
                existingNegotiationMap.delete(String(neg.id));
            } else {
                // Create new card
                card = document.createElement('negotiation-card');
                card.negotiation = neg;
                card.currentUserId = this.currentUser;
                card.context = 'summary';
            }

            // Handle synopsis view state
            const shouldShowSynopsis = neg.status !== 'pending' && !this.seenCompletedNegotiations.has(neg.id);
            if (shouldShowSynopsis) {
                if (!card.hasAttribute('show-synopsis')) {
                    card.setAttribute('show-synopsis', 'true');
                }
            } else {
                if (card.hasAttribute('show-synopsis')) {
                    card.removeAttribute('show-synopsis');
                }
            }

            // Append after buy request cards
            container.appendChild(card);
        });

        // Remove cards that are no longer in the list
        existingBuyRequestMap.forEach(card => card.remove());
        existingNegotiationMap.forEach(card => card.remove());
    }

    /**
     * Post listing (interest to buy)
     */
    async postListing(chemical, type = 'buy') {
        const adKey = `${chemical}-buy`;

        // Check if already posting this listing (prevent race condition)
        if (this.pendingListingPosts.has(adKey)) {
            notifications.showToast(`Already posting buy listing for Chemical ${chemical}...`, 'warning');
            return;
        }

        // Check if user already has an active listing for this chemical
        const existingAds = this.listings[chemical]?.['buy'] || [];
        const hasActiveAd = existingAds.some(ad => ad.teamId === this.currentUser);

        if (hasActiveAd) {
            notifications.showToast(`You already have an active buy listing for Chemical ${chemical}`, 'warning');
            return;
        }

        try {
            // Mark as pending to prevent duplicate clicks
            this.pendingListingPosts.add(adKey);

            const response = await api.listings.post(chemical, 'buy');

            // Check if the returned listing was just created or already existed
            const returnedAd = response.listing;
            const isNewAd = returnedAd && (Date.now() - returnedAd.createdAt * 1000) < 2000; // Created within last 2 seconds

            if (isNewAd) {
                notifications.showToast(`Posted interest to buy ${chemical}`, 'success');
            } else {
                notifications.showToast(`You already have an active buy listing for Chemical ${chemical}`, 'warning');
            }

            // Wait for listings to reload so duplicate check works on next click
            await stateManager.loadListings();
        } catch (error) {
            console.error('Failed to post listing:', error);
            notifications.showToast('Failed to post listing: ' + error.message, 'error');
        } finally {
            // Always remove pending flag
            this.pendingListingPosts.delete(adKey);
        }
    }

    /**
     * Open buy request modal
     */
    openBuyRequestModal(chemical) {
        console.log(`📋 Opening Buy Request Modal for ${chemical}`);
        window.LAST_OPENED_MODAL = chemical;
        // Don't open if production modal is visible (it should block everything)
        if (this.isProductionModalBlocking()) {
            console.log('⚠️ Production modal is open - blocking offer modal');
            return;
        }

        // Check if user already has an active buy listing for this chemical
        const existingAds = this.listings[chemical]?.['buy'] || [];
        const hasActiveBuyAd = existingAds.some(ad => ad.buyerId === this.currentUser);

        // If revising, check for active negotiations related to this chemical
        if (hasActiveBuyAd) {
            const activeNegotiationsForChemical = this.myNegotiations.filter(
                n => n.chemical === chemical && n.status === 'pending'
            );
            if (activeNegotiationsForChemical.length > 0) {
                notifications.showToast(
                    `Cannot revise: You have ${activeNegotiationsForChemical.length} active negotiation(s) for Chemical ${chemical}. Complete or cancel them first.`,
                    'warning',
                    5000
                );
                return;
            }
        }

        const modal = document.getElementById('offer-modal');
        document.getElementById('offer-chemical').value = `Chemical ${chemical}`;
        document.getElementById('offer-shadow-hint').textContent = this.formatCurrency(this.shadowPrices[chemical]);
        
        // Store current chemical for later
        this.currentOfferChemical = chemical;

        modal?.classList.remove('hidden');
    }

    /**
     * Update buy request total and validate funds
     */
    updateBuyRequestTotal() {
        const quantity = parseInt(document.getElementById('offer-quantity').value) || 0;
        const price = parseFloat(document.getElementById('offer-price').value) || 0;
        const total = quantity * price;

        document.getElementById('offer-total').textContent = this.formatCurrency(total);
        
        // Calculate Profit Delta (Buying: (ShadowPrice - Price) * Quantity)
        const shadowPrice = this.shadowPrices[this.currentOfferChemical] || 0;
        const profitDelta = (shadowPrice - price) * quantity;
        const deltaEl = document.getElementById('offer-profit-delta');
        if (deltaEl) {
            deltaEl.textContent = (profitDelta >= 0 ? '+' : '') + this.formatCurrency(profitDelta);
            deltaEl.className = `font-bold ${profitDelta >= 0 ? 'text-green-400' : 'text-red-400'}`;
        }

        // Sensitivity Warning (Buying: check allowableIncrease)
        const range = this.ranges?.[this.currentOfferChemical];
        const warningEl = document.getElementById('offer-sensitivity-warning');
        if (range && warningEl) {
            if (quantity > range.allowableIncrease) {
                warningEl?.classList.remove('hidden');
            } else {
                warningEl?.classList.add('hidden');
            }
        }

        // Funds display in modal now shows projected profit improvement
        document.getElementById('offer-current-funds').textContent = this.formatCurrency(this.profile.currentFunds);

        const warning = document.getElementById('insufficient-funds-warning');
        warning?.classList.add('hidden');
    }

    /**
     * Submit buy request
     */
    async submitBuyRequest() {
        const chemical = this.currentOfferChemical;
        const quantity = parseInt(document.getElementById('offer-quantity').value);
        const maxPrice = parseFloat(document.getElementById('offer-price').value);

        if (!chemical || quantity <= 0 || maxPrice < 0) {
            notifications.showToast('Invalid input', 'error');
            return;
        }

        try {
            const response = await api.offers.bid(chemical, quantity, maxPrice);

            if (response.success) {
                notifications.showToast(`Buy request posted for ${quantity} gallons of ${chemical}`, 'success');
                
                // Teachable moment: Remind about stale shadow prices
                if (this.lastStalenessLevel === 'stale') {
                    notifications.showToast('💡 Tip: Prices change as you trade! Recalculate shadow prices to see how this buy request affects your production value.', 'info', 6000);
                }

                this.closeOfferModal();
                await stateManager.loadListings();
            } else {
                notifications.showToast(response.message || 'Failed to post buy request', 'error');
            }
        } catch (error) {
            console.error('Failed to submit buy request:', error);
            notifications.showToast('Failed to post buy request: ' + error.message, 'error');
        }
    }

    /**
     * Close offer modal
     */
    closeOfferModal() {
        document.getElementById('offer-modal')?.classList.add('hidden');
        this.currentOfferChemical = null;
    }

    /**
     * Open respond to buy request modal
     */
    openRespondModal(buyerTeamId, buyerTeamName, chemical, listingId) {
        // Don't open if production modal is visible (it should block everything)
        if (this.isProductionModalBlocking()) {
            console.log('⚠️ Production modal is open - blocking respond modal');
            return;
        }

        const modal = document.getElementById('respond-modal');

        // Store context for later
        this.currentRespondContext = {
            buyerTeamId,
            buyerTeamName,
            chemical,
            listingId
        };

        // Set buyer info
        document.getElementById('respond-buyer-name').textContent = buyerTeamName;
        document.getElementById('respond-chemical').textContent = `Chemical ${chemical}`;

        // Get buy request details from listings
        const buyListings = this.listings[chemical]?.buy || [];
        const buyRequest = buyListings.find(ad => ad.id === listingId);

        // Set request details (if we have them - otherwise they are hidden/negotiable)
        const requestedQtyEl = document.getElementById('respond-requested-qty');
        const maxPriceEl = document.getElementById('respond-max-price');

        if (buyRequest && buyRequest.quantity) {
            requestedQtyEl.textContent = `${buyRequest.quantity.toLocaleString()} gallons`;
        } else {
            requestedQtyEl.textContent = 'Negotiable';
        }

        if (buyRequest && buyRequest.maxPrice) {
            maxPriceEl.textContent = this.formatCurrency(buyRequest.maxPrice) + '/gal';
        } else {
            maxPriceEl.textContent = 'Negotiable';
        }

        // Set your inventory and shadow price
        const yourInventory = this.inventory[chemical] || 0;
        const yourShadowPrice = this.shadowPrices[chemical] || 0;

        document.getElementById('respond-your-inventory').textContent = yourInventory.toLocaleString();
        document.getElementById('respond-shadow-price').textContent = this.formatCurrency(yourShadowPrice);

        // Set range display
        const rangeEl = document.getElementById('respond-range-display');
        if (rangeEl) {
            const range = this.ranges?.[chemical];
            if (range) {
                const dec = range.allowableDecrease || 0;
                const inc = range.allowableIncrease || 0;
                const incText = inc >= 9000 ? '∞' : inc.toFixed(0);
                rangeEl.textContent = `[-${dec.toFixed(0)}, +${incText}] gal`;
            } else {
                rangeEl.textContent = 'N/A';
            }
        }

        // Set slider max to inventory
        document.getElementById('respond-quantity-slider').max = yourInventory;
        document.getElementById('respond-quantity').max = yourInventory;

        // Initialize with reasonable defaults
        // If buyer requested a specific qty, default to that (clamped by inventory)
        const requestedQty = buyRequest?.quantity || 100;
        const defaultQty = Math.min(requestedQty, yourInventory);
        document.getElementById('respond-quantity').value = defaultQty;
        document.getElementById('respond-quantity-slider').value = defaultQty;
        
        // Default price: If buyer has max price, use that. Otherwise use shadow price + a bit of profit.
        const defaultPrice = buyRequest?.maxPrice || (yourShadowPrice + 1.00);
        document.getElementById('respond-price').value = defaultPrice.toFixed(2);

        // Update total
        this.updateRespondTotal();

        modal?.classList.remove('hidden');
    }

    /**
     * Update respond modal total and validate inventory
     */
    updateRespondTotal() {
        const quantity = parseInt(document.getElementById('respond-quantity').value) || 0;
        const price = parseFloat(document.getElementById('respond-price').value) || 0;
        const total = quantity * price;

        document.getElementById('respond-total').textContent = this.formatCurrency(total);

        // Calculate Profit Delta (Selling: (Price - ShadowPrice) * Quantity)
        const chemical = this.currentRespondContext?.chemical;
        const shadowPrice = this.shadowPrices[chemical] || 0;
        const profitDelta = (price - shadowPrice) * quantity;
        const deltaEl = document.getElementById('respond-profit-delta');
        if (deltaEl) {
            deltaEl.textContent = (profitDelta >= 0 ? '+' : '') + this.formatCurrency(profitDelta);
            deltaEl.className = `font-bold ${profitDelta >= 0 ? 'text-green-400' : 'text-red-400'}`;
        }

        // Sensitivity Warning (Selling: check allowableDecrease)
        const range = this.ranges?.[chemical];
        const warningEl = document.getElementById('respond-sensitivity-warning');
        if (range && warningEl) {
            if (quantity > range.allowableDecrease) {
                warningEl?.classList.remove('hidden');
            } else {
                warningEl?.classList.add('hidden');
            }
        }

        const submitBtn = document.getElementById('respond-submit-btn');
        const warning = document.getElementById('insufficient-inventory-warning');

        const yourInventory = this.inventory[chemical] || 0;

        if (quantity > yourInventory) {
            warning?.classList.remove('hidden');
            submitBtn.disabled = true;
        } else {
            warning?.classList.add('hidden');
            submitBtn.disabled = false;
        }
    }

    /**
     * Submit response to buy request (initiate negotiation)
     */
    async submitRespondOffer() {
        if (!this.currentRespondContext) return;

        const { buyerTeamId, buyerTeamName, chemical, listingId } = this.currentRespondContext;
        const quantity = parseInt(document.getElementById('respond-quantity').value);
        const price = parseFloat(document.getElementById('respond-price').value);

        const yourInventory = this.inventory[chemical] || 0;

        if (quantity <= 0 || price < 0) {
            notifications.showToast('Invalid quantity or price', 'error');
            return;
        }

        if (quantity > yourInventory) {
            notifications.showToast('Insufficient inventory', 'error');
            return;
        }

        try {
            // Initiate negotiation with the buyer. User is responding to a buy request,
            // so from the user's perspective (the initiator of this negotiation), it's a 'sell'.
            const response = await api.negotiations.initiate(buyerTeamId, chemical, quantity, price, 'sell', listingId);

            if (response.success) {
                notifications.showToast(`Offer sent to ${buyerTeamName} for ${quantity} gallons of ${chemical}`, 'success');
                
                // OPTIMISTIC UPDATE: Remove the listing from UI immediately
                if (this.listings[chemical] && this.listings[chemical].buy) {
                    this.listings[chemical].buy = this.listings[chemical].buy.filter(l => l.id !== listingId);
                    this.renderListings();
                }

                // Teachable moment: Remind about stale shadow prices
                if (this.lastStalenessLevel === 'stale') {
                    notifications.showToast('💡 Tip: Before sending more offers, recalculate your shadow prices. Your inventory has changed!', 'info', 6000);
                }

                this.closeRespondModal();
                await stateManager.loadNegotiations();
                await stateManager.loadListings(); // Refresh to ensure sync
            } else {
                notifications.showToast(response.message || 'Failed to send offer', 'error');
            }
        } catch (error) {
            console.error('Failed to submit respond offer:', error);
            notifications.showToast('Failed to send offer: ' + error.message, 'error');
        }
    }

    /**
     * Close respond modal
     */
    closeRespondModal() {
        document.getElementById('respond-modal')?.classList.add('hidden');
        this.currentRespondContext = null;
    }

    /**
     * Check if production modal is currently blocking other modals
     */
    isProductionModalBlocking() {
        const productionModal = document.getElementById('production-results-modal');
        if (!productionModal || !productionModal.classList) return false;
        const isBlocking = !productionModal?.classList.contains('hidden');
        if (isBlocking) {
            console.warn('⚠️ UI Blocked: Production modal is currently visible.');
        }
        return isBlocking;
    }

    /**
     * Open negotiation modal
     */
    openNegotiationModal() {
        // Don't open if production modal is visible (it should block everything)
        if (this.isProductionModalBlocking()) {
            console.log('⚠️ Production modal is open - blocking negotiation modal');
            return;
        }

        const modal = document.getElementById('negotiation-modal');
        modal?.classList.remove('hidden');
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');

        // Show list view by default
        this.showNegotiationListView();

        // Render negotiations in modal
        this.renderNegotiationsInModal();
    }

    /**
     * Close negotiation modal
     */
    closeNegotiationModal() {
        const modal = document.getElementById('negotiation-modal');
        modal?.classList.add('hidden');
        modal.removeAttribute('role');
        modal.removeAttribute('aria-modal');
    }

    /**
     * Show negotiation list view in modal
     */
    showNegotiationListView() {
        document.getElementById('negotiation-list-view')?.classList.remove('hidden');
        document.getElementById('negotiation-detail-view')?.classList.add('hidden');
        document.getElementById('start-negotiation-view')?.classList.add('hidden');
    }

    /**
     * Render negotiations in modal
     */
    renderNegotiationsInModal() {
        const pending = this.myNegotiations.filter(n => n.status === 'pending');
        const completed = this.myNegotiations.filter(n => n.status !== 'pending');

        // Render pending
        const pendingContainer = document.getElementById('pending-negotiations');
        if (pending.length === 0) {
            pendingContainer.innerHTML = '<p class="text-gray-300 text-center py-4">No pending negotiations</p>';
        } else {
            pendingContainer.innerHTML = '';
            pending.forEach(neg => {
                const card = document.createElement('negotiation-card');
                card.negotiation = neg;
                card.currentUserId = this.currentUser;
                card.context = 'list';
                pendingContainer.appendChild(card);
            });
        }

        // Render completed
        const completedContainer = document.getElementById('completed-negotiations');
        if (completed.length === 0) {
            completedContainer.innerHTML = '<p class="text-gray-300 text-center py-4">No completed negotiations</p>';
        } else {
            completedContainer.innerHTML = '';
            // Show newest completed first
            completed.sort((a, b) => (b.updatedAt || 0) - (a.updatedAt || 0));
            
            completed.forEach(neg => {
                const card = document.createElement('negotiation-card');
                card.negotiation = neg;
                card.currentUserId = this.currentUser;
                card.context = 'list';
                // If it's completed and not yet seen, show the synopsis view
                if (!this.seenCompletedNegotiations.has(neg.id)) {
                    card.setAttribute('show-synopsis', true);
                }
                completedContainer.appendChild(card);
            });
        }
    }

    /**
     * View negotiation detail
     */
    viewNegotiationDetail(negotiationId) {
        const negotiation = this.myNegotiations.find(n => n.id === negotiationId);
        if (!negotiation) {
            console.error('Negotiation not found:', negotiationId);
            return;
        }

        // CRITICAL: Ensure the modal is actually open
        this.openNegotiationModal();

        this.currentNegotiation = negotiation;

        // Show detail view
        document.getElementById('negotiation-list-view')?.classList.add('hidden');
        document.getElementById('negotiation-detail-view')?.classList.remove('hidden');
        document.getElementById('start-negotiation-view')?.classList.add('hidden');

        // Set header
        document.getElementById('detail-chemical').textContent = `Chemical ${negotiation.chemical}`;
        const otherTeam = negotiation.initiatorId === this.currentUser ? negotiation.responderName : negotiation.initiatorName;
        
        // Determine if user is buying or selling
        const type = negotiation.type || 'buy';
        const isBuyer = (negotiation.initiatorId === this.currentUser && type === 'buy') || 
                        (negotiation.responderId === this.currentUser && type === 'sell');
        const roleText = isBuyer ? '<span class="text-blue-400 font-bold ml-2">BUYING</span>' : '<span class="text-green-400 font-bold ml-2">SELLING</span>';
        
        document.getElementById('detail-participants').innerHTML = `Negotiation with ${otherTeam} • ${roleText}`;

        // Set status badge
        const statusBadge = document.getElementById('detail-status-badge');
        if (negotiation.status === 'pending') {
            statusBadge.textContent = 'Pending';
            statusBadge.className = 'px-3 py-1 rounded-full text-sm font-semibold bg-yellow-600 text-white';
        } else if (negotiation.status === 'accepted') {
            statusBadge.textContent = 'Accepted';
            statusBadge.className = 'px-3 py-1 rounded-full text-sm font-semibold bg-green-600 text-white';
        } else {
            statusBadge.textContent = 'Rejected';
            statusBadge.className = 'px-3 py-1 rounded-full text-sm font-semibold bg-red-600 text-white';
        }

        // Render offer history
        const historyContainer = document.getElementById('offer-history');
        historyContainer.innerHTML = '';
        
        negotiation.offers.forEach((offer) => {
            const bubble = document.createElement('offer-bubble');
            bubble.offer = offer;
            bubble.isFromMe = offer.fromTeamId === this.currentUser;
            historyContainer.appendChild(bubble);
        });

        // Show/hide action buttons based on state
        const isMyTurn = negotiation.lastOfferBy !== this.currentUser && negotiation.status === 'pending';
        const counterForm = document.getElementById('counter-offer-form');
        const actions = document.getElementById('negotiation-actions');
        const waiting = document.getElementById('waiting-message');

        counterForm?.classList.add('hidden');

        if (negotiation.status !== 'pending') {
            // Negotiation is complete
            actions?.classList.add('hidden');
            waiting?.classList.add('hidden');
        } else if (isMyTurn) {
            // My turn to respond
            actions?.classList.remove('hidden');
            waiting?.classList.add('hidden');

            // Initialize Haggle Sliders (Witcher 3 Style)
            const shadowVal = this.shadowPrices[negotiation.chemical] || 2.0;
            const inventoryVal = this.inventory[negotiation.chemical] || 0;
            const latestOffer = negotiation.offers[negotiation.offers.length - 1];

            const qtySlider = document.getElementById('haggle-qty-slider');
            const priceInput = document.getElementById('haggle-price-input');

            // Set Quantity Range
            const maxQty = isBuyer ? 2000 : Math.floor(inventoryVal); // Buyer max is arbitrary/funds-based, Seller max is inventory
            document.getElementById('haggle-qty-max').textContent = maxQty;
            qtySlider.min = 1;
            qtySlider.max = maxQty;
            qtySlider.value = latestOffer.quantity;
            document.getElementById('haggle-qty-display').textContent = latestOffer.quantity;

            // Display shadow price range information
            const rangeDisplay = document.getElementById('haggle-range-display');
            if (rangeDisplay && this.ranges && this.ranges[negotiation.chemical]) {
                const range = this.ranges[negotiation.chemical];
                const decrease = range.allowableDecrease || 0;
                const increase = range.allowableIncrease || 0;
                const isRangeZero = (increase + decrease) < 1;

                if (isRangeZero) {
                    rangeDisplay.textContent = 'N/A (Low Inventory)';
                } else {
                    const increaseText = increase >= 9000 ? '∞' : increase.toFixed(0);
                    rangeDisplay.textContent = `[-${decrease.toFixed(0)}, +${increaseText}] gal`;
                }
            }

            // Set Price Input (pre-populated with last offer price)
            priceInput.value = latestOffer.price.toFixed(2);
            document.getElementById('haggle-shadow-hint').textContent = this.formatCurrency(shadowVal);

            this.updateHaggleUI(shadowVal, isBuyer);
        } else {
            // Waiting for other team
            actions?.classList.add('hidden');
            waiting?.classList.remove('hidden');
        }
    }

    /**
     * Start new negotiation with a team
     */
    startNewNegotiation(teamId, teamName, chemical, type) {
        // Show start negotiation view
        document.getElementById('negotiation-list-view')?.classList.add('hidden');
        document.getElementById('negotiation-detail-view')?.classList.add('hidden');
        document.getElementById('start-negotiation-view')?.classList.remove('hidden');

        // Set fields
        document.getElementById('new-neg-team').value = teamName;
        document.getElementById('new-neg-chemical').value = chemical;
        const shadowPrice = this.shadowPrices[chemical] || 0;
        document.getElementById('new-neg-shadow-hint').textContent = this.formatCurrency(shadowPrice);

        // Store in temp state
        this.tempNegotiation = { teamId, teamName, chemical, type };

        // Initialize values
        const qtyInput = document.getElementById('new-neg-quantity');
        const qtySlider = document.getElementById('new-neg-quantity-slider');
        const priceInput = document.getElementById('new-neg-price');
        
        // Determine max quantity
        let maxQty = 2000;
        if (type === 'sell') {
            const inventory = this.inventory[chemical] || 0;
            maxQty = Math.floor(inventory);
        }

        qtySlider.max = maxQty;
        // Default to 100 or max if less
        const defaultQty = Math.min(100, maxQty);
        qtySlider.value = defaultQty;
        qtyInput.value = defaultQty;
        
        // Initialize price
        priceInput.value = Math.max(shadowPrice, 5.00).toFixed(2);
    }

    /**
     * Submit new negotiation
     */
    async submitNewNegotiation() {
        const quantity = parseFloat(document.getElementById('new-neg-quantity').value);
        const price = parseFloat(document.getElementById('new-neg-price').value);

        if (!quantity || quantity <= 0) {
            notifications.showToast('Please enter a valid quantity', 'error');
            return;
        }

        if (price === null || price < 0) {
            notifications.showToast('Please enter a valid price', 'error');
            return;
        }

        try {
            await api.negotiations.initiate(
                this.tempNegotiation.teamId,
                this.tempNegotiation.chemical,
                quantity,
                price,
                this.tempNegotiation.type || 'buy'
            );
            notifications.showToast('Negotiation started', 'success');

            // Teachable moment: Remind about stale shadow prices
            if (this.lastStalenessLevel === 'stale') {
                notifications.showToast('💡 Tip: Your inventory changed! Are you sure your offer is still profitable? Recalculate shadow prices to be sure.', 'info', 6000);
            }

            await stateManager.loadNegotiations();
            this.showNegotiationListView();
            this.renderNegotiationsInModal();
        } catch (error) {
            console.error('Failed to start negotiation:', error);
            notifications.showToast('Failed to start negotiation: ' + error.message, 'error');
        }
    }

    async makeCounterOffer() {
        const quantity = parseFloat(document.getElementById('haggle-qty-slider').value);
        const price = parseFloat(document.getElementById('haggle-price-input').value) || 0;
        const reaction = parseFloat(document.getElementById('haggle-reaction-slider').value);
        const total = quantity * price;
        const chemical = this.currentNegotiation.chemical;

        if (!quantity || quantity <= 0) {
            notifications.showToast('Please enter a valid quantity', 'error');
            return;
        }

        if (!price || price <= 0) {
            notifications.showToast('Please enter a valid price', 'error');
            return;
        }

        // Determine if current user is buying or selling
        const type = this.currentNegotiation.type || 'buy';
        const isSelling = (this.currentNegotiation.initiatorId === this.currentUser && type === 'sell') || 
                          (this.currentNegotiation.responderId === this.currentUser && type === 'buy');

        // Check feasibility
        if (isSelling) {
            const currentInv = this.inventory[chemical] || 0;
            if (currentInv < quantity) {
                notifications.showToast(`Insufficient inventory for this counter-offer!`, 'error');
                return;
            }
        } else {
            // NEW MODEL: Infinite Capital.
            // Buyer can always send an offer regardless of funds.
        }

        try {
            // First send the reaction (ghost player event)
            await api.post('api/negotiations/react.php', {
                negotiationId: this.currentNegotiation.id,
                level: reaction
            });

            await api.negotiations.counter(this.currentNegotiation.id, quantity, price);
            notifications.showToast('Counter-offer sent', 'success');
            await stateManager.loadNegotiations();
            this.viewNegotiationDetail(this.currentNegotiation.id);
        } catch (error) {
            console.error('Failed to send counter-offer:', error);
            notifications.showToast('Failed to send counter-offer: ' + error.message, 'error');
        }
    }

    /**
     * Analyze trade quality based on shadow prices
     */
    analyzeTradeQuality(negotiation) {
        const chemical = negotiation.chemical;
        const shadowPrice = this.shadowPrices[chemical];
        const lastOffer = negotiation.offers[negotiation.offers.length - 1];
        const tradePrice = lastOffer.price;
        const quantity = lastOffer.quantity;

        // Determine if current user is buying or selling
        // type is from initiator's perspective
        const type = negotiation.type || 'buy';
        const isSelling = (negotiation.initiatorId === this.currentUser && type === 'sell') || 
                          (negotiation.responderId === this.currentUser && type === 'buy');

        // Calculate percentage difference from shadow price
        const priceDiff = ((tradePrice - shadowPrice) / shadowPrice) * 100;

        let quality = 'neutral';
        let message = '';

        // Only show special toasts if shadow price is meaningful (> 0)
        if (shadowPrice > 0) {
            if (isSelling) {
                // Selling: Good if price > shadow price
                if (priceDiff >= 25) {
                    quality = 'excellent';
                    message = `Excellent sale! ${priceDiff.toFixed(0)}% above optimal value ($${tradePrice.toFixed(2)} vs $${shadowPrice.toFixed(2)} shadow price)`;
                } else if (priceDiff >= 10) {
                    quality = 'good';
                    message = `Good sale! ${priceDiff.toFixed(0)}% above shadow price ($${tradePrice.toFixed(2)} vs $${shadowPrice.toFixed(2)})`;
                } else if (priceDiff <= -25) {
                    quality = 'bad';
                    message = `Poor sale! ${Math.abs(priceDiff).toFixed(0)}% below optimal value ($${tradePrice.toFixed(2)} vs $${shadowPrice.toFixed(2)} shadow price)`;
                } else if (priceDiff <= -10) {
                    quality = 'warning';
                    message = `Below-market sale: ${Math.abs(priceDiff).toFixed(0)}% under shadow price ($${tradePrice.toFixed(2)} vs $${shadowPrice.toFixed(2)})`;
                }
            } else {
                // Buying: Good if price < shadow price
                if (priceDiff <= -25) {
                    quality = 'excellent';
                    message = `Excellent purchase! ${Math.abs(priceDiff).toFixed(0)}% below optimal value ($${tradePrice.toFixed(2)} vs $${shadowPrice.toFixed(2)} shadow price)`;
                } else if (priceDiff <= -10) {
                    quality = 'good';
                    message = `Good purchase! ${Math.abs(priceDiff).toFixed(0)}% below shadow price ($${tradePrice.toFixed(2)} vs $${shadowPrice.toFixed(2)})`;
                } else if (priceDiff >= 25) {
                    quality = 'bad';
                    message = `Overpaid! ${priceDiff.toFixed(0)}% above optimal value ($${tradePrice.toFixed(2)} vs $${shadowPrice.toFixed(2)} shadow price)`;
                } else if (priceDiff >= 10) {
                    quality = 'warning';
                    message = `Above-market purchase: ${priceDiff.toFixed(0)}% over shadow price ($${tradePrice.toFixed(2)} vs $${shadowPrice.toFixed(2)})`;
                }
            }
        }

        return { quality, message, priceDiff, isSelling };
    }

    /**
     * Accept negotiation offer
     */
    async acceptNegotiation() {
        const negotiation = this.currentNegotiation;
        const lastOffer = negotiation.offers[negotiation.offers.length - 1];
        const chemical = negotiation.chemical;
        const quantity = lastOffer.quantity;
        const price = lastOffer.price;
        const total = quantity * price;

        // Determine if current user is buying or selling
        const type = negotiation.type || 'buy';
        const isSelling = (negotiation.initiatorId === this.currentUser && type === 'sell') || 
                          (negotiation.responderId === this.currentUser && type === 'buy');

        // Check feasibility
        if (isSelling) {
            const currentInv = this.inventory[chemical] || 0;
            if (currentInv < quantity) {
                notifications.showToast(`Insufficient inventory! You need ${quantity} gallons of ${chemical} but only have ${currentInv.toFixed(1)}.`, 'error');
                return;
            }
        } else {
            // NEW MODEL: Infinite Capital. 
            // Buyer can always accept an offer regardless of funds.
        }

        const otherTeam = (negotiation.initiatorId === this.currentUser)
            ? negotiation.responderName : negotiation.initiatorName;
        const action = isSelling ? 'SELL' : 'BUY';
        const confirmMsg = `${action} ${quantity} gal of Chemical ${chemical} @ ${this.formatCurrency(price)}/gal\n` +
            `Total: ${this.formatCurrency(total)} with ${otherTeam}\n\nThis trade cannot be undone.`;
        const confirmed = await this.showConfirm(confirmMsg, `Confirm Trade — Chemical ${chemical}`);
        if (!confirmed) return;

        try {
            const response = await api.negotiations.accept(this.currentNegotiation.id);
            const heat = response.trade?.heat;

            if (heat) {
                if (heat.isHot) {
                    notifications.showToast(`🔥 HOT TRADE! Value created: ${this.formatCurrency(heat.total)}`, 'hot', 5000);
                } else if (heat.isCold) {
                    notifications.showToast(`❄️ COLD TRADE! Value destroyed: ${this.formatCurrency(Math.abs(heat.total))}`, 'cold', 5000);
                } else {
                    notifications.showToast('Trade executed successfully!', 'success');
                }
            } else {
                notifications.showToast('Trade executed successfully!', 'success');
            }

            // Teachable moment: Remind about stale shadow prices
            if (this.lastStalenessLevel === 'stale') {
                notifications.showToast('💡 Tip: You\'ve made several trades without updating your valuations. Recalculate shadow prices to see your new optimal strategy!', 'info', 6000);
            }

            await stateManager.loadNegotiations();
            await stateManager.loadProfile(); // Refresh inventory
            await stateManager.loadShadowPrices(); // Refresh shadow prices

            // Close the modal - the synopsis card will show on the main page
            this.closeNegotiationModal();
        } catch (error) {
            console.error('Failed to accept offer:', error);
            
            // Check for "already accepted" message which can happen if counterparty accepts while we are looking at it
            if (error.message && (error.message.includes('already been accepted') || error.message.includes('already accepted'))) {
                notifications.showToast('This trade was already completed!', 'success', 5000);
                await stateManager.loadNegotiations();
                await stateManager.loadProfile();
                await stateManager.loadShadowPrices();
                // Close modal - synopsis card will show on main page
                this.closeNegotiationModal();
            } else {
                notifications.showToast('Failed to accept offer: ' + error.message, 'error');
            }
        }
    }

    /**
     * Reject/cancel negotiation
     */
    async rejectNegotiation(negotiationId = null) {
        // Support passing ID directly (from card) or using current active one
        const idToReject = negotiationId || this.currentNegotiation?.id;
        if (!idToReject) return;

        // Find the negotiation object for the confirmation message
        const negotiation = this.myNegotiations.find(n => n.id === idToReject);
        const name = negotiation 
            ? (negotiation.initiatorId === this.currentUser ? negotiation.responderName : negotiation.initiatorName)
            : 'this negotiation';

        const confirmed = await this.showConfirm(`Cancel negotiation with ${name}?`, 'Cancel Negotiation');
        if (!confirmed) return;

        // OPTIMISTIC UPDATE: Immediately remove from UI
        this.closeNegotiationModal();
        
        // Remove from local state
        this.myNegotiations = this.myNegotiations.filter(n => n.id !== idToReject);
        this.renderNegotiations();
        this.renderNegotiationsInModal();

        try {
            await api.negotiations.reject(idToReject);
            notifications.showToast('Negotiation cancelled', 'info');
            
            // Background sync to ensure state is consistent
            await stateManager.loadNegotiations();
            // No need to re-render here unless the server state differs, 
            // but loadNegotiations triggers 'negotiationsUpdated' event which will re-render.
        } catch (error) {
            console.error('Failed to cancel negotiation:', error);
            notifications.showToast('Failed to cancel negotiation: ' + error.message, 'error');
            // Revert on error
            await stateManager.loadNegotiations();
        }
    }

    /**
     * Load notifications
     */
    async loadNotifications() {
        try {
            const data = await api.notifications.list();
            if (data && data.success) {
                this.notifications = data.notifications;
                
                // Update component
                const notifManager = document.getElementById('notification-manager');
                if (notifManager) {
                    notifManager.notifications = data.notifications;
                    notifManager.unreadCount = data.unreadCount || 0;
                }
            }
        } catch (error) {
            console.error('Failed to load notifications:', error);
        }
    }

    /**
     * Handle clicking a notification
     */
    async handleNotificationClick(notifId) {
        // Find notification
        const notif = this.notifications.find(n => n.id === notifId);
        if (!notif) return;

        // Mark as read (optimistic)
        notif.read = true;
        
        // Refresh UI
        const notifManager = document.getElementById('notification-manager');
        if (notifManager) {
            notifManager.notifications = [...this.notifications];
        }

        // Action based on type
        if (notif.type.toLowerCase() === 'negotiation' || notif.type.toLowerCase() === 'offer') {
            this.openNegotiationModal();
        }
    }

    /**
     * Load settings
     */
    async loadSettings() {
        try {
            const data = await api.team.getSettings();
            this.settings = data.settings;
            // Settings UI update not needed for marketplace view
        } catch (error) {
            console.error('Failed to load settings:', error);
        }
    }


    /**
     * Update the Haggle (Witcher 3) UI visuals
     */
    updateHaggleUI(shadowPrice, isBuyer) {
        const qty = parseFloat(document.getElementById('haggle-qty-slider').value);
        const price = parseFloat(document.getElementById('haggle-price-input').value) || 0;
        const reaction = parseFloat(document.getElementById('haggle-reaction-slider').value);
        const total = qty * price;

        document.getElementById('haggle-qty-display').textContent = qty;
        document.getElementById('haggle-total').textContent = this.formatCurrency(total);

        // Calculate Profit Delta
        const type = this.currentNegotiation.type || 'buy';
        const userIsSelling = (this.currentNegotiation.initiatorId === this.currentUser && type === 'sell') ||
                            (this.currentNegotiation.responderId === this.currentUser && type === 'buy');
        
        const profitDelta = userIsSelling ? (price - shadowPrice) * qty : (shadowPrice - price) * qty;
        const deltaEl = document.getElementById('haggle-profit-delta');
        if (deltaEl) {
            deltaEl.textContent = (profitDelta >= 0 ? '+' : '') + this.formatCurrency(profitDelta);
            deltaEl.className = `font-bold ${profitDelta >= 0 ? 'text-green-400' : 'text-red-400'}`;
        }

        // Sensitivity Warning
        const range = this.ranges?.[this.currentNegotiation.chemical];
        const warningEl = document.getElementById('haggle-sensitivity-warning');
        if (range && warningEl) {
            const limit = userIsSelling ? range.allowableDecrease : range.allowableIncrease;
            if (qty > limit) {
                warningEl?.classList.remove('hidden');
            } else {
                warningEl?.classList.add('hidden');
            }
        }

        // Real-time Resource Validation
        const errorEl = document.getElementById('haggle-error');
        const submitBtn = document.getElementById('submit-counter-btn');
        let hasError = false;
        let errorMsg = '';

        if (userIsSelling) {
            const currentInv = this.inventory[this.currentNegotiation.chemical] || 0;
            if (qty > currentInv) {
                hasError = true;
                errorMsg = `⚠️ INSUFFICIENT ${this.currentNegotiation.chemical}: ${currentInv.toFixed(1)} gal available`;
            }
        } else {
            // NEW MODEL: Infinite Capital. 
            // Buyer can always make an offer regardless of funds.
            hasError = false;
        }

        if (hasError) {
            errorEl.textContent = errorMsg;
            errorEl?.classList.remove('hidden');
            submitBtn.disabled = true;
            submitBtn?.classList.add('opacity-50', 'cursor-not-allowed');
        } else {
            errorEl?.classList.add('hidden');
            submitBtn.disabled = false;
            submitBtn?.classList.remove('opacity-50', 'cursor-not-allowed');
        }

        // Player Reaction Label
        const reactionLabel = document.getElementById('reaction-label');
        if (reaction > 80) { reactionLabel.textContent = "Offended"; reactionLabel.className = "text-red-500 font-bold"; }
        else if (reaction > 50) { reactionLabel.textContent = "Disappointed"; reactionLabel.className = "text-yellow-500 font-bold"; }
        else if (reaction > 20) { reactionLabel.textContent = "Wary"; reactionLabel.className = "text-blue-400 font-bold"; }
        else { reactionLabel.textContent = "Neutral"; reactionLabel.className = "text-blue-300 font-bold"; }

        // Persistent NPC Patience (Loaded from state)
        const negId = this.currentNegotiation.id;
        const negState = this.profile.negotiationStates?.[negId] || { patience: 100 };

        // Calculate "Predicted" patience drain if we send this offer
        const ratio = price / Math.max(0.01, shadowPrice);

        // Show the persistent bar
        const patienceBar = document.getElementById('patience-bar');
        const patienceVal = document.getElementById('patience-value');
        const patiencePercent = Math.max(0, negState.patience);
        
        patienceBar.style.width = `${patiencePercent}%`;
        patienceVal.textContent = `${patiencePercent}%`;

        if (patiencePercent < 30) {
            patienceBar.className = "h-full bg-red-600 animate-pulse";
            // Trigger shake if they keep pushing
            const modalContent = document.querySelector('#negotiation-modal > div');
            if (modalContent) {
                modalContent?.classList.add('animate-shake');
                setTimeout(() => modalContent?.classList.remove('animate-shake'), 500);
            }
        } else if (patiencePercent < 60) {
            patienceBar.className = "h-full bg-yellow-500";
        } else {
            patienceBar.className = "h-full bg-emerald-500";
        }
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Game Over Overlay Tabs
        const setupGameOverTabs = () => {
            const tabs = {
                leaderboard: document.getElementById('tab-leaderboard'),
                history: document.getElementById('tab-history'),
                'team-sheet': document.getElementById('tab-team-sheet'),
                'answer-report': document.getElementById('tab-answer-report'),
                'sensitivity-report': document.getElementById('tab-sensitivity-report')
            };
            
            const contents = {
                leaderboard: document.getElementById('content-leaderboard'),
                history: document.getElementById('content-history'),
                'team-sheet': document.getElementById('content-team-sheet'),
                'answer-report': document.getElementById('content-answer-report'),
                'sensitivity-report': document.getElementById('content-sensitivity-report')
            };

            const resetTabs = () => {
                Object.values(tabs).forEach(t => {
                    if(t) {
                        t.classList.remove('border-purple-500', 'text-purple-200');
                        t.classList.add('border-transparent', 'text-gray-300');
                    }
                });
                Object.values(contents).forEach(c => c?.classList.add('hidden'));
            };

            const activateTab = (tabName) => {
                resetTabs();
                const tab = tabs[tabName];
                tab?.classList.remove('border-transparent', 'text-gray-300');
                tab?.classList.add('border-purple-500', 'text-purple-200');

                const content = contents[tabName];
                content?.classList.remove('hidden');

                if (tabName === 'history') {
                    this.loadGlobalTransactions();
                } else if (['team-sheet', 'answer-report', 'sensitivity-report'].includes(tabName)) {
                    // Trigger data fetch for the specific viewer in this tab
                    const viewerId = `report-viewer-${tabName}`;
                    const viewer = document.getElementById(viewerId);
                    if (viewer && typeof viewer.fetchData === 'function') {
                        viewer.fetchData();
                    }
                }
            };

            // Bind click events
            Object.keys(tabs).forEach(key => {
                tabs[key]?.addEventListener('click', () => activateTab(key));
            });
        };
        setupGameOverTabs();

        // Recalculate shadow prices
        document.getElementById('recalc-shadow-btn').addEventListener('click', () => {
            this.recalculateShadowPrices();
        });

        // Web Component Events: Post interest (from chemical-card)
        document.addEventListener('post-interest', (e) => {
            const { chemical, type } = e.detail;
            if (type === 'buy') {
                // Post buy interest directly — no price/quantity needed at this stage.
                // Price and quantity are negotiated when a seller responds.
                this.postListing(chemical, 'buy');
            } else {
                // Sell is disabled in simplified version
                notifications.showToast('Selling is disabled. Only buy requests are supported.', 'info');
            }
        });

        // Buy Request Modal event listeners
        document.getElementById('offer-cancel-btn').addEventListener('click', () => {
            this.closeOfferModal();
        });

        document.getElementById('offer-submit-btn')?.addEventListener('click', () => {
            this.submitBuyRequest();
        });

        // Sync slider and number input
        document.getElementById('offer-quantity-slider').addEventListener('input', (e) => {
            document.getElementById('offer-quantity').value = e.target.value;
            this.updateBuyRequestTotal();
        });

        document.getElementById('offer-quantity').addEventListener('input', (e) => {
            document.getElementById('offer-quantity-slider').value = e.target.value;
            this.updateBuyRequestTotal();
        });

        document.getElementById('offer-price').addEventListener('input', () => {
            this.updateBuyRequestTotal();
        });

        // +/- buttons (optional — removed from simplified modal)
        document.getElementById('quantity-plus')?.addEventListener('click', () => {
            const input = document.getElementById('offer-quantity');
            input.value = parseInt(input.value) + 10;
            document.getElementById('offer-quantity-slider').value = input.value;
            this.updateBuyRequestTotal();
        });

        document.getElementById('quantity-minus')?.addEventListener('click', () => {
            const input = document.getElementById('offer-quantity');
            input.value = Math.max(1, parseInt(input.value) - 10);
            document.getElementById('offer-quantity-slider').value = input.value;
            this.updateBuyRequestTotal();
        });

        document.getElementById('price-plus')?.addEventListener('click', () => {
            const input = document.getElementById('offer-price');
            input.value = (parseFloat(input.value) + 0.5).toFixed(2);
            this.updateBuyRequestTotal();
        });

        document.getElementById('price-minus')?.addEventListener('click', () => {
            const input = document.getElementById('offer-price');
            input.value = Math.max(0, parseFloat(input.value) - 0.5).toFixed(2);
            this.updateBuyRequestTotal();
        });

        // Web Component Events: Negotiate (from listing-item)
        document.addEventListener('negotiate', (e) => {
            const { teamId, teamName, chemical, type, listingId } = e.detail;

            // If responding to a buy request, use special respond modal
            if (type === 'buy') {
                this.openRespondModal(teamId, teamName, chemical, listingId);
            } else if (type === 'sell') {
                // If initiating negotiation with a seller (we want to buy from them)
                this.openNegotiationModal();
                this.startNewNegotiation(teamId, teamName, chemical, 'buy');
            }
        });

        // Respond Modal event listeners
        document.getElementById('respond-cancel-btn').addEventListener('click', () => {
            this.closeRespondModal();
        });

        document.getElementById('respond-submit-btn').addEventListener('click', () => {
            this.submitRespondOffer();
        });

        // Sync slider and number input for respond modal
        document.getElementById('respond-quantity-slider').addEventListener('input', (e) => {
            document.getElementById('respond-quantity').value = e.target.value;
            this.updateRespondTotal();
        });

        document.getElementById('respond-quantity').addEventListener('input', (e) => {
            document.getElementById('respond-quantity-slider').value = e.target.value;
            this.updateRespondTotal();
        });

        document.getElementById('respond-price').addEventListener('input', () => {
            this.updateRespondTotal();
        });

        // +/- buttons for respond modal
        document.getElementById('respond-qty-plus').addEventListener('click', () => {
            const input = document.getElementById('respond-quantity');
            input.value = parseInt(input.value) + 10;
            document.getElementById('respond-quantity-slider').value = input.value;
            this.updateRespondTotal();
        });

        document.getElementById('respond-qty-minus').addEventListener('click', () => {
            const input = document.getElementById('respond-quantity');
            input.value = Math.max(1, parseInt(input.value) - 10);
            document.getElementById('respond-quantity-slider').value = input.value;
            this.updateRespondTotal();
        });

        document.getElementById('respond-price-plus').addEventListener('click', () => {
            const input = document.getElementById('respond-price');
            input.value = (parseFloat(input.value) + 0.5).toFixed(2);
            this.updateRespondTotal();
        });

        document.getElementById('respond-price-minus').addEventListener('click', () => {
            const input = document.getElementById('respond-price');
            input.value = Math.max(0, parseFloat(input.value) - 0.5).toFixed(2);
            this.updateRespondTotal();
        });

        // Tutorial Navigation
        const nextBtn = document.getElementById('tutorial-next');
        if (nextBtn) {
            nextBtn.onclick = () => {
                console.log('🔘 Tutorial NEXT clicked');
                this.tutorialNext();
            };
        }

        const prevBtn = document.getElementById('tutorial-prev');
        if (prevBtn) {
            prevBtn.onclick = () => {
                console.log('🔘 Tutorial PREV clicked');
                this.tutorialPrev();
            };
        }

        const diveBtn = document.getElementById('tutorial-deep-dive');
        if (diveBtn) {
            diveBtn.onclick = () => {
                console.log('🔘 Tutorial DEEP DIVE clicked');
                this.tutorialDeepDive();
            };
        }

        const closeBtn = document.getElementById('tutorial-close');
        if (closeBtn) {
            closeBtn.onclick = () => {
                console.log('🔘 Tutorial CLOSE clicked');
                this.closeTutorial();
            };
        }

        const helpBtn = document.getElementById('help-btn');
        if (helpBtn) {
            helpBtn.onclick = () => {
                console.log('🔘 Help Button clicked');
                this.showTutorial();
            };
        }

        // Web Component Events: View negotiation detail (from negotiation-card)
        document.addEventListener('view-detail', (e) => {
            const { negotiationId, listingId, chemical, isBuyRequest } = e.detail;
            
            if (isBuyRequest) {
                console.log('📢 View-detail caught for buy request:', chemical);
                this.openBuyRequestModal(chemical);
            } else {
                console.log('📢 View-detail caught for negotiation:', negotiationId);
                this.viewNegotiationDetail(negotiationId);
            }
        });

        // Event listener for dismissing a synopsis card
        document.addEventListener('dismiss-synopsis', (e) => {
            const { negotiationId } = e.detail;
            this.seenCompletedNegotiations.add(negotiationId);
            // Re-render the views to show the normal card now
            this.renderNegotiations();
            this.renderNegotiationsInModal();
        });

        // Event listener for cancelling a negotiation from the card
        document.addEventListener('cancel-negotiation', (e) => {
            const { negotiationId } = e.detail;
            this.rejectNegotiation(negotiationId);
        });

        // Event listener for cancelling a buy request from the buy-request-card
        document.addEventListener('cancel-buy-request', async (e) => {
            const { listingId, chemical } = e.detail;
            
            // OPTIMISTIC UPDATE: Remove card immediately
            const container = document.getElementById('my-negotiations');
            const cardToRemove = container.querySelector(`buy-request-card[listing-id="${listingId}"]`);
            if (cardToRemove) {
                cardToRemove.remove();
            }

            // Update local state (listings)
            if (this.listings[chemical] && this.listings[chemical].buy) {
                this.listings[chemical].buy = this.listings[chemical].buy.filter(l => l.id !== listingId);
            }
            
            // If container is empty, show empty message
            if (container.children.length === 0) {
                 container.innerHTML = '<p class="text-gray-300 text-center py-8">You have no active negotiations</p>';
            }

            try {
                // Direct API call to bypass potential caching issues with api.js
                await api.post('api/listings/cancel.php', { listingId });
                notifications.showToast(`Cancelled buy request for Chemical ${chemical}`, 'info');
                await stateManager.loadListings();
            } catch (error) {
                notifications.showToast('Failed to cancel: ' + error.message, 'error');
                // Revert on error
                await stateManager.loadListings();
            }
        });

        // View all negotiations button
        document.getElementById('view-all-negotiations-btn').addEventListener('click', () => {
            this.openNegotiationModal();
        });

        // Negotiation modal controls
        document.getElementById('negotiation-modal-close-btn').addEventListener('click', () => {
            this.closeNegotiationModal();
        });

        document.getElementById('back-to-list-btn').addEventListener('click', () => {
            this.showNegotiationListView();
            this.renderNegotiationsInModal();
        });

        document.getElementById('back-from-new-btn').addEventListener('click', () => {
            this.showNegotiationListView();
            this.renderNegotiationsInModal();
        });

        // Start New Negotiation Sliders & Buttons
        document.getElementById('new-neg-quantity-slider').addEventListener('input', (e) => {
            document.getElementById('new-neg-quantity').value = e.target.value;
        });

        document.getElementById('new-neg-quantity').addEventListener('input', (e) => {
            document.getElementById('new-neg-quantity-slider').value = e.target.value;
        });

        document.getElementById('new-neg-qty-plus').addEventListener('click', () => {
            const input = document.getElementById('new-neg-quantity');
            const slider = document.getElementById('new-neg-quantity-slider');
            // Respect max from slider
            const max = parseFloat(slider.max);
            const val = Math.min(max, parseInt(input.value) + 10);
            input.value = val;
            slider.value = val;
        });

        document.getElementById('new-neg-qty-minus').addEventListener('click', () => {
            const input = document.getElementById('new-neg-quantity');
            const slider = document.getElementById('new-neg-quantity-slider');
            const val = Math.max(1, parseInt(input.value) - 10);
            input.value = val;
            slider.value = val;
        });

        document.getElementById('new-neg-price-plus').addEventListener('click', () => {
            const input = document.getElementById('new-neg-price');
            input.value = (parseFloat(input.value) + 0.5).toFixed(2);
        });

        document.getElementById('new-neg-price-minus').addEventListener('click', () => {
            const input = document.getElementById('new-neg-price');
            input.value = Math.max(0, parseFloat(input.value) - 0.5).toFixed(2);
        });

        // Negotiation actions
        document.getElementById('submit-new-negotiation-btn').addEventListener('click', () => {
            this.submitNewNegotiation();
        });

        document.getElementById('show-counter-form-btn').addEventListener('click', () => {
            document.getElementById('negotiation-actions')?.classList.add('hidden');
            document.getElementById('counter-offer-form')?.classList.remove('hidden');
        });

        document.getElementById('submit-counter-btn').addEventListener('click', () => {
            this.makeCounterOffer();
        });

        document.getElementById('cancel-counter-btn').addEventListener('click', () => {
            document.getElementById('counter-offer-form')?.classList.add('hidden');
            document.getElementById('negotiation-actions')?.classList.remove('hidden');
        });

        // Haggle Input Listeners
        document.getElementById('haggle-qty-slider').addEventListener('input', () => {
            const shadowVal = this.shadowPrices[this.currentNegotiation?.chemical] || 2.0;
            this.updateHaggleUI(shadowVal);
        });

        // Price +/- buttons and input
        document.getElementById('haggle-price-minus').addEventListener('click', () => {
            const input = document.getElementById('haggle-price-input');
            input.value = Math.max(0, (parseFloat(input.value) || 0) - 0.5).toFixed(2);
            const shadowVal = this.shadowPrices[this.currentNegotiation?.chemical] || 2.0;
            this.updateHaggleUI(shadowVal);
        });
        document.getElementById('haggle-price-plus').addEventListener('click', () => {
            const input = document.getElementById('haggle-price-input');
            input.value = ((parseFloat(input.value) || 0) + 0.5).toFixed(2);
            const shadowVal = this.shadowPrices[this.currentNegotiation?.chemical] || 2.0;
            this.updateHaggleUI(shadowVal);
        });
        document.getElementById('haggle-price-input').addEventListener('input', () => {
            const shadowVal = this.shadowPrices[this.currentNegotiation?.chemical] || 2.0;
            this.updateHaggleUI(shadowVal);
        });

        document.getElementById('haggle-reaction-slider').addEventListener('input', () => {
            const shadowVal = this.shadowPrices[this.currentNegotiation?.chemical] || 2.0;
            this.updateHaggleUI(shadowVal);
        });

        document.getElementById('accept-offer-btn').addEventListener('click', () => {
            this.acceptNegotiation();
        });

        document.getElementById('reject-offer-btn').addEventListener('click', () => {
            this.rejectNegotiation();
        });

        // Notifications
        const notifManager = document.getElementById('notification-manager');
        if (notifManager) {
            notifManager.addEventListener('notification-click', (e) => {
                const notif = e.detail.notification;
                this.handleNotificationClick(notif.id);
            });
        }

        // Settings
        document.getElementById('settings-btn').addEventListener('click', () => {
            this.openSettings();
        });

        document.getElementById('settings-close-btn').addEventListener('click', () => {
            this.closeSettings();
        });

        document.getElementById('theme-selector').addEventListener('change', (e) => {
            this.setTheme(e.target.value);
        });

        // Audio Settings
        const audioToggle = document.getElementById('audio-enabled-toggle');
        const volumeSlider = document.getElementById('volume-slider');
        const volumeLabel = document.getElementById('volume-value-label');
        const audioStatus = document.getElementById('audio-status-label');

        if (audioToggle) {
            audioToggle.checked = !sounds.muted;
            if (audioStatus) audioStatus.textContent = sounds.muted ? 'Muted' : 'Enabled';
            audioToggle.addEventListener('change', (e) => {
                sounds.setMuted(!e.target.checked);
                if (audioStatus) audioStatus.textContent = e.target.checked ? 'Enabled' : 'Muted';
                if (e.target.checked) sounds.playNotification();
            });
        }

        if (volumeSlider) {
            volumeSlider.value = sounds.volume * 100;
            if (volumeLabel) volumeLabel.textContent = `${Math.round(sounds.volume * 100)}%`;
            
            volumeSlider.addEventListener('input', (e) => {
                const val = e.target.value;
                sounds.setVolume(val / 100);
                if (volumeLabel) volumeLabel.textContent = `${val}%`;
            });

            volumeSlider.addEventListener('change', () => {
                sounds.playTrade(); // Test sound
            });
        }

        // Leaderboard
        document.getElementById('leaderboard-btn').addEventListener('click', () => {
            this.openLeaderboard();
        });

        // Production Guide
        document.getElementById('production-guide-btn').addEventListener('click', () => {
            this.openProductionGuide();
        });

        document.getElementById('production-guide-close-btn').addEventListener('click', () => {
            this.closeProductionGuide();
        });

        document.getElementById('production-guide-ok-btn').addEventListener('click', () => {
            this.closeProductionGuide();
        });

        // Production Results Modal event listeners
        document.getElementById('prod-result-close').addEventListener('click', () => {
            this.closeProductionResults();
        });

        document.getElementById('prod-result-continue').addEventListener('click', () => {
            this.closeProductionResults();
        });

        // Production insight toggle (collapsible section)
        document.getElementById('prod-insight-toggle')?.addEventListener('click', () => {
            const content = document.getElementById('prod-insight-content');
            const chevron = document.getElementById('prod-insight-chevron');
            if (content && chevron) {
                const isHidden = content.classList.contains('hidden');
                content.classList.toggle('hidden');
                chevron.style.transform = isHidden ? 'rotate(180deg)' : '';
            }
        });

        // Game Over Overlay restart button (only on Simulation Complete screen)
        document.getElementById('restart-game-btn').addEventListener('click', () => {
            this.restartGame();
        });

        // Transaction History
        const viewHistoryBtn = document.getElementById('view-history-btn');
        if (viewHistoryBtn) {
            viewHistoryBtn.addEventListener('click', () => {
                this.openTransactionHistory();
            });
        }

        const historyCloseBtn = document.getElementById('history-close-btn');
        if (historyCloseBtn) {
            historyCloseBtn.addEventListener('click', () => {
                this.closeTransactionHistory();
            });
        }

        // CSV Export Buttons
        document.getElementById('export-leaderboard-btn')?.addEventListener('click', () => this.exportLeaderboardCSV());
        document.getElementById('export-history-btn')?.addEventListener('click', () => this.exportTransactionHistoryCSV());
        document.getElementById('export-global-history-btn')?.addEventListener('click', () => this.exportGlobalHistoryCSV());
    }


    /**
     * Handle Polling Service Update
     */
    async handlePoll(data) {
        // Process session state
        await this._processSessionData(data);

        // If game is not finished, refresh data
        if (!this.gameFinished) {
            try {
                await Promise.all([
                    stateManager.loadProfile().catch(e => console.error('Profile poll failed', e)),
                    stateManager.loadListings().catch(e => console.error('Listing poll failed', e)),
                    stateManager.loadNegotiations().catch(e => console.error('Neg poll failed', e)),
                    this.loadNotifications().catch(e => console.error('Notif poll failed', e)),
                    stateManager.loadTransactions().catch(e => console.error('Txn poll failed', e))
                ]);
            } catch (error) {
                console.warn('⚠️ Data refresh error:', error.message);
            }
        }
    }

    /**
     * Update timer UI
     */
    updateTimerUI(time) {
        const timerEl = document.getElementById('game-timer');
        if (!timerEl) return;
        
        const minutes = Math.floor(time / 60);
        const seconds = time % 60;
        timerEl.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
    }

    /**
     * Start polling (via service)
     */
    startPolling() {
        this.pollingService.start();
    }

    /**
     * Stop polling (via service)
     */
    stopPolling() {
        this.pollingService.stop();
    }





    /**
     * Check session phase (triggers auto-advance if enabled)
     */
    async checkSessionPhase() {
        try {
            const data = await api.session.getStatus();
            await this._processSessionData(data);
        } catch (error) {
            console.error('🚨 CRITICAL ERROR in checkSessionPhase:', error);
            console.error(error.stack);
        }
    }

    async _processSessionData(data) {
        try {
            console.log(`[Session] Polled: Session=${data.session}, Phase=${data.phase}, Time=${data.timeRemaining}, Stopped=${data.gameStopped}`);
            
            // DETECT HARD RESET: If current session is 1 but we thought we were further ahead,
            // or if we have profile data but the server session is 1 and we haven't handled a reset yet.
            if (this.lastSessionNumber && data.session < this.lastSessionNumber && data.session === 1) {
                console.log('🔄 Session reset detected (New Game started). Reloading UI...');
                window.location.reload();
                return;
            }
            this.lastSessionNumber = data.session;

            this.gameStopped = data.gameStopped;
            this.lastServerTimeRemaining = data.timeRemaining;
            this.autoAdvance = data.autoAdvance ?? false;

            // Show/hide restart buttons based on autoAdvance (24/7 mode)
            // Non-admins can only restart when admin has enabled Auto-Cycle mode
            this.updateRestartButtonVisibility();

            // Check for game finished state (End Screen)
            const gameOverOverlay = document.getElementById('game-over-overlay');
            if (data.gameFinished) {
                if (gameOverOverlay && gameOverOverlay.classList && gameOverOverlay?.classList.contains('hidden')) {
                    gameOverOverlay?.classList.remove('hidden');
                    await this.renderGameOverStats();
                }
                this.gameFinished = true;
                return; // Stop processing other updates if game is finished
            } else {
                if (gameOverOverlay && gameOverOverlay.classList) gameOverOverlay?.classList.add('hidden');
                this.gameFinished = false;
            }

            // Check for game stopped state (Market Closed)
            const mainApp = document.getElementById('app');
            const closedOverlay = document.getElementById('market-closed-overlay');
            if (data.gameStopped) {
                this.wasGameStopped = true; // Track that game is stopped

                // Close any open modals when market closes
                this.closeAllModals();

                if (closedOverlay && closedOverlay.classList) closedOverlay?.classList.remove('hidden');
                if (mainApp && mainApp.classList) mainApp?.classList.add('hidden');
                return; // Stop processing other updates if game is stopped
            } else {
                // Game is running - check if it was previously stopped
                if (this.wasGameStopped === true) {
                    console.log('🎮 Game started! Performing hard refresh to clear cache...');
                    window.location.reload(true); // Force reload from server, not cache
                    return;
                }
                this.wasGameStopped = false;
                if (closedOverlay && closedOverlay.classList) closedOverlay?.classList.add('hidden');
                if (mainApp && mainApp.classList) mainApp?.classList.remove('hidden');
            }

            // Update UI elements
            const phaseEl = document.getElementById('current-phase');
            if (phaseEl) {
                phaseEl.textContent = data.phase;
                if (data.gameStopped) {
                    phaseEl.className = 'text-xs text-red-400 uppercase font-bold';
                } else {
                    phaseEl.className = 'text-xs text-green-400 uppercase font-bold';
                }
            }

            // Process Global Trades/Events for Toasts
            if (data.recentTrades && Array.isArray(data.recentTrades)) {
                // Process in reverse (oldest first) so they stack correctly
                [...data.recentTrades].reverse().forEach(event => {
                    // Support both transactionId (trades) and eventId (joins)
                    const uniqueId = event.transactionId || event.eventId;

                    if (uniqueId && !this.processedGlobalTrades.has(uniqueId)) {
                        this.processedGlobalTrades.add(uniqueId);

                        // Get event timestamp (trades use timestamp, joins use joinedAt)
                        const eventTime = event.timestamp || event.joinedAt;

                        // Only show toasts for events that happened after page load
                        // This prevents toast flood on refresh/late join
                        if (eventTime && eventTime >= this.pageLoadTime) {
                            if (event.type === 'join') {
                                // Team Joined Event
                                if (event.teamName !== (this.profile?.teamName)) { // Don't toast my own join (I know I joined)
                                    notifications.showToast(`👋 Team ${event.teamName} has joined the game!`, 'info', 5000);
                                }
                            } else {
                                // Trade Event
                                // Don't toast if I was part of it (I already got a personal toast/notif)
                                const involvesMe = event.sellerId === this.currentUser || event.buyerId === this.currentUser;

                                if (!involvesMe) {
                                    const isHot = event.heat?.isHot;
                                    // Only toast hot trades to reduce noise with many players
                                    if (isHot) {
                                        const message = `🔥 ${event.sellerName} sold ${this.formatNumber(event.quantity)} gal of ${event.chemical} to ${event.buyerName}`;
                                        notifications.showToast(message, 'hot', 4000);
                                    }
                                }
                            }
                        }
                    }
                });

                // Cleanup memory: keep only last 100 IDs

                // Cleanup memory: keep only last 100 IDs
                if (this.processedGlobalTrades.size > 100) {
                    const tradesArray = Array.from(this.processedGlobalTrades);
                    this.processedGlobalTrades = new Set(tradesArray.slice(-100));
                }
            }

            // Update Timer
            this.updateTimerDisplay(data.timeRemaining);

            // Check for production results to display
            if (data.productionJustRan && !this.productionResultsShown) {
                console.log('✨ Production just completed! Showing results modal...');
                this.productionResultsShown = true;
                // Session 1 = "Start Session 1" (initial production)
                // Session 2 = "End Session 1" (session 1 just ended)
                // Session 3 = "End Session 2" (session 2 just ended)
                const isInitial = data.session === 1;
                const productionSession = data.session === 1 ? 1 : data.session - 1;
                await this.showProductionResults(productionSession, isInitial);
                await stateManager.loadProfile(); // Refresh to show updated inventory/funds
            }
        } catch (error) {
            console.error('🚨 ERROR in _processSessionData:', error);
            throw error;
        }
    }

    /**
     * Update the timer display
     */
    updateTimerDisplay(timeRemaining) {
        const minutes = Math.floor(timeRemaining / 60);
        const seconds = timeRemaining % 60;
        const timerEl = document.getElementById('session-timer');
        if (timerEl) {
            timerEl.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
            
            // To prevent label shifting when time decreases (e.g. 1000:00 to 99:59),
            // we ensure the timer box never shrinks below the maximum width it has reached.
            const currentWidth = timerEl.offsetWidth;
            const currentMin = parseFloat(timerEl.style.minWidth) || 0;
            if (currentWidth > currentMin) {
                timerEl.style.minWidth = currentWidth + 'px';
            }
        }
    }

    /**
     * Open settings modal
     */
    openSettings() {
        modalManager.open('settings-modal');
    }

    /**
     * Close settings modal
     */
    closeSettings() {
        modalManager.close('settings-modal');
    }

    /**
     * Show production results modal (transition from "in progress" to "complete" state)
     */
    async showProductionResults(sessionNumber, isInitial = false) {
        try {
            // Fetch production results from API
            const data = await api.get(`api/production/results.php?session=${sessionNumber}`);

            // Populate modal with data
            const sessionNum = data.sessionNumber || sessionNumber;
            document.getElementById('prod-result-session').textContent = sessionNum;

            // Set title: "Initial Potential" for initial load, "Final Results" for session completions
            const titleElement = document.getElementById('prod-result-title');
            const revenueNote = document.getElementById('revenue-note');
            if (isInitial) {
                titleElement.innerHTML = `Round Baseline Potential`;
                if (revenueNote) revenueNote.textContent = 'Initial inventory potential';
            } else {
                titleElement.innerHTML = `Final Round Results`;
                if (revenueNote) revenueNote.textContent = 'Total value after optimization';
            }

            document.getElementById('prod-result-deicer').textContent = this.formatNumber(data.production.deicer);
            document.getElementById('prod-result-solvent').textContent = this.formatNumber(data.production.solvent);
            document.getElementById('prod-result-revenue').textContent = this.formatCurrency(data.revenue);

            // Chemicals consumed
            document.getElementById('prod-result-chem-C').textContent = this.formatNumber(data.chemicalsConsumed.C);
            document.getElementById('prod-result-chem-N').textContent = this.formatNumber(data.chemicalsConsumed.N);
            document.getElementById('prod-result-chem-D').textContent = this.formatNumber(data.chemicalsConsumed.D);
            document.getElementById('prod-result-chem-Q').textContent = this.formatNumber(data.chemicalsConsumed.Q);

            // Optimization Analysis (Sensitivity Report)
            const constraintsList = document.getElementById('prod-constraints-list');
            const shadowPricesList = document.getElementById('prod-shadow-prices-list');
            
            if (data.constraints && constraintsList) {
                constraintsList.innerHTML = '';
                ['C', 'N', 'D', 'Q'].forEach(chem => {
                    const c = data.constraints[chem];
                    const isBinding = c.status === 'Binding';
                    const div = document.createElement('div');
                    div.className = `flex justify-between items-center p-2 rounded ${isBinding ? 'bg-red-900/30 border border-red-800' : 'bg-green-900/30 border border-green-800'}`;
                    
                    div.innerHTML = `
                        <span class="font-bold ${isBinding ? 'text-red-400' : 'text-green-400'}">Chemical ${chem}</span>
                        <div class="text-right">
                            <span class="text-xs text-gray-400 block">${isBinding ? 'Bottleneck (0 Excess)' : `Excess: ${this.formatNumber(c.slack)} gal`}</span>
                            <span class="font-bold text-sm text-white">${c.status}</span>
                        </div>
                    `;
                    constraintsList.appendChild(div);
                });
            }

            if (data.shadowPrices && shadowPricesList) {
                shadowPricesList.innerHTML = '';
                ['C', 'N', 'D', 'Q'].forEach(chem => {
                    const sp = data.shadowPrices[chem];
                    const isValuable = sp > 0;
                    const div = document.createElement('div');
                    div.className = `p-3 rounded border ${isValuable ? 'bg-purple-900/30 border-purple-500/50' : 'bg-gray-700/50 border-gray-600'}`;
                    
                    // Interpret range if available
                    let rangeInfo = '';
                    if (data.ranges && data.ranges[chem]) {
                        const r = data.ranges[chem];
                        const inc = r.allowableIncrease > 9000 ? '∞' : this.formatNumber(r.allowableIncrease);
                        rangeInfo = `<div class="text-[10px] text-gray-500 mt-1">Range: -${this.formatNumber(r.allowableDecrease)} / +${inc}</div>`;
                    }

                    div.innerHTML = `
                        <div class="flex justify-between items-baseline mb-1">
                            <span class="text-sm font-bold text-gray-300">Chem ${chem}</span>
                            <span class="text-lg font-mono font-bold ${isValuable ? 'text-purple-400' : 'text-gray-500'}">${this.formatCurrency(sp)}</span>
                        </div>
                        <div class="text-xs ${isValuable ? 'text-purple-300' : 'text-gray-400'}">
                            ${isValuable ? 'High Value - BUY MORE!' : 'Low Value - SELL EXCESS'}
                        </div>
                        ${rangeInfo}
                    `;
                    shadowPricesList.appendChild(div);
                });
            }

            // Current status
            document.getElementById('prod-result-current-funds').textContent = this.formatCurrency(data.currentFunds);
            document.getElementById('prod-result-inv-C').textContent = this.formatNumber(data.currentInventory.C);
            document.getElementById('prod-result-inv-N').textContent = this.formatNumber(data.currentInventory.N);
            document.getElementById('prod-result-inv-D').textContent = this.formatNumber(data.currentInventory.D);
            document.getElementById('prod-result-inv-Q').textContent = this.formatNumber(data.currentInventory.Q);

            // Transition from "in progress" to "complete" state
            const modal = document.getElementById('production-results-modal');
            const prodInProgress = document.getElementById('production-in-progress');
            const prodComplete = document.getElementById('production-complete');

            modal?.classList.remove('hidden');
            prodInProgress?.classList.add('hidden');
            prodComplete?.classList.remove('hidden');

            console.log('✅ Production results modal displayed (complete state)');
        } catch (error) {
            console.error('Error showing production results:', error);
        }
    }

    /**
     * Close production results modal
     */
    async closeProductionResults() {
        const modal = document.getElementById('production-results-modal');
        const prodInProgress = document.getElementById('production-in-progress');
        const prodComplete = document.getElementById('production-complete');

        modal?.classList.add('hidden');
        prodInProgress?.classList.add('hidden');
        prodComplete?.classList.add('hidden');

        // Acknowledge production to server (clears productionJustRan flag)
        try {
            await api.post('api/session/status.php', { acknowledgeProduction: true });
        } catch (error) {
            console.error('Failed to acknowledge production:', error);
        }

        // Reset flags so they can show again next time
        this.productionResultsShown = false;
        this.productionModalShown = false;

        // Refresh profile to show updated funds/inventory
        stateManager.loadProfile();

        console.log('✅ Production results modal closed');
    }

    /**
     * Render current funds
     */
    renderFunds() {
        const fundsEl = document.getElementById('current-funds');
        if (fundsEl && this.profile) {
            const newFunds = this.formatCurrency(this.profile.currentFunds);
            if (fundsEl.textContent !== newFunds) {
                fundsEl?.classList.remove('animate-success-pop');
                void fundsEl.offsetWidth; // Trigger reflow
                fundsEl?.classList.add('animate-success-pop');
            }
            fundsEl.textContent = newFunds;
        }
    }

    /**
     * Set and persist theme
     */
    setTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('cndq-theme', theme);
    }

    /**
     * Load saved theme from localStorage
     */
    loadSavedTheme() {
        const savedTheme = localStorage.getItem('cndq-theme') || 'dark';
        this.setTheme(savedTheme);
        document.getElementById('theme-selector').value = savedTheme;
    }

    /**
     * Open leaderboard modal
     */
    openLeaderboard() {
        const modal = document.getElementById('leaderboard-modal');
        if (modal) {
            modal.currentTeamId = this.currentUser;
            modal.open();
            this.currentModal = { id: 'leaderboard-modal' }; // Maintain compatibility with closeCurrentModal
        }
    }

    /**
     * Close leaderboard modal
     */
    closeLeaderboard() {
        const modal = document.getElementById('leaderboard-modal');
        if (modal) {
            modal.close();
            this.currentModal = null;
        }
    }

    /**
     * Open production guide modal
     */
    openProductionGuide() {
        modalManager.open('production-guide-modal');
    }

    /**
     * Close production guide modal
     */
    closeProductionGuide() {
        modalManager.close('production-guide-modal');
    }



    /**
     * Close currently open modal (for ESC key)
     */
    closeCurrentModal() {
        if (this.currentModal) {
            const modalId = this.currentModal.id;

            // Call appropriate close method
            if (modalId === 'settings-modal') this.closeSettings();
            else if (modalId === 'leaderboard-modal') this.closeLeaderboard();
            else if (modalId === 'production-guide-modal') this.closeProductionGuide();
            else if (modalId === 'negotiation-modal') this.closeNegotiationModal();
            else if (modalId === 'offer-modal') this.closeOfferModal();
        }
    }

    /**
     * Close all modals (used when market closes)
     */
    closeAllModals() {
        // Close negotiation modal if open
        const negotiationModal = document.getElementById('negotiation-modal');
        if (negotiationModal && !negotiationModal.classList.contains('hidden')) {
            this.closeNegotiationModal();
        }

        // Close offer modal if open
        const offerModal = document.getElementById('offer-modal');
        if (offerModal && !offerModal.classList.contains('hidden')) {
            this.closeOfferModal();
        }

        // Close other modals
        const modals = ['settings-modal', 'leaderboard-modal', 'production-guide-modal', 'history-modal'];
        modals.forEach(modalId => {
            const modal = document.getElementById(modalId);
            if (modal && !modal.classList.contains('hidden')) {
                modal.classList.add('hidden');
            }
        });

        // Clear current modal state
        this.currentModal = null;
        this.currentNegotiation = null;
    }

    /**
     * Render stats for the end game screen
     */
    async renderGameOverStats() {
        console.log('🏁 Game Over: Rendering final results...');
        await Promise.all([
            this.renderFinalLeaderboard(),
            this.renderFinalHistory()
        ]);
    }

    /**
     * Render the final leaderboard in end screen
     */
    async renderFinalLeaderboard() {
        const container = document.getElementById('final-leaderboard-container');
        if (!container) return;

        try {
            const data = await api.leaderboard.getStandings();
            if (!data.success) return;

            container.innerHTML = data.standings.map((team, index) => `
                <div class="bg-gray-700/50 p-6 rounded-xl flex items-center justify-between border ${team.email === this.currentUser ? 'border-purple-500 bg-purple-900/20' : 'border-gray-600'}">
                    <div class="flex items-center gap-6">
                        <div class="w-12 h-12 rounded-full bg-gray-800 flex items-center justify-center font-black text-2xl ${index < 3 ? 'text-yellow-400' : 'text-gray-400'}">
                            ${index + 1}
                        </div>
                        <div>
                            <div class="font-black text-xl uppercase tracking-tight">${team.teamName} ${team.email === this.currentUser ? '<span class="text-xs bg-purple-600 px-2 py-0.5 rounded ml-2">YOU</span>' : ''}</div>
                            <div class="text-xs text-gray-400 font-mono">${team.totalTrades} trades executed</div>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-3xl font-black ${team.percentChange >= 0 ? 'text-green-400' : 'text-red-400'} font-mono">
                            ${team.percentChange >= 0 ? '+' : ''}${team.percentChange.toFixed(1)}%
                        </div>
                        <div class="text-sm font-bold text-gray-400 uppercase tracking-widest">Success Score</div>
                    </div>
                </div>
            `).join('');
        } catch (error) {
            console.error('Failed to load final leaderboard:', error);
            container.innerHTML = '<p class="text-red-400 text-center">Failed to load leaderboard results</p>';
        }
    }

    /**
     * Render personal activity history in end screen
     */
    async renderFinalHistory() {
        const container = document.getElementById('final-history-container');
        if (!container) return;

        try {
            const data = await api.notifications.list();
            if (!data.success || !data.notifications.length) {
                container.innerHTML = '<p class="text-gray-500 text-center py-12 italic">No activity recorded for this simulation.</p>';
                return;
            }

            container.innerHTML = data.notifications.map(notif => `
                <div class="bg-gray-700/30 p-4 rounded-lg border-l-4 border-blue-500">
                    <div class="text-sm text-gray-200">${notif.message}</div>
                    <div class="text-[10px] text-gray-500 mt-1 uppercase font-bold tracking-widest">${this.formatTimeAgo(notif.createdAt)}</div>
                </div>
            `).join('');
        } catch (error) {
            console.error('Failed to load final history:', error);
            container.innerHTML = '<p class="text-red-400 text-center">Failed to load activity history</p>';
        }
    }

    /**
     * Update visibility of restart button on Simulation Complete screen
     * Users can only restart when admin has enabled Auto-Cycle (24/7 mode)
     * Note: Restart button is ONLY on Simulation Complete screen, not Market Closed
     * (Admins can use the admin panel to restart regardless)
     */
    updateRestartButtonVisibility() {
        const restartBtn = document.getElementById('restart-game-btn');

        // Show button only if autoAdvance (24/7 mode) is enabled
        if (restartBtn) {
            restartBtn.style.display = this.autoAdvance ? '' : 'none';
        }
    }

    /**
     * Restart the game
     * @param {string} buttonId - ID of the button that triggered restart (for UI feedback)
     */
    async restartGame(buttonId = 'restart-game-btn') {
        const confirmed = await this.showConfirm(
            'This will reset the entire simulation for ALL players and start over with the current NPC count. Are you sure?',
            'Restart Simulation'
        );

        if (!confirmed) return;

        try {
            const btn = document.getElementById(buttonId);
            if (btn) {
                btn.disabled = true;
                btn.textContent = 'Restarting...';
            }

            const data = await api.post('api/session/restart.php');

            if (data.success) {
                notifications.showToast('Simulation restarted! Reloading...', 'success');
                setTimeout(() => window.location.reload(), 1500);
            } else {
                notifications.showToast(data.error || 'Failed to restart', 'error');
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = 'Restart Simulation';
                }
            }
        } catch (error) {
            console.error('Restart failed:', error);
            notifications.showToast(error.message || 'Network error during restart', 'error');
            const btn = document.getElementById(buttonId);
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Restart Simulation';
            }
        }
    }

    /**
     * Format number with commas
     */
    formatNumber(num) {
        if (num === null || num === undefined) return '0';
        const parsed = parseFloat(num);
        if (isNaN(parsed)) return '0';
        // Fix negative zero display issue
        const value = Object.is(parsed, -0) ? 0 : parsed;
        return value.toLocaleString('en-US', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 2
        });
    }

    /**
     * Format currency with correct negative sign placement (-$100 vs $-100)
     */
    formatCurrency(num) {
        if (num === null || num === undefined) return '$0.00';
        const parsed = parseFloat(num);
        if (isNaN(parsed)) return '$0.00';
        const value = Object.is(parsed, -0) ? 0 : parsed;
        const formatted = Math.abs(value).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        return (value < 0 ? '-$' : '$') + formatted;
    }

    /**
     * Format time ago
     */
    formatTimeAgo(timestamp) {
        const seconds = Math.floor(Date.now() / 1000 - timestamp);
        if (seconds < 60) return 'Just now';
        if (seconds < 3600) return Math.floor(seconds / 60) + ' minutes ago';
        if (seconds < 86400) return Math.floor(seconds / 3600) + ' hours ago';
        return Math.floor(seconds / 86400) + ' days ago';
    }

    /**
     * Download data as CSV file
     */
    downloadCSV(data, filename, headers) {
        // Escape CSV values (handle commas, quotes, newlines)
        const escapeCSV = (val) => {
            if (val === null || val === undefined) return '';
            const str = String(val);
            if (str.includes(',') || str.includes('"') || str.includes('\n')) {
                return '"' + str.replace(/"/g, '""') + '"';
            }
            return str;
        };

        // Build CSV content
        let csv = headers.map(escapeCSV).join(',') + '\n';
        data.forEach(row => {
            csv += row.map(escapeCSV).join(',') + '\n';
        });

        // Create and trigger download
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = filename;
        link.click();
        URL.revokeObjectURL(link.href);
    }

    /**
     * Export Leaderboard/Rankings to CSV
     */
    async exportLeaderboardCSV() {
        try {
            const response = await api.leaderboard.getStandings();
            if (!response.success || !response.standings || response.standings.length === 0) {
                notifications.showToast('No leaderboard data to export', 'error');
                return;
            }
            const headers = ['Rank', 'Team Name', 'Starting Funds', 'Current Funds', '% Change', 'Total Trades'];
            const data = response.standings.map((team, idx) => [
                idx + 1,
                team.teamName,
                (team.startingFunds ?? 0).toFixed(2),
                (team.currentFunds ?? 0).toFixed(2),
                ((team.percentChange ?? 0) * 100).toFixed(1) + '%',
                team.totalTrades ?? 0
            ]);
            this.downloadCSV(data, 'leaderboard.csv', headers);
        } catch (e) {
            notifications.showToast('Failed to export leaderboard', 'error');
        }
    }

    /**
     * Export Personal Transaction History to CSV
     */
    exportTransactionHistoryCSV() {
        const headers = ['Time', 'Type', 'Chemical', 'Quantity', 'Price/Gal', 'Total', 'Inv Before', 'Inv After', 'Counterparty'];
        const data = (this.transactions || [])
            .filter(t => !t.status || t.status === 'accepted')
            .sort((a, b) => (b.timestamp || 0) - (a.timestamp || 0))
            .map(t => {
                const date = t.timestamp ? new Date(t.timestamp * 1000) : null;
                const timeStr = date ? date.toLocaleString() : 'Unknown';
                return [
                    timeStr,
                    t.role === 'seller' ? 'SALE' : 'BUY',
                    'Chemical ' + t.chemical,
                    t.quantity,
                    t.pricePerGallon?.toFixed(2) || '0.00',
                    t.totalAmount?.toFixed(2) || '0.00',
                    t.inventoryBefore ?? '-',
                    t.inventoryAfter ?? '-',
                    t.counterpartyName || t.counterparty || 'Unknown'
                ];
            });
        this.downloadCSV(data, 'my-transaction-history.csv', headers);
    }

    /**
     * Export Global Market History to CSV
     */
    async exportGlobalHistoryCSV() {
        try {
            const response = await fetch('/CNDQ/api/trades/global.php?limit=100');
            const data = await response.json();

            if (!data.success || !data.transactions) {
                notifications.showToast('No transactions to export', 'error');
                return;
            }

            // Complete audit trail headers
            const headers = [
                'Time', 'Chemical', 'Quantity', 'Price/Gal', 'Total',
                'Seller', 'Seller Inv Before', 'Seller Inv After',
                'Buyer', 'Buyer Inv Before', 'Buyer Inv After',
                'Heat'
            ];

            const rows = data.transactions.map(t => {
                const date = t.timestamp ? new Date(t.timestamp * 1000) : null;
                const timeStr = date ? date.toLocaleString() : 'Unknown';
                const heatLabel = t.heat?.isHot ? 'Hot' : t.heat?.total > 0 ? 'Good' : 'Fair';

                return [
                    timeStr,
                    t.chemical,
                    t.quantity,
                    t.pricePerGallon?.toFixed(2) || '0.00',
                    t.totalAmount?.toFixed(2) || '0.00',
                    t.sellerName,
                    t.sellerInvBefore?.toFixed(1) ?? '',
                    t.sellerInvAfter?.toFixed(1) ?? '',
                    t.buyerName,
                    t.buyerInvBefore?.toFixed(1) ?? '',
                    t.buyerInvAfter?.toFixed(1) ?? '',
                    heatLabel
                ];
            });

            this.downloadCSV(rows, 'market-history-complete.csv', headers);
        } catch (error) {
            console.error('Failed to export global history:', error);
            notifications.showToast('Export failed', 'error');
        }
    }

    /**
     * Highlight a specific UI element during tutorial
     */
    highlightElement(selector) {
        this.clearHighlights();

        // Handle shadow DOM selectors
        let element = null;
        if (Array.isArray(selector)) {
            // Shadow DOM chain
            let root = document;
            for (const sel of selector) {
                element = (root.shadowRoot || root).querySelector(sel);
                if (!element) break;
                root = element;
            }
        } else {
            element = document.querySelector(selector);
        }

        if (element) {
            element.classList.add('tutorial-highlight');
            element.classList.add('tutorial-pulse');

            // Only scroll into view for elements that are likely below the fold
            // Skip scrolling for header elements to avoid disrupting modal positioning
            const rect = element.getBoundingClientRect();
            const isInHeader = rect.top < 100;
            if (!isInHeader && rect.bottom > window.innerHeight) {
                element.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    }

    /**
     * Remove all tutorial highlights
     */
    clearHighlights() {
        document.querySelectorAll('.tutorial-highlight').forEach(el => {
            el.classList.remove('tutorial-highlight');
            el.classList.remove('tutorial-pulse');
        });
        
        // Also check shadow DOM components
        ['C', 'N', 'D', 'Q'].forEach(chem => {
            const card = document.querySelector(`chemical-card[chemical="${chem}"]`);
            if (card && card.shadowRoot) {
                card.shadowRoot.querySelectorAll('.tutorial-highlight').forEach(el => {
                    el.classList.remove('tutorial-highlight');
                    el.classList.remove('tutorial-pulse');
                });
            }
        });
    }

    // ==================== TUTORIAL SYSTEM ====================

    /**
     * Check if tutorial should show on first load
     * Set to false to disable auto-play; users can still access via "?" button
     */
    checkFirstVisitTutorial() {
        const autoPlayTutorial = false;

        const seen = localStorage.getItem('cndq_tutorial_seen');
        if (autoPlayTutorial && !seen) {
            // Wait for data to be fully loaded before auto-showing
            const checkData = setInterval(() => {
                if (this.shadowPrices && this.constraints && this.constraints.length > 0) {
                    clearInterval(checkData);
                    setTimeout(() => this.showTutorial(), 1500);
                }
            }, 500);

            // Safety timeout
            setTimeout(() => clearInterval(checkData), 10000);
        }
    }

    /**
     * Show the trading tutorial with dynamic content
     */
    showTutorial() {
        // Ensure data is ready
        if (!this.shadowPrices || !this.constraints || this.constraints.length === 0) {
            notifications.showToast('Waiting for production data...', 'info');
            stateManager.recalculateShadowPrices().then(() => {
                this.showTutorial();
            });
            return;
        }

        this.buildTutorialSteps();
        this.tutorialStep = 0;
        this.renderTutorialStep();
        document.getElementById('tutorial-modal')?.classList.remove('hidden');
    }

    /**
     * Build tutorial steps based on current player data
     */
    buildTutorialSteps() {
        const chemicals = ['C', 'N', 'D', 'Q'];

        // Analyze each chemical
        const analysis = chemicals.map(chem => {
            const shadowPrice = this.shadowPrices[chem] || 0;
            const constraint = this.constraints?.find(c => c.name === chem) || {};
            const isBinding = constraint.slack < 0.01 || shadowPrice > 0;
            const slack = constraint.slack || 0;
            const inventory = this.inventory[chem] || 0;

            return { chem, shadowPrice, isBinding, slack, inventory };
        });

        // Find best to buy (highest shadow price) and best to sell (lowest/zero shadow price with inventory)
        const sortedByValue = [...analysis].sort((a, b) => b.shadowPrice - a.shadowPrice);
        const buyTargets = sortedByValue.filter(a => a.shadowPrice > 0);
        const sellTargets = sortedByValue.filter(a => a.shadowPrice === 0 && a.inventory > 0).reverse();

        // Get projected production
        const deicer = Math.round(this.optimalMix?.deicer || 0);
        const solvent = Math.round(this.optimalMix?.solvent || 0);

        this.tutorialSteps = [
            // Step 1: Welcome - What are you making?
            {
                title: 'Welcome to CNDQ Trading!',
                content: `
                    <div class="text-center mb-4">
                        <div class="text-5xl mb-3">🏭</div>
                        <p class="text-lg text-gray-300">You're running a chemical plant that makes <strong>two products</strong>:</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div class="bg-blue-900/40 border border-blue-500 rounded-lg p-4 text-center">
                            <div class="text-3xl mb-2">🧊</div>
                            <div class="text-blue-400 font-bold text-lg">DE-ICER</div>
                            <div class="text-gray-300 text-sm">$2 profit/gallon</div>
                            <div class="text-xs text-blue-300 mt-2">Uses: C, N, D</div>
                        </div>
                        <div class="bg-purple-900/40 border border-purple-500 rounded-lg p-4 text-center">
                            <div class="text-3xl mb-2">🧪</div>
                            <div class="text-purple-400 font-bold text-lg">SOLVENT</div>
                            <div class="text-gray-300 text-sm">$3 profit/gallon</div>
                            <div class="text-xs text-purple-300 mt-2">Uses: N, D, Q</div>
                        </div>
                    </div>

                    <div class="bg-gray-700 rounded-lg p-3 text-center">
                        <p class="text-gray-300 text-sm">Your current optimal production:</p>
                        <p class="text-white font-bold">${deicer} gal De-Icer + ${solvent} gal Solvent</p>
                    </div>
                `
            },

            // Step 2: The Chemical Inputs
            {
                title: 'Your Chemical Inputs',
                content: `
                    <div class="space-y-4">
                        <p class="text-gray-300">You have 4 chemicals (C, N, D, Q) that are <strong class="text-yellow-400">ingredients</strong> for making De-Icer and Solvent.</p>

                        <div class="bg-gray-700 rounded-lg p-4">
                            <div class="text-sm text-gray-400 mb-3">Production recipes:</div>
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span class="text-blue-400 font-bold">🧊 De-Icer</span>
                                    <div class="text-gray-300 mt-1">= 0.5C + 0.3N + 0.2D</div>
                                </div>
                                <div>
                                    <span class="text-purple-400 font-bold">🧪 Solvent</span>
                                    <div class="text-gray-300 mt-1">= 0.25N + 0.35D + 0.4Q</div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-green-900/30 border border-green-500 rounded-lg p-3">
                            <p class="text-green-400 text-sm"><strong>The key insight:</strong> Some chemicals limit your production more than others. Those are the ones you should <strong>BUY</strong>!</p>
                        </div>
                    </div>
                `,
                elementToHighlight: '#chemical-cards-grid' // Highlight the 4 chemical cards
            },

            // Step 3: What are Shadow Prices?
            {
                title: 'What are Shadow Prices?',
                content: `
                    <div class="space-y-4">
                        <p class="text-gray-300">A <strong class="text-purple-400">Shadow Price</strong> tells you how much your profit increases for each additional gallon of a chemical.</p>

                        <div class="grid grid-cols-2 gap-4 mt-4">
                            <div class="bg-red-900/30 border border-red-500 rounded-lg p-4">
                                <div class="text-red-400 font-bold mb-2">🔥 High Shadow Price</div>
                                <p class="text-sm text-gray-300">You're using ALL of this chemical. You need MORE!</p>
                                <p class="text-sm text-green-400 mt-2">→ <strong>BUY</strong> this chemical</p>
                            </div>
                            <div class="bg-blue-900/30 border border-blue-500 rounded-lg p-4">
                                <div class="text-blue-400 font-bold mb-2">❄️ Zero Shadow Price</div>
                                <p class="text-sm text-gray-300">You have EXCESS of this chemical. It's sitting unused!</p>
                                <p class="text-sm text-yellow-400 mt-2">→ <strong>SELL</strong> this chemical</p>
                            </div>
                        </div>
                        <p class="text-xs text-gray-400">Shadow prices are now shown prominently on each chemical card below.</p>
                    </div>
                `,
                elementToHighlight: 'chemical-card[chemical="C"]' // Highlight first chemical card
            },

            // Step 4: Your Chemical Analysis
            {
                title: 'Your Chemical Analysis',
                content: `
                    <div class="space-y-3">
                        <p class="text-gray-400 text-sm mb-4">Based on your current inventory and production needs:</p>
                        ${analysis.map(a => `
                            <div class="flex items-center justify-between bg-gray-700 rounded-lg p-3 ${a.isBinding ? 'border-l-4 border-red-500' : 'border-l-4 border-blue-500'}">
                                <div class="flex items-center gap-3">
                                    <span class="text-2xl font-bold ${a.isBinding ? 'text-red-400' : 'text-blue-400'}">${a.chem}</span>
                                    <div>
                                        <div class="text-white font-semibold">
                                            ${a.isBinding ? '🔥 BINDING' : '❄️ EXCESS'}
                                        </div>
                                        <div class="text-xs text-gray-400">
                                            ${a.isBinding ? 'Using every drop!' : `${this.formatNumber(a.slack)} gal unused`}
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-lg font-mono ${a.shadowPrice > 0 ? 'text-green-400' : 'text-gray-500'}">
                                        ${this.formatCurrency(a.shadowPrice)}
                                    </div>
                                    <div class="text-xs ${a.shadowPrice > 0 ? 'text-green-400' : 'text-yellow-400'}">
                                        ${a.shadowPrice > 0 ? '→ BUY' : '→ SELL'}
                                    </div>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                `
            },

            // Step 5: Your Trading Recommendation
            {
                title: 'Your First Trade Recommendation',
                content: `
                    <div class="space-y-4">
                        ${buyTargets.length > 0 ? `
                            <div class="bg-green-900/30 border border-green-500 rounded-lg p-4">
                                <div class="text-green-400 font-bold text-lg mb-2">📈 You Should BUY:</div>
                                <div class="text-2xl font-bold text-white mb-2">Chemical ${buyTargets[0].chem}</div>
                                <p class="text-gray-300 text-sm">
                                    Shadow price: <strong class="text-green-400">${this.formatCurrency(buyTargets[0].shadowPrice)}</strong> per gallon
                                </p>
                                <p class="text-gray-400 text-xs mt-2">
                                    Every gallon you buy increases your profit by ${this.formatCurrency(buyTargets[0].shadowPrice)}!
                                </p>
                            </div>
                        ` : `
                            <div class="bg-gray-700 rounded-lg p-4 text-gray-400">
                                No chemicals are binding - you have excess of everything!
                            </div>
                        `}

                        ${sellTargets.length > 0 ? `
                            <div class="bg-yellow-900/30 border border-yellow-500 rounded-lg p-4">
                                <div class="text-yellow-400 font-bold text-lg mb-2">📤 You Should SELL:</div>
                                <div class="text-2xl font-bold text-white mb-2">Chemical ${sellTargets[0].chem}</div>
                                <p class="text-gray-300 text-sm">
                                    You have <strong class="text-yellow-400">${this.formatNumber(sellTargets[0].inventory)}</strong> gallons
                                    with <strong>${this.formatNumber(sellTargets[0].slack)}</strong> sitting unused
                                </p>
                                <p class="text-gray-400 text-xs mt-2">
                                    Sell at ANY price > $0 to convert waste into profit!
                                </p>
                            </div>
                        ` : `
                            <div class="bg-gray-700 rounded-lg p-4 text-gray-400">
                                You don't have excess inventory to sell right now.
                            </div>
                        `}
                    </div>
                `,
                elementToHighlight: buyTargets.length > 0 ? `chemical-card[chemical="${buyTargets[0].chem}"]` : null
            },

            // Step 6: How to Sell
            {
                title: 'Responding to Others',
                content: `
                    <div class="space-y-4">
                        <p class="text-gray-300">You don't always have to post your own requests. You can also <strong class="text-green-400">respond to others</strong>!</p>
                        
                        <div class="bg-gray-700 rounded-lg p-4">
                            <p class="text-sm text-gray-400 mb-2">Look at the chemical cards. If you see other teams listed under <strong>"Buy Requests"</strong>, you can sell to them!</p>
                            <div class="mt-3 bg-green-600/20 border border-green-500 rounded p-2 text-xs text-center">
                                Click <strong>"Sell to"</strong> to start a negotiation.
                            </div>
                        </div>

                        <div class="bg-blue-900/30 border border-blue-500 rounded-lg p-3">
                            <p class="text-blue-400 text-sm"><strong>Strategy:</strong> If your Shadow Price is $0 for a chemical, sell it to anyone willing to pay more than $0!</p>
                        </div>
                    </div>
                `,
                elementToHighlight: '.listings-container'
            },

            // Step 7: The Haggling System
            {
                title: 'Mastering the Haggle 🤝',
                content: `
                    <div class="space-y-4">
                        <p class="text-gray-300">Negotiations aren't just one-click. You can <strong class="text-blue-400">haggle</strong> over price and quantity!</p>

                        <div class="bg-gray-700 rounded-lg p-4 space-y-3">
                            <div>
                                <div class="text-yellow-400 font-bold text-sm">⚖️ The Greed Bar</div>
                                <p class="text-xs text-gray-400">Higher prices make you more profit, but test the merchant's patience.</p>
                            </div>
                            <div>
                                <div class="text-red-400 font-bold text-sm">⏳ Merchant Patience</div>
                                <p class="text-xs text-gray-400">If you push too hard, the merchant will walk away from the deal!</p>
                            </div>
                        </div>

                        <p class="text-sm text-gray-400">Keep an eye on the <strong>Patience Meter</strong> during negotiations.</p>
                    </div>
                `,
                elementToHighlight: '#my-negotiations'
            },

            // Step 8: How to Win
            {
                title: 'How to Win the Game 🏆',
                content: `
                    <div class="space-y-4">
                        <p class="text-gray-300">Success in CNDQ is measured by how much you <strong class="text-green-400">improve</strong> your starting position.</p>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="bg-gray-800 border border-gray-700 rounded-lg p-3 text-center">
                                <div class="text-gray-500 text-xs uppercase">Your Goal</div>
                                <div class="text-xl font-bold text-white">Maximize ROI</div>
                            </div>
                            <div class="bg-gray-800 border border-gray-700 rounded-lg p-3 text-center">
                                <div class="text-gray-500 text-xs uppercase">Check Progress</div>
                                <div class="text-xl font-bold text-yellow-500">Scoreboard</div>
                            </div>
                        </div>

                        <div class="bg-purple-900/30 border border-purple-500 rounded-lg p-3">
                            <p class="text-purple-400 text-sm">The team with the highest <strong>% Improvement</strong> at the end of the simulation wins!</p>
                        </div>

                        <div class="text-center mt-4">
                            <p class="text-white font-semibold">Ready to dominate the market? 🚀</p>
                        </div>
                    </div>
                `,
                elementToHighlight: '#leaderboard-btn',
                isLastBasicStep: true
            },

            // Step 9: Optional Deep Dive - LP Generalization
            {
                title: 'The Bigger Picture (Optional)',
                isOptional: true,
                content: `
                    <div class="space-y-4">
                        <div class="bg-indigo-900/30 border border-indigo-500 rounded-lg p-4 mb-4">
                            <div class="text-center mb-2">
                                <span class="text-3xl">🎓</span>
                            </div>
                            <p class="text-indigo-300 text-sm text-center">This is an optional deep dive for those curious about the math!</p>
                        </div>

                        <div class="space-y-4">
                            <p class="text-gray-300">Here's a powerful insight: <strong class="text-yellow-400">De-Icer and Solvent are just labels.</strong></p>

                            <p class="text-gray-300">What really matters are the <strong class="text-purple-400">recipes</strong> - the ratios of chemicals needed:</p>

                            <div class="bg-gray-700 rounded-lg p-4 font-mono text-sm">
                                <div class="text-blue-400 mb-2">Product A = 0.5C + 0.3N + 0.2D</div>
                                <div class="text-purple-400">Product B = 0.25N + 0.35D + 0.4Q</div>
                            </div>

                            <p class="text-gray-300">This same LP model works for <strong class="text-green-400">ANY two-product scenario</strong> with shared inputs:</p>

                            <div class="grid grid-cols-2 gap-3 text-sm">
                                <div class="bg-gray-800 rounded-lg p-3 border border-gray-600">
                                    <div class="text-yellow-400 font-bold">Bakery</div>
                                    <div class="text-gray-400 text-xs">Bread & Pastries sharing flour, sugar, eggs</div>
                                </div>
                                <div class="bg-gray-800 rounded-lg p-3 border border-gray-600">
                                    <div class="text-yellow-400 font-bold">Refinery</div>
                                    <div class="text-gray-400 text-xs">Gasoline & Diesel sharing crude oil fractions</div>
                                </div>
                                <div class="bg-gray-800 rounded-lg p-3 border border-gray-600">
                                    <div class="text-yellow-400 font-bold">Furniture</div>
                                    <div class="text-gray-400 text-xs">Tables & Chairs sharing wood, labor, hardware</div>
                                </div>
                                <div class="bg-gray-800 rounded-lg p-3 border border-gray-600">
                                    <div class="text-yellow-400 font-bold">Software</div>
                                    <div class="text-gray-400 text-xs">Products sharing dev time, servers, support</div>
                                </div>
                            </div>

                            <div class="bg-green-900/30 border border-green-500 rounded-lg p-4 mt-4">
                                <p class="text-green-400 text-sm"><strong>The Takeaway:</strong> Shadow prices and binding constraints are universal concepts. Master them here, apply them everywhere!</p>
                            </div>
                        </div>
                    </div>
                `
            }
        ];

        document.getElementById('tutorial-step-total').textContent = this.tutorialSteps.length;
    }

    /**
     * Position the tutorial modal relative to a target element
     *
     * NOTE: Popover positioning is disabled due to pointer-events issues.
     * When the overlay has pointer-events: none, clicks pass through to
     * elements BEHIND the overlay (not to child elements within it).
     * The modal stays centered and the highlight draws attention to elements.
     */
    positionTutorial(_targetSelector) {
        const modal = document.getElementById('tutorial-modal');
        const container = modal?.querySelector('div');

        if (!modal || !container) return;

        // Always keep modal centered - popover positioning causes click issues
        // (_targetSelector is kept for API compatibility but not used)
        modal.className = 'fixed inset-0 bg-black bg-opacity-80 flex items-center justify-center z-[120] p-4';
        container.classList.remove('tutorial-popover');
        container.style.top = '';
        container.style.left = '';
        container.style.transform = '';
    }

    /**
     * Render current tutorial step
     */
    renderTutorialStep() {
        const step = this.tutorialSteps[this.tutorialStep];
        if (!step) return;

        // Reset scroll position of content
        const contentContainer = document.getElementById('tutorial-content');
        if (contentContainer) contentContainer.scrollTop = 0;

        // For display purposes, don't count optional steps in total
        const basicStepsCount = this.tutorialSteps.filter(s => !s.isOptional).length;
        const displayStep = step.isOptional ? 'Bonus' : (this.tutorialStep + 1);
        const displayTotal = step.isOptional ? 'Deep Dive' : basicStepsCount;

        document.getElementById('tutorial-step-num').textContent = displayStep;
        document.getElementById('tutorial-step-total').textContent = displayTotal;

        // Add optional badge if applicable
        const optionalBadge = step.isOptional ? '<span class="text-xs bg-indigo-600 text-indigo-100 px-2 py-1 rounded-full ml-2">Optional</span>' : '';

        document.getElementById('tutorial-content').innerHTML = `
            <h3 class="text-xl font-bold text-white mb-4">${step.title}${optionalBadge}</h3>
            ${step.content}
        `;

        // Announce to screen readers
        const announcement = `Step ${displayStep} of ${displayTotal}: ${step.title}. ${step.content.replace(/<[^>]*>/g, '')}`;
        notifications.announce(announcement);

        // Update button states
        const tutorialPrevBtn = document.getElementById('tutorial-prev');
        const tutorialNextBtn = document.getElementById('tutorial-next');
        const tutorialDeepDiveBtn = document.getElementById('tutorial-deep-dive');

        if (tutorialPrevBtn) tutorialPrevBtn.disabled = this.tutorialStep === 0;

        // Handle Highlights and Positioning
        this.clearHighlights();
        if (step.elementToHighlight) {
            this.highlightElement(step.elementToHighlight);
            this.positionTutorial(step.elementToHighlight);
        } else {
            this.positionTutorial(null); // Reset to center
        }

        // Show Deep Dive option on last basic step
        if (step.isLastBasicStep) {
            if (tutorialNextBtn) tutorialNextBtn.textContent = 'Got It!';
            if (tutorialDeepDiveBtn) {
                tutorialDeepDiveBtn.classList.remove('hidden');
                tutorialDeepDiveBtn.textContent = 'Deep Dive →';
            }
        } else if (step.isOptional) {
            if (tutorialNextBtn) tutorialNextBtn.textContent = 'Done!';
            if (tutorialDeepDiveBtn) tutorialDeepDiveBtn.classList.add('hidden');
        } else {
            if (tutorialNextBtn) tutorialNextBtn.textContent = 'Next';
            if (tutorialDeepDiveBtn) tutorialDeepDiveBtn.classList.add('hidden');
        }
    }

    /**
     * Go to previous tutorial step
     */
    tutorialPrev() {
        console.log(`📖 Tutorial: Prev clicked. Current step index: ${this.tutorialStep}`);
        if (this.tutorialStep > 0) {
            this.tutorialStep--;
            this.renderTutorialStep();
        }
    }

    /**
     * Go to next tutorial step or close
     */
    tutorialNext() {
        if (!this.tutorialSteps || this.tutorialSteps.length === 0) {
            console.warn('📖 Tutorial: tutorialSteps is empty, rebuilding...');
            this.buildTutorialSteps();
        }
        
        const currentStep = this.tutorialSteps[this.tutorialStep];
        console.log(`📖 Tutorial: Next clicked. Current step index: ${this.tutorialStep}, Total steps: ${this.tutorialSteps.length}`);

        // If on last basic step, close (unless they clicked Deep Dive)
        if (currentStep?.isLastBasicStep) {
            console.log('📖 Tutorial: Last basic step reached, closing.');
            this.closeTutorial();
            return;
        }

        // If on optional step or last step, close
        if (currentStep?.isOptional || this.tutorialStep >= this.tutorialSteps.length - 1) {
            console.log('📖 Tutorial: Optional or final step reached, closing.');
            this.closeTutorial();
            return;
        }

        // Otherwise advance to next step
        this.tutorialStep++;
        console.log(`📖 Tutorial: Advancing to step index: ${this.tutorialStep}`);
        this.renderTutorialStep();
    }

    /**
     * Jump to the optional deep dive step
     */
    tutorialDeepDive() {
        // Find the first optional step
        const optionalIndex = this.tutorialSteps.findIndex(s => s.isOptional);
        if (optionalIndex !== -1) {
            this.tutorialStep = optionalIndex;
            this.renderTutorialStep();
        }
    }

    /**
     * Close the tutorial
     * Always marks tutorial as seen - user can re-access via Quick Actions
     */
    closeTutorial() {
        const modal = document.getElementById('tutorial-modal');
        this.clearHighlights();

        // Reset position BEFORE hiding (positionTutorial overwrites className)
        this.positionTutorial(null);

        // Now hide the modal
        if (modal) {
            modal.classList.add('hidden');
            console.log('📖 Tutorial: Modal hidden');
        }

        // Always mark tutorial as seen when closed (either via X or completion)
        // User can still re-access tutorial via Quick Actions menu
        localStorage.setItem('cndq_tutorial_seen', 'true');
    }
}

// Global initialization
const startMarketplace = () => {
    if (window.appInitialized) return;
    window.appInitialized = true;
    
    console.log('🚀 Starting Marketplace Application...');
    const app = new MarketplaceApp();
    window.app = app;
    app.init();

    // Health check - detect team wipe
    setInterval(async () => {
        try {
            const data = await api.team.getProfile();
            // Detect if team was reset (new creation date)
            if (window.app && window.app.profile && data.profile.createdAt > window.app.profile.createdAt) {
                console.log('🔄 Team reset detected (New Game). Reloading...');
                window.location.reload();
            }
        } catch (error) {
            console.log('⚠️ Session lost or team deleted - reloading...');
            window.location.reload();
        }
    }, 5000);

    // NPC Heartbeat
    setInterval(async () => {
        try {
            await api.admin.getSession();
        } catch (error) {
            // Silently ignore
        }
    }, 5000);
};

// Handle all ready states
if (document.readyState === 'complete' || document.readyState === 'interactive') {
    startMarketplace();
} else {
    document.addEventListener('DOMContentLoaded', startMarketplace);
}
