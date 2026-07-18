# CNDQ AI Assistant Guide
**Quick Start Guide for AI Assistants Working on the CNDQ Codebase**

Last Updated: 2026-01-15

---

## Table of Contents
1. [What is CNDQ?](#what-is-cndq)
2. [Project Structure](#project-structure)
3. [Key Concepts](#key-concepts)
4. [Technology Stack](#technology-stack)
5. [Important Files](#important-files)
6. [Common Tasks](#common-tasks)
7. [Testing](#testing)
8. [Deployment](#deployment)
9. [Troubleshooting](#troubleshooting)

---

## What is CNDQ?

CNDQ is an **educational business simulation game** that teaches students about:
- **Linear Programming** - optimizing production decisions
- **Shadow Prices** - understanding marginal value of resources
- **Market Economics** - trading, negotiation, and profit maximization
- **Strategic Decision Making** - cooperation vs competition

### The Game Premise
Students are divided into teams representing companies in the "Deicer and Solvent" industry:
- Each team starts with inventory of 4 chemicals: **C, N, D, Q**
- Teams produce two products:
  - **Deicer** (50 gal) = 25C + 15N + 10D → $100 revenue
  - **Solvent** (20 gal) = 5N + 7D + 8Q → $60 revenue
- Teams trade chemicals with each other to optimize production
- Linear programming determines optimal product mix
- **Shadow prices** reveal which chemicals are bottlenecks

### Educational Goals
- Understanding that trades can be **mutually beneficial** (non-zero-sum)
- Learning to use **shadow prices** as decision-making tools
- Recognizing **opportunity cost** and **marginal value**
- Developing negotiation and strategy skills

See [Problem.md](Problem.md) for the full educational context.

---

## Project Structure

```
CNDQ/
├── index.php                 # Main game interface (player marketplace)
├── admin/
│   └── index.php            # Admin control panel (session management)
├── api/
│   ├── 404.php              # Catch-all for unknown API routes
│   ├── listings/            # Buy request endpoints
│   │   ├── list.php         # GET  - All active buy listings
│   │   ├── my-listings.php  # GET  - Current user's listings
│   │   ├── post.php         # POST - Post a buy request
│   │   └── cancel.php       # POST - Cancel a buy request
│   ├── marketplace/
│   │   └── offers.php       # GET  - All active offers
│   ├── negotiations/
│   │   ├── list.php, initiate.php, accept.php, counter.php, reject.php, react.php
│   ├── production/
│   │   ├── results.php      # GET  - Production results
│   │   └── shadow-prices.php # POST - Recalculate shadow prices
│   ├── session/
│   │   ├── status.php       # GET  - Session status (public)
│   │   └── restart.php      # POST - Restart session
│   ├── leaderboard/standings.php, notifications/list.php
│   ├── team/profile.php, team/settings.php
│   └── admin/
│       ├── session.php, reset-game.php, list-teams.php
│       └── npc/
│           ├── list.php, create.php, delete.php, toggle.php
│           ├── toggle-system.php, set-variability.php
│           ├── trigger-cycle.php, update-delays.php
├── lib/                     # PHP backend classes
│   ├── Database.php         # SQLite database abstraction
│   ├── TeamStorage.php      # Team data management (funds, inventory)
│   ├── SystemStorage.php    # System-wide settings storage
│   ├── SessionManager.php   # Game session lifecycle
│   ├── LPSolver.php         # Linear programming solver (production optimization)
│   ├── ListingManager.php   # Buy listing/advertisement management
│   ├── NegotiationManager.php # Trading/haggling system
│   ├── TradeExecutor.php    # Executes confirmed trades (inventory/fund updates)
│   ├── NPCManager.php       # AI player management
│   ├── NPCStrategyFactory.php # NPC strategy selector
│   ├── NPCTradingStrategy.php # Strategy interface
│   ├── GlobalAggregator.php # Cross-team market aggregation
│   ├── MarketplaceAggregator.php # Marketplace event aggregation
│   ├── AdminAuth.php        # Admin authentication
│   ├── TeamNameGenerator.php # Random NPC name generator
│   └── strategies/          # NPC trading strategies
│       ├── ShadowPriceArbitrageStrategy.php  # Beginner
│       ├── BottleneckEliminationStrategy.php # Novice
│       └── RecipeBalancingStrategy.php       # Expert
├── js/                      # Frontend JavaScript
│   ├── marketplace.js       # Main UI orchestrator
│   ├── api.js              # API client wrapper
│   ├── solver.js           # Client-side LP solver (glpk.js)
│   ├── config.js           # Game constants
│   ├── components/          # Lit web components
│   │   ├── chemical-card.js      # Chemical inventory card
│   │   ├── buy-request-card.js   # Pending buy request card
│   │   ├── listing-item.js       # Buy listing item (was advertisement-item.js)
│   │   ├── negotiation-card.js   # Active negotiation card
│   │   ├── offer-bubble.js       # Offer message bubble
│   │   ├── ModalManager.js       # Reusable modal dialog
│   │   ├── leaderboard-modal.js  # Leaderboard overlay
│   │   ├── notification-manager.js # Toast/notification system
│   │   ├── report-viewer.js      # End-of-game report viewer
│   │   └── shared-styles.js      # Shared CSS-in-JS styles
│   └── modules/             # Non-component JS modules
│       ├── StateManager.js       # App state and polling coordination
│       ├── PollingService.js     # Polling loop for real-time updates
│       ├── NotificationService.js # Notification data fetching
│       ├── FinancialRenderer.js  # Financial display helpers
│       └── SoundService.js       # Audio feedback (earcons)
├── css/
│   ├── styles.css          # Main stylesheet (theme variables, WCAG AA)
│   └── design-system.css   # Chemical element colors and badge/button classes
├── stylesheet.html          # Component gallery — all UI components in all 3 themes (no auth needed)
├── tests/                   # Playwright integration tests
│   └── playwright/
│       ├── health.spec.js       # API endpoint health checks
│       ├── trade.spec.js        # Trade balance deterministic checks
│       ├── ui-smoke.spec.js     # Browser smoke tests
│       └── stylesheet.spec.js  # Accessibility (axe-core WCAG 2.1 AA) — no auth/session needed
├── data/                    # Runtime data directory
│   └── cndq.db             # SQLite database (event-sourced)
├── topology.md              # Development environment setup
├── Problem.md               # Educational context
├── NPC-STRATEGIES.md        # NPC behavior documentation
└── SETUP.md                 # Local installation guide
```

### Key Subdirectory Note
**IMPORTANT**: The application runs in a subdirectory structure for production parity:
- Production: `https://server.com/CNDQ/`
- Local (Herd): `http://cndq.test/CNDQ/www/`

All paths use **relative references** (`./` and `../`) to work in any subdirectory. See [topology.md](topology.md) for details.

---

## Key Concepts

### 1. Shadow Prices
**The most important concept in the game!**

Shadow prices represent the **marginal value** of each chemical to a team's production:
- Calculated via **Linear Programming sensitivity analysis**
- Shows how much profit increases per additional gallon
- Used as decision-making tool:
  - **Buy** chemicals with high shadow prices (bottlenecks)
  - **Sell** chemicals with low/zero shadow prices (excess)

Example:
```
Team has shadow prices: C=$3.50, N=$2.00, D=$0.00, Q=$1.50

Interpretation:
- Chemical C is a BOTTLENECK (worth $3.50/gal in production)
- Chemical D is EXCESS (worth $0/gal - not needed)
- Should BUY C, SELL D
```

### 2. Linear Programming Solver
The LP solver calculates optimal production:
- **Objective**: Maximize revenue from Deicer + Solvent production
- **Constraints**: Limited inventory of C, N, D, Q chemicals
- **Output**:
  - Optimal barrels of Deicer and Solvent to produce
  - Shadow prices for each chemical
  - Constraint status (binding vs non-binding)

Implementation: `lib/LPSolver.php` (server) and `js/solver.js` (client) using glpk.js

### 3. Trading System
Three types of market interactions:

#### A. Advertisements (Buy Requests ONLY)
- Teams can **ONLY post BUY requests** - "I want to buy Chemical C"
- **NO sell advertisements** exist in the system
- To sell, you must respond to someone else's buy request
- Other teams see your buy request and can initiate negotiations to sell to you
- Creates liquidity in the market

#### B. Direct Negotiations
- Two teams haggle over price/quantity
- Initiated when someone responds to a buy request
- Multi-round negotiation (counter-offers)
- Either party can accept/reject/walk away

#### C. NPC Traders
- AI players with three skill levels:
  - **Beginner**: Shadow price arbitrage (simple buy low/sell high)
  - **Novice**: Bottleneck elimination (acquire limiting chemicals)
  - **Expert**: Recipe balancing + aggressive haggling

See [NPC-STRATEGIES.md](NPC-STRATEGIES.md) for NPC behavior details.

### 4. Game Phases
A session progresses through phases:

1. **Production** (Auto)
   - LP solver runs for each team
   - Optimal product mix calculated
   - Initial shadow prices determined

2. **Trading** (Active)
   - Market opens for chemical trading
   - Negotiations, haggling, deals
   - Shadow prices update as inventory changes

3. **End** (Summary)
   - Final production run
   - Leaderboard shows rankings
   - Event history displayed

Managed by: `lib/SessionManager.php`

---

## Architecture

### Event Sourcing
CNDQ uses **event sourcing** for data persistence:
- All state changes are stored as immutable events
- Current state is derived by replaying events
- Provides complete audit trail and time-travel debugging

**Key Tables**:
- `team_events` - All team actions (inventory changes, fund updates, etc.)
- `marketplace_events` - All market actions (ads posted, buy orders, transactions)
- `team_state_cache` - Cached current state (rebuilt from events)
- `marketplace_snapshot` - Cached marketplace state

**Example Event Flow**:
```php
// When NPC posts buy ad:
1. TeamStorage->addAd('C', 'buy')
2. Emits 'add_ad' event to marketplace_events
3. Event payload: {"type": "buy", "chemical": "C", "id": "ad_123..."}
4. Marketplace aggregator rebuilds snapshot from events
```

**Querying Data**:
```python
# To find NPC advertisements:
SELECT payload FROM marketplace_events
WHERE event_type = 'add_ad' AND team_email LIKE 'npc_%'

# Parse JSON payload to get ad details
```

---

## Technology Stack

### Backend
- **PHP 8.x** - Server-side logic
- **SQLite** - Database (single file, no server needed)
- **GLPK** - Linear programming solver (via PHP exec)

### Frontend
- **Vanilla JavaScript** - No framework dependency
- **Lit Web Components** - For reusable UI components (via CDN)
- **UnoCSS** - Utility-first CSS (JIT mode, no build step)
- **glpk.js** - WebAssembly LP solver (client-side shadow prices)

### Development Tools
- **Laravel Herd** - Local PHP server (macOS/Windows)
- **Puppeteer** - Automated browser testing
- **Node.js** - For test runner only (app itself doesn't need Node)

### Styling — Two CSS Files (Important)
There are **two CSS variable systems** that coexist. Do not conflate them:

| File | Variable prefix | Purpose |
|---|---|---|
| `css/styles.css` | `--color-*` | Theme-aware variables (dark/light/high-contrast), WCAG AA compliant |
| `css/design-system.css` | `--chem-*`, `--bg-*`, `--text-*`, `--brand-*` | Chemical card system, badge classes, button classes |

`styles.css` variables override `design-system.css` variables via `[data-theme]` selectors. Both files are loaded on every page. When adding colors, use `--color-*` from `styles.css`. When styling chemical cards/badges, use the design-system classes.

**Button text on green**: `.btn-primary` and `.btn-sell` use dark text (`#0f2418`) not white — green backgrounds fail WCAG AA with white text.

### Key Design Decisions
- **No build step**: Everything runs directly (importmap for ES modules)
- **Relative paths**: Works in any subdirectory without config
- **Event Sourcing**: All state changes stored as immutable events for audit trail
- **SQLite**: Zero-config database, easy to reset/backup
- **Client-side solver**: Shadow prices recalculate instantly in browser

---

## Important Files

### Core Backend Files

#### `lib/Database.php`
Database abstraction layer:
```php
$db = Database::getInstance();
$db->query("SELECT * FROM teams WHERE email = ?", [$email]);
```
- Singleton pattern
- Prepared statements for security
- Automatic table creation

#### `lib/TeamStorage.php`
Team data management:
```php
$storage = new TeamStorage($email);
$storage->updateFunds(100);  // Add $100
$storage->updateInventory(['C' => 50]); // Add 50 gallons of C
$shadowPrices = $storage->getShadowPrices();
```
- Stores: funds, inventory (C/N/D/Q), shadow prices
- Transaction history
- Shadow price staleness tracking

#### `lib/LPSolver.php`
Production optimization:
```php
$solver = new LPSolver();
$result = $solver->solve($inventory);
// Returns: ['deicer' => 10, 'solvent' => 5, 'revenue' => 1300, 'shadowPrices' => [...]]
```
- Uses GLPK command-line tool
- Generates CPLEX LP format
- Parses sensitivity analysis

#### `lib/NegotiationManager.php`
Trading system:
```php
$nm = new NegotiationManager();
$nm->createNegotiation($buyerEmail, $sellerEmail, 'C', 100, 5.00);
$nm->respondToNegotiation($negId, 'counter', 4.50, 80);
$nm->acceptNegotiation($negId);
```
- Multi-round haggling
- Status tracking (pending, accepted, rejected, cancelled)
- Automatic inventory/funds updates on completion

#### `lib/NPCManager.php`
AI player controller:
```php
$npcMgr = new NPCManager();
$npcMgr->createNPC('beginner', 'Crafty Otter');
$npcMgr->runAllNPCs(); // Execute one trading cycle for all NPCs
```
- Creates/deletes NPCs
- Executes NPC trading decisions
- Uses strategy pattern for behavior

### Core Frontend Files

#### `js/marketplace.js`
Main UI orchestrator:
- Initializes app and coordinates modules
- Handles UI events (button clicks, form submissions)
- Real-time updates via `PollingService`
- Manages modals via `ModalManager`

#### `js/modules/StateManager.js`
Centralizes app state (listings, negotiations, team data) and coordinates polling.

#### `js/modules/SoundService.js`
Web Audio API earcons for trade events (success, warning, alert).

#### `js/api.js`
API client wrapper:
```javascript
const api = new API();
await api.updateInventory({ C: 50 });
const offers = await api.getMarketOffers();
```
- RESTful API calls
- Error handling
- Path prefix detection (for subdirectory support)

#### `js/solver.js`
Client-side LP solver (uses glpk.js):
```javascript
const solver = new LPSolver();
const result = solver.solve({ C: 100, N: 80, D: 50, Q: 60 });
console.log(result.shadowPrices); // { C: 3.5, N: 2.0, ... }
```
- Instant shadow price recalculation
- No server round-trip needed
- Same logic as backend solver

### Configuration Files

#### `config.php`
Google Sheets integration (for team roster import):
```php
$sheetId = "1YvSAxbFty76hR1_mnKVzuEnEYc33xuaHvfW6Tsr2rso";
$sheetName = "Groups";
$credentials = __DIR__ . "/credentials.json";
```

#### `js/config.js`
Game constants:
```javascript
export const CHEMICALS = ['C', 'N', 'D', 'Q'];
export const INITIAL_INVENTORY = { C: 200, N: 200, D: 200, Q: 200 };
export const INITIAL_FUNDS = 5000;
```

---

## Common Tasks

### 1. Setting Up Local Environment
```bash
# Clone/navigate to project
cd C:\Users\pauls\HerdRoot\CNDQ

# Ensure Herd is running and serving C:\Users\pauls\HerdRoot

# Visit local URL
# http://cndq.test/CNDQ/www/
```

See [SETUP.md](SETUP.md) for detailed installation.

### 2. Starting a Game Session
**Admin Panel**: `http://cndq.test/CNDQ/www/admin/`

1. Click "Start Session"
2. Set timer (e.g., 30 minutes)
3. Teams automatically run initial production
4. Market opens for trading

### 3. Creating NPC Players
**Via Admin Panel**:
- Click "Manage NPCs"
- Select skill level (beginner/novice/expert)
- Set count
- NPCs auto-trade every 5 seconds

**Via API**:
```bash
curl -X POST http://cndq.test/CNDQ/www/api/admin/npc/create \
  -H "Content-Type: application/json" \
  -d '{"skillLevel": "expert", "count": 2}'
```

### 4. Debugging Shadow Prices
**Check Team Shadow Prices**:
```php
// In any PHP file
require_once 'lib/TeamStorage.php';
$storage = new TeamStorage('user@email.com');
$sp = $storage->getShadowPrices();
var_dump($sp); // ['C' => 3.5, 'N' => 2.0, ...]
```

**Check Staleness**:
```javascript
// In browser console
const api = new API();
const sp = await api.getShadowPrices();
console.log(sp.isStale ? 'STALE' : 'FRESH');
```

### 5. Resetting the Database
```bash
# Delete database file
rm data/cndq.db

# Refresh browser - tables auto-recreate
# All teams/sessions/trades/events will be wiped
```

### 6. Adding a New Chemical
**This is a major change!** Requires updates to:

1. `js/config.js` - Add to CHEMICALS array
2. `lib/LPSolver.php` - Update LP constraints
3. `lib/TeamStorage.php` - Update inventory schema
4. `js/solver.js` - Update client-side solver
5. Database schema - Add column to teams table

### 7. Modifying NPC Behavior
Edit strategy files in `lib/strategies/`:
- `ShadowPriceArbitrageStrategy.php` (Beginner)
- `BottleneckEliminationStrategy.php` (Novice)
- `RecipeBalancingStrategy.php` (Expert)

Each strategy implements:
```php
interface NPCTradingStrategy {
    public function decideTrade();           // Return action or null
    public function respondToNegotiations(); // Handle pending offers
}
```

See [NPC-STRATEGIES.md](NPC-STRATEGIES.md) for strategy documentation.

---

## Testing

### Running Tests
```bash
npm test                          # Full Playwright suite
npx playwright test health        # API health checks
npx playwright test trade         # Trade balance checks
npx playwright test ui-smoke      # Browser smoke tests
npx playwright test stylesheet    # Accessibility scan (no session needed)
```

### Test Suite Coverage
- **health.spec.js** — Every API endpoint returns the expected shape after a clean reset
- **trade.spec.js** — Trade balance is deterministic across buyer/seller
- **ui-smoke.spec.js** — Player marketplace and admin panel render correctly
- **stylesheet.spec.js** — WCAG 2.1 AA axe-core scan across dark/light/high-contrast themes; runs against `stylesheet.html` with no auth or game session required

### Accessibility Testing (`stylesheet.html`)
`stylesheet.html` is a static component gallery showing every UI component in every state. Load it directly at `http://cndq.test/CNDQ/www/stylesheet.html` — no login needed. The theme switcher toggles dark / light / high-contrast. `stylesheet.spec.js` runs axe-core against all three themes automatically.

### Manual Testing Checklist
1. Start session as admin
2. Create 2-3 NPCs (different skill levels)
3. Log in as player (via dev.php)
4. Post a buy request
5. Wait for NPC response
6. Haggle (counter-offer)
7. Accept deal
8. Verify inventory/funds updated
9. Check shadow prices recalculated

### Debugging Tips
- **PHP Errors**: Check `error_log` (PHP error log location varies by server)
- **JavaScript Errors**: Open browser DevTools console
- **SQL Queries**: Enable logging in `Database.php`
- **NPC Decisions**: NPCs log to error_log with prefix "NPC {name}:"

---

## Deployment

### Production Environment
- **URL**: `https://production.server.com/CNDQ/`
- **Subdirectory**: Must be deployed in `/CNDQ/` folder
- **Database**: Upload `cndq.db` (or let it auto-create)
- **Permissions**: Ensure web server can write to database file

### File Upload
Upload entire `CNDQ/` directory to server:
```
server_root/
└── CNDQ/
    ├── index.php
    ├── admin/
    ├── api/
    ├── lib/
    ├── js/
    ├── css/
    └── data/cndq.db (optional)
```

### Environment-Specific Config
No config changes needed! Relative paths work everywhere.

### Authentication
- **Production**: Uses Shibboleth (university SSO)
- **Local**: Uses dev.php bypass for testing

---

## Troubleshooting

### Issue: NPCs Not Trading
**Symptoms**: NPCs created but no market activity

**Causes**:
1. Session not started (market closed)
2. NPCs have no inventory
3. Shadow prices are zero (no bottlenecks)
4. Global variability set too high (NPCs idle)

**Solutions**:
- Check session status in admin panel
- Verify NPC inventory in `team_state_cache` table or via TeamStorage API
- Run `POST /api/admin/npc/reset-variability` to set to 0.5

### Issue: Shadow Prices Not Updating
**Symptoms**: Shadow prices shown as "stale" or outdated

**Causes**:
1. Inventory changed but LP solver not re-run
2. Client-side solver failed (glpk.js error)
3. Database out of sync

**Solutions**:
- Click "Recalculate Shadow Prices" button
- Check browser console for JavaScript errors
- Verify inventory values in database

### Issue: Negotiations Stuck in "Pending"
**Symptoms**: Counter-offers not completing

**Causes**:
1. One party logged out
2. Session ended mid-negotiation
3. Database transaction failed

**Solutions**:
- Admin can manually cancel negotiations in database
- Restart session to clear pending negotiations
- Check `negotiations` table for status

### Issue: Database Locked
**Symptoms**: "Database locked" error in PHP

**Causes**:
- SQLite default locking behavior
- Multiple simultaneous writes

**Solutions**:
- Add `PRAGMA busy_timeout = 5000;` to Database.php
- Implement transaction retry logic
- Consider switching to MySQL for production

### Issue: NPC "Crafty Otter" Won't Sell

**Understanding NPC Selling Behavior**:
NPCs don't post "sell offers" - this is **by design**! The market only allows BUY advertisements.

**How NPCs Sell**:
1. **You post a BUY request** for the chemical you want
2. **NPC sees your request** and evaluates it against their shadow prices
3. **NPC initiates a SELL negotiation** if your offered price is attractive
4. **You and NPC haggle** over price/quantity

**Why NPCs Might Not Respond to Your Buy Request**:
- NPC's shadow price for that chemical is higher than your offered max price
- NPC doesn't have sufficient inventory to sell
- NPC is using that chemical for their own production (bottleneck)

**Debugging**:
```php
// Check NPC details via TeamStorage
$storage = new TeamStorage('npc_xxxxx@system');
$shadowPrices = $storage->getShadowPrices();
$inventory = $storage->getInventory();

print_r(['shadow_prices' => $shadowPrices, 'inventory' => $inventory]);
```

**Solution**: Increase your max buy price to exceed the NPC's shadow price for that chemical!

---

## Additional Resources

- **[topology.md](topology.md)** - Development environment setup
- **[Problem.md](Problem.md)** - Educational context and game pedagogy
- **[NPC-STRATEGIES.md](NPC-STRATEGIES.md)** - AI player strategy documentation
- **[SETUP.md](SETUP.md)** - Local installation guide
- **API Docs**: Visit `http://cndq.test/CNDQ/www/api-docs.php` (when running locally)

---

## Quick Reference

### Database Tables (Event-Sourced Architecture)
```sql
team_events          -- Event log for team state changes
team_state_cache     -- Cached aggregated team state
team_snapshots       -- Team state snapshots for performance
marketplace_events   -- Event log for marketplace actions (ads, buy orders, transactions)
marketplace_snapshot -- Cached marketplace state
negotiations         -- Active/completed negotiations
negotiation_offers   -- Offer history within negotiations
config               -- System-wide settings (NPC variability, etc.)
migration_log        -- Database migration history
```

**Important**: This is an **event-sourced system**. Data is stored as events, not normalized tables:
- **No `advertisements` table** - ads are stored in `marketplace_events` with `event_type = 'add_ad'`
- **No `teams` table** - team data is in `team_events` and cached in `team_state_cache`
- To query data, aggregate events or use cached snapshots
- Database located at `data/cndq.db` (not `database.sqlite`)

### API Endpoints
```
POST /api/admin/session.php              -- Control game session (start/stop)
POST /api/session/restart.php            -- Restart session
POST /api/admin/npc/create.php           -- Create NPC players
POST /api/admin/npc/trigger-cycle.php    -- Force NPC trading cycle
POST /api/admin/npc/set-variability.php  -- Set global NPC variability
GET  /api/marketplace/offers.php         -- All active offers
GET  /api/listings/list.php              -- All active buy listings
POST /api/listings/post.php              -- Post a buy listing
POST /api/listings/cancel.php            -- Cancel a buy listing
POST /api/negotiations/initiate.php      -- Start negotiation
POST /api/negotiations/counter.php       -- Counter-offer
POST /api/negotiations/accept.php        -- Accept offer
POST /api/negotiations/reject.php        -- Reject negotiation
GET  /api/team/profile.php               -- Get team data
GET  /api/team/settings.php              -- Get/update team settings
POST /api/production/shadow-prices.php   -- Recalculate shadow prices
GET  /api/leaderboard/standings.php      -- Team rankings
```

### Chemical Colors (UI)
- **C** - Blue
- **N** - Purple
- **D** - Yellow
- **Q** - Red

### Game Constants
```
Initial Inventory: 200 gal each (C/N/D/Q)
Initial Funds: $5000
Deicer Recipe: 25C + 15N + 10D = 50 gal → $100
Solvent Recipe: 5N + 7D + 8Q = 20 gal → $60
```

---

## Questions?

When working on this codebase, remember:
1. **Shadow prices are central** - most game logic revolves around them
2. **NPCs use strategies** - don't modify NPCManager directly, edit strategy classes
3. **Relative paths everywhere** - never use absolute URLs
4. **LP solver is critical** - production optimization drives the entire game
5. **Non-zero-sum trades** - the educational goal is showing mutual benefit

For debugging, start with:
1. Check team inventory/funds in database
2. Verify shadow prices are calculated
3. Check session status (is market open?)
4. Look at error logs for NPC decisions

---

*This guide was created to help AI assistants quickly understand the CNDQ codebase structure, key concepts, and common workflows. For detailed information, refer to the individual documentation files linked throughout.*
