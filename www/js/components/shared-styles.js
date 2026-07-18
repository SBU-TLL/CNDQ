/**
 * Shared Styles for Lit Web Components
 *
 * Common utility classes and component styles that can be imported
 * by any Lit component to maintain consistency.
 */

import { css } from 'lit';

/**
 * Shared utility styles that mirror the global CSS utilities
 * but work inside Shadow DOM
 */
export const sharedStyles = css`
    /* Host inherits theme variables */
    :host {
        color: var(--color-text-primary);
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    }

    /* Layout Utilities */
    .flex { display: flex; }
    .flex-col { flex-direction: column; }
    .flex-wrap { flex-wrap: wrap; }
    .items-center { align-items: center; }
    .items-start { align-items: flex-start; }
    .justify-between { justify-content: space-between; }
    .justify-center { justify-content: center; }
    .justify-end { justify-content: flex-end; }
    .gap-1 { gap: 0.25rem; }
    .gap-2 { gap: 0.5rem; }
    .gap-3 { gap: 0.75rem; }
    .gap-4 { gap: 1rem; }

    .grid { display: grid; }
    .grid-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }

    /* Spacing */
    .p-2 { padding: 0.5rem; }
    .p-3 { padding: 0.75rem; }
    .p-4 { padding: 1rem; }
    .px-2 { padding-left: 0.5rem; padding-right: 0.5rem; }
    .px-3 { padding-left: 0.75rem; padding-right: 0.75rem; }
    .px-4 { padding-left: 1rem; padding-right: 1rem; }
    .py-1 { padding-top: 0.25rem; padding-bottom: 0.25rem; }
    .py-2 { padding-top: 0.5rem; padding-bottom: 0.5rem; }
    .py-3 { padding-top: 0.75rem; padding-bottom: 0.75rem; }

    .m-0 { margin: 0; }
    .mt-1 { margin-top: 0.25rem; }
    .mt-2 { margin-top: 0.5rem; }
    .mt-4 { margin-top: 1rem; }
    .mb-1 { margin-bottom: 0.25rem; }
    .mb-2 { margin-bottom: 0.5rem; }
    .mb-3 { margin-bottom: 0.75rem; }
    .mb-4 { margin-bottom: 1rem; }

    /* Typography */
    .text-xs { font-size: 0.75rem; }
    .text-sm { font-size: 0.875rem; }
    .text-base { font-size: 1rem; }
    .text-lg { font-size: 1.125rem; }
    .text-xl { font-size: 1.25rem; }
    .text-2xl { font-size: 1.5rem; }

    .font-normal { font-weight: 400; }
    .font-semibold { font-weight: 600; }
    .font-bold { font-weight: 700; }
    .font-mono { font-family: 'Courier New', monospace; }

    .text-center { text-align: center; }
    .text-right { text-align: right; }

    .uppercase { text-transform: uppercase; }
    .capitalize { text-transform: capitalize; }

    /* Colors - Theme Aware */
    .text-white { color: #ffffff; }
    .text-gray-300 { color: #d1d5db; }
    .text-gray-400 { color: #9ca3af; }

    .text-success { color: var(--color-success); }
    .text-error { color: var(--color-error); }
    .text-warning { color: var(--color-warning); }
    .text-info { color: var(--color-info); }

    .text-green-400 { color: var(--color-success); }
    .text-blue-400 { color: var(--color-info); }
    .text-yellow-400 { color: var(--color-warning); }
    .text-red-400 { color: var(--color-error); }
    .text-purple-400 { color: var(--color-chemical-n); }

    .bg-gray-600 { background-color: #4b5563; }
    .bg-gray-700 { background-color: var(--color-bg-tertiary); }
    .bg-gray-800 { background-color: var(--color-bg-secondary); }
    .bg-gray-900 { background-color: var(--color-bg-primary); }

    .bg-green-600 { background-color: var(--color-brand-primary); }
    .bg-blue-600 { background-color: var(--color-info); }
    .bg-red-600 { background-color: var(--color-error); }
    .bg-yellow-600 { background-color: var(--color-warning); }

    /* Borders */
    .border { border: 1px solid var(--color-border); }
    .border-2 { border-width: 2px; }
    .border-gray-600 { border-color: #4b5563; }
    .border-gray-700 { border-color: var(--color-border); }

    .rounded { border-radius: 0.25rem; }
    .rounded-lg { border-radius: 0.5rem; }
    .rounded-full { border-radius: 9999px; }

    /* Shadows */
    .shadow { box-shadow: var(--shadow-sm); }
    .shadow-md { box-shadow: var(--shadow-md); }
    .shadow-lg { box-shadow: var(--shadow-lg); }

    /* Display */
    .hidden { display: none; }
    .block { display: block; }
    .inline-block { display: inline-block; }

    /* Width/Height */
    .w-full { width: 100%; }
    .min-w-0 { min-width: 0; }

    /* Button Base Styles */
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        border: none;
        font-size: 0.875rem;
    }

    .btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .btn-primary {
        background-color: var(--color-brand-primary);
        color: white;
    }
    .btn-primary:hover:not(:disabled) {
        background-color: var(--color-brand-secondary);
    }

    .btn-secondary {
        background-color: var(--color-bg-tertiary);
        color: var(--color-text-primary);
    }
    .btn-secondary:hover:not(:disabled) {
        background-color: var(--color-border);
    }

    .btn-danger {
        background-color: var(--color-error);
        color: white;
    }
    .btn-danger:hover:not(:disabled) {
        filter: brightness(0.9);
    }

    /* Card Styles */
    .card {
        background-color: var(--color-bg-secondary);
        border-radius: 0.5rem;
        border: 2px solid var(--color-border);
        box-shadow: var(--shadow-md);
    }

    .card-header {
        padding: 1rem;
        background-color: var(--color-bg-tertiary);
        border-bottom: 1px solid var(--color-border);
    }

    .card-body {
        padding: 1rem;
    }

    /* Transitions */
    .transition {
        transition: all 0.2s ease;
    }
`;

/**
 * Legacy Tailwind Style Manager (deprecated)
 * Kept for backward compatibility
 */
class TailwindStyleManager {
    constructor() {
        this.stylesheet = null;
    }

    async load() {
        if (!this.stylesheet) {
            this.stylesheet = new CSSStyleSheet();
        }
        return this.stylesheet;
    }
}

export const tailwindStyles = new TailwindStyleManager();
