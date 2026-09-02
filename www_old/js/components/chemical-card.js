import { LitElement, html, css } from 'lit';
import { unsafeHTML } from 'lit/directives/unsafe-html.js';

// Since we can't use Tailwind inside the Shadow DOM directly,
// we define our styles here. These are inspired by your existing styles.
const componentStyles = css`
    :host {
        display: block;
    }
    .card {
        background-color: var(--color-bg-secondary, #1f2937);
        border-radius: 0.5rem;
        border: 2px solid var(--border-color, #4b5563);
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        color: var(--color-text-primary, #f9fafb);
    }
    .header {
        padding: 1rem;
        text-align: center;
        background-color: var(--header-bg-color, #374151);
    }
    .header h2 {
        font-weight: 700;
        font-size: 1.25rem;
        color: white;
    }
    .content {
        padding: 1rem;
    }
    .info-box {
        background-color: var(--color-bg-tertiary, #374151);
        border-radius: 0.5rem;
        padding: 0.75rem;
        margin-bottom: 1rem;
    }
    .info-label {
        font-size: 0.875rem;
        color: var(--color-text-secondary, #e5e7eb);
    }
    .info-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--color-text-primary, white);
    }
    .shadow-price {
        font-size: 0.75rem;
        color: var(--color-text-secondary, #e5e7eb);
        margin-top: 0.25rem;
    }
    .shadow-price span {
        color: var(--color-success, #34d399);  /* green-400 for better contrast on gray-700 */
        font-weight: 700;
    }
    .btn {
        width: 100%;
        background-color: #2563eb;
        color: white;
        padding: 0.75rem;
        border-radius: 0.5rem;
        font-weight: 600;
        border: none;
        cursor: pointer;
        transition: background-color 0.2s;
    }
    .btn:hover:not(:disabled) {
        background-color: #1d4ed8;
    }
    .btn-disabled,
    .btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        background-color: #4b5563;
    }
    .listings-header {
        font-size: 0.75rem;
        font-weight: 700;
        color: var(--color-text-secondary, #e5e7eb);
        margin-bottom: 0.5rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .listings-container {
        max-height: 24rem;
        overflow-y: auto;
        padding-right: 0.25rem; /* for scrollbar */
    }
    .empty-listings {
        font-size: 0.75rem;
        color: var(--color-text-tertiary, #d1d5db);
        text-align: center;
        padding: 1rem 0;
    }
`;

class ChemicalCard extends LitElement {
    static styles = componentStyles;

    static properties = {
        chemical: { type: String },
        inventory: { type: Number },
        shadowPrice: { type: Number },
        slack: { type: Number },
        ranges: { type: Object },
        buyListings: { type: Array },
        currentUserId: { type: String },
        hasActiveNegotiation: { type: Boolean }
    };

    constructor() {
        super();
        this.chemical = '';
        this.inventory = 0;
        this.shadowPrice = 0;
        this.slack = 0;
        this.ranges = { allowableIncrease: 0, allowableDecrease: 0 };
        this.buyListings = [];
        this.currentUserId = '';
        this.hasActiveNegotiation = false;
    }

    getChemicalColor(chemical) {
        const colors = {
            C: { border: 'var(--color-chemical-c, #6eb5ff)', header: '#1d4ed8', symbol: '●', bg: 'bg-blue-600' },
            N: { border: 'var(--color-chemical-n, #d4a8fc)', header: '#7c3aed', symbol: '▲', bg: 'bg-purple-600' },
            D: { border: 'var(--color-chemical-d, #fcd34d)', header: '#b45309', symbol: '■', bg: 'bg-yellow-600' },
            Q: { border: 'var(--color-chemical-q, #ffa0a0)', header: '#b91c1c', symbol: '◆', bg: 'bg-red-600' }
        };
        return colors[chemical] || colors.C;
    }

    handlePostBuyRequest() {
        this.dispatchEvent(new CustomEvent('post-interest', {
            detail: { chemical: this.chemical, type: 'buy' },
            bubbles: true,
            composed: true
        }));
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
        const { border, header, symbol } = this.getChemicalColor(this.chemical);
        const hasActiveBuyListing = this.buyListings.some(listing => listing.teamId === this.currentUserId);
        
        const increase = this.ranges?.allowableIncrease ?? 0;
        const decrease = this.ranges?.allowableDecrease ?? 0;
        const isRangeZero = (increase + decrease) < 1;

        return html`
            <div class="card" style="--border-color: ${border};">
                <div class="header" style="--header-bg-color: ${header};">
                    <h2><span aria-hidden="true">${symbol}</span> Chemical ${this.chemical}</h2>
                </div>
                <div class="content">
                    <div class="info-box">
                        <div class="flex justify-between items-center mb-2">
                            <div>
                                <div class="info-label">Your Inventory</div>
                                <div id="inventory" class="info-value">${Math.round(this.inventory).toLocaleString()}</div>
                            </div>
                            <div class="text-right">
                                <div class="info-label">Total Slack</div>
                                <div id="slack" class="info-value" style="font-size: 1.1rem; color: ${this.slack > 0 ? '#34d399' : '#9ca3af'}">${Math.round(this.slack).toLocaleString()}</div>
                            </div>
                        </div>
                        
                        <div class="mt-4 p-2 rounded text-center" style="background-color: ${header}; color: white; border: 1px solid rgba(255,255,255,0.2); box-shadow: inset 0 1px 0 rgba(255,255,255,0.1);">
                            <div class="text-[10px] uppercase font-bold tracking-wider opacity-80 mb-1">Shadow Price (Marginal Value)</div>
                            <div id="shadow-price" class="text-2xl font-black">${this.formatCurrency(this.shadowPrice)}</div>
                        </div>

                        <div style="font-size: 0.65rem; color: var(--color-text-secondary); margin-top: 0.8rem; line-height: 1.2;">
                            <div class="flex justify-between">
                                <span>Stability Range:</span>
                                <span class="text-success font-bold">
                                    ${isRangeZero 
                                        ? 'N/A (Low Inv)' 
                                        : `-${decrease.toFixed(0)} / +${increase >= 9000 ? '∞' : increase.toFixed(0)} gal`
                                    }
                                </span>
                            </div>
                            <div style="opacity: 0.7; font-style: italic; margin-top: 2px;">(Shadow price is accurate within this trade volume)</div>
                        </div>
                    </div>

                    <div style="margin-bottom: 1rem;">
                        <button
                            id="post-buy-btn"
                            class="btn ${hasActiveBuyListing ? 'btn-disabled' : ''}"
                            ?disabled=${hasActiveBuyListing}
                            @click=${this.handlePostBuyRequest}
                            aria-label="Post buy request for Chemical ${this.chemical}"
                            title="Post buy request for Chemical ${this.chemical}">
                            📋 Post Buy Request
                        </button>
                        <p class="empty-listings" style="margin: 0.5rem 0 0; text-align: center;">
                            ${hasActiveBuyListing ? 'Cancel in My Negotiations to post new.' : 'Post what you need, teams will offer to sell.'}
                        </p>
                    </div>

                    <div>
                        <h4 class="listings-header">Buy Requests</h4>
                        <div class="listings-container">
                            ${this.buyListings.length === 0
                                ? html`<p class="empty-listings">${this.inventory > 0 ? 'No buy requests yet' : 'Get inventory to see buy requests'}</p>`
                                : this.buyListings.map(listing => {
                                    console.log(`🔧 Creating listing-item for ${this.chemical}:`, listing.teamName, listing.id, listing.teamId === this.currentUserId ? '(MINE)' : '');
                                    return html`
                                        <listing-item
                                            .listingId=${listing.id}
                                            .teamName=${listing.teamName}
                                            .teamId=${listing.teamId}
                                            type="buy"
                                            .chemical=${this.chemical}
                                            .quantity=${listing.quantity}
                                            .maxPrice=${listing.maxPrice}
                                            ?isMyListing=${listing.teamId === this.currentUserId}
                                        ></listing-item>
                                    `;
                                })
                            }
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
}

customElements.define('chemical-card', ChemicalCard);
