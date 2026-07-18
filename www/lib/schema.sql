-- CNDQ Database Schema
-- Single database design with clear separation for future split into multiple databases
-- Database: cndq.db (will split into teams.db, marketplace.db, negotiations.db, config.db)

-- =============================================================================
-- TEAM STORAGE TABLES (Future: teams.db)
-- =============================================================================

-- Team events table - Event sourcing for all team actions
CREATE TABLE IF NOT EXISTS team_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    team_email TEXT NOT NULL,
    team_name TEXT,
    event_type TEXT NOT NULL,
    payload TEXT NOT NULL,           -- JSON payload
    timestamp REAL NOT NULL,          -- Microtime for ordering
    created_at INTEGER DEFAULT (strftime('%s', 'now'))
);

-- Indexes for fast team event queries
CREATE INDEX IF NOT EXISTS idx_team_events_email ON team_events(team_email, timestamp);
CREATE INDEX IF NOT EXISTS idx_team_events_type ON team_events(event_type);
CREATE INDEX IF NOT EXISTS idx_team_events_timestamp ON team_events(timestamp);

-- Team state cache - Aggregated state for fast reads
CREATE TABLE IF NOT EXISTS team_state_cache (
    team_email TEXT PRIMARY KEY,
    state TEXT NOT NULL,              -- JSON aggregated state
    last_event_id INTEGER,
    events_processed INTEGER DEFAULT 0,
    updated_at INTEGER DEFAULT (strftime('%s', 'now')),
    FOREIGN KEY (last_event_id) REFERENCES team_events(id)
);

-- Team snapshots - Periodic baseline snapshots for faster aggregation
CREATE TABLE IF NOT EXISTS team_snapshots (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    team_email TEXT NOT NULL,
    state TEXT NOT NULL,              -- JSON state at snapshot time
    event_count INTEGER NOT NULL,     -- Number of events processed
    snapshot_at INTEGER DEFAULT (strftime('%s', 'now')),
    created_at INTEGER DEFAULT (strftime('%s', 'now'))
);

CREATE INDEX IF NOT EXISTS idx_team_snapshots_email ON team_snapshots(team_email, created_at DESC);

-- =============================================================================
-- MARKETPLACE TABLES (Future: marketplace.db)
-- =============================================================================

-- Marketplace events - Shared event log for offers, buy orders, ads
CREATE TABLE IF NOT EXISTS marketplace_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    team_email TEXT NOT NULL,
    team_name TEXT,
    event_type TEXT NOT NULL,         -- add_offer, remove_offer, add_buy_order, etc.
    payload TEXT NOT NULL,            -- JSON payload
    timestamp REAL NOT NULL,
    created_at INTEGER DEFAULT (strftime('%s', 'now'))
);

CREATE INDEX IF NOT EXISTS idx_marketplace_events_type ON marketplace_events(event_type);
CREATE INDEX IF NOT EXISTS idx_marketplace_events_timestamp ON marketplace_events(timestamp DESC);
CREATE INDEX IF NOT EXISTS idx_marketplace_events_team ON marketplace_events(team_email);

-- Marketplace snapshot - Cached aggregated marketplace state
CREATE TABLE IF NOT EXISTS marketplace_snapshot (
    id INTEGER PRIMARY KEY CHECK (id = 1),  -- Singleton table
    offers TEXT,                      -- JSON array of active offers
    buy_orders TEXT,                  -- JSON array of active buy orders
    ads TEXT,                         -- JSON array of active ads
    recent_trades TEXT,               -- JSON array of recent trades (global)
    last_event_id INTEGER,
    updated_at INTEGER DEFAULT (strftime('%s', 'now')),
    FOREIGN KEY (last_event_id) REFERENCES marketplace_events(id)
);

-- Insert singleton row
INSERT OR IGNORE INTO marketplace_snapshot (id, offers, buy_orders, ads, recent_trades)
VALUES (1, '[]', '[]', '[]', '[]');

-- =============================================================================
-- NEGOTIATIONS TABLES (Future: negotiations.db)
-- =============================================================================

-- Negotiations - Private negotiation sessions between teams
CREATE TABLE IF NOT EXISTS negotiations (
    id TEXT PRIMARY KEY,              -- neg_timestamp_hash
    chemical TEXT NOT NULL,           -- C, N, D, or Q
    type TEXT NOT NULL,               -- 'buy' or 'sell' from initiator perspective
    initiator_id TEXT NOT NULL,
    initiator_name TEXT,
    responder_id TEXT NOT NULL,
    responder_name TEXT,
    session_number INTEGER,
    status TEXT NOT NULL DEFAULT 'pending',  -- pending, accepted, rejected
    last_offer_by TEXT,
    accepted_by TEXT,
    rejected_by TEXT,
    rejection_reason TEXT,            -- Reason for rejection (e.g., "Insufficient inventory")
    created_at INTEGER DEFAULT (strftime('%s', 'now')),
    updated_at INTEGER DEFAULT (strftime('%s', 'now')),
    accepted_at INTEGER,
    rejected_at INTEGER,
    ad_id TEXT                        -- Reference to marketplace ad if negotiation started from ad
);

-- Migration: Add rejection_reason column to existing negotiations table (safe to run multiple times)
-- SQLite doesn't support ADD COLUMN IF NOT EXISTS, so we check and add programmatically
-- This is handled in Database.php updateSchema() method

CREATE INDEX IF NOT EXISTS idx_negotiations_initiator ON negotiations(initiator_id, status);
CREATE INDEX IF NOT EXISTS idx_negotiations_responder ON negotiations(responder_id, status);
CREATE INDEX IF NOT EXISTS idx_negotiations_status ON negotiations(status, updated_at);

-- Negotiation offers - Counter-offers within a negotiation
CREATE TABLE IF NOT EXISTS negotiation_offers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    negotiation_id TEXT NOT NULL,
    from_team_id TEXT NOT NULL,
    from_team_name TEXT,
    quantity REAL NOT NULL,
    price REAL NOT NULL,
    heat_is_hot INTEGER DEFAULT 0,    -- Boolean: is this a mutually beneficial deal?
    heat_total REAL,                  -- Total heat (combined gain)
    created_at INTEGER DEFAULT (strftime('%s', 'now')),
    FOREIGN KEY (negotiation_id) REFERENCES negotiations(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_negotiation_offers_neg_id ON negotiation_offers(negotiation_id, created_at);

-- =============================================================================
-- CONFIG TABLES (Future: config.db)
-- =============================================================================

-- Configuration key-value store for NPCs, admin settings, etc.
CREATE TABLE IF NOT EXISTS config (
    key TEXT PRIMARY KEY,
    value TEXT NOT NULL,              -- JSON value
    updated_at INTEGER DEFAULT (strftime('%s', 'now'))
);

-- =============================================================================
-- UTILITY VIEWS
-- =============================================================================

-- View: Active negotiations per team
CREATE VIEW IF NOT EXISTS v_active_negotiations AS
SELECT
    n.id,
    n.chemical,
    n.type,
    n.initiator_id,
    n.initiator_name,
    n.responder_id,
    n.responder_name,
    n.status,
    n.last_offer_by,
    n.updated_at,
    COUNT(o.id) as offer_count
FROM negotiations n
LEFT JOIN negotiation_offers o ON n.id = o.negotiation_id
WHERE n.status = 'pending'
GROUP BY n.id;

-- View: Team event count
CREATE VIEW IF NOT EXISTS v_team_event_counts AS
SELECT
    team_email,
    team_name,
    COUNT(*) as event_count,
    MIN(timestamp) as first_event,
    MAX(timestamp) as last_event
FROM team_events
GROUP BY team_email;

-- View: Marketplace event summary
CREATE VIEW IF NOT EXISTS v_marketplace_summary AS
SELECT
    event_type,
    COUNT(*) as count,
    MAX(timestamp) as last_timestamp
FROM marketplace_events
GROUP BY event_type;

-- =============================================================================
-- CLEANUP TRIGGERS (for automatic garbage collection)
-- =============================================================================

-- Trigger: Auto-delete old completed negotiations after 7 days
CREATE TRIGGER IF NOT EXISTS cleanup_old_negotiations
AFTER UPDATE ON negotiations
WHEN NEW.status IN ('accepted', 'rejected')
  AND NEW.updated_at < (strftime('%s', 'now') - 604800)  -- 7 days in seconds
BEGIN
    DELETE FROM negotiations WHERE id = NEW.id;
END;

-- =============================================================================
-- MIGRATION HELPER TABLES
-- =============================================================================

-- Track file migration progress
CREATE TABLE IF NOT EXISTS migration_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    migration_type TEXT NOT NULL,     -- 'team_events', 'marketplace', 'negotiations'
    source_file TEXT,
    status TEXT NOT NULL,             -- 'success', 'error', 'skipped'
    error_message TEXT,
    migrated_at INTEGER DEFAULT (strftime('%s', 'now'))
);

CREATE INDEX IF NOT EXISTS idx_migration_log_type ON migration_log(migration_type, status);