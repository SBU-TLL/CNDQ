/**
 * ModalManager
 * Handles opening, closing, and accessibility for application modals.
 */
export class ModalManager {
    constructor() {
        this.currentModal = null;
        this.focusBeforeModal = null;
    }

    /**
     * Open a modal with accessibility support
     * @param {string} modalId - The ID of the modal element
     */
    open(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;

        // Save focus
        this.focusBeforeModal = document.activeElement;

        // Show modal
        modal.classList.remove('hidden');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('role', 'dialog');

        this.currentModal = modal;

        // Hide background from screen readers. aria-modal alone is not reliably
        // honoured by NVDA+Firefox or JAWS in virtual-cursor mode, so we must
        // also mark every body-level sibling as aria-hidden while the modal is open.
        this._hiddenSiblings = [];
        Array.from(document.body.children).forEach(el => {
            if (!el.contains(modal) && el !== modal && !el.getAttribute('aria-hidden')) {
                el.setAttribute('aria-hidden', 'true');
                this._hiddenSiblings.push(el);
            }
        });

        // Focus first interactive element or the modal itself
        const focusable = modal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        if (focusable.length > 0) {
            setTimeout(() => focusable[0].focus(), 100);
        }

        // Trap focus and handle Escape key
        this._setupAccessibilityListeners(modal);

        // Notify
        document.dispatchEvent(new CustomEvent('modalOpened', { detail: { modalId } }));
    }

    /**
     * Close a modal
     * @param {string} modalId - The ID of the modal element
     */
    close(modalId) {
        const modal = document.getElementById(modalId) || this.currentModal;
        if (!modal) return;

        modal.classList.add('hidden');
        modal.removeAttribute('aria-modal');
        modal.removeAttribute('role');

        // Restore background visibility for screen readers
        if (this._hiddenSiblings) {
            this._hiddenSiblings.forEach(el => el.removeAttribute('aria-hidden'));
            this._hiddenSiblings = null;
        }

        if (this.currentModal === modal) {
            this.currentModal = null;
        }

        // Restore focus
        if (this.focusBeforeModal) {
            this.focusBeforeModal.focus();
            this.focusBeforeModal = null;
        }

        // Cleanup listeners (simplified for this implementation)
        document.dispatchEvent(new CustomEvent('modalClosed', { detail: { modalId } }));
    }

    _setupAccessibilityListeners(modal) {
        const handleKeyDown = (e) => {
            if (modal.classList.contains('hidden')) {
                document.removeEventListener('keydown', handleKeyDown);
                return;
            }

            if (e.key === 'Escape') {
                this.close(modal.id);
            }

            if (e.key === 'Tab') {
                const focusable = modal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
                const first = focusable[0];
                const last = focusable[focusable.length - 1];

                if (e.shiftKey && document.activeElement === first) {
                    last.focus();
                    e.preventDefault();
                } else if (!e.shiftKey && document.activeElement === last) {
                    first.focus();
                    e.preventDefault();
                }
            }
        };

        document.addEventListener('keydown', handleKeyDown);
    }
}

export const modalManager = new ModalManager();
