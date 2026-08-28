import { LitElement, html, css } from 'lit';
import { sharedStyles } from './shared-styles.js';
import { api } from '../api.js';

class LeaderboardModal extends LitElement {
    static styles = [
        sharedStyles,
        css`
            :host {
                display: block;
            }

            .modal-overlay {
                position: fixed;
                inset: 0;
                background-color: var(--color-bg-modal);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 200;
                padding: 1rem;
                visibility: hidden;
                opacity: 0;
                transition: all 0.2s;
            }

            .modal-overlay.open {
                visibility: visible;
                opacity: 1;
            }

            .modal-content {
                background-color: var(--color-bg-secondary);
                border-radius: 0.75rem;
                padding: 1.5rem;
                border: 2px solid var(--color-warning);
                box-shadow: var(--shadow-2xl);
                max-width: 56rem;
                width: 100%;
                max-height: 90vh;
                overflow-y: auto;
                transform: scale(0.95);
                transition: transform 0.2s;
            }

            .modal-overlay.open .modal-content {
                transform: scale(1);
            }

            .header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 1.5rem;
            }

            .header h2 {
                margin: 0;
                font-size: 1.875rem;
                color: var(--color-warning);
            }

            .close-btn {
                background: none;
                border: none;
                color: var(--color-text-tertiary);
                cursor: pointer;
                padding: 0.5rem;
                font-size: 2rem;
                line-height: 1;
            }

            .close-btn:hover {
                color: var(--color-text-primary);
            }

            .session-info {
                text-align: center;
                margin-bottom: 1rem;
                color: var(--color-text-secondary);
            }

            .table-container {
                overflow-x: auto;
            }

            table {
                width: 100%;
                border-collapse: collapse;
                text-align: left;
            }

            thead {
                background-color: var(--color-bg-tertiary);
                border-bottom: 2px solid var(--color-warning);
            }

            th {
                padding: 0.75rem 1rem;
                color: var(--color-warning);
                font-weight: 700;
            }

            td {
                padding: 0.75rem 1rem;
                border-bottom: 1px solid var(--color-border);
            }

            .rank {
                font-weight: 700;
                color: var(--color-text-secondary);
            }

            .team-name {
                font-weight: 600;
            }

            .current-user {
                background-color: rgba(251, 191, 36, 0.1);
            }

            .current-user .team-name {
                color: var(--color-warning);
            }

            .text-right { text-align: right; }

            .roi-positive { color: var(--color-success); }
            .roi-negative { color: var(--color-error); }

            .footer-info {
                margin-top: 1.5rem;
                padding: 1rem;
                background-color: var(--color-bg-tertiary);
                border-radius: 0.5rem;
                font-size: 0.875rem;
                color: var(--color-text-secondary);
            }

            .loading {
                text-align: center;
                padding: 3rem;
                color: var(--color-text-tertiary);
            }
        `
    ];

    static properties = {
        isOpen: { type: Boolean },
        standings: { type: Array },
        session: { type: String },
        phase: { type: String },
        currentTeamId: { type: String },
        isLoading: { type: Boolean }
    };

    constructor() {
        super();
        this.isOpen = false;
        this.standings = [];
        this.session = '-';
        this.phase = '-';
        this.currentTeamId = '';
        this.isLoading = false;
    }

    async open() {
        this.isOpen = true;
        await this.loadData();
    }

    close() {
        this.isOpen = false;
    }

    async loadData() {
        this.isLoading = true;
        try {
            const data = await api.leaderboard.getStandings();
            if (data && data.success) {
                this.standings = data.standings || [];
                this.session = data.session || '-';
                this.phase = data.phase || '-';
            }
        } catch (error) {
            console.error('Failed to load leaderboard data:', error);
        } finally {
            this.isLoading = false;
        }
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
        return html`
            <div class="modal-overlay ${this.isOpen ? 'open' : ''}" @click=${this.close}>
                <div class="modal-content" @click=${(e) => e.stopPropagation()}>
                    <div class="header">
                        <h2>Leaderboard</h2>
                        <button class="close-btn" @click=${this.close}>&times;</button>
                    </div>

                    <div class="session-info">
                        <span>Round <strong>${this.session}</strong></span>
                        <span style="margin: 0 0.5rem">•</span>
                        <span style="text-transform: capitalize;">${this.phase}</span>
                    </div>

                    <div class="table-container">
                        ${this.isLoading 
                            ? html`<div class="loading">Loading standings...</div>`
                            : html`
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Rank</th>
                                            <th>Team</th>
                                            <th class="text-right">Total Value</th>
                                            <th class="text-right">Net Gain/Loss</th>
                                            <th class="text-right">Improvement %</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${this.standings.length === 0 
                                            ? html`<tr><td colspan="5" class="text-center">No standings available</td></tr>`
                                            : this.standings.map((team, index) => {
                                                const isMe = team.teamId === this.currentTeamId;
                                                return html`
                                                    <tr class="${isMe ? 'current-user' : ''}">
                                                        <td class="rank">${index + 1}</td>
                                                        <td class="team-name">${team.teamName} ${isMe ? '(You)' : ''}</td>
                                                        <td class="text-right font-bold text-warning">${this.formatCurrency(team.currentFunds)}</td>
                                                        <td class="text-right ${team.profit >= 0 ? 'roi-positive' : 'roi-negative'}">
                                                            ${team.profit >= 0 ? '+' : ''}${this.formatCurrency(team.profit)}
                                                        </td>
                                                        <td class="text-right ${team.roi >= 0 ? 'roi-positive' : 'roi-negative'}">
                                                            ${team.roi >= 0 ? '+' : ''}${(team.roi || 0).toFixed(1)}%
                                                        </td>
                                                    </tr>
                                                `;
                                            })}
                                    </tbody>
                                </table>
                            `
                        }
                    </div>

                    <div class="footer-info">
                        <strong style="color: var(--color-warning);">How scoring works:</strong> 
                        Teams are ranked by <strong>Total Value Created</strong> — the combined value of your net trading cash and your potential production profit. The <strong>Improvement %</strong> shows how much you've optimized your initial inventory.
                    </div>
                </div>
            </div>
        `;
    }
}

customElements.define('leaderboard-modal', LeaderboardModal);
