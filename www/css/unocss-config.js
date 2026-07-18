/**
 * UnoCSS Runtime Configuration - Final Stable Version using Rules
 *
 * This version uses explicit rules to avoid theme merging conflicts with the default preset.
 */

// Helper to create simple CSS rules for our semantic colors
const createColorRules = (prefix, property, variable) => [
    new RegExp(`^${prefix}$`),
    () => ({ [property]: `var(${variable})` })
];

window.__unocss = {
    // Explicitly use the default preset
    presets: [
        () => window.unocss.presetUno(),
    ],
    // Define explicit rules for every semantic class
    rules: [
        // Backgrounds
        createColorRules('bg-base', 'background-color', '--color-bg-primary'),
        createColorRules('bg-surface', 'background-color', '--color-bg-secondary'),
        createColorRules('bg-surface-alt', 'background-color', '--color-bg-tertiary'),

        // Text
        createColorRules('text-main', 'color', '--color-text-primary'),
        createColorRules('text-dim', 'color', '--color-text-secondary'),
        createColorRules('text-muted', 'color', '--color-text-tertiary'),
        
        // Brand
        createColorRules('bg-brand', 'background-color', '--color-brand-primary'),
        createColorRules('text-brand', 'color', '--color-brand-primary'),
        createColorRules('border-brand', 'border-color', '--color-brand-primary'),
        createColorRules('accent-brand', 'accent-color', '--color-brand-primary'),

        createColorRules('bg-brand-alt', 'background-color', '--color-brand-secondary'),
        
        // Status
        createColorRules('bg-success', 'background-color', '--color-success'),
        createColorRules('text-success', 'color', '--color-success'),
        createColorRules('border-success', 'border-color', '--color-success'),

        createColorRules('bg-error', 'background-color', '--color-error'),
        createColorRules('text-error', 'color', '--color-error'),
        createColorRules('border-error', 'border-color', '--color-error'),

        createColorRules('bg-warning', 'background-color', '--color-warning'),
        createColorRules('text-warning', 'color', '--color-warning'),
        createColorRules('border-warning', 'border-color', '--color-warning'),

        createColorRules('bg-info', 'background-color', '--color-info'),
        createColorRules('text-info', 'color', '--color-info'),
        createColorRules('border-info', 'border-color', '--color-info'),
        createColorRules('accent-info', 'accent-color', '--color-info'),

        // Chemicals
        createColorRules('bg-chem-c', 'background-color', '--color-chemical-c'),
        createColorRules('text-chem-c', 'color', '--color-chemical-c'),
        createColorRules('border-chem-c', 'border-color', '--color-chemical-c'),

        createColorRules('bg-chem-n', 'background-color', '--color-chemical-n'),
        createColorRules('text-chem-n', 'color', '--color-chemical-n'),
        createColorRules('border-chem-n', 'border-color', '--color-chemical-n'),

        createColorRules('bg-chem-d', 'background-color', '--color-chemical-d'),
        createColorRules('text-chem-d', 'color', '--color-chemical-d'),
        createColorRules('border-chem-d', 'border-color', '--color-chemical-d'),

        createColorRules('bg-chem-q', 'background-color', '--color-chemical-q'),
        createColorRules('text-chem-q', 'color', '--color-chemical-q'),
        createColorRules('border-chem-q', 'border-color', '--color-chemical-q'),

        // Borders
        createColorRules('border-border-base', 'border-color', '--color-border'),
        createColorRules('border-border-subtle', 'border-color', '--color-border-light'),
    ],
    // Define shortcuts using the now-stable base classes
    shortcuts: {
        'card': 'bg-surface rounded-lg border-2 border-border-base shadow-md',
        'btn': 'inline-flex items-center justify-center px-4 py-2 rounded-lg font-semibold cursor-pointer transition-all duration-200',
        'btn-brand': 'btn bg-brand text-main hover:bg-brand-alt',
        'btn-success': 'btn bg-success text-main hover:brightness-110',
        'btn-danger': 'btn bg-error text-main hover:brightness-110',
    }
};