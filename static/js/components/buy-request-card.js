/**
 * Buy Request Card Web Component
 *
 * Displays a pending buy request card (Phase 0 of negotiation lifecycle).
 * Shown in "My Negotiations" section before anyone responds to the buy request.
 *
 * Usage:
 *   const card = document.createElement('buy-request-card');
 *   card.listing = { id, chemical, quantity, maxPrice, teamId, teamName };
 *   card.currentUserId = 'user@example.com';
 *
 * Properties:
 *   - listing: Object - buy request data
 *   - currentUserId: String - for ownership verification
 *
 * Events:
 *   - cancel-buy-request: Dispatched when cancel button is clicked
 *     detail: { listingId, chemical }
 */

import { tailwindStyles } from './shared-styles.js';

class BuyRequestCard extends HTMLElement {
    constructor() {
        super();
        this._listing = null;
    }

    get listing() { return this._listing; }
    set listing(val) {
        this._listing = val;
        if (val && val.id) {
            this.setAttribute('listing-id', val.id);
        }
        if (this.firstChild) this.render();
    }

    get currentUserId() { return this.getAttribute('current-user-id'); }
    set currentUserId(val) {
        this.setAttribute('current-user-id', val);
    }

    connectedCallback() {
        if (!this.querySelector('.card-wrapper')) {
            this.innerHTML = `
                <div class="card-wrapper bg-gray-800 rounded p-4 border-2 border-cyan-600 shadow-lg transition relative hover:border-gray-500 cursor-pointer" role="button" tabindex="0">
                </div>
            `;
            // Add global event listeners to the wrapper once
            const wrapper = this.querySelector('.card-wrapper');
            wrapper.addEventListener('click', (e) => {
                // Don't trigger view detail if clicking a button
                if (e.target.closest('button')) return;
                this.handleViewDetail();
            });
            wrapper.addEventListener('keydown', (e) => {
                if (e.target.closest('button')) return;
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    this.handleViewDetail();
                }
            });
        }
        this.render();
    }

    attributeChangedCallback(name, oldValue, newValue) {
        if (oldValue !== newValue && this.firstChild) {
            this.render();
        }
    }

    static get observedAttributes() {
        return ['current-user-id'];
    }

    formatCurrency(num) {
        if (num === null || num === undefined || isNaN(num)) return '$0.00';
        const parsed = parseFloat(num);
        const value = Object.is(parsed, -0) ? 0 : parsed;
        const formatted = Math.abs(value).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        return (value < 0 ? '-$' : '$') + formatted;
    }

    render() {
        const wrapper = this.querySelector('.card-wrapper');
        const listing = this._listing;

        if (!wrapper || !listing) return;

        const chemical = listing.chemical || '?';
        const quantity = listing.quantity || 0;
        const maxPrice = listing.maxPrice || 0;

        // Chemical badge styling
        const chemicalColors = {
            C: { bg: '#1d4ed8', border: 'var(--color-chemical-c)', symbol: '●' },
            N: { bg: '#7c3aed', border: 'var(--color-chemical-n)', symbol: '▲' },
            D: { bg: '#b45309', border: 'var(--color-chemical-d)', symbol: '■' },
            Q: { bg: '#b91c1c', border: 'var(--color-chemical-q)', symbol: '◆' }
        };
        const colors = chemicalColors[chemical] || chemicalColors.C;
        const chemicalStyle = `background-color: ${colors.bg}; color: white; border: 1px solid ${colors.border}; font-weight: 700;`;
        const chemicalBadge = `<span class="font-bold px-2 py-0.5 rounded text-sm shadow-sm" style="${chemicalStyle}"><span aria-hidden="true">${colors.symbol}</span> Chemical ${chemical}</span>`;

        // Status badge - waiting for sellers (using CSS variable)
        const statusBadge = '<span class="px-2 py-1 rounded text-xs font-semibold" style="background-color: var(--color-waiting); color: white;">Waiting...</span>';

        // Update wrapper border color - using CSS variable
        wrapper.className = 'card-wrapper bg-gray-800 rounded p-4 border-2 shadow-lg transition relative hover:border-gray-500 cursor-pointer';
        wrapper.style.borderColor = 'var(--color-waiting)';
        wrapper.setAttribute('role', 'article');
        wrapper.setAttribute('aria-label', `Buy Request: ${quantity} gallons of Chemical ${chemical} at max ${this.formatCurrency(maxPrice)} per gallon`);

        wrapper.innerHTML = `
            <button class="cancel-btn absolute -top-2 -right-2 bg-gray-700 text-gray-400 hover:bg-red-600 hover:text-white rounded-full p-1 shadow-md transition z-20 border border-gray-600 hover:border-red-500" 
                    aria-label="Cancel buy request for Chemical ${chemical}" 
                    title="Cancel buy request for Chemical ${chemical}">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
            </button>

            <div class="flex items-center justify-between mb-2 pr-6">
                <div class="flex items-center gap-2">
                    ${chemicalBadge}
                    <span class="text-gray-400 text-sm">•</span>
                    <span class="badge badge-info text-[10px] uppercase font-bold px-1.5 py-0.5">Buy Request</span>
                </div>
            </div>

            <div class="flex items-center justify-between">
                <div class="text-sm text-gray-300">
                    <span class="font-mono font-bold text-white">${quantity} gal</span> @ max <span class="font-mono font-bold text-white">${this.formatCurrency(maxPrice)}</span>
                </div>
                ${statusBadge}
            </div>

            <div class="mt-2 text-xs text-gray-400 italic">
                Waiting for sellers to respond...
            </div>
        `;

        // Bind cancel button event
        const cancelBtn = wrapper.querySelector('.cancel-btn');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.handleCancel();
            });
        }
    }

    handleCancel() {
        this.dispatchEvent(new CustomEvent('cancel-buy-request', {
            bubbles: true,
            composed: true,
            detail: {
                listingId: this._listing.id,
                chemical: this._listing.chemical
            }
        }));
    }

    handleViewDetail() {
        this.dispatchEvent(new CustomEvent('view-detail', {
            bubbles: true,
            composed: true,
            detail: {
                listingId: this._listing.id,
                chemical: this._listing.chemical,
                isBuyRequest: true
            }
        }));
    }
}

customElements.define('buy-request-card', BuyRequestCard);
