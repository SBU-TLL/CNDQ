/**
 * NotificationService
 * Handles toast notifications
 */
import { sounds } from './SoundService.js';

export class NotificationService {
    constructor() {
        this.containerId = 'toast-container';
    }

    /**
     * Announce a message to screen readers without showing a visual toast
     * @param {string} message 
     * @param {boolean} assertive - If true, use assertive live region (interrupts current speech)
     */
    announce(message, assertive = false) {
        const announcer = document.getElementById('sr-announcer');
        if (!announcer) return;

        announcer.setAttribute('aria-live', assertive ? 'assertive' : 'polite');
        
        // Clear and set to force re-announcement if message is the same
        announcer.textContent = '';
        setTimeout(() => {
            announcer.textContent = message;
        }, 50);
    }

    /**
     * Show a toast notification
     * @param {string} message - The message to display
     * @param {string} type - 'success', 'error', 'info', 'warning', 'excellent', 'bad', 'hot', 'cold'
     * @param {number} duration - Duration in milliseconds
     */
    showToast(message, type = 'info', duration = 3000) {
        // Automatically announce toasts to screen readers
        this.announce(message, type === 'error' || type === 'bad');

        // Play audio earcon
        if (type === 'success' || type === 'excellent') {
            sounds.playSuccess();
        } else if (type === 'error' || type === 'bad') {
            sounds.playError();
        } else if (type === 'hot') {
            sounds.playTrade();
        } else {
            sounds.playNotification();
        }

        // Use solid bg classes that are definitely in the Tailwind build
        const colors = {
            success: 'bg-green-600',
            error: 'bg-red-600',
            info: 'bg-blue-600',
            warning: 'bg-yellow-600',
            excellent: 'bg-green-600',
            bad: 'bg-red-600',
            hot: 'bg-orange-600',
            cold: 'bg-blue-600'
        };

        // Use inline styles for gradients (Tailwind gradient classes may not be in pre-built CSS)
        const gradientStyles = {
            excellent: 'background: linear-gradient(to right, #22c55e, #059669);',
            bad: 'background: linear-gradient(to right, #ef4444, #e11d48);',
            hot: 'background: linear-gradient(to right, #f97316, #dc2626); box-shadow: 0 4px 14px rgba(249, 115, 22, 0.4);',
            cold: 'background: linear-gradient(to right, #22d3ee, #2563eb); box-shadow: 0 4px 14px rgba(59, 130, 246, 0.4);'
        };

        const icons = {
            success: '✓',
            error: '✗',
            info: 'ℹ',
            warning: '⚠',
            excellent: '🎉',
            bad: '⚠️',
            hot: '🔥',
            cold: '❄️'
        };

        const container = document.getElementById(this.containerId);
        if (!container) {
            console.warn(`Toast container #${this.containerId} not found`);
            return;
        }

        const toast = document.createElement('div');
        toast.className = `${colors[type] || colors.info} text-white px-4 py-2 rounded-lg shadow-lg transform transition-all duration-300 ease-in-out flex items-center gap-2 pointer-events-auto`;

        // Apply gradient styles if available for this type
        if (gradientStyles[type]) {
            toast.style.cssText += gradientStyles[type];
        }

        // Start hidden below
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(100%)';

        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');

        // Add icon if special type
        if (icons[type]) {
            const iconSpan = document.createElement('span');
            iconSpan.textContent = icons[type];
            iconSpan.className = 'text-lg';
            toast.appendChild(iconSpan);
        }

        const messageSpan = document.createElement('span');
        messageSpan.className = 'text-sm';
        messageSpan.textContent = message;
        toast.appendChild(messageSpan);

        container.appendChild(toast);

        // Animate in (slide up from below)
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                toast.style.opacity = '1';
                toast.style.transform = 'translateY(0)';
            });
        });

        // Remove after specified duration
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(100%)';
            setTimeout(() => {
                toast.remove();
            }, 300);
        }, duration);
    }
}

// Export singleton
export const notifications = new NotificationService();
