<?php
/**
 * Google Calendar Database Migration
 * 
 * Adds google_event_id column to bookings table to track synced events
 * Run this migration to enable booking-to-calendar sync
 */

require_once __DIR__ . '/../config/database.php';

class GoogleCalendarMigration {
    
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Run all migrations
     */
    public function run() {
        try {
            echo "Starting Google Calendar migrations...\n";
            
            $this->addGoogleEventIdToBookings();
            echo "✓ Added google_event_id to bookings\n";
            
            $this->createGoogleCalendarTokensTable();
            echo "✓ Created google_calendar_tokens table\n";
            
            $this->addGoogleCalendarIdSetting();
            echo "✓ Added google_calendar_id setting\n";
            
            echo "\n✓ All migrations completed successfully!\n";
            return true;
            
        } catch (Exception $e) {
            echo "✗ Migration failed: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Add google_event_id column to bookings table
     */
    private function addGoogleEventIdToBookings() {
        // Check if column already exists
        $result = $this->db->query("SHOW COLUMNS FROM bookings LIKE 'google_event_id'");
        
        if ($result->rowCount() > 0) {
            echo "  • google_event_id column already exists\n";
            return;
        }
        
        $sql = "
            ALTER TABLE bookings 
            ADD COLUMN google_event_id VARCHAR(255) DEFAULT NULL AFTER `status`,
            ADD INDEX idx_google_event_id (google_event_id)
        ";
        
        $this->db->exec($sql);
    }
    
    /**
     * Create google_calendar_tokens table
     */
    private function createGoogleCalendarTokensTable() {
        // Check if table already exists
        $result = $this->db->query("SHOW TABLES LIKE 'google_calendar_tokens'");
        
        if ($result->rowCount() > 0) {
            echo "  • google_calendar_tokens table already exists\n";
            return;
        }
        
        $sql = "
            CREATE TABLE google_calendar_tokens (
                id INT PRIMARY KEY AUTO_INCREMENT,
                provider_id INT NOT NULL UNIQUE,
                access_token LONGTEXT NOT NULL,
                refresh_token LONGTEXT DEFAULT NULL,
                expires_in INT DEFAULT NULL,
                expires_at INT DEFAULT NULL,
                token_type VARCHAR(50) DEFAULT 'Bearer',
                scope LONGTEXT DEFAULT NULL,
                authenticated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (provider_id) REFERENCES service_providers(id) ON DELETE CASCADE,
                INDEX idx_provider (provider_id),
                INDEX idx_expires_at (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ";
        
        $this->db->exec($sql);
    }
    
    /**
     * Add google_calendar_id setting to provider_settings
     */
    private function addGoogleCalendarIdSetting() {
        // This is informational - the setting will be created when needed
        echo "  • Settings will be created automatically on first auth\n";
    }
    
    /**
     * Rollback all migrations
     */
    public function rollback() {
        try {
            echo "Rolling back Google Calendar migrations...\n";
            
            // Drop google_event_id from bookings
            $this->db->query("
                ALTER TABLE bookings 
                DROP INDEX idx_google_event_id,
                DROP COLUMN google_event_id
            ");
            echo "✓ Removed google_event_id from bookings\n";
            
            // Drop google_calendar_tokens table
            $this->db->exec("DROP TABLE IF EXISTS google_calendar_tokens");
            echo "✓ Dropped google_calendar_tokens table\n";
            
            // Remove settings
            $this->db->exec("
                DELETE FROM provider_settings 
                WHERE setting_key LIKE 'google_%'
            ");
            echo "✓ Removed Google Calendar settings\n";
            
            echo "\n✓ Rollback completed!\n";
            return true;
            
        } catch (Exception $e) {
            echo "✗ Rollback failed: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Get migration status
     */
    public static function getStatus() {
        try {
            $db = Database::getInstance()->getConnection();
            
            $status = [
                'google_event_id_exists' => false,
                'google_calendar_tokens_exists' => false,
                'migration_status' => 'Not migrated'
            ];
            
            // Check if bookings has google_event_id
            $result = $db->query("SHOW COLUMNS FROM bookings LIKE 'google_event_id'");
            $status['google_event_id_exists'] = $result->rowCount() > 0;
            
            // Check if google_calendar_tokens table exists
            $result = $db->query("SHOW TABLES LIKE 'google_calendar_tokens'");
            $status['google_calendar_tokens_exists'] = $result->rowCount() > 0;
            
            // Determine overall status
            if ($status['google_event_id_exists'] && $status['google_calendar_tokens_exists']) {
                $status['migration_status'] = 'Migrated';
            } elseif ($status['google_event_id_exists'] || $status['google_calendar_tokens_exists']) {
                $status['migration_status'] = 'Partially migrated';
            }
            
            return $status;
            
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}

// Handle CLI execution
if (php_sapi_name() === 'cli') {
    $action = $argv[1] ?? 'run';
    
    if ($action === 'rollback') {
        $migration = new GoogleCalendarMigration();
        $success = $migration->rollback();
        exit($success ? 0 : 1);
    } elseif ($action === 'status') {
        $status = GoogleCalendarMigration::getStatus();
        echo json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit(0);
    } else {
        $migration = new GoogleCalendarMigration();
        $success = $migration->run();
        exit($success ? 0 : 1);
    }
} else {
    // Web interface
    $status = GoogleCalendarMigration::getStatus();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Google Calendar Migration</title>
        <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
        <style>
            body {
                background: #f5f7fb;
                padding: 2rem;
            }
            .container {
                max-width: 600px;
                margin-top: 2rem;
            }
            .card {
                border-radius: 12px;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            }
            .status-item {
                padding: 1rem;
                border-bottom: 1px solid #e9ecef;
            }
            .status-item:last-child {
                border-bottom: none;
            }
            .badge-success {
                background: #d4edda;
                color: #155724;
            }
            .badge-warning {
                background: #fff3cd;
                color: #856404;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0">Google Calendar Migration Status</h3>
                </div>
                <div class="card-body">
                    <?php if (isset($status['error'])): ?>
                        <div class="alert alert-danger">
                            Error: <?php echo htmlspecialchars($status['error']); ?>
                        </div>
                    <?php else: ?>
                        <div class="status-item">
                            <strong>Overall Status:</strong>
                            <span class="badge badge-lg badge-<?php echo $status['migration_status'] === 'Migrated' ? 'success' : 'warning'; ?>">
                                <?php echo $status['migration_status']; ?>
                            </span>
                        </div>
                        
                        <div class="status-item">
                            <strong>google_event_id Column:</strong>
                            <span class="badge badge-<?php echo $status['google_event_id_exists'] ? 'success' : 'danger'; ?>">
                                <?php echo $status['google_event_id_exists'] ? '✓ Exists' : '✗ Missing'; ?>
                            </span>
                        </div>
                        
                        <div class="status-item">
                            <strong>google_calendar_tokens Table:</strong>
                            <span class="badge badge-<?php echo $status['google_calendar_tokens_exists'] ? 'success' : 'danger'; ?>">
                                <?php echo $status['google_calendar_tokens_exists'] ? '✓ Exists' : '✗ Missing'; ?>
                            </span>
                        </div>
                        
                        <div class="status-item mt-3">
                            <form method="POST" style="display: inline;">
                                <button type="submit" name="action" value="migrate" class="btn btn-primary">
                                    <i class="fas fa-play me-2"></i> Run Migration
                                </button>
                            </form>
                            
                            <form method="POST" style="display: inline;">
                                <button type="submit" name="action" value="rollback" class="btn btn-danger"
                                        onclick="return confirm('This will remove Google Calendar integration. Continue?')">
                                    <i class="fas fa-undo me-2"></i> Rollback
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <script src="../bootstrap/js/bootstrap.bundle.min.js"></script>
        <?php
        // Handle form submissions
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
            $migration = new GoogleCalendarMigration();
            
            if ($_POST['action'] === 'migrate') {
                if ($migration->run()) {
                    echo '<script>alert("Migration completed successfully!"); location.reload();</script>';
                } else {
                    echo '<script>alert("Migration failed. Check logs for details."); location.reload();</script>';
                }
            } elseif ($_POST['action'] === 'rollback') {
                if ($migration->rollback()) {
                    echo '<script>alert("Rollback completed successfully!"); location.reload();</script>';
                } else {
                    echo '<script>alert("Rollback failed. Check logs for details."); location.reload();</script>';
                }
            }
        }
        ?>
    </body>
    </html>
    <?php
}
