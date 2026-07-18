import { LitElement, html, css } from 'lit';
import { sharedStyles } from './shared-styles.js';
import { api } from '../api.js';

class ReportViewer extends LitElement {
    static styles = [
        sharedStyles,
        css`
            :host {
                display: block;
                z-index: 200;
            }

            .modal-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.75);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 200;
                opacity: 0;
                pointer-events: none;
                transition: opacity 0.2s;
            }

            .modal-overlay.open {
                opacity: 1;
                pointer-events: auto;
            }

            .modal-container {
                background-color: var(--color-bg-primary);
                width: 95%;
                max-width: 1200px;
                height: 90vh;
                border-radius: 0.75rem;
                display: flex;
                flex-direction: column;
                box-shadow: var(--shadow-xl);
                border: 2px solid var(--color-brand-primary);
                overflow: hidden;
            }

            .modal-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 1rem 1.5rem;
                background-color: var(--color-bg-tertiary);
                border-bottom: 1px solid var(--color-border);
            }

            .modal-title {
                font-size: 1.25rem;
                font-weight: 700;
                color: var(--color-brand-primary);
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }

            .tabs {
                display: flex;
                background-color: var(--color-bg-secondary);
                border-bottom: 1px solid var(--color-border);
                overflow-x: auto;
            }

            .tab-btn {
                padding: 1rem 1.5rem;
                background: none;
                border: none;
                color: var(--color-text-tertiary);
                font-weight: 600;
                cursor: pointer;
                border-bottom: 2px solid transparent;
                white-space: nowrap;
                transition: all 0.2s;
            }

            .tab-btn:hover {
                color: var(--color-text-primary);
                background-color: var(--color-bg-tertiary);
            }

            .tab-btn.active {
                color: var(--color-brand-primary);
                border-bottom-color: var(--color-brand-primary);
                background-color: var(--color-bg-tertiary);
            }

            .content-area {
                flex: 1;
                overflow-y: auto;
                padding: 1.5rem;
                position: relative;
            }

            /* Tables */
            .report-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 1rem;
                font-size: 0.875rem;
                background-color: var(--color-bg-secondary);
                border: 1px solid var(--color-border);
            }

            .report-table th,
            .report-table td {
                padding: 0.75rem 1rem;
                text-align: left;
                border-bottom: 1px solid var(--color-border);
            }

            .report-table th {
                background-color: var(--color-bg-tertiary);
                color: var(--color-text-primary);
                font-weight: 600;
                position: sticky;
                top: 0;
            }

            .report-table tr:hover {
                background-color: var(--color-bg-tertiary);
            }

            .report-table td.num {
                text-align: right;
                font-family: monospace;
            }
            .report-table th.num {
                text-align: right;
            }

            /* Complexity Radio Bank */
            .complexity-selector {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                background-color: var(--color-bg-tertiary);
                padding: 0.25rem;
                border-radius: 0.5rem;
                margin-right: auto;
            }
            .complexity-selector label {
                display: flex;
                align-items: center;
                gap: 0.375rem;
                padding: 0.5rem 0.75rem;
                border-radius: 0.375rem;
                cursor: pointer;
                font-size: 0.813rem;
                font-weight: 500;
                color: var(--color-text-tertiary);
                transition: all 0.15s;
            }
            .complexity-selector label:hover {
                color: var(--color-text-secondary);
                background-color: var(--color-bg-secondary);
            }
            .complexity-selector input[type="radio"] {
                display: none;
            }
            .complexity-selector input[type="radio"]:checked + label {
                background-color: var(--color-brand-primary);
                color: white;
            }
            .complexity-hint {
                font-size: 0.7rem;
                color: var(--color-text-tertiary);
                margin-left: 0.5rem;
            }

            /* Scrollable Table Container */
            .table-scroll-container {
                overflow-x: auto;
                margin-top: 1rem;
                border: 1px solid var(--color-border);
                border-radius: 0.375rem;
            }
            .table-scroll-container .report-table {
                margin-top: 0;
                border: none;
                min-width: max-content;
            }

            /* Heat indicator styles */
            .heat-indicator {
                display: inline-flex;
                align-items: center;
                gap: 0.25rem;
                padding: 0.125rem 0.5rem;
                border-radius: 0.25rem;
                font-size: 0.75rem;
                font-weight: 600;
            }
            .heat-indicator.hot {
                background-color: rgba(239, 68, 68, 0.2);
                color: var(--color-error);
            }
            .heat-indicator.good {
                background-color: rgba(16, 185, 129, 0.2);
                color: var(--color-success);
            }
            .heat-indicator.fair {
                background-color: rgba(107, 114, 128, 0.2);
                color: var(--color-text-tertiary);
            }

            /* Financial Cards */
            .financial-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 1.5rem;
                margin-bottom: 2rem;
            }

            .stat-card {
                background-color: var(--color-bg-secondary);
                padding: 1.5rem;
                border-radius: 0.5rem;
                border: 1px solid var(--color-border);
            }

            .stat-label {
                color: var(--color-text-tertiary);
                font-size: 0.875rem;
                margin-bottom: 0.5rem;
            }

            .stat-value {
                font-size: 1.5rem;
                font-weight: 700;
                color: var(--color-text-primary);
            }

            .stat-value.profit { color: var(--color-success); }
            .stat-value.loss { color: var(--color-error); }

            .action-bar {
                display: flex;
                justify-content: flex-end;
                margin-bottom: 1rem;
            }

            .loading-spinner {
                display: flex;
                justify-content: center;
                align-items: center;
                height: 200px;
                color: var(--color-brand-primary);
            }
        `
    ];

    static properties = {
        isOpen: { type: Boolean },
        activeTab: { type: String, attribute: 'active-tab' },
        loading: { type: Boolean },
        data: { type: Object },
        embedded: { type: Boolean },
        hideTabs: { type: Boolean, attribute: 'hide-tabs' },
        txnComplexity: { type: String, state: true }
    };

    constructor() {
        super();
        this.isOpen = false;
        this.activeTab = 'financials';
        this.loading = false;
        this.data = null;
        this.embedded = false;
        this.hideTabs = false;
        this.txnComplexity = 'simple'; // simple | detailed | full
    }

    updated(changedProperties) {
        if (changedProperties.has('isOpen') && this.isOpen) {
            this.fetchData();
        }
        // If embedded, fetch data immediately or when connected
        if (this.embedded && !this.data && !this.loading) {
            this.fetchData();
        }
    }

    async fetchData() {
        if (this.loading) return;
        this.loading = true;
        try {
            const result = await api.reports.get('all');
            if (result.success) {
                this.data = result;
            }
        } catch (error) {
            console.error('Failed to load reports:', error);
        } finally {
            this.loading = false;
        }
    }

    close() {
        this.isOpen = false;
        this.dispatchEvent(new CustomEvent('close'));
    }

    setTab(tab) {
        this.activeTab = tab;
    }

    downloadCSV(filename, headers, rows) {
        const csvContent = [
            headers.join(','),
            ...rows.map(row => row.map(cell => {
                if (typeof cell === 'string' && cell.includes(',')) {
                    return `"${cell}"`;
                }
                return cell;
            }).join(','))
        ].join('\n');

        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.setAttribute('href', url);
        link.setAttribute('download', filename);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    renderTeamSheet() {
        if (!this.data?.financials) return html`<div>No data available</div>`;
        
        const f = this.data.financials;
        const txns = this.data.transactions || [];
        
        const sales = txns.filter(t => t.type === 'Sale');
        const purchases = txns.filter(t => t.type === 'Purchase');

        return html`
            <div class="action-bar">
                <button class="btn btn-secondary" @click=${() => this.downloadCSV('team_summary.csv', ['Category', 'Value'], [['Production', f.productionRevenue], ['Sales', f.salesRevenue], ['Purchases', f.purchaseCosts], ['Total', f.totalProfit]])}>
                    Download Summary CSV
                </button>
            </div>

            <!-- Financial Summary Panel (Excel Section F) -->
            <div class="financial-grid">
                <div class="stat-card">
                    <div class="stat-label">Production (Profit from Mfg)</div>
                    <div class="stat-value text-success">$${f.productionRevenue.toFixed(2)}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Sales (Revenue from Trades)</div>
                    <div class="stat-value text-info">$${f.salesRevenue.toFixed(2)}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Purchases (Cost of Trades)</div>
                    <div class="stat-value text-error">$${f.purchaseCosts.toFixed(2)}</div>
                </div>
                <div class="stat-card" style="border-color: var(--color-brand-primary);">
                    <div class="stat-label">Total Net Profit</div>
                    <div class="stat-value ${f.totalProfit >= 0 ? 'profit' : 'loss'}">
                        $${f.totalProfit.toFixed(2)}
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-8 mt-8">
                <!-- Sales Log (Excel Section D) -->
                <div class="card">
                    <div class="card-header flex justify-between items-center">
                        <span class="font-bold uppercase tracking-wider">Sales Transaction Log</span>
                        <span class="text-xs text-gray-400">${sales.length} transactions</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="report-table m-0" style="border:none;">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Chemical</th>
                                    <th class="num">Gallons</th>
                                    <th class="num">Unit Price</th>
                                    <th class="num">Total</th>
                                    <th>To Team</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${sales.map(t => html`
                                    <tr>
                                        <td>${t.date}</td>
                                        <td><span class="font-bold" style="color: var(--color-chemical-${t.chemical.toLowerCase()})">${t.chemical}</span></td>
                                        <td class="num">${t.quantity}</td>
                                        <td class="num">$${t.pricePerGallon.toFixed(2)}</td>
                                        <td class="num font-bold text-success">$${t.totalPrice.toFixed(2)}</td>
                                        <td>${t.counterparty}</td>
                                    </tr>
                                `)}
                                ${sales.length === 0 ? html`<tr><td colspan="6" class="text-center p-4 text-gray-500 italic">No sales recorded</td></tr>` : ''}
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Purchases Log (Excel Section E) -->
                <div class="card">
                    <div class="card-header flex justify-between items-center">
                        <span class="font-bold uppercase tracking-wider">Purchases Transaction Log</span>
                        <span class="text-xs text-gray-400">${purchases.length} transactions</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="report-table m-0" style="border:none;">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Chemical</th>
                                    <th class="num">Gallons</th>
                                    <th class="num">Unit Price</th>
                                    <th class="num">Total</th>
                                    <th>From Team</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${purchases.map(t => html`
                                    <tr>
                                        <td>${t.date}</td>
                                        <td><span class="font-bold" style="color: var(--color-chemical-${t.chemical.toLowerCase()})">${t.chemical}</span></td>
                                        <td class="num">${t.quantity}</td>
                                        <td class="num">$${t.pricePerGallon.toFixed(2)}</td>
                                        <td class="num font-bold text-error">$${t.totalPrice.toFixed(2)}</td>
                                        <td>${t.counterparty}</td>
                                    </tr>
                                `)}
                                ${purchases.length === 0 ? html`<tr><td colspan="6" class="text-center p-4 text-gray-500 italic">No purchases recorded</td></tr>` : ''}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;
    }

    renderFinancials() {
        return this.renderTeamSheet();
    }

    setTxnComplexity(level) {
        this.txnComplexity = level;
    }

    getHeatIndicator(t) {
        if (!t.heat || typeof t.heat.yourGain !== 'number') return '';
        const heatClass = t.heat.isHot ? 'hot' : t.heat.yourGain > 0 ? 'good' : 'fair';
        const label = t.heat.isHot ? '🔥 Hot' : t.heat.yourGain > 0 ? '✓ Good' : '— Fair';
        return html`<span class="heat-indicator ${heatClass}">${label}</span>`;
    }

    renderTransactions() {
        if (!this.data?.transactions) return html`<div>No transactions found</div>`;

        const txns = this.data.transactions;
        const complexity = this.txnComplexity;

        // Define columns for each complexity level
        const columns = {
            simple: ['Date', 'Type', 'Chem', 'Qty', 'Price', 'Total', 'Counterparty'],
            detailed: ['Date', 'Type', 'Chem', 'Qty', 'Price', 'Total', 'Counterparty', 'Inv Before', 'Inv After', 'Change'],
            full: ['Date', 'Type', 'Chem', 'Qty', 'Price', 'Total', 'Counterparty', 'Inv Before', 'Inv After', 'Change', 'Heat', 'Your Gain']
        };

        const currentColumns = columns[complexity] || columns.simple;
        const colCount = currentColumns.length;

        const downloadTransactions = () => {
            // CSV always exports based on current complexity
            const headers = currentColumns;
            const rows = txns.map(t => {
                const invChange = (t.inventoryBefore !== null && t.inventoryAfter !== null)
                    ? (t.inventoryAfter - t.inventoryBefore) : '';
                const heatLabel = t.heat ? (t.heat.isHot ? 'Hot' : t.heat.yourGain > 0 ? 'Good' : 'Fair') : '';
                const yourGain = t.heat?.yourGain ?? '';

                const row = [t.date, t.type, t.chemical, t.quantity, t.pricePerGallon, t.totalPrice, t.counterparty];
                if (complexity === 'detailed' || complexity === 'full') {
                    row.push(t.inventoryBefore ?? '', t.inventoryAfter ?? '', invChange);
                }
                if (complexity === 'full') {
                    row.push(heatLabel, yourGain);
                }
                return row;
            });
            this.downloadCSV(`transaction_history_${complexity}.csv`, headers, rows);
        };

        return html`
            <div class="action-bar">
                <div class="complexity-selector">
                    <input type="radio" id="txn-simple" name="txn-complexity" value="simple"
                        ?checked=${complexity === 'simple'}
                        @change=${() => this.setTxnComplexity('simple')}>
                    <label for="txn-simple">Simple</label>

                    <input type="radio" id="txn-detailed" name="txn-complexity" value="detailed"
                        ?checked=${complexity === 'detailed'}
                        @change=${() => this.setTxnComplexity('detailed')}>
                    <label for="txn-detailed">Detailed</label>

                    <input type="radio" id="txn-full" name="txn-complexity" value="full"
                        ?checked=${complexity === 'full'}
                        @change=${() => this.setTxnComplexity('full')}>
                    <label for="txn-full">Full</label>
                </div>
                ${complexity !== 'simple' ? html`<span class="complexity-hint">← Scroll table for more columns</span>` : ''}
                <button class="btn btn-secondary" @click=${downloadTransactions}>Download CSV</button>
            </div>

            <div class="table-scroll-container">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Chem</th>
                            <th class="num">Qty</th>
                            <th class="num">Price</th>
                            <th class="num">Total</th>
                            <th>Counterparty</th>
                            ${complexity === 'detailed' || complexity === 'full' ? html`
                                <th class="num">Inv Before</th>
                                <th class="num">Inv After</th>
                                <th class="num">Change</th>
                            ` : ''}
                            ${complexity === 'full' ? html`
                                <th>Heat</th>
                                <th class="num">Your Gain</th>
                            ` : ''}
                        </tr>
                    </thead>
                    <tbody>
                        ${txns.map(t => {
                            const invChange = (t.inventoryBefore !== null && t.inventoryAfter !== null)
                                ? (t.inventoryAfter - t.inventoryBefore) : null;
                            const changeClass = invChange !== null ? (invChange > 0 ? 'color: var(--color-success)' : invChange < 0 ? 'color: var(--color-error)' : '') : '';

                            return html`
                                <tr>
                                    <td>${t.date}</td>
                                    <td>
                                        <span class="px-2 py-1 rounded text-xs font-bold ${t.type === 'Sale' ? 'bg-green-600' : 'bg-red-600'} text-white">
                                            ${t.type.toUpperCase()}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="font-bold" style="color: var(--color-chemical-${t.chemical.toLowerCase()})">
                                            ${t.chemical}
                                        </span>
                                    </td>
                                    <td class="num">${t.quantity}</td>
                                    <td class="num">$${t.pricePerGallon.toFixed(2)}</td>
                                    <td class="num">$${t.totalPrice.toFixed(2)}</td>
                                    <td>${t.counterparty}</td>
                                    ${complexity === 'detailed' || complexity === 'full' ? html`
                                        <td class="num">${t.inventoryBefore?.toFixed(1) ?? '—'}</td>
                                        <td class="num">${t.inventoryAfter?.toFixed(1) ?? '—'}</td>
                                        <td class="num" style="${changeClass}">
                                            ${invChange !== null ? `${invChange > 0 ? '+' : ''}${invChange.toFixed(1)}` : '—'}
                                        </td>
                                    ` : ''}
                                    ${complexity === 'full' ? html`
                                        <td>${this.getHeatIndicator(t)}</td>
                                        <td class="num" style="${t.heat?.yourGain >= 0 ? 'color: var(--color-success)' : 'color: var(--color-error)'}">
                                            ${t.heat?.yourGain !== undefined ? `${t.heat.yourGain >= 0 ? '+' : ''}$${t.heat.yourGain.toFixed(2)}` : '—'}
                                        </td>
                                    ` : ''}
                                </tr>
                            `;
                        })}
                        ${txns.length === 0 ? html`<tr><td colspan="${colCount}" class="text-center p-4">No transactions yet</td></tr>` : ''}
                    </tbody>
                </table>
            </div>
        `;
    }

    renderAnswerReport() {
        if (!this.data?.optimization?.answerReport) return html`<div>No optimization data</div>`;
        
        const ar = this.data.optimization.answerReport;

        const downloadAnswer = () => {
            const rows = [];
            // Objective
            rows.push(['OBJECTIVE', 'Name', 'Final Value']);
            rows.push(['', ar.objective.name, ar.objective.finalValue]);
            // Variables
            rows.push([]);
            rows.push(['VARIABLES', 'Name', 'Final Value', 'Obj Coef']);
            ar.variables.forEach(v => rows.push(['', v.name, v.finalValue, v.objectiveCoef]));
            // Constraints
            rows.push([]);
            rows.push(['CONSTRAINTS', 'Name', 'Status', 'Slack', 'Used', 'Available']);
            ar.constraints.forEach(c => rows.push(['', c.name, c.status, c.slack, c.used, c.available]));

            this.downloadCSV('answer_report.csv', ['Section', 'Item', 'Value', 'Extra1', 'Extra2', 'Extra3'], rows);
        };

        return html`
             <div class="action-bar flex justify-between items-center">
                <div class="font-bold text-lg">${ar.title || 'Answer Report'}</div>
                <button class="btn btn-secondary" @click=${downloadAnswer}>Download CSV</button>
            </div>

            <div class="grid gap-4">
                <div class="card">
                    <div class="card-header font-bold">Target Cell (Max)</div>
                    <table class="report-table m-0" style="border:none;">
                        <thead><tr><th>Name</th><th class="num">Final Value</th></tr></thead>
                        <tbody>
                            <tr><td>${ar.objective.name}</td><td class="num font-bold text-success">$${ar.objective.finalValue.toFixed(2)}</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="card">
                    <div class="card-header font-bold">Optimal Production Mix</div>
                    <table class="report-table m-0" style="border:none;">
                        <thead><tr><th>Name</th><th class="num">Gallons Produced</th><th class="num">Unit Profit</th></tr></thead>
                        <tbody>
                            ${ar.variables.map(v => html`
                                <tr>
                                    <td>${v.name}</td>
                                    <td class="num font-bold">${v.finalValue}</td>
                                    <td class="num">$${v.objectiveCoef.toFixed(2)}</td>
                                </tr>
                            `)}
                        </tbody>
                    </table>
                </div>

                <div class="card">
                    <div class="card-header font-bold">Constraints (Chemical Availability)</div>
                    <table class="report-table m-0" style="border:none;">
                        <thead><tr><th>Name</th><th>Status</th><th class="num">Slack (Leftover)</th><th class="num">Used</th><th class="num">Available</th></tr></thead>
                        <tbody>
                            ${ar.constraints.map(c => html`
                                <tr>
                                    <td>${c.name}</td>
                                    <td>
                                        <span class="px-2 py-1 rounded text-xs font-bold ${c.status === 'Binding' ? 'bg-red-600 text-white' : 'bg-gray-600 text-gray-300'}">
                                            ${c.status}
                                        </span>
                                    </td>
                                    <td class="num">${c.slack?.toFixed(2) ?? '-'}</td>
                                    <td class="num">${c.used?.toFixed(2) ?? '-'}</td>
                                    <td class="num">${c.available?.toFixed(2) ?? '-'}</td>
                                </tr>
                            `)}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }

    renderSensitivityReport() {
        if (!this.data?.optimization?.sensitivityReport) return html`<div>No sensitivity data</div>`;

        const sr = this.data.optimization.sensitivityReport;

        const downloadSensitivity = () => {
            this.downloadCSV('sensitivity_report.csv',
                ['Chemical', 'Shadow Price', 'Current Inventory', 'Allowable Increase', 'Allowable Decrease'],
                sr.shadowPrices.map(s => [s.chemical, s.shadowPrice, s.currentInventory, s.allowableIncrease, s.allowableDecrease])
            );
        };

        return html`
            <div class="action-bar">
                <button class="btn btn-secondary" @click=${downloadSensitivity}>Download CSV</button>
            </div>

            <div class="card">
                <div class="card-header font-bold text-lg">Shadow Prices & Ranges</div>
                <table class="report-table m-0" style="border:none;">
                    <thead>
                        <tr>
                            <th>Chemical</th>
                            <th class="num">Shadow Price</th>
                            <th class="num">Inventory</th>
                            <th class="num">Allowable Increase</th>
                            <th class="num">Allowable Decrease</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${sr.shadowPrices?.map(s => html`
                            <tr>
                                <td><span class="font-bold" style="color: var(--color-chemical-${s.chemical?.toLowerCase() ?? 'c'})">${s.chemical ?? '-'}</span></td>
                                <td class="num font-bold ${(s.shadowPrice ?? 0) > 0 ? 'text-success' : 'text-gray-400'}">
                                    $${s.shadowPrice?.toFixed(2) ?? '0.00'}
                                </td>
                                <td class="num">${s.currentInventory?.toFixed(2) ?? '-'}</td>
                                <td class="num">${s.allowableIncrease ?? '-'}</td>
                                <td class="num">${s.allowableDecrease ?? '-'}</td>
                            </tr>
                        `) ?? html`<tr><td colspan="5" class="text-center text-gray-400">No shadow price data available</td></tr>`}
                    </tbody>
                </table>
            </div>

            <div class="mt-4 p-4 card border-gray-600">
                <h4 class="font-bold mb-2">Interpretation Guide</h4>
                <ul class="text-sm text-gray-300 gap-2 flex flex-col pl-4" style="list-style: disc;">
                    <li><strong>Shadow Price:</strong> The extra profit you would make from having ONE more gallon of this chemical.</li>
                    <li><strong>Binding:</strong> If Shadow Price > $0, this chemical is a BOTTLENECK. You are using all of it.</li>
                    <li><strong>Allowable Increase:</strong> How many more gallons you can add before the Shadow Price changes (usually drops).</li>
                    <li><strong>Allowable Decrease:</strong> How many gallons you can lose before the Shadow Price changes (usually rises).</li>
                </ul>
            </div>
        `;
    }

    renderContent() {
        return html`
            ${!this.hideTabs ? html`
                <div class="tabs">
                    <button class="tab-btn ${this.activeTab === 'team-sheet' ? 'active' : ''}" 
                            @click=${() => this.setTab('team-sheet')}>
                        Team Sheet
                    </button>
                    <button class="tab-btn ${this.activeTab === 'answer' ? 'active' : ''}" 
                            @click=${() => this.setTab('answer')}>
                        Answer Report
                    </button>
                    <button class="tab-btn ${this.activeTab === 'sensitivity' ? 'active' : ''}" 
                            @click=${() => this.setTab('sensitivity')}>
                        Sensitivity Report
                    </button>
                </div>
            ` : ''}

            <div class="content-area" style="${this.embedded ? 'height: 100%; overflow: visible;' : ''}">
                ${this.loading 
                    ? html`<div class="loading-spinner">Loading data...</div>` 
                    : (() => {
                        switch(this.activeTab) {
                            case 'team-sheet':
                            case 'financials': return this.renderTeamSheet();
                            case 'transactions': return this.renderTransactions();
                            case 'answer': return this.renderAnswerReport();
                            case 'sensitivity': return this.renderSensitivityReport();
                            default: return html`<div>Select a report ${this.activeTab}</div>`;
                        }
                    })()
                }
            </div>
        `;
    }

    render() {
        if (this.embedded) {
            return html`
                <div style="height: 100%; display: flex; flex-direction: column; background-color: var(--color-bg-primary);">
                    ${this.renderContent()}
                </div>
            `;
        }

        return html`
            <div class="modal-overlay ${this.isOpen ? 'open' : ''}" @click=${(e) => { if(e.target.classList.contains('modal-overlay')) this.close(); }}>
                <div class="modal-container">
                    <div class="modal-header">
                        <div class="modal-title">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                            Reports & Analysis
                        </div>
                        <button class="btn btn-secondary" @click=${() => this.close()}>Close</button>
                    </div>

                    ${this.renderContent()}
                </div>
            </div>
        `;
    }
}

customElements.define('report-viewer', ReportViewer);
