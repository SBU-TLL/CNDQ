import { LitElement, html, css } from 'lit';
import { sharedStyles } from './shared-styles.js';
import { api } from '../api.js';

class NotificationManager extends LitElement {
    static styles = [
        sharedStyles,
        css`
            :host {
                position: relative;
                display: block;
            }

            .notif-btn {
                position: relative;
                background-color: #374151;
                color: #d1d5db;
                padding: 0.5rem;
                border-radius: 0.5rem;
                border: none;
                cursor: pointer;
                transition: all 0.2s;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            @media (min-width: 768px) {
                .notif-btn {
                    padding: 0.75rem;
                }
            }

            .notif-btn:hover {
                background-color: #4b5563;
            }

            .badge {
                position: absolute;
                top: -0.25rem;
                right: -0.25rem;
                background-color: var(--color-error);
                color: white;
                font-size: 0.7rem;
                font-weight: 700;
                border-radius: 9999px;
                height: 1.25rem;
                width: 1.25rem;
                display: flex;
                align-items: center;
                justify-content: center;
                border: 2px solid var(--color-bg-primary);
            }

            .panel {
                position: absolute;
                right: 0;
                top: calc(100% + 0.75rem);
                width: 20rem;
                background-color: var(--color-bg-secondary);
                border: 2px solid var(--color-brand-primary);
                border-radius: 0.75rem;
                box-shadow: var(--shadow-xl);
                z-index: 150;
                overflow: hidden;
                display: none;
                flex-direction: column;
            }

            .panel.open {
                display: flex;
            }

            .header {
                padding: 1rem;
                background-color: var(--color-bg-tertiary);
                border-bottom: 1px solid var(--color-border);
                display: flex;
                align-items: center;
                justify-content: space-between;
            }

            .header h3 {
                margin: 0;
                font-size: 1rem;
                color: var(--color-brand-primary);
            }

            .close-btn {
                background: none;
                border: none;
                color: var(--color-text-tertiary);
                cursor: pointer;
                padding: 0.25rem;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .close-btn:hover {
                color: var(--color-text-primary);
            }

            .list {
                max-height: 20rem;
                overflow-y: auto;
                padding: 0.75rem;
            }

            /* Scrollbar */
            .list::-webkit-scrollbar {
                width: 6px;
            }
            .list::-webkit-scrollbar-track {
                background: transparent;
            }
            .list::-webkit-scrollbar-thumb {
                background: var(--color-border);
                border-radius: 3px;
            }

            .notif-item {
                padding: 0.75rem;
                background-color: var(--color-bg-tertiary);
                border-radius: 0.5rem;
                border-left: 4px solid var(--notif-color, var(--color-border));
                margin-bottom: 0.5rem;
                cursor: pointer;
                transition: background-color 0.2s;
            }

            .notif-item:hover {
                /* --color-border is lighter than --color-bg-tertiary in dark mode,
                   which reduces contrast with small tertiary-coloured labels to near
                   the WCAG AA floor. Use --color-bg-secondary (darker in dark mode,
                   safe in all themes) instead. */
                background-color: var(--color-bg-secondary);
            }

            .notif-type {
                font-size: 0.65rem;
                font-weight: 700;
                text-transform: uppercase;
                color: var(--notif-text-color, var(--color-text-tertiary));
            }

            .notif-time {
                font-size: 0.65rem;
                color: var(--color-text-tertiary);
            }

            .notif-message {
                font-size: 0.875rem;
                color: var(--color-text-primary);
                margin-top: 0.25rem;
            }

            .empty {
                padding: 2rem;
                text-align: center;
                color: var(--color-text-tertiary);
                font-size: 0.875rem;
            }
        `
    ];

    static properties = {
        notifications: { type: Array },
        unreadCount: { type: Number },
        isOpen: { type: Boolean }
    };

    constructor() {
        super();
        this.notifications = [];
        this.unreadCount = 0;
        this.isOpen = false;
        
        // Bind event listeners
        this._handleOutsideClick = this._handleOutsideClick.bind(this);
    }

    connectedCallback() {
        super.connectedCallback();
        document.addEventListener('click', this._handleOutsideClick);
    }

    disconnectedCallback() {
        super.disconnectedCallback();
        document.removeEventListener('click', this._handleOutsideClick);
    }

    _handleOutsideClick(e) {
        if (this.isOpen && !this.contains(e.target)) {
            this.isOpen = false;
        }
    }

    togglePanel(e) {
        e.stopPropagation();
        this.isOpen = !this.isOpen;
        if (this.isOpen) {
            this.loadNotifications();
        }
    }

    async loadNotifications() {
        try {
            const data = await api.notifications.list();
            if (data && data.success) {
                this.notifications = data.notifications;
                this.unreadCount = data.unreadCount || 0;
            }
        } catch (error) {
            console.error('Failed to load notifications in component:', error);
        }
    }

    getNotificationStyles(type) {
        switch (type.toLowerCase()) {
            case 'trade':
            case 'success':
                return { color: 'var(--color-success)', text: 'var(--color-success)' };
            case 'offer':
            case 'negotiation':
                return { color: 'var(--color-info)', text: 'var(--color-info)' };
            case 'warning':
                return { color: 'var(--color-warning)', text: 'var(--color-warning)' };
            case 'error':
            case 'rejected':
                return { color: 'var(--color-error)', text: 'var(--color-error)' };
            default:
                return { color: 'var(--color-border)', text: 'var(--color-text-tertiary)' };
        }
    }

    handleNotifClick(notif) {
        this.isOpen = false;
        this.dispatchEvent(new CustomEvent('notification-click', {
            detail: { notification: notif },
            bubbles: true,
            composed: true
        }));
    }

    formatTimestamp(timestamp) {
        if (!timestamp) return '';
        const date = new Date(timestamp);
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    render() {
        return html`
            <button class="notif-btn" @click=${this.togglePanel} aria-label="View notifications" title="View notifications">
                <svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                </svg>
                ${this.unreadCount > 0 ? html`<span class="badge">${this.unreadCount}</span>` : ''}
            </button>

            <div class="panel ${this.isOpen ? 'open' : ''}">
                <div class="header">
                    <h3>Notifications</h3>
                    <button class="close-btn" @click=${() => this.isOpen = false} aria-label="Close notifications" title="Close notifications">
                        <svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
                <div class="list">
                    ${this.notifications.length === 0 
                        ? html`<div class="empty">No notifications</div>`
                        : this.notifications.map(notif => {
                            const styles = this.getNotificationStyles(notif.type);
                            return html`
                                <div class="notif-item" 
                                     style="--notif-color: ${styles.color}; --notif-text-color: ${styles.text};"
                                     @click=${() => this.handleNotifClick(notif)}>
                                    <div class="flex justify-between items-start">
                                        <span class="notif-type">${notif.type}</span>
                                        <span class="notif-time">${this.formatTimestamp(notif.created_at)}</span>
                                    </div>
                                    <div class="notif-message">${notif.message}</div>
                                </div>
                            `;
                        })
                    }
                </div>
            </div>
        `;
    }
}

customElements.define('notification-manager', NotificationManager);
