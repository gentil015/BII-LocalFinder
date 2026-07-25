<?php
// config/database.php
// PDO-based database connection helper.
// Configuration is loaded from environment variables first, then fallback defaults.

if (!function_exists('env')) {
    function env($key, $default = null) {
        $value = getenv($key);
        if ($value === false || $value === '') {
            $value = $_ENV[$key] ?? $_SERVER[$key] ?? $default;
        }
        return $value;
    }
}

if (!function_exists('loadEnvFile')) {
    function loadEnvFile() {
        $envFile = dirname(__DIR__) . '/.env';
        if (!is_file($envFile)) {
            return;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $name = trim($parts[0]);
            $value = trim($parts[1]);
            if ($name === '') {
                continue;
            }

            $value = trim($value, "\"'");
            if (!array_key_exists($name, $_ENV)) {
                $_ENV[$name] = $value;
            }
            putenv($name . '=' . $value);
        }
    }
}

loadEnvFile();

if (!defined('DB_HOST')) {
    define('DB_HOST', env('DB_HOST', '127.0.0.1'));
}
if (!defined('DB_PORT')) {
    define('DB_PORT', env('DB_PORT', '3306'));
}
if (!defined('DB_NAME')) {
    define('DB_NAME', env('DB_NAME', 'bii_localfinder'));
}
if (!defined('DB_USER')) {
    define('DB_USER', env('DB_USER', 'gentil'));
}
if (!defined('DB_PASS')) {
    define('DB_PASS', env('DB_PASS', 'Dushime330805'));
}
if (!defined('DB_CHARSET')) {
    define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));
}

/**
 * Database connection class using singleton pattern
 */
class Database {
    private static $instance = null;
    private $connection = null;

    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);

        try {
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException('Database connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Get singleton instance
     *
     * @return Database
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get the PDO connection
     *
     * @return PDO
     */
    public function getConnection() {
        return $this->connection;
    }

    // Prevent cloning of the instance
    private function __clone() {}

    // Prevent unserializing of the instance
    public function __wakeup() {
        throw new RuntimeException("Cannot unserialize singleton");
    }
}
