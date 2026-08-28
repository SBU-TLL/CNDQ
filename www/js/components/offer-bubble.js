/**
 * Offer Bubble Web Component
 *
 * Displays a single offer in the negotiation history.
 * Styled differently for offers from current user vs other party.
 *
 * Usage:
 *   const bubble = document.createElement('offer-bubble');
 *   bubble.offer = { fromTeamName, quantity, price, createdAt };
 *   bubble.isFromMe = true;
 *
 * Properties:
 *   - offer: Object - offer data
 *   - isFromMe: Boolean - alignment and color
 */

import { tailwindStyles } from './shared-styles.js';

class OfferBubble extends HTMLElement {
    constructor() {
        super();
        // Use light DOM to work with global Tailwind styles
        this._offer = null;
    }

    get offer() { return this._offer; }
    set offer(val) {
        this._offer = val;
        if (this.firstChild) this.render();
    }

    get isFromMe() { return this.hasAttribute('is-from-me'); }
    set isFromMe(val) {
        if (val) this.setAttribute('is-from-me', '');
        else this.removeAttribute('is-from-me');
        if (this.firstChild) this.render();
    }

    connectedCallback() {
        this.render();
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
        if (!this._offer) {
            this.innerHTML = '';
            return;
        }

        const alignment = this.isFromMe ? 'ml-auto' : 'mr-auto';
        // Use theme-aware ad-mine styling for 'Me', and standard tertiary for 'Them'
        const bgStyle = this.isFromMe ? 
            'background-color: var(--color-bg-ad-mine, #422006); border: 1px solid var(--color-border-ad-mine, #d97706);' : 
            'background-color: var(--color-bg-tertiary, #374151); border: 1px solid var(--color-border, #4b5563);';
        
        const textColorClass = this.isFromMe ? 'text-primary' : 'text-secondary';
        
        const total = this._offer.quantity * this._offer.price;
        const date = new Date(this._offer.createdAt * 1000).toLocaleString();
        
        const isHot = this._offer.heat && this._offer.heat.isHot;
        const hotBadge = isHot ? `
            <div class="mt-2 py-1 px-2 bg-orange-500 text-white text-[10px] font-bold uppercase rounded flex items-center gap-1 animate-pulse">
                <span>🔥 Hot Trade</span>
                <span class="text-[9px] font-normal opacity-90">(Beneficial for both)</span>
            </div>
        ` : '';

        this.innerHTML = `
            <div class="max-w-xs ${alignment} rounded-lg p-3 shadow-sm relative overflow-hidden" style="${bgStyle}">
                ${isHot ? '<div class="absolute top-0 right-0 w-16 h-16 bg-orange-400 opacity-10 rotate-45 translate-x-8 -translate-y-8"></div>' : ''}
                <div class="font-semibold text-sm flex justify-between items-center ${textColorClass}">
                    <span>${this._offer.fromTeamName}</span>
                </div>
                <div class="text-xs text-secondary">
                    ${this._offer.quantity} gal @ ${this.formatCurrency(this._offer.price)}/gal
                </div>
                <div class="text-xs font-bold text-success">
                    Total: ${this.formatCurrency(total)}
                </div>
                ${hotBadge}
                <div class="text-[10px] text-tertiary mt-1 italic">
                    ${date}
                </div>
            </div>
        `;
    }
}

customElements.define('offer-bubble', OfferBubble);
