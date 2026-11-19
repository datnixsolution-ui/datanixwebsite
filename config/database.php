<?php
/**
 * DATNIX SOLUTIONS - Hardened Database Connection Handler
 * Tagline: DATA. PROCESS. PERFORMANCE.
 */

class Database {
    private static ?PDO $instance = null;
    private static array $config = [];

    /**
     * Prevent direct construction
     */
    private function __construct() {}
    private function __clone() {}

    /**
     * Get or initialize PDO connection instance
     * 
     * @return PDO
     * @throws Exception If database connection or configuration fails
     */
    public static function getConnection(): PDO {
        if (self::$instance === null) {
            self::init();
        }
        return self::$instance;
    }

    /**
     * Initialize connection with hardened PDO attributes and credential validation
     */
    private static function init(): void {
        $configFile = __DIR__ . '/config.php';
        if (!file_exists($configFile)) {
            $configFile = __DIR__ . '/config.example.php';
        }
        
        $config = require $configFile;
        self::$config = $config['db'] ?? [];

        // 1. Validate mandatory database configuration
        if (empty(self::$config['database']) || empty(self::$config['username'])) {
            throw new Exception('Database configuration error: Missing database name or username. Please configure valid database credentials in config/config.php or environment variables (DB_NAME, DB_USER, DB_PASS).');
        }

        // 2. Reject insecure root with empty password in production
        $environment = $config['app']['environment'] ?? 'production';
        if ($environment !== 'development' && strtolower(self::$config['username']) === 'root' && empty(self::$config['password'])) {
            throw new Exception('Security Alert: Insecure database credentials detected. Running as "root" with an empty password is forbidden in production. Please set a dedicated database user and secure password in config/config.php.');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            self::$config['host'] ?? 'localhost',
            self::$config['port'] ?? 3306,
            self::$config['database'],
            self::$config['charset'] ?? 'utf8mb4'
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 2,
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci'
        ];

        $connected = false;
        try {
            self::$instance = new PDO($dsn, self::$config['username'], self::$config['password'], $options);
            $connected = true;
        } catch (PDOException $e) {
            // Auto-Discovery: If database name differs or was freshly created in cPanel
            try {
                $genericDsn = sprintf(
                    'mysql:host=%s;port=%d;charset=%s',
                    self::$config['host'] ?? 'localhost',
                    self::$config['port'] ?? 3306,
                    self::$config['charset'] ?? 'utf8mb4'
                );
                $tempPdo = new PDO($genericDsn, self::$config['username'], self::$config['password'], $options);
                $stmt = $tempPdo->query("SHOW DATABASES");
                $databases = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
                $prefix = strtolower(self::$config['username']);
                $matchedDb = null;
                foreach ($databases as $dbName) {
                    if (strpos(strtolower($dbName), $prefix) === 0) {
                        $matchedDb = $dbName;
                        break;
                    }
                }
                if ($matchedDb) {
                    $tempPdo->exec("USE `" . str_replace("`", "``", $matchedDb) . "`");
                    self::$instance = $tempPdo;
                    $connected = true;
                }
            } catch (Throwable $discoveryErr) {
                // Auto-discovery failed
            }

            if (!$connected) {
                error_log('[DATNIX DB ERROR] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
                throw new Exception('Database connection failed. Please ensure MySQL service is running and credentials are valid in config/config.php.');
            }
        }

        // Auto-Provision: Check if tables exist; if fresh install, self-provision database.sql
        if ($connected && self::$instance !== null) {
            try {
                $tblCheck = self::$instance->query("SHOW TABLES LIKE 'admins'");
                if ($tblCheck && $tblCheck->rowCount() === 0) {
                    $sqlFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'database.sql';
                    if (file_exists($sqlFile)) {
                        $schema = @file_get_contents($sqlFile);
                        if (!empty($schema)) {
                            // Split statements safely and execute
                            self::$instance->exec($schema);
                        }
                    }
                }
            } catch (Throwable $provErr) {
                error_log('[DATNIX AUTO-PROVISION NOTICE] ' . $provErr->getMessage());
            }
        }
    }

    /**
     * Test if database connection is currently operational
     * 
     * @return bool
     */
    public static function isConnected(): bool {
        try {
            $pdo = self::getConnection();
            $pdo->query('SELECT 1');
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
}
