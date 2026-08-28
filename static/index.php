<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CNDQ Marketplace</title>

    <!-- Import map for Lit web components -->
    <script type="importmap">
    {
        "imports": {
            "lit": "https://cdn.jsdelivr.net/npm/lit@3/index.js",
            "lit/": "https://cdn.jsdelivr.net/npm/lit@3/",
            "lit-element": "https://cdn.jsdelivr.net/npm/lit-element@4/index.js",
            "lit-element/": "https://cdn.jsdelivr.net/npm/lit-element@4/",
            "@lit/reactive-element": "https://cdn.jsdelivr.net/npm/@lit/reactive-element@2/reactive-element.js",
            "@lit/reactive-element/": "https://cdn.jsdelivr.net/npm/@lit/reactive-element@2/",
            "lit-html": "https://cdn.jsdelivr.net/npm/lit-html@3/lit-html.js",
            "lit-html/": "https://cdn.jsdelivr.net/npm/lit-html@3/",
            "lit/directives/unsafe-html.js": "https://cdn.jsdelivr.net/npm/lit-html@3/directives/unsafe-html.js"
        }
    }
    </script>

    <!-- UnoCSS Runtime (JIT) - No build step needed! -->
    <script src="https://cdn.jsdelivr.net/npm/@unocss/runtime@0.58.5/uno.global.js"></script>
    <script src="./css/unocss-config.js"></script>

    <link rel="stylesheet" href="./css/styles.css">
    <style>
        /* Page-specific animations */
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .animate-spin {
            animation: spin 1s linear infinite;
        }
        @keyframes pulse-green {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .pulse-green {
            animation: pulse-green 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        /* Scrollbar */
        .scrollbar-thin::-webkit-scrollbar {
            width: 8px;
        }
        .scrollbar-thin::-webkit-scrollbar-track {
            background: var(--color-bg-secondary);
        }
        .scrollbar-thin::-webkit-scrollbar-thumb {
            background: var(--color-border);
            border-radius: 4px;
        }
        .scrollbar-thin::-webkit-scrollbar-thumb:hover {
            background: var(--color-border-light);
        }
        /* Focus styles */
        *:focus-visible {
            outline: 2px solid var(--color-brand-primary);
            outline-offset: 2px;
        }
        /* Loading spinner colors */
        .border-r-transparent {
            border-right-color: transparent;
        }
        .h-16 { height: 4rem; }
        .w-16 { width: 4rem; }
        .border-4 { border-width: 4px; }
        .border-solid { border-style: solid; }
        .min-h-screen { min-height: 100vh; }
    </style>
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen">

    <!-- ARIA Live Region for Screen Reader Announcements -->
    <div id="sr-announcer" 
         class="sr-only" 
         aria-live="polite" 
         aria-atomic="true" 
         style="position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border-width: 0;">
    </div>

    <!-- Skip Navigation Link -->
    <a href="#main-content" class="skip-link">Skip to main content</a>

    <!-- Production Phase Overlay -->
    <div id="production-overlay" class="hidden fixed inset-0 flex items-center justify-center z-[120]">
        <div class="text-center">
            <div class="cog-container mx-auto mb-8">
                <svg class="cog cog-1" viewBox="0 0 24 24"><path d="M19.44 12.99l-.01.02c.04-.33.08-.67.08-1.01 0-.34-.03-.66-.07-.99l.01.02 2.44-1.92-2.43-4.22-2.87.96.01.02c-.48-.42-1.03-.77-1.62-1.01l.02-.01L14.58 2h-4.85L9.32 4.86l.02.01c-.59.24-1.14.59-1.62 1.01l.01-.02-2.87-.96-2.43 4.22 2.44 1.92-.01-.02c-.04.33-.07.65-.07.99 0 .34.03.68.08 1.01l-.01-.02-2.44 1.92 2.43 4.22 2.87-.96-.01-.02c.48.42 1.03.77 1.62 1.01l-.02.01L9.73 22h4.85l.41-2.86-.02-.01c.59-.24 1.14-.59 1.62-1.01l-.01.02 2.87.96 2.43-4.22-2.44-1.9zm-7.44 3c-2.21 0-4-1.79-4-4s1.79-4 4-4 4 1.79 4 4-1.79 4-4 4z"/></svg>
                <svg class="cog cog-2" viewBox="0 0 24 24"><path d="M19.44 12.99l-.01.02c.04-.33.08-.67.08-1.01 0-.34-.03-.66-.07-.99l.01.02 2.44-1.92-2.43-4.22-2.87.96.01.02c-.48-.42-1.03-.77-1.62-1.01l.02-.01L14.58 2h-4.85L9.32 4.86l.02.01c-.59.24-1.14.59-1.62 1.01l.01-.02-2.87-.96-2.43 4.22 2.44 1.92-.01-.02c-.04.33-.07.65-.07.99 0 .34.03.68.08 1.01l-.01-.02-2.44 1.92 2.43 4.22 2.87-.96-.01-.02c.48.42 1.03.77 1.62 1.01l-.02.01L9.73 22h4.85l.41-2.86-.02-.01c.59-.24 1.14-.59 1.62-1.01l-.01.02 2.87.96 2.43-4.22-2.44-1.9zm-7.44 3c-2.21 0-4-1.79-4-4s1.79-4 4-4 4 1.79 4 4-1.79 4-4 4z"/></svg>
                <svg class="cog cog-3" viewBox="0 0 24 24"><path d="M19.44 12.99l-.01.02c.04-.33.08-.67.08-1.01 0-.34-.03-.66-.07-.99l.01.02 2.44-1.92-2.43-4.22-2.87.96.01.02c-.48-.42-1.03-.77-1.62-1.01l.02-.01L14.58 2h-4.85L9.32 4.86l.02.01c-.59.24-1.14.59-1.62 1.01l.01-.02-2.87-.96-2.43 4.22 2.44 1.92-.01-.02c-.04.33-.07.65-.07.99 0 .34.03.68.08 1.01l-.01-.02-2.44 1.92 2.43 4.22 2.87-.96-.01-.02c.48.42 1.03.77 1.62 1.01l-.02.01L9.73 22h4.85l.41-2.86-.02-.01c.59-.24 1.14-.59 1.62-1.01l-.01.02 2.87.96 2.43-4.22-2.44-1.9zm-7.44 3c-2.21 0-4-1.79-4-4s1.79-4 4-4 4 1.79 4 4-1.79 4-4 4z"/></svg>
            </div>
            <h2 class="text-4xl font-bold text-green-500 font-mono animate-pulse uppercase tracking-tighter">Automatic Production Running</h2>
            <p class="text-gray-400 mt-4 text-xl">Linear Programming solvers are optimizing your profit...</p>
        </div>
    </div>

    <!-- Market Closed Overlay -->
    <div id="market-closed-overlay" class="hidden fixed inset-0 bg-gray-900 z-[130] flex flex-col items-center justify-center p-4">
        <div class="text-center max-w-2xl">
            <h1 class="text-6xl md:text-8xl font-black text-red-600 mb-6 tracking-tighter uppercase">Market Closed</h1>
            <p class="text-2xl md:text-3xl text-gray-300 font-light mb-8">Trading is currently suspended.</p>
            <div class="w-24 h-1 bg-red-600 mx-auto mb-8"></div>
            <p class="text-gray-400 mb-6">Waiting for the instructor to start the market...</p>
            <p class="text-gray-500 text-sm mb-8">💡 While you wait: review your shadow prices and production strategy in the <strong class="text-gray-300">Production Guide</strong> (top-right corner once the market opens).</p>
        </div>
    </div>

    <!-- Game Over Overlay -->
    <div id="game-over-overlay" class="hidden fixed inset-0 bg-gray-900 z-[150]">
        <div class="flex flex-col h-full">
        <!-- Header -->
        <header class="bg-gray-800 border-b-2 border-purple-500 p-6 flex items-center justify-between shadow-2xl">
            <h2 class="text-4xl font-black text-purple-500 tracking-tighter uppercase">Simulation Complete</h2>
            <button id="restart-game-btn" style="display:none" class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-8 rounded-xl transition shadow-lg flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                Restart Simulation
            </button>
        </header>

        <!-- Tabs -->
        <div class="bg-gray-800 flex justify-center border-b border-gray-700 overflow-x-auto">
            <button id="tab-leaderboard" class="px-6 md:px-8 py-4 font-black uppercase tracking-widest border-b-4 border-purple-500 text-purple-200 transition-all whitespace-nowrap">Scoreboard</button>
            <button id="tab-history" class="px-6 md:px-8 py-4 font-black uppercase tracking-widest border-b-4 border-transparent text-gray-300 hover:text-white transition-all whitespace-nowrap">Market History</button>
            <button id="tab-team-sheet" class="px-6 md:px-8 py-4 font-black uppercase tracking-widest border-b-4 border-transparent text-gray-300 hover:text-white transition-all whitespace-nowrap">Team Sheet</button>
            <button id="tab-answer-report" class="px-6 md:px-8 py-4 font-black uppercase tracking-widest border-b-4 border-transparent text-gray-300 hover:text-white transition-all whitespace-nowrap">Answer Report</button>
            <button id="tab-sensitivity-report" class="px-6 md:px-8 py-4 font-black uppercase tracking-widest border-b-4 border-transparent text-gray-300 hover:text-white transition-all whitespace-nowrap">Sensitivity Report</button>
        </div>

        <!-- Content Area -->
        <main class="flex-1 overflow-y-auto p-8 container mx-auto max-w-4xl">
            <!-- Leaderboard Content -->
            <div id="content-leaderboard">
                <div class="bg-gray-800 rounded-2xl border border-gray-700 shadow-2xl p-8">
                    <div class="flex justify-between items-center mb-8">
                        <div></div>
                        <h3 class="text-2xl font-bold text-gray-300">Final Performance Rankings</h3>
                        <button id="export-leaderboard-btn" class="text-gray-300 hover:text-white text-sm flex items-center gap-1" title="Download CSV">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                            CSV
                        </button>
                    </div>
                    <div id="final-leaderboard-container">
                        <!-- Rankings will be injected here -->
                        <div class="animate-pulse space-y-4">
                            <div class="h-16 bg-gray-700 rounded-lg"></div>
                            <div class="h-16 bg-gray-700 rounded-lg"></div>
                            <div class="h-16 bg-gray-700 rounded-lg"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- History Content (Global Transaction History) -->
            <div id="content-history" class="hidden">
                <div class="bg-gray-800 rounded-2xl border border-gray-700 shadow-2xl p-6">
                    <div class="flex justify-between items-center mb-4">
                        <div class="text-gray-400 text-sm">← Scroll for all columns →</div>
                        <div class="text-center">
                            <h3 class="text-2xl font-bold text-gray-300">Complete Market History</h3>
                            <p class="text-gray-400 text-sm">All trades with full inventory audit trail</p>
                        </div>
                        <button id="export-global-history-btn" class="text-gray-300 hover:text-white text-sm flex items-center gap-1" title="Download CSV">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                            CSV
                        </button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-max" id="global-history-table">
                            <thead class="text-gray-400 text-xs border-b border-gray-600 sticky top-0 bg-gray-800">
                                <tr>
                                    <th class="py-3 px-2 font-semibold text-left whitespace-nowrap">Time</th>
                                    <th class="py-3 px-2 font-semibold text-left whitespace-nowrap">Chem</th>
                                    <th class="py-3 px-2 font-semibold text-right whitespace-nowrap">Qty</th>
                                    <th class="py-3 px-2 font-semibold text-right whitespace-nowrap">Price</th>
                                    <th class="py-3 px-2 font-semibold text-right whitespace-nowrap">Total</th>
                                    <th class="py-3 px-2 font-semibold text-left whitespace-nowrap border-l border-gray-600">Seller</th>
                                    <th class="py-3 px-2 font-semibold text-right whitespace-nowrap">Before</th>
                                    <th class="py-3 px-2 font-semibold text-right whitespace-nowrap">After</th>
                                    <th class="py-3 px-2 font-semibold text-left whitespace-nowrap border-l border-gray-600">Buyer</th>
                                    <th class="py-3 px-2 font-semibold text-right whitespace-nowrap">Before</th>
                                    <th class="py-3 px-2 font-semibold text-right whitespace-nowrap">After</th>
                                    <th class="py-3 px-2 font-semibold text-center whitespace-nowrap border-l border-gray-600">Heat</th>
                                </tr>
                            </thead>
                            <tbody id="global-history-table-body" class="text-gray-300 text-sm divide-y divide-gray-700">
                                <!-- Rows injected via JS -->
                            </tbody>
                        </table>
                        <p id="global-history-empty-msg" class="hidden text-center text-gray-500 py-8">No transactions yet.</p>
                        <p id="global-history-loading" class="text-center text-gray-500 py-8">Loading market history...</p>
                    </div>
                </div>
            </div>

            <!-- Team Sheet Content -->
            <div id="content-team-sheet" class="hidden">
                <report-viewer id="report-viewer-team-sheet" embedded="true" hide-tabs="true" active-tab="team-sheet"></report-viewer>
            </div>

            <!-- Answer Report Content -->
            <div id="content-answer-report" class="hidden">
                <report-viewer id="report-viewer-answer-report" embedded="true" hide-tabs="true" active-tab="answer"></report-viewer>
            </div>

            <!-- Sensitivity Report Content -->
            <div id="content-sensitivity-report" class="hidden">
                <report-viewer id="report-viewer-sensitivity-report" embedded="true" hide-tabs="true" active-tab="sensitivity"></report-viewer>
            </div>
        </main>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loading-overlay" class="fixed inset-0 bg-gray-900 bg-opacity-95 flex items-center justify-center z-50">
        <div class="text-center">
            <div class="inline-block h-16 w-16 animate-spin rounded-full border-4 border-solid border-green-500 border-r-transparent"></div>
            <p class="mt-4 text-green-500 font-mono text-lg">Loading Marketplace...</p>
        </div>
    </div>

    <!-- Trading Tutorial Modal -->
    <div id="tutorial-modal" role="dialog" aria-modal="true" aria-labelledby="tutorial-heading" class="hidden fixed inset-0 bg-black bg-opacity-80 flex items-center justify-center z-[120] p-4">
        <div class="bg-gray-800 rounded-2xl w-full max-w-2xl border-2 border-purple-500 shadow-2xl overflow-hidden">
            <!-- Header -->
            <div class="bg-purple-900 p-4 flex justify-between items-center">
                <h2 id="tutorial-heading" class="text-xl font-bold text-white flex items-center gap-2">
                    <span class="text-2xl">📊</span> Trading Strategy Guide
                </h2>
                <div class="flex items-center gap-3">
                    <div class="text-sm text-purple-300">
                        Step <span id="tutorial-step-num">1</span> of <span id="tutorial-step-total">5</span>
                    </div>
                    <button id="tutorial-close" class="text-purple-300 hover:text-white transition p-1" aria-label="Close tutorial">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Content - dynamically populated -->
            <div id="tutorial-content" class="p-6 min-h-[300px]">
                <!-- Steps injected by JS -->
            </div>

            <!-- Footer with navigation -->
            <div class="bg-gray-900 p-4 flex justify-end items-center">
                <div class="flex gap-3">
                    <button id="tutorial-prev" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded font-semibold transition disabled:opacity-50 disabled:cursor-not-allowed">
                        Back
                    </button>
                    <button id="tutorial-deep-dive" class="hidden px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded font-semibold transition text-sm">
                        Deep Dive →
                    </button>
                    <button id="tutorial-next" class="px-6 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded font-semibold transition">
                        Next
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirmation Dialog -->
    <div id="confirm-dialog" role="alertdialog" aria-modal="true" aria-labelledby="confirm-title" aria-describedby="confirm-message" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[200] p-4">
        <div class="bg-gray-800 rounded-lg p-4 md:p-6 max-w-md w-full border border-gray-700 shadow-xl">
            <h3 class="text-xl font-bold mb-4 text-white" id="confirm-title">Confirm Action</h3>
            <p class="text-gray-300 mb-6 whitespace-pre-line" id="confirm-message"></p>
            <div class="flex gap-3 justify-end">
                <button id="confirm-cancel" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded font-semibold transition">
                    Cancel
                </button>
                <button id="confirm-ok" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded font-semibold transition">
                    Confirm
                </button>
            </div>
        </div>
    </div>

    <!-- Production Results Modal -->
    <div id="production-results-modal" class="hidden fixed inset-0 bg-gray-900 z-[140] flex items-center justify-center p-0">
        <div class="bg-gray-800 w-full h-full border-none shadow-2xl overflow-y-auto flex flex-col">
            <!-- Header (Sticky) -->
            <!-- Header (Sticky) -->
            <div class="bg-gray-900 border-b-2 border-green-500 p-6 flex items-center justify-between sticky top-0 z-10">
                <h3 class="text-3xl font-bold text-green-500 flex items-center gap-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span id="prod-result-title">Production Results</span>
                    <span id="prod-result-session" class="hidden"></span>
                </h3>
                <button id="prod-result-close" class="text-gray-300 hover:text-white text-4xl font-bold transition-colors" aria-label="Close">&times;</button>
            </div>

            <div class="flex-1 container mx-auto max-w-4xl py-12 px-6">
                <!-- Production In Progress State (Inside fullscreen for consistency if triggered) -->
                <div id="production-in-progress" class="hidden text-center py-20">
                    <div class="cog-container mx-auto mb-8 scale-150">
                        <svg class="cog cog-1" viewBox="0 0 24 24"><path d="M19.44 12.99l-.01.02c.04-.33.08-.67.08-1.01 0-.34-.03-.66-.07-.99l.01.02 2.44-1.92-2.43-4.22-2.87.96.01.02c-.48-.42-1.03-.77-1.62-1.01l.02-.01L14.58 2h-4.85L9.32 4.86l.02.01c-.59.24-1.14.59-1.62 1.01l.01-.02-2.87-.96-2.43 4.22 2.44 1.92-.01-.02c-.04.33-.07.65-.07.99 0 .34.03.68.08 1.01l-.01-.02-2.44 1.92 2.43 4.22 2.87-.96-.01-.02c.48.42 1.03.77 1.62 1.01l-.02.01L9.73 22h4.85l.41-2.86-.02-.01c.59-.24 1.14-.59 1.62-1.01l-.01.02 2.87.96 2.43-4.22-2.44-1.9zm-7.44 3c-2.21 0-4-1.79-4-4s1.79-4 4-4 4 1.79 4 4-1.79 4-4 4z"/></svg>
                        <svg class="cog cog-2" viewBox="0 0 24 24"><path d="M19.44 12.99l-.01.02c.04-.33.08-.67.08-1.01 0-.34-.03-.66-.07-.99l.01.02 2.44-1.92-2.43-4.22-2.87.96.01.02c-.48-.42-1.03-.77-1.62-1.01l.02-.01L14.58 2h-4.85L9.32 4.86l.02.01c-.59.24-1.14.59-1.62 1.01l.01-.02-2.87-.96-2.43 4.22 2.44 1.92-.01-.02c-.04.33-.07.65-.07.99 0 .34.03.68.08 1.01l-.01-.02-2.44 1.92 2.43 4.22 2.87-.96-.01-.02c.48.42 1.03.77 1.62 1.01l-.02.01L9.73 22h4.85l.41-2.86-.02-.01c.59-.24 1.14-.59 1.62-1.01l-.01.02 2.87.96 2.43-4.22-2.44-1.9zm-7.44 3c-2.21 0-4-1.79-4-4s1.79-4 4-4 4 1.79 4 4-1.79 4-4 4z"/></svg>
                        <svg class="cog cog-3" viewBox="0 0 24 24"><path d="M19.44 12.99l-.01.02c.04-.33.08-.67.08-1.01 0-.34-.03-.66-.07-.99l.01.02 2.44-1.92-2.43-4.22-2.87.96.01.02c-.48-.42-1.03-.77-1.62-1.01l.02-.01L14.58 2h-4.85L9.32 4.86l.02.01c-.59.24-1.14.59-1.62 1.01l.01-.02-2.87-.96-2.43 4.22 2.44 1.92-.01-.02c-.04.33-.07.65-.07.99 0 .34.03.68.08 1.01l-.01-.02-2.44 1.92 2.43 4.22 2.87-.96-.01-.02c.48.42 1.03.77 1.62 1.01l-.02.01L9.73 22h4.85l.41-2.86-.02-.01c.59-.24 1.14-.59 1.62-1.01l-.01.02 2.87.96 2.43-4.22-2.44-1.9zm-7.44 3c-2.21 0-4-1.79-4-4s1.79-4 4-4 4 1.79 4 4-1.79 4-4 4z"/></svg>
                    </div>
                    <h2 class="text-5xl font-bold text-green-500 font-mono animate-pulse uppercase tracking-tight">Production Running</h2>
                    <p class="text-gray-300 mt-6 text-2xl">Linear programming solvers optimizing your profit...</p>
                </div>

                <!-- Production Complete State -->
                <div id="production-complete" class="hidden">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-12">
                        <!-- Left Column: Primary Stats -->
                        <div class="space-y-8">
                            <!-- Revenue -->
                            <div class="bg-tertiary rounded-2xl p-8 border-4 border-success shadow-xl">
                                <h4 class="text-xl font-semibold text-success mb-4 uppercase tracking-widest">Potential Revenue</h4>
                                <div class="text-6xl font-black text-white"><span id="prod-result-revenue">$0</span></div>
                                <div class="mt-4 text-green-300 flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                                    <span id="revenue-note">Initial inventory potential</span>
                                </div>
                            </div>

                            <!-- Production Output -->
                            <div class="bg-gray-700 rounded-2xl p-8 border border-gray-600 shadow-lg">
                                <h4 class="text-xl font-semibold text-white mb-6 uppercase tracking-widest">Optimal Product Mix</h4>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                    <div class="bg-tertiary p-6 rounded-xl border border-info">
                                        <div class="text-info text-sm font-bold mb-2 uppercase">De-Icer</div>
                                        <div class="text-4xl font-black text-white"><span id="prod-result-deicer">0</span> <span class="text-lg font-normal opacity-70">gal</span></div>
                                    </div>
                                    <div class="bg-tertiary p-6 rounded-xl border border-purple-500">
                                        <div class="text-purple-400 text-sm font-bold mb-2 uppercase">Solvent</div>
                                        <div class="text-4xl font-black text-white"><span id="prod-result-solvent">0</span> <span class="text-lg font-normal opacity-70">gal</span></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Details & Inventory -->
                        <div class="space-y-8">
                            <!-- Chemicals Consumed -->
                            <div id="resources-section" class="bg-gray-700 rounded-2xl p-8 border border-gray-600 shadow-lg">
                                <h4 class="text-xl font-semibold text-white mb-6 uppercase tracking-widest">Resources Required</h4>
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="bg-blue-600 bg-opacity-20 p-4 rounded-lg flex justify-between items-center">
                                        <span class="text-blue-400 font-bold">Chem C</span>
                                        <span class="text-xl font-mono font-bold text-white"><span id="prod-result-chem-C">0</span></span>
                                    </div>
                                    <div class="bg-purple-600 bg-opacity-20 p-4 rounded-lg flex justify-between items-center">
                                        <span class="text-purple-400 font-bold">Chem N</span>
                                        <span class="text-xl font-mono font-bold text-white"><span id="prod-result-chem-N">0</span></span>
                                    </div>
                                    <div class="bg-yellow-600 bg-opacity-20 p-4 rounded-lg flex justify-between items-center">
                                        <span class="text-yellow-400 font-bold">Chem D</span>
                                        <span class="text-xl font-mono font-bold text-white"><span id="prod-result-chem-D">0</span></span>
                                    </div>
                                    <div class="bg-red-600 bg-opacity-20 p-4 rounded-lg flex justify-between items-center">
                                        <span class="text-red-400 font-bold">Chem Q</span>
                                        <span class="text-xl font-mono font-bold text-white"><span id="prod-result-chem-Q">0</span></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Sensitivity & Constraints Analysis -->
                            <div id="sensitivity-section" class="bg-gray-800 rounded-2xl p-8 border border-gray-700 shadow-lg">
                                <h4 class="text-xl font-semibold text-white mb-6 uppercase tracking-widest">Optimization Analysis</h4>
                                
                                <div class="mb-6">
                                    <h5 class="text-sm font-bold text-gray-400 mb-3 uppercase">Constraint Status (Bottlenecks)</h5>
                                    <div class="space-y-2 text-sm" id="prod-constraints-list">
                                        <!-- Injected by JS -->
                                    </div>
                                </div>

                                <div>
                                    <h5 class="text-sm font-bold text-gray-400 mb-3 uppercase">Shadow Prices (Marginal Value)</h5>
                                    <div class="grid grid-cols-2 gap-3" id="prod-shadow-prices-list">
                                        <!-- Injected by JS -->
                                    </div>
                                </div>
                            </div>

                            <!-- Current Status -->
                            <div class="bg-gray-900 bg-opacity-50 rounded-2xl p-8 border border-gray-700 shadow-lg">
                                <h4 class="text-xl font-semibold text-white mb-6 uppercase tracking-widest">Account Balance</h4>
                                <div class="grid grid-cols-1 gap-6">
                                    <div class="flex justify-between items-center border-b border-gray-800 pb-4">
                                        <span class="text-gray-400">Total Value</span>
                                        <span class="text-3xl font-bold text-green-500"><span id="prod-result-current-funds">$0</span></span>
                                    </div>
                                    <div>
                                        <span class="text-gray-400 block mb-4">Inventory</span>
                                        <div class="grid grid-cols-4 gap-2 text-center">
                                            <div class="bg-gray-800 p-2 rounded">
                                                <div class="text-[10px] text-blue-400 font-bold">C</div>
                                                <div class="text-sm font-bold"><span id="prod-result-inv-C">0</span></div>
                                            </div>
                                            <div class="bg-gray-800 p-2 rounded">
                                                <div class="text-[10px] text-purple-400 font-bold">N</div>
                                                <div class="text-sm font-bold"><span id="prod-result-inv-N">0</span></div>
                                            </div>
                                            <div class="bg-gray-800 p-2 rounded">
                                                <div class="text-[10px] text-yellow-400 font-bold">D</div>
                                                <div class="text-sm font-bold"><span id="prod-result-inv-D">0</span></div>
                                            </div>
                                            <div class="bg-gray-800 p-2 rounded">
                                                <div class="text-[10px] text-red-400 font-bold">Q</div>
                                                <div class="text-sm font-bold"><span id="prod-result-inv-Q">0</span></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- LP Generalization Insight (Collapsible) -->
                    <div id="prod-insight-section" class="mb-8 max-w-2xl mx-auto">
                        <button id="prod-insight-toggle" class="w-full flex items-center justify-between bg-indigo-900/30 hover:bg-indigo-900/50 border border-indigo-500/50 rounded-xl p-4 transition group">
                            <div class="flex items-center gap-3">
                                <span class="text-2xl">🎓</span>
                                <span class="text-indigo-300 font-semibold">The Bigger Picture: Why This Matters Beyond CNDQ</span>
                            </div>
                            <svg id="prod-insight-chevron" class="w-6 h-6 text-indigo-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div id="prod-insight-content" class="hidden mt-4 bg-gray-800 border border-indigo-500/30 rounded-xl p-6 space-y-4">
                            <p class="text-gray-300">
                                <strong class="text-yellow-400">Here's the powerful insight:</strong> De-Icer and Solvent are just labels.
                                What really matters are the <strong class="text-purple-400">recipes</strong> - the ratios of inputs each product requires.
                            </p>

                            <div class="bg-gray-700 rounded-lg p-4 font-mono text-sm">
                                <div class="text-blue-400 mb-2">Product A = 0.5C + 0.3N + 0.2D</div>
                                <div class="text-purple-400">Product B = 0.25N + 0.35D + 0.4Q</div>
                            </div>

                            <p class="text-gray-300">
                                This same Linear Programming model works for <strong class="text-green-400">ANY two-product scenario</strong> where products share inputs:
                            </p>

                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                                <div class="bg-gray-900 rounded-lg p-3 text-center">
                                    <div class="text-yellow-400 font-bold">🥐 Bakery</div>
                                    <div class="text-gray-500 text-xs mt-1">Bread & Pastries</div>
                                </div>
                                <div class="bg-gray-900 rounded-lg p-3 text-center">
                                    <div class="text-yellow-400 font-bold">⛽ Refinery</div>
                                    <div class="text-gray-500 text-xs mt-1">Gas & Diesel</div>
                                </div>
                                <div class="bg-gray-900 rounded-lg p-3 text-center">
                                    <div class="text-yellow-400 font-bold">🪑 Furniture</div>
                                    <div class="text-gray-500 text-xs mt-1">Tables & Chairs</div>
                                </div>
                                <div class="bg-gray-900 rounded-lg p-3 text-center">
                                    <div class="text-yellow-400 font-bold">💻 Software</div>
                                    <div class="text-gray-500 text-xs mt-1">Products A & B</div>
                                </div>
                            </div>

                            <div class="bg-green-900/30 border border-green-500 rounded-lg p-4">
                                <p class="text-green-400 text-sm">
                                    <strong>The Takeaway:</strong> Shadow prices tell you the value of acquiring more of each input.
                                    Binding constraints identify your bottlenecks. These concepts apply universally -
                                    from manufacturing to finance to resource allocation. Master them here, apply them everywhere!
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Continue Button -->
                    <div class="max-w-md mx-auto">
                        <button id="prod-result-continue" class="w-full bg-green-600 hover:bg-green-700 text-white font-black py-6 px-8 rounded-2xl transition-all hover:scale-105 active:scale-95 shadow-2xl text-2xl uppercase tracking-tighter">
                            Start Trading →
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notifications Container - Horizontal bottom bar -->
    <div id="toast-container" class="fixed bottom-0 left-0 right-0 z-[9999] flex flex-row gap-2 p-2 pointer-events-none justify-center flex-wrap" role="region" aria-live="polite" aria-atomic="false" aria-label="Notifications"></div>

    <!-- Main App Container -->
    <div id="app">

        <!-- Header -->
        <header class="bg-gray-800 border-b-2 border-green-500 shadow-lg">
            <div class="container mx-auto px-4 py-3 md:py-4">
                <div class="flex items-center justify-between flex-wrap gap-3 md:gap-4">
                    <div>
                        <h1 class="text-xl md:text-2xl lg:text-3xl font-bold text-green-500 font-mono">CNDQ MARKETPLACE</h1>
                        <p class="text-xs md:text-sm text-gray-300 mt-1">Team: <span id="team-name" class="text-white font-semibold"></span></p>
                    </div>

                    <div role="toolbar" aria-label="Quick actions" class="flex items-center gap-2 md:gap-4">

                        <!-- Notifications -->
                        <notification-manager id="notification-manager"></notification-manager>

                        <!-- Leaderboard -->
                        <button id="leaderboard-btn" class="bg-yellow-600 hover:bg-yellow-700 p-2 md:p-3 rounded-lg transition" aria-label="View leaderboard" title="View leaderboard">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 md:w-6 md:h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                        </button>

                        <!-- Production Guide -->
                        <button id="production-guide-btn" class="bg-blue-600 hover:bg-blue-700 p-2 md:p-3 rounded-lg transition" aria-label="View production formulas" title="View production formulas">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 md:w-6 md:h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                            </svg>
                        </button>

                        <!-- Help/Tutorial -->
                        <button id="help-btn" class="bg-purple-600 hover:bg-purple-700 p-2 md:p-3 rounded-lg transition" aria-label="Trading Guide" title="Trading Guide">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 md:w-6 md:h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </button>

                        <!-- Settings -->
                        <button id="settings-btn" class="bg-gray-700 hover:bg-gray-600 p-2 md:p-3 rounded-lg transition" aria-label="Open settings" title="Open settings">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 md:w-6 md:h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Shadow Prices & Recalculate -->
                <div id="shadow-price-bar" class="mt-3 md:mt-4 bg-gray-700 rounded-lg p-3 md:p-4 border border-gray-600">
                    <div class="flex items-center justify-between flex-wrap gap-3 md:gap-4">
                        <div class="flex items-center gap-3 md:gap-6 flex-wrap w-full lg:w-auto">
                            <div class="text-xs md:text-sm w-full lg:w-auto">
                                <span class="text-gray-200 font-semibold uppercase tracking-wider">Strategy Insight</span>
                                <button type="button" class="ml-1 text-gray-400 hover:text-gray-200 text-xs align-middle leading-none"
                                    title="Shadow prices show how much your profit increases if you gain 1 more gallon of each chemical. Higher = more valuable to your production. Recalculate after trades to keep them accurate."
                                    aria-label="What are shadow prices?">
                                    <span aria-hidden="true" class="inline-block w-4 h-4 rounded-full bg-gray-600 text-center leading-4 text-[10px] font-bold">?</span>
                                </button>
                                <span id="staleness-indicator" class="ml-2 text-xs"></span>
                            </div>
                            <div class="text-sm text-gray-400 italic">
                                Your shadow prices and bottlenecks are shown on each chemical card below.
                            </div>
                        </div>
                    </div>
                    <div id="staleness-warning" class="hidden mt-3 p-3 rounded text-sm"></div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main id="main-content" class="container mx-auto px-4 py-4 md:py-6" role="main">

            <!-- Screen Reader Game Summary (Hidden) -->
            <div id="sr-game-summary" class="sr-only" aria-live="polite">
                Loading game state...
            </div>

            <!-- Session Status Bar -->
            <div class="bg-gray-800 border-l-4 border-purple-500 p-4 mb-6 rounded shadow-lg flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div id="phase-badge" class="bg-green-900/30 px-3 py-1 rounded border border-green-500/50">
                        <span id="current-phase" role="status" aria-live="polite" class="text-xs text-green-400 uppercase font-bold">TRADING</span>
                    </div>
                    <div id="improvement-badge" class="hidden bg-blue-900/30 px-3 py-1 rounded border border-blue-500/50">
                        <span class="text-xs text-blue-400 uppercase font-bold">Growth</span>
                        <span id="fin-improvement" class="text-sm font-mono font-bold ml-1">+0.0%</span>
                    </div>
                </div>
                
                <div class="flex items-center gap-3">
                    <span class="text-gray-400 text-xs uppercase font-bold">Time Remaining</span>
                    <div class="bg-gray-900 px-4 py-2 rounded font-mono text-xl text-yellow-400 border border-gray-700 w-fit min-w-[6rem] text-center" id="session-timer" role="timer" aria-label="Session time remaining">
                        00:00
                    </div>
                </div>
            </div>

            <!-- Financial Summary Panel -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6" role="region" aria-label="Financial Summary">
                <div class="bg-gray-800 p-4 rounded-lg border border-gray-700 shadow flex flex-col items-center">
                    <span class="text-xs text-gray-400 uppercase font-bold mb-1">Production Rev</span>
                    <span class="text-xl font-mono text-blue-400" id="fin-production-rev" aria-live="polite">$0.00</span>
                    <span class="text-[10px] text-gray-400 mt-1" id="fin-production-mix"></span>
                    <span class="text-[10px] text-gray-400 uppercase" id="fin-production-delta"></span>
                </div>
                <div class="bg-gray-800 p-4 rounded-lg border border-gray-700 shadow flex flex-col items-center">
                    <span class="text-xs text-gray-400 uppercase font-bold mb-1">Sales Rev</span>
                    <span class="text-xl font-mono text-green-400" id="fin-sales-rev" aria-live="polite">$0.00</span>
                    <span id="fin-sales-delta"></span>
                </div>
                <div class="bg-gray-800 p-4 rounded-lg border border-gray-700 shadow flex flex-col items-center">
                    <span class="text-xs text-gray-400 uppercase font-bold mb-1">Purchase Cost</span>
                    <span class="text-xl font-mono text-red-400" id="fin-purchase-cost" aria-live="polite">$0.00</span>
                    <span id="fin-purchase-delta"></span>
                </div>
                <div class="bg-gray-800 p-4 rounded-lg border border-gray-700 shadow flex flex-col items-center relative overflow-hidden">
                    <div class="absolute inset-0 bg-green-600 opacity-10"></div>
                    <span class="text-xs text-green-300 uppercase font-bold mb-1 z-10">Net Profit</span>
                    <span class="text-2xl font-mono font-bold text-white z-10" id="fin-net-profit" aria-live="polite">$0.00</span>
                    <span class="text-[10px] text-gray-400 uppercase mt-1 z-10" id="fin-total-delta"></span>
                    <span class="text-[10px] text-yellow-400 font-bold mt-1 z-10" id="fin-rank" aria-live="polite"></span>
                </div>
            </div>
            
            <!-- Actions bar above chemical cards -->
            <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
                <div class="flex items-center gap-2">
                    <h3 class="text-xl font-bold text-white uppercase tracking-tight">Chemical Inventory</h3>
                    <button id="recalc-shadow-btn" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition shadow-lg flex items-center justify-center gap-2 ml-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        Recalculate Shadow Prices
                    </button>
                </div>
                <button id="view-history-btn" class="bg-gray-700 hover:bg-gray-600 text-white px-3 py-2 rounded shadow flex items-center gap-1 transition text-sm" aria-label="Open your personal transaction history">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    View Transaction History
                </button>
            </div>

            <!-- 4-Column Chemical Grid -->
            <div id="chemical-cards-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4 mb-4 md:mb-6" role="region" aria-label="Chemical Marketplace">
                <chemical-card chemical="C"></chemical-card>
                <chemical-card chemical="N"></chemical-card>
                <chemical-card chemical="D"></chemical-card>
                <chemical-card chemical="Q"></chemical-card>
            </div>

            <!-- My Negotiations -->
            <div class="bg-gray-800 rounded-lg p-4 md:p-6 border-2 border-gray-700 shadow-xl" role="region" aria-label="My Active Negotiations and Buy Requests">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-2xl font-bold text-green-500 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                        </svg>
                        MY NEGOTIATIONS
                    </h3>
                    <button id="view-all-negotiations-btn" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded font-semibold transition flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                        </svg>
                        View All
                    </button>
                </div>
                <div id="my-negotiations" class="space-y-3">
                    <p class="text-gray-300 text-center py-8">You have no active negotiations</p>
                </div>
            </div>
        </main>

    </div>

    <!-- Buy Request Detail Modal -->
    <div id="offer-modal" class="hidden fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 p-4 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="offer-modal-title">
        <div class="bg-gray-800 rounded-lg p-4 md:p-6 w-full max-w-md border-2 border-blue-500 shadow-2xl my-auto">
            <h3 class="text-xl md:text-2xl font-bold mb-4 text-blue-400" id="offer-modal-title">📋 Buy Request</h3>
            <p class="text-sm text-gray-300 mb-4">You have signalled interest in buying this chemical. Sellers will contact you to negotiate price and quantity.</p>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold mb-2 text-gray-300">Chemical</label>
                    <input type="text" id="offer-chemical" readonly class="w-full bg-gray-700 border border-gray-600 rounded px-4 py-3 text-white font-bold text-xl">
                </div>
                <p class="text-xs text-gray-400">💡 Shadow Price: <span class="text-green-400 font-semibold"><span id="offer-shadow-hint">$0</span></span> per gallon (your current value for this chemical)</p>
            </div>

            <!-- Hidden inputs kept for JS compatibility -->
            <input type="hidden" id="offer-quantity" value="0">
            <input type="hidden" id="offer-quantity-slider" value="0">
            <input type="hidden" id="offer-price" value="0">
            <span id="offer-sensitivity-warning" class="hidden"></span>
            <span id="offer-total" class="hidden"></span>
            <span id="offer-profit-delta" class="hidden"></span>
            <span id="offer-current-funds" class="hidden"></span>

            <div class="flex gap-3 mt-6">
                <button id="offer-cancel-btn" class="flex-1 bg-gray-600 hover:bg-gray-700 text-white py-3 rounded font-semibold transition">Close</button>
            </div>
        </div>
    </div>

    <!-- Respond to Buy Request Modal -->
    <div id="respond-modal" class="hidden fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 p-4 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="respond-modal-title">
        <div class="bg-gray-800 rounded-lg p-4 md:p-6 w-full max-w-md border-2 border-green-500 shadow-2xl my-auto">
            <h3 class="text-xl md:text-2xl font-bold mb-4 text-green-500" id="respond-modal-title">💰 Respond to Buy Request</h3>
            <p class="text-sm text-gray-300 mb-4"><strong id="respond-buyer-name">Team</strong> wants to buy <strong id="respond-chemical">Chemical</strong></p>

            <div class="space-y-4">
                <!-- Buy Request Details -->
                <div id="respond-buyer-details" class="bg-gray-700 p-3 rounded-lg border border-gray-600">
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <div class="text-gray-300">They want:</div>
                        <div class="text-white font-semibold"><span id="respond-requested-qty">Negotiable</span></div>
                        <div class="text-gray-300">Max price:</div>
                        <div class="text-blue-400 font-semibold"><span id="respond-max-price">Negotiable</span></div>
                    </div>
                </div>

                <!-- Your Inventory -->
                <div class="bg-tertiary border border-info rounded-lg p-3">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-300">Your Inventory:</span>
                        <span class="text-white font-bold"><span id="respond-your-inventory">0</span> gallons</span>
                    </div>
                    <div class="flex justify-between text-xs mt-1">
                        <span class="text-gray-400">Your Shadow Price:</span>
                        <span class="text-green-400 font-semibold"><span id="respond-shadow-price">$0</span>/gal</span>
                    </div>
                    <div class="flex justify-between text-[10px] mt-1 text-gray-400 italic">
                        <span>Stability Range:</span>
                        <span id="respond-range-display" class="text-info font-mono">N/A</span>
                    </div>
                </div>

                <!-- Quantity to Sell (Slider) -->
                <div>
                    <label for="respond-quantity" class="block text-sm font-semibold mb-2 text-gray-300">Quantity You'll Sell (gallons)</label>
                    <input type="range" id="respond-quantity-slider" min="0" max="1000" step="10" value="100" class="w-full h-2 bg-gray-600 rounded-lg appearance-none cursor-pointer accent-green-500">
                    <div class="flex items-center gap-2 mt-2">
                        <button type="button" id="respond-qty-minus" class="w-10 h-10 bg-gray-600 hover:bg-gray-500 rounded font-bold text-lg transition">−</button>
                        <input type="number" id="respond-quantity" min="1" step="10" value="100" aria-label="Quantity to sell in gallons" class="flex-1 bg-gray-700 border border-gray-600 rounded px-4 py-2 text-white text-lg text-center font-bold">
                        <button type="button" id="respond-qty-plus" class="w-10 h-10 bg-gray-600 hover:bg-gray-500 rounded font-bold text-lg transition">+</button>
                    </div>
                    <p id="respond-sensitivity-warning" class="hidden text-[10px] text-yellow-500 mt-1">⚠️ This quantity exceeds the stable range. Shadow price may change.</p>
                    <p class="text-xs text-gray-400 mt-1">💡 Sell only what you don't need for production</p>
                </div>

                <!-- Your Price -->
                <div>
                    <label for="respond-price" class="block text-sm font-semibold mb-2 text-gray-300">Your Price ($ per gallon)</label>
                    <div class="flex items-center gap-2">
                        <button type="button" id="respond-price-minus" class="w-10 h-10 bg-gray-600 hover:bg-gray-500 rounded font-bold text-lg transition">−</button>
                        <input type="number" id="respond-price" min="0" step="0.50" value="5.00" class="flex-1 bg-gray-700 border border-gray-600 rounded px-4 py-2 text-white text-lg text-center font-bold">
                        <button type="button" id="respond-price-plus" class="w-10 h-10 bg-gray-600 hover:bg-gray-500 rounded font-bold text-lg transition">+</button>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">💡 Higher than shadow price = Profit</p>
                </div>

                <!-- Total Revenue -->
                <div class="bg-gray-700 p-4 rounded-lg border border-gray-600">
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-sm text-gray-300"><strong>Trade Cash:</strong></span>
                        <span class="text-green-400 font-bold text-xl"><span id="respond-total">$0.00</span></span>
                    </div>
                    <div class="flex justify-between items-center text-xs">
                        <span class="text-gray-400">Projected Profit Change:</span>
                        <span class="font-bold" id="respond-profit-delta">$0.00</span>
                    </div>
                </div>

                <!-- Warning if exceeds inventory -->
                <div id="insufficient-inventory-warning" class="hidden badge-error border border-red-500 rounded-lg p-3">
                    <p class="text-red-400 text-sm font-semibold">⚠️ You don't have enough inventory! Reduce quantity.</p>
                </div>
            </div>

            <div class="flex gap-3 mt-6">
                <button id="respond-submit-btn" class="flex-1 bg-green-600 hover:bg-green-700 text-white py-3 rounded font-semibold transition disabled:opacity-50 disabled:cursor-not-allowed">Make Offer</button>
                <button id="respond-cancel-btn" class="flex-1 bg-gray-600 hover:bg-gray-700 text-white py-3 rounded font-semibold transition">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Settings Modal -->
    <div id="settings-modal" class="hidden fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 p-4" role="dialog" aria-modal="true" aria-labelledby="settings-modal-title">
        <div class="bg-gray-800 rounded-lg p-4 md:p-6 w-full max-w-md border-2 border-green-500 shadow-2xl">
            <h3 class="text-xl md:text-2xl font-bold mb-4 text-green-500" id="settings-modal-title">Settings</h3>

            <div class="space-y-6">
                <!-- Theme Selector -->
                <div class="flex items-start justify-between gap-4">
                    <div class="flex-1">
                        <label for="theme-selector" class="block text-sm font-semibold mb-1">Color Theme</label>
                        <p class="text-xs text-gray-300">Choose your preferred color scheme</p>
                    </div>
                    <select id="theme-selector" class="bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-green-500">
                        <option value="dark">Dark</option>
                        <option value="light">Light</option>
                        <option value="high-contrast">High Contrast</option>
                    </select>
                </div>

                <!-- Audio Settings -->
                <div class="border-t border-gray-700 pt-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex-1">
                            <label for="mute-toggle" class="block text-sm font-semibold mb-1">Audio Cues</label>
                            <p class="text-xs text-gray-300">Enable sound effects for game events</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-gray-400" id="audio-status-label">Enabled</span>
                            <input type="checkbox" id="audio-enabled-toggle" checked class="accent-green-500">
                        </div>
                    </div>
                    
                    <div id="volume-control-group">
                        <div class="flex justify-between items-center mb-2">
                            <label for="volume-slider" class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Volume</label>
                            <span id="volume-value-label" class="text-xs font-mono text-green-400">50%</span>
                        </div>
                        <input type="range" id="volume-slider" min="0" max="100" value="50" class="w-full h-2 bg-gray-700 rounded-lg appearance-none cursor-pointer accent-green-500">
                    </div>
                </div>
            </div>

            <div class="mt-6">
                <button id="settings-close-btn" class="w-full bg-gray-600 hover:bg-gray-700 text-white py-3 rounded font-semibold transition">Close</button>
            </div>
        </div>
    </div>

    <!-- Negotiation Modal -->
    <div id="negotiation-modal" class="hidden fixed inset-0 bg-black bg-opacity-75 flex items-start md:items-center justify-center z-50 p-2 md:p-4 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="negotiation-modal-title">
        <div class="bg-gray-800 rounded-lg p-3 md:p-6 w-full max-w-4xl my-2 md:my-0 border-2 border-green-500 shadow-2xl">
            <div class="flex items-center justify-between mb-4 md:mb-6">
                <h3 class="text-xl md:text-2xl font-bold text-green-500" id="negotiation-modal-title">Negotiations</h3>
                <button id="negotiation-modal-close-btn" class="text-gray-700 hover:text-gray-900 transition" aria-label="Close negotiations" style="background: none; border: none; cursor: pointer; padding: 0;">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <!-- Negotiation List View -->
            <div id="negotiation-list-view">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div class="bg-gray-700 rounded-lg p-4 flex flex-col">
                        <h4 class="font-bold text-lg mb-2 text-green-400">Pending Negotiations</h4>
                        <div id="pending-negotiations" class="space-y-2 overflow-y-auto scrollbar-thin flex-1 min-h-[150px] max-h-[40vh] pr-2">
                            <p class="text-gray-300 text-center py-4">No pending negotiations</p>
                        </div>
                    </div>
                    <div class="bg-gray-700 rounded-lg p-4 flex flex-col">
                        <h4 class="font-bold text-lg mb-2 text-gray-400">Completed Negotiations</h4>
                        <div id="completed-negotiations" class="space-y-2 overflow-y-auto scrollbar-thin flex-1 min-h-[150px] max-h-[40vh] pr-2">
                            <p class="text-gray-300 text-center py-4">No completed negotiations</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Negotiation Detail View -->
            <div id="negotiation-detail-view" class="hidden">
                <button id="back-to-list-btn" class="mb-4 text-blue-400 hover:text-blue-300 flex items-center gap-2" style="background: none; border: none; cursor: pointer; padding: 0;">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    Back to list
                </button>

                <div class="bg-gray-700 rounded-lg p-6 mb-4">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h4 class="text-xl font-bold" id="detail-chemical">Chemical C</h4>
                            <p class="text-sm text-gray-300">
                                <span id="detail-participants"></span>
                            </p>
                        </div>
                        <div class="text-right">
                            <span id="detail-status-badge" class="px-3 py-1 rounded-full text-sm font-semibold"></span>
                        </div>
                    </div>

                    <!-- Offer History -->
                    <div class="mb-4">
                        <h5 class="font-bold mb-3 text-gray-300">Offer History</h5>
                        <div id="offer-history" class="space-y-2 max-h-64 overflow-y-auto">
                            <!-- Offers will be dynamically added here -->
                        </div>
                    </div>

                    <!-- Witcher 3 Style Counter-Offer Form -->
                    <div id="counter-offer-form" class="hidden bg-gray-800 rounded-lg p-4 border border-blue-500 shadow-inner">
                        <h5 class="font-bold mb-4 text-blue-400 flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Haggle with Merchant
                        </h5>
                        
                        <div class="space-y-6">
                            <!-- Quantity Slider -->
                            <div>
                                <div class="flex justify-between text-sm mb-2">
                                    <label class="text-gray-300 font-semibold">Quantity</label>
                                    <span class="text-white font-mono"><span id="haggle-qty-display">0</span> gal</span>
                                </div>
                                <input type="range" id="haggle-qty-slider" class="w-full h-2 bg-gray-700 rounded-lg appearance-none cursor-pointer accent-blue-500">
                                <div class="flex justify-between text-[10px] text-gray-500 mt-1">
                                    <span>Min: 1</span>
                                    <span>Max: <span id="haggle-qty-max">0</span></span>
                                </div>
                                <div class="text-[10px] text-gray-400 mt-2 text-center">
                                    <span>Shadow Price Stable Range: </span>
                                    <span class="text-blue-400 font-mono" id="haggle-range-display">[N/A]</span>
                                </div>
                                <p id="haggle-sensitivity-warning" class="hidden text-[10px] text-yellow-500 mt-1 text-center">⚠️ Quantity exceeds stable range.</p>
                            </div>

                            <!-- Price Input (matches Post Buy Request style) -->
                            <div>
                                <label class="block text-sm font-semibold mb-2 text-gray-300">Offer Price ($ per gallon)</label>
                                <div class="flex items-center gap-2">
                                    <button type="button" id="haggle-price-minus" class="w-10 h-10 bg-gray-600 hover:bg-gray-500 rounded font-bold text-lg transition" aria-label="Decrease price">−</button>
                                    <input type="number" id="haggle-price-input" min="0" step="0.50" value="0" class="flex-1 bg-gray-700 border border-gray-600 rounded px-4 py-2 text-white text-lg text-center font-bold" aria-label="Price per gallon">
                                    <button type="button" id="haggle-price-plus" class="w-10 h-10 bg-gray-600 hover:bg-gray-500 rounded font-bold text-lg transition" aria-label="Increase price">+</button>
                                </div>
                                <p class="text-xs text-gray-300 mt-1">💡 Shadow Price: <span class="text-green-400 font-semibold"><span id="haggle-shadow-hint">$0</span></span> (value to you)</p>
                            </div>

                            <!-- Player Reaction (Your Annoyance) -->
                            <div>
                                <div class="flex justify-between text-sm mb-2">
                                    <label class="text-blue-300 font-semibold">Your Reaction to Counter-Offer</label>
                                    <span id="reaction-label" class="text-blue-400 font-bold">Neutral</span>
                                </div>
                                <input type="range" id="haggle-reaction-slider" min="0" max="100" value="0" class="w-full h-2 bg-gray-700 rounded-lg appearance-none cursor-pointer accent-blue-500">
                                <div class="flex justify-between text-[10px] text-gray-500 mt-1">
                                    <span>Accepting</span>
                                    <span>Displeased</span>
                                </div>
                            </div>

                            <!-- Persistent NPC Patience Meter -->
                            <div class="pt-2 border-t border-gray-700">
                                <div class="flex justify-between text-[10px] uppercase tracking-wider mb-1">
                                    <span class="text-gray-400">Merchant Patience</span>
                                    <span id="patience-value" class="text-white font-bold">100%</span>
                                </div>
                                <div class="w-full h-2 bg-gray-900 rounded-full overflow-hidden border border-gray-800">
                                    <div id="patience-bar" class="h-full bg-emerald-500 transition-all duration-500" style="width: 100%"></div>
                                </div>
                                <p class="text-[10px] text-gray-500 mt-1">If patience runs out, the deal is cancelled.</p>
                            </div>

                            <div class="bg-gray-900 p-3 rounded">
                                <div class="flex justify-between items-center mb-1">
                                    <span class="text-xs text-gray-400">Total Transaction:</span>
                                    <span class="text-lg font-bold text-blue-400"><span id="haggle-total">$0.00</span></span>
                                </div>
                                <div class="flex justify-between items-center text-xs">
                                    <span class="text-gray-400">Projected Profit Change:</span>
                                    <span class="font-bold" id="haggle-profit-delta">$0.00</span>
                                </div>
                                <div id="haggle-error" class="hidden badge-error mt-2 text-[10px] font-bold text-center border border-red-900/50 rounded py-1">
                                    ⚠️ INSUFFICIENT RESOURCES
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-2 mt-6">
                            <button id="submit-counter-btn" class="flex-2 bg-blue-600 hover:bg-blue-700 text-white py-3 rounded font-bold transition flex items-center justify-center gap-2">
                                Send Offer
                            </button>
                            <button id="cancel-counter-btn" class="flex-1 bg-gray-700 hover:bg-gray-600 text-gray-300 py-3 rounded font-semibold transition">
                                Back
                            </button>
                        </div>
                    </div>

                    <!-- Action Buttons (only shown when it's user's turn to respond) -->
                    <div id="negotiation-actions" class="hidden flex gap-3">
                        <button id="accept-offer-btn" class="flex-1 bg-green-600 hover:bg-green-700 text-white py-3 rounded font-semibold transition">
                            Accept Offer
                        </button>
                        <button id="reject-offer-btn" class="flex-1 bg-red-600 hover:bg-red-700 text-white py-3 rounded font-semibold transition">
                            Reject / Cancel
                        </button>
                        <button id="show-counter-form-btn" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-3 rounded font-semibold transition">
                            Counter-Offer
                        </button>
                    </div>

                    <!-- Waiting message (shown when waiting for other team) -->
                    <div id="waiting-message" class="hidden text-center p-4 bg-gray-600 rounded">
                        <p class="text-gray-300">Waiting for other team to respond...</p>
                    </div>
                </div>
            </div>

            <!-- Start New Negotiation View -->
            <div id="start-negotiation-view" class="hidden">
                <button id="back-from-new-btn" class="mb-4 text-blue-400 hover:text-blue-300 flex items-center gap-2" style="background: none; border: none; cursor: pointer; padding: 0;">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    Back
                </button>

                <div class="bg-gray-700 rounded-lg p-6">
                    <h4 class="text-xl font-bold mb-4 text-green-400">Start New Negotiation</h4>
                    <div class="space-y-4">
                        <div>
                            <label for="new-neg-team" class="block text-sm font-semibold mb-2 text-gray-300">Select Team</label>
                            <input type="text" id="new-neg-team" readonly class="w-full bg-gray-600 border border-gray-500 rounded px-4 py-2 text-white cursor-not-allowed">
                        </div>
                        <div>
                            <label for="new-neg-chemical" class="block text-sm font-semibold mb-2 text-gray-300">Chemical</label>
                            <input type="text" id="new-neg-chemical" readonly class="w-full bg-gray-600 border border-gray-500 rounded px-4 py-2 text-white cursor-not-allowed">
                        </div>
                        <div>
                            <label for="new-neg-quantity" class="block text-sm font-semibold mb-2 text-gray-300">Quantity (gallons)</label>
                            <input type="range" id="new-neg-quantity-slider" min="1" max="2000" step="10" value="100" class="w-full h-2 bg-gray-600 rounded-lg appearance-none cursor-pointer accent-blue-500 mb-2">
                            <div class="flex items-center gap-2">
                                <button type="button" id="new-neg-qty-minus" class="w-10 h-10 bg-gray-600 hover:bg-gray-500 rounded font-bold text-lg transition">−</button>
                                <input type="number" id="new-neg-quantity" min="1" step="10" value="100" class="flex-1 bg-gray-600 border border-gray-500 rounded px-4 py-2 text-white text-center font-bold">
                                <button type="button" id="new-neg-qty-plus" class="w-10 h-10 bg-gray-600 hover:bg-gray-500 rounded font-bold text-lg transition">+</button>
                            </div>
                        </div>
                        <div>
                            <label for="new-neg-price" class="block text-sm font-semibold mb-2 text-gray-300">Your Offer Price ($)</label>
                            <div class="flex items-center gap-2">
                                <button type="button" id="new-neg-price-minus" class="w-10 h-10 bg-gray-600 hover:bg-gray-500 rounded font-bold text-lg transition">−</button>
                                <input type="number" id="new-neg-price" min="0" step="0.50" value="5.00" class="flex-1 bg-gray-600 border border-gray-500 rounded px-4 py-2 text-white text-center font-bold">
                                <button type="button" id="new-neg-price-plus" class="w-10 h-10 bg-gray-600 hover:bg-gray-500 rounded font-bold text-lg transition">+</button>
                            </div>
                        </div>
                        <p class="text-xs text-gray-300">Your Shadow Price: <span class="text-green-400 font-semibold"><span id="new-neg-shadow-hint">$0</span></span></p>
                        <button id="submit-new-negotiation-btn" class="w-full bg-green-600 hover:bg-green-700 text-white py-3 rounded font-semibold transition">
                            Send Initial Offer
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Leaderboard Modal -->
    <leaderboard-modal id="leaderboard-modal"></leaderboard-modal>

    <!-- Production Guide Modal -->
    <div id="production-guide-modal" class="hidden fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 p-4" role="dialog" aria-modal="true" aria-labelledby="production-guide-modal-title">
        <div class="bg-gray-800 rounded-lg p-4 md:p-6 w-full max-w-2xl border-2 border-blue-500 shadow-2xl">
            <div class="flex items-center justify-between mb-4 md:mb-6">
                <h3 class="text-xl md:text-2xl font-bold text-blue-500" id="production-guide-modal-title">Production Formulas</h3>
                <button id="production-guide-close-btn" class="text-gray-700 hover:text-gray-900 transition" aria-label="Close production guide" style="background: none; border: none; cursor: pointer; padding: 0;">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <div class="space-y-6">
                <!-- Deicer Formula -->
                <div class="bg-gray-700 rounded-lg p-4 border-2 border-blue-400">
                    <h4 class="text-lg font-bold text-blue-400 mb-3">Deicer Production</h4>
                    <div class="grid grid-cols-2 gap-4 mb-3">
                        <div>
                            <p class="text-sm text-gray-300 mb-2">Chemicals Required (per 50-gal barrel):</p>
                            <div class="space-y-1 font-mono">
                                <div class="flex justify-between text-white">
                                    <span class="text-blue-400">Chemical C:</span>
                                    <span class="font-bold">25 gal</span>
                                </div>
                                <div class="flex justify-between text-white">
                                    <span class="text-purple-400">Chemical N:</span>
                                    <span class="font-bold">15 gal</span>
                                </div>
                                <div class="flex justify-between text-white">
                                    <span class="text-yellow-400">Chemical D:</span>
                                    <span class="font-bold">10 gal</span>
                                </div>
                            </div>
                        </div>
                        <div>
                            <p class="text-sm text-gray-300 mb-2">Revenue:</p>
                            <div class="font-mono text-green-400 font-bold text-2xl">$100</div>
                            <p class="text-xs text-gray-300 mt-1">(per 50-gal barrel)</p>
                        </div>
                    </div>
                </div>

                <!-- Solvent Formula -->
                <div class="bg-gray-700 rounded-lg p-4 border-2 border-purple-400">
                    <h4 class="text-lg font-bold text-purple-400 mb-3">Solvent Production</h4>
                    <div class="grid grid-cols-2 gap-4 mb-3">
                        <div>
                            <p class="text-sm text-gray-300 mb-2">Chemicals Required (per 20-gal barrel):</p>
                            <div class="space-y-1 font-mono">
                                <div class="flex justify-between text-white">
                                    <span class="text-purple-400">Chemical N:</span>
                                    <span class="font-bold">5 gal</span>
                                </div>
                                <div class="flex justify-between text-white">
                                    <span class="text-yellow-400">Chemical D:</span>
                                    <span class="font-bold">7 gal</span>
                                </div>
                                <div class="flex justify-between text-white">
                                    <span class="text-red-400">Chemical Q:</span>
                                    <span class="font-bold">8 gal</span>
                                </div>
                            </div>
                        </div>
                        <div>
                            <p class="text-sm text-gray-300 mb-2">Revenue:</p>
                            <div class="font-mono text-green-400 font-bold text-2xl">$60</div>
                            <p class="text-xs text-gray-300 mt-1">(per 20-gal barrel)</p>
                        </div>
                    </div>
                </div>

                <!-- Strategy Tips -->
                <div class="bg-tertiary border border-info rounded-lg p-4">
                    <h4 class="font-bold text-white-always mb-2">💡 Trading Strategy Tips</h4>
                    <ul class="text-sm text-white-always space-y-2">
                        <li>• <strong class="text-white-always">Shadow Prices</strong> show how much your profit increases per additional gallon of each chemical</li>
                        <li>• Buy chemicals with high shadow prices to maximize production profit</li>
                        <li>• Sell chemicals with low/zero shadow prices - you don't need them!</li>
                        <li>• Your production automatically runs at the end of the session using these formulas</li>
                    </ul>
                </div>
            </div>

            <div class="mt-6">
                <button id="production-guide-ok-btn" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 rounded font-semibold transition">Got It!</button>
            </div>
        </div>
    </div>

    <!-- Transaction History Modal -->
    <div id="history-modal" class="hidden fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-[110] p-4">
        <div class="bg-gray-800 rounded-lg w-full max-w-4xl max-h-[85vh] flex flex-col border border-gray-700 shadow-2xl">
            <div class="p-6 border-b border-gray-700 flex justify-between items-center">
                <h3 class="text-2xl font-bold text-white">Transaction History</h3>
                <div class="flex items-center gap-4">
                    <button id="export-history-btn" class="text-gray-300 hover:text-white text-sm flex items-center gap-1" title="Download CSV">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                        CSV
                    </button>
                    <button id="history-close-btn" class="text-gray-300 hover:text-white text-2xl" aria-label="Close">&times;</button>
                </div>
            </div>
            <div class="p-6 overflow-y-auto flex-1">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="text-gray-400 border-b border-gray-700 text-sm uppercase">
                            <th class="py-3 font-semibold">Time</th>
                            <th class="py-3 font-semibold">Type</th>
                            <th class="py-3 font-semibold">Chemical</th>
                            <th class="py-3 font-semibold text-right">Qty</th>
                            <th class="py-3 font-semibold text-right">Price</th>
                            <th class="py-3 font-semibold text-right">Total</th>
                            <th class="py-3 font-semibold text-right">Inv Before</th>
                            <th class="py-3 font-semibold text-right">Inv After</th>
                            <th class="py-3 font-semibold pl-4">Counterparty</th>
                        </tr>
                    </thead>
                    <tbody id="history-table-body" class="text-gray-300 text-sm divide-y divide-gray-700">
                        <!-- Rows injected via JS -->
                    </tbody>
                </table>
                <p id="history-empty-msg" class="hidden text-center text-gray-500 py-8">No transactions found.</p>
            </div>
        </div>
    </div>

    <!-- Main JavaScript Application -->
    <script id="main-app-script"
            type="module"
            src="./js/marketplace.js?v=<?php echo time(); ?>"></script>
</body>
</html>
