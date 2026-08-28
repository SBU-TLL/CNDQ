<?php
/**
 * Database - SQLite connection manager and helper class
 *
 * Provides PDO connection pooling and common database operations.
 * Designed to start as single database (cndq.db) with easy path to split later.
 */

/**
 * Resolve the data directory for the current instance.
 *
 * On production, SCRIPT_FILENAME carries the unresolved symlink path set by
 * Apache, so walking up from it finds the correct per-instance data/ directory
 * even though __DIR__ resolves to the shared template code.
 * On Herd (local dev) SCRIPT_FILENAME is Valet's server.php, so we fall back
 * to the __DIR__-relative path which is correct for single-instance dev.
 */
function cndq_data_dir(): string {
    $script = $_SERVER['SCRIPT_FILENAME'] ?? '';
    if (!empty($script) && !str_contains($script, 'server.php')) {
        $pos = strpos($script, '/static/');
        if ($pos !== false) {
            return substr($script, 0, $pos) . '/data';
        }
    }
    // Herd/local dev fallback: static/lib/../../data = repo-root/data
    return __DIR__ . '/../../data';
}

class Database {
    private static $instances = [];
    private $pdo;
    private $dbPath;

    /**
     * Get database instance (singleton per database file)
     *
     * @param string $dbName Database name (default: 'cndq' for main db)
     * @return Database
     */
    public static function getInstance($dbName = 'cndq') {
        if (!isset(self::$instances[$dbName])) {
            self::$instances[$dbName] = new self($dbName);
        }
        return self::$instances[$dbName];
    }

    /**
     * Private constructor - use getInstance() instead
     */
    private function __construct($dbName) {
        $dataDir = cndq_data_dir();

        // Create data directory if it doesn't exist (and isn't a symlink)
        if (!file_exists($dataDir) && !is_link($dataDir)) {
            mkdir($dataDir, 0755, true);
        }

        // If data is a symlink, make sure the target directory exists
        if (is_link($dataDir)) {
            $target = readlink($dataDir);
            if (!is_dir($target)) {
                // Create the symlink target directory if it doesn't exist
                mkdir($target, 0755, true);
            }
        }

        $this->dbPath = $dataDir . '/' . $dbName . '.db';

        try {
            $this->pdo = new PDO('sqlite:' . $this->dbPath);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Performance optimizations for SQLite
            $this->pdo->exec('PRAGMA journal_mode = WAL');        // Write-Ahead Logging for better concurrency
            $this->pdo->exec('PRAGMA synchronous = NORMAL');      // Faster writes, still safe
            $this->pdo->exec('PRAGMA temp_store = MEMORY');       // Use memory for temp tables
            $this->pdo->exec('PRAGMA cache_size = -64000');       // 64MB cache
            $this->pdo->exec('PRAGMA foreign_keys = ON');         // Enforce foreign keys
            $this->pdo->exec('PRAGMA busy_timeout = 5000');       // Wait up to 5 seconds if database is locked

            // Check if database needs initialization or schema update
            $needsInit = false;
            $needsUpdate = false;

            try {
                $result = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='config'")->fetchAll();
                $needsInit = empty($result);

                if (!$needsInit) {
                    // Check schema version
                    $needsUpdate = $this->checkSchemaVersion();
                }
            } catch (PDOException $e) {
                // If query fails, assume we need to initialize
                $needsInit = true;
            }

            if ($needsInit) {
                $this->initializeSchema();
            } elseif ($needsUpdate) {
                $this->updateSchema();
            }
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }

    /**
     * Get PDO connection
     *
     * @return PDO
     */
    public function getPdo() {
        return $this->pdo;
    }

    /**
     * Execute a query and return all results
     *
     * @param string $sql SQL query
     * @param array $params Query parameters
     * @return array
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Database query error: " . $e->getMessage() . " | SQL: $sql");
            throw $e;
        }
    }

    /**
     * Execute a query and return single row
     *
     * @param string $sql SQL query
     * @param array $params Query parameters
     * @return array|null
     */
    public function queryOne($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result !== false ? $result : null;
        } catch (PDOException $e) {
            error_log("Database queryOne error: " . $e->getMessage() . " | SQL: $sql");
            throw $e;
        }
    }

    /**
     * Execute a statement (INSERT, UPDATE, DELETE)
     *
     * @param string $sql SQL statement
     * @param array $params Statement parameters
     * @return int Number of affected rows
     */
    public function execute($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("Database execute error: " . $e->getMessage() . " | SQL: $sql");
            throw $e;
        }
    }

    /**
     * Insert a row and return the last insert ID
     *
     * @param string $sql INSERT statement
     * @param array $params Statement parameters
     * @return int Last insert ID
     */
    public function insert($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Database insert error: " . $e->getMessage() . " | SQL: $sql");
            throw $e;
        }
    }

    /**
     * Check if a transaction is currently active
     */
    public function inTransaction() {
        return $this->pdo->inTransaction();
    }

    /**
     * Begin a transaction
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }

    /**
     * Commit a transaction
     */
    public function commit() {
        return $this->pdo->commit();
    }

    /**
     * Rollback a transaction
     */
    public function rollback() {
        return $this->pdo->rollBack();
    }

    /**
     * Execute callback within a transaction
     * Automatically commits on success, rolls back on exception
     *
     * @param callable $callback Function to execute
     * @return mixed Return value of callback
     */
    public function transaction(callable $callback) {
        $this->beginTransaction();
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * Initialize database schema
     */
    private function initializeSchema() {
        $schemaFile = __DIR__ . '/schema.sql';

        if (!file_exists($schemaFile)) {
            throw new Exception("Schema file not found: $schemaFile");
        }

        $schema = file_get_contents($schemaFile);
        $this->pdo->exec($schema);

        // Set initial schema version
        $this->setSchemaVersion($this->getExpectedSchemaVersion());
        error_log("Database initialized with schema version " . $this->getExpectedSchemaVersion());
    }

    /**
     * Check if schema needs updating
     * @return bool True if update needed
     */
    private function checkSchemaVersion() {
        $currentVersion = $this->getCurrentSchemaVersion();
        $expectedVersion = $this->getExpectedSchemaVersion();

        if ($currentVersion < $expectedVersion) {
            error_log("Schema update needed: current=$currentVersion, expected=$expectedVersion");
            return true;
        }

        return false;
    }

    /**
     * Get current schema version from database
     * @return int
     */
    private function getCurrentSchemaVersion() {
        try {
            $result = $this->pdo->query("SELECT value FROM config WHERE key = 'schema_version'")->fetch(PDO::FETCH_ASSOC);
            return $result ? (int)json_decode($result['value']) : 0;
        } catch (PDOException $e) {
            return 0;
        }
    }

    /**
     * Get expected schema version from file
     * @return int
     */
    private function getExpectedSchemaVersion() {
        $versionFile = __DIR__ . '/schema_version.txt';
        if (file_exists($versionFile)) {
            return (int)trim(file_get_contents($versionFile));
        }
        return 1; // Default to version 1 if file doesn't exist
    }

    /**
     * Set schema version in database
     * @param int $version
     */
    private function setSchemaVersion($version) {
        $this->pdo->exec("INSERT OR REPLACE INTO config (key, value, updated_at) VALUES ('schema_version', '" . json_encode($version) . "', " . time() . ")");
    }

    /**
     * Update schema to latest version
     */
    private function updateSchema() {
        $currentVersion = $this->getCurrentSchemaVersion();
        $expectedVersion = $this->getExpectedSchemaVersion();

        error_log("Updating schema from version $currentVersion to $expectedVersion");

        // Apply schema.sql (idempotent - uses CREATE IF NOT EXISTS)
        $schemaFile = __DIR__ . '/schema.sql';
        if (file_exists($schemaFile)) {
            $schema = file_get_contents($schemaFile);
            try {
                $this->pdo->exec($schema);
            } catch (PDOException $e) {
                error_log("Schema update failed: " . $e->getMessage());
                // Don't throw - allow app to continue with partial update
            }
        }

        // Apply migrations based on version
        $this->applyMigrations($currentVersion, $expectedVersion);

        $this->setSchemaVersion($expectedVersion);
        error_log("Schema updated successfully to version $expectedVersion");
    }

    /**
     * Apply incremental migrations between versions
     */
    private function applyMigrations($fromVersion, $toVersion) {
        // Migration: v2 -> v3: Add rejection_reason column to negotiations
        if ($fromVersion < 3 && $toVersion >= 3) {
            $this->addColumnIfNotExists('negotiations', 'rejection_reason', 'TEXT');
        }
    }

    /**
     * Add a column to a table if it doesn't already exist
     */
    private function addColumnIfNotExists($table, $column, $type) {
        try {
            // Check if column exists by querying table info
            $result = $this->pdo->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);
            $columnExists = false;
            foreach ($result as $col) {
                if ($col['name'] === $column) {
                    $columnExists = true;
                    break;
                }
            }

            if (!$columnExists) {
                $this->pdo->exec("ALTER TABLE $table ADD COLUMN $column $type");
                error_log("Added column $column to $table");
            }
        } catch (PDOException $e) {
            error_log("Failed to add column $column to $table: " . $e->getMessage());
        }
    }

    /**
     * Vacuum the database to reclaim space
     */
    public function vacuum() {
        $this->pdo->exec('VACUUM');
    }

    /**
     * Get database file path
     *
     * @return string
     */
    public function getPath() {
        return $this->dbPath;
    }

    /**
     * Get database file size in bytes
     *
     * @return int
     */
    public function getSize() {
        return file_exists($this->dbPath) ? filesize($this->dbPath) : 0;
    }

    /**
     * Get database statistics
     *
     * @return array
     */
    public function getStats() {
        $stats = [
            'path' => $this->dbPath,
            'size' => $this->getSize(),
            'size_mb' => round($this->getSize() / 1024 / 1024, 2),
        ];

        // Get table row counts
        $stats['tables'] = [];
        $tables = $this->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
        foreach ($tables as $table) {
            $tableName = $table['name'];
            $count = $this->queryOne("SELECT COUNT(*) as count FROM " . $tableName);
            $stats['tables'][$tableName] = $count['count'];
        }

        return $stats;
    }
}
