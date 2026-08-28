/**
 * FinancialRenderer
 * Handles rendering of financial summary panel and transaction history
 * Extracted from marketplace.js for modularity
 */

export class FinancialRenderer {
    /**
     * Format currency value
     * @param {number} value
     * @returns {string}
     */
    formatCurrency(value) {
        if (value === null || value === undefined) return '$0.00';
        const parsed = parseFloat(value);
        if (isNaN(parsed)) return '$0.00';
        const formatted = Math.abs(parsed).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        return (parsed < 0 ? '-$' : '$') + formatted;
    }

    /**
     * Format number with commas
     * @param {number} num
     * @returns {string}
     */
    formatNumber(num) {
        if (num === null || num === undefined) return '0';
        const parsed = parseFloat(num);
        if (isNaN(parsed)) return '0';
        return parsed.toLocaleString('en-US');
    }

    /**
     * Render Financial Summary Panel
     * @param {Object} params
     * @param {Array} params.transactions - Array of transaction objects
     * @param {Object} params.profile - Team profile with currentFunds, startingFunds, etc.
     * @param {Object} params.shadowPrices - Shadow prices with maxProfit
     * @param {Object} params.optimalMix - Optimal production mix (deicer, solvent)
     */
    renderFinancialSummary({ transactions, profile, shadowPrices, optimalMix, staleness }) {
        if (!transactions || !profile) return;

        // If shadow prices haven't loaded yet, wait for them
        if (!shadowPrices || !shadowPrices.hasOwnProperty('maxProfit')) {
            console.log('Waiting for shadow prices to load before rendering financial summary...');
            return;
        }

        let salesRevenue = 0;
        let purchaseCosts = 0;

        transactions.forEach(t => {
            const amount = parseFloat(t.totalAmount) || 0;
            if (t.role === 'seller') {
                salesRevenue += amount;
            } else if (t.role === 'buyer') {
                purchaseCosts += amount;
            }
        });

        const tradingNet = salesRevenue - purchaseCosts;

        // Financial Summary:
        // - currentFunds: Current money (realized profit from trading)
        const realizedProfit = (profile.currentFunds || 0) - (profile.startingFunds || 0);

        // Inventory Value (Projected Production):
        const hasProduction = (profile.productions?.length ?? 0) > 0;
        const projectedRevenue = shadowPrices?.maxProfit || 0;
        const inventoryValue = hasProduction ? (realizedProfit - tradingNet) : projectedRevenue;

        // Total Projected Value:
        const totalValue = realizedProfit + (hasProduction ? 0 : projectedRevenue);

        // Success Metric: % Improvement over initial production potential
        const initialPotential = profile.initialProductionPotential || 0;
        let percentImprovement = 0;
        if (initialPotential > 0) {
            percentImprovement = ((totalValue - initialPotential) / initialPotential) * 100;
        }

        // Calculate delta from last transaction
        let inventoryDelta = 0;
        let totalDelta = 0;
        let salesDelta = 0;
        let purchaseDelta = 0;

        if (transactions.length > 0) {
            // Sort by timestamp or use the last one in the array (assuming it's chronological)
            const sortedTxns = [...transactions].sort((a, b) => (a.timestamp || 0) - (b.timestamp || 0));
            const lastTransaction = sortedTxns[sortedTxns.length - 1];
            
            const lastAmount = Math.abs(lastTransaction.totalAmount || (lastTransaction.quantity * lastTransaction.pricePerGallon));
            const heat = lastTransaction.heat || { sellerGain: 0, buyerGain: 0 };
            const quantity = lastTransaction.quantity || 0;
            const pricePerGallon = lastTransaction.pricePerGallon || 0;

            if (lastTransaction.role === 'seller') {
                // I sold inventory. The value lost is (pricePerGallon - sellerGain) * quantity
                // sellerGain = pricePerGallon - shadowPrice => shadowPrice = pricePerGallon - sellerGain
                const myShadowPrice = pricePerGallon - (heat.sellerGain || 0);
                inventoryDelta = -(myShadowPrice * quantity);
                totalDelta = (heat.sellerGain || 0) * quantity;
                salesDelta = lastAmount;
            } else if (lastTransaction.role === 'buyer') {
                // I bought inventory. The value gained is (pricePerGallon + buyerGain) * quantity
                // buyerGain = shadowPrice - pricePerGallon => shadowPrice = pricePerGallon + buyerGain
                const myShadowPrice = pricePerGallon + (heat.buyerGain || 0);
                inventoryDelta = (myShadowPrice * quantity);
                totalDelta = (heat.buyerGain || 0) * quantity;
                purchaseDelta = lastAmount;
            }

            console.log('[DEBUG] Financial Delta from Last Transaction:', {
                transactionId: lastTransaction.transactionId,
                role: lastTransaction.role,
                chemical: lastTransaction.chemical,
                quantity: lastTransaction.quantity,
                pricePerGallon: lastTransaction.pricePerGallon,
                totalAmount: lastAmount,
                sellerGain: heat.sellerGain,
                buyerGain: heat.buyerGain,
                inventoryDelta,
                totalDelta
            });
        }

        // Update DOM
        const els = {
            inventory: document.getElementById('fin-production-rev'),
            inventoryDelta: document.getElementById('fin-production-delta'),
            salesRev: document.getElementById('fin-sales-rev'),
            salesDelta: document.getElementById('fin-sales-delta'),
            purchaseCost: document.getElementById('fin-purchase-cost'),
            purchaseDelta: document.getElementById('fin-purchase-delta'),
            totalValue: document.getElementById('fin-net-profit'),
            totalDelta: document.getElementById('fin-total-delta'),
            improvement: document.getElementById('fin-improvement'),
            improvementBadge: document.getElementById('improvement-badge')
        };

        if (els.inventory) {
            const isStale = staleness && staleness !== 'fresh';
            els.inventory.textContent = this.formatCurrency(inventoryValue) + (isStale ? ' *' : '');
            els.inventory.title = isStale ? 'Shadow prices are stale — click "Recalculate" to update this value' : '';
        }

        // Show projected production mix (Deicer + Solvent)
        const mixEl = document.getElementById('fin-production-mix');
        if (mixEl && optimalMix) {
            const deicer = Math.round(optimalMix.deicer || 0);
            const solvent = Math.round(optimalMix.solvent || 0);
            if (deicer > 0 || solvent > 0) {
                mixEl.textContent = `${deicer} gal Deicer + ${solvent} gal Solvent`;
            } else {
                mixEl.textContent = 'Calculating...';
            }
        }

        if (els.salesRev) {
            els.salesRev.textContent = this.formatCurrency(salesRevenue);
        }

        if (els.salesDelta) {
            els.salesDelta.textContent = salesDelta !== 0 ? `+${this.formatCurrency(salesDelta)}` : '';
            els.salesDelta.className = `text-[10px] font-bold text-green-400 mt-1`;
        }

        if (els.purchaseCost) {
            els.purchaseCost.textContent = this.formatCurrency(purchaseCosts);
        }

        if (els.purchaseDelta) {
            els.purchaseDelta.textContent = purchaseDelta !== 0 ? `+${this.formatCurrency(purchaseDelta)}` : '';
            els.purchaseDelta.className = `text-[10px] font-bold text-red-400 mt-1`;
        }

        if (els.inventoryDelta) {
            const deltaSign = inventoryDelta >= 0 ? '+' : '';
            const deltaColor = inventoryDelta >= 0 ? 'text-green-400' : 'text-red-400';
            els.inventoryDelta.textContent = inventoryDelta !== 0 ? `${deltaSign}${this.formatCurrency(inventoryDelta)} value change` : 'No change yet';
            els.inventoryDelta.className = `text-[10px] uppercase mt-1 ${inventoryDelta !== 0 ? deltaColor : 'text-gray-500'}`;
        }

        if (els.totalValue) {
            const isStale = staleness && staleness !== 'fresh';
            els.totalValue.textContent = this.formatCurrency(totalValue) + (isStale ? ' *' : '');
            els.totalValue.title = isStale ? 'Shadow prices are stale — click "Recalculate" to update this value' : '';
            els.totalValue.className = `text-2xl font-mono font-bold z-10 ${totalValue >= 0 ? 'text-green-400' : 'text-red-400'}`;
        }

        if (els.totalDelta) {
            const deltaSign = totalDelta >= 0 ? '+' : '';
            const deltaColor = totalDelta >= 0 ? 'text-green-400' : 'text-red-400';
            const deltaLabel = totalDelta >= 0 ? 'net gain' : 'net loss';
            els.totalDelta.textContent = totalDelta !== 0 ? `${deltaSign}${this.formatCurrency(totalDelta)} ${deltaLabel}` : 'No change yet';
            els.totalDelta.className = `text-[10px] uppercase mt-1 z-10 ${totalDelta !== 0 ? deltaColor : 'text-gray-400'}`;
        }

        // Display Growth Badge
        if (els.improvement) {
            const sign = percentImprovement >= 0 ? '+' : '';
            els.improvement.textContent = `${sign}${percentImprovement.toFixed(1)}%`;
            els.improvement.className = `text-sm font-mono font-bold ml-1 ${percentImprovement >= 0 ? 'text-green-400' : 'text-red-400'}`;

            if (els.improvementBadge) {
                els.improvementBadge.classList.remove('hidden');
            }
        }
    }

    /**
     * Render Transaction History Table
     * @param {Array} transactions - Array of transaction objects
     */
    renderTransactionHistoryTable(transactions) {
        const tbody = document.getElementById('history-table-body');
        const emptyMsg = document.getElementById('history-empty-msg');

        if (!tbody) return;

        tbody.innerHTML = '';

        if (!transactions || transactions.length === 0) {
            emptyMsg?.classList.remove('hidden');
            return;
        }

        // Filter accepted and Sort by time desc
        const sorted = [...transactions]
            .filter(t => !t.status || t.status === 'accepted')
            .sort((a, b) => (b.timestamp || 0) - (a.timestamp || 0));

        if (sorted.length === 0) {
             emptyMsg?.classList.remove('hidden');
             return;
        }
        emptyMsg?.classList.add('hidden');

        sorted.forEach(t => {
            const row = document.createElement('tr');
            row.className = 'hover:bg-gray-700/50 transition';

            let timeStr = 'Unknown';
            if (t.timestamp) {
                const date = new Date(t.timestamp * 1000);
                timeStr = date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            }

            const isSale = t.role === 'seller';
            const typeColor = isSale ? 'text-green-400' : 'text-blue-400';
            const typeIcon = isSale ? '↗' : '↙';
            const counterpartyName = t.counterpartyName || t.counterparty || 'Unknown';
            const invBefore = t.inventoryBefore !== undefined ? this.formatNumber(t.inventoryBefore) : '-';
            const invAfter = t.inventoryAfter !== undefined ? this.formatNumber(t.inventoryAfter) : '-';

            row.innerHTML = `
                <td class="py-3 font-mono text-gray-400">${timeStr}</td>
                <td class="py-3 font-bold ${typeColor}">${typeIcon} ${isSale ? 'SALE' : 'BUY'}</td>
                <td class="py-3 font-bold">Chemical ${t.chemical}</td>
                <td class="py-3 text-right font-mono">${this.formatNumber(t.quantity)}</td>
                <td class="py-3 text-right font-mono">${this.formatCurrency(t.pricePerGallon)}</td>
                <td class="py-3 text-right font-mono font-bold text-white">${this.formatCurrency(t.totalAmount)}</td>
                <td class="py-3 text-right font-mono text-gray-400">${invBefore}</td>
                <td class="py-3 text-right font-mono text-gray-400">${invAfter}</td>
                <td class="py-3 pl-4 text-gray-400">vs ${counterpartyName}</td>
            `;
            tbody.appendChild(row);
        });
    }
}

// Export singleton instance
export const financialRenderer = new FinancialRenderer();
