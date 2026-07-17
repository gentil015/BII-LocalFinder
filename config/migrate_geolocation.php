<?php
// config/migrate_geolocation.php
// Run this file ONCE to set up geospatial features
// Access via browser: http://localhost/xampp/htdocs/Bii_localFinder/config/migrate_geolocation.php

require_once 'database.php';

$db = Database::getInstance()->getConnection();

echo "<pre>";
echo "Starting Geolocation Migration...\n";
echo "================================\n\n";

try {
    // Step 1: Add geospatial columns to service_providers table
    echo "[1/3] Adding geospatial columns to service_providers table...\n";
    
    // Check if columns already exist
    $colsStmt = $db->query("SHOW COLUMNS FROM service_providers");
    $columns = $colsStmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('latitude', $columns)) {
        $db->exec("ALTER TABLE service_providers ADD COLUMN latitude DECIMAL(10, 8) NULL AFTER sector");
        echo "✓ Added latitude column\n";
    } else {
        echo "✓ latitude column already exists\n";
    }
    
    if (!in_array('longitude', $columns)) {
        $db->exec("ALTER TABLE service_providers ADD COLUMN longitude DECIMAL(11, 8) NULL AFTER latitude");
        echo "✓ Added longitude column\n";
    } else {
        echo "✓ longitude column already exists\n";
    }
    
    // Check if index exists
    $indexStmt = $db->query("SHOW INDEXES FROM service_providers WHERE Key_name = 'idx_coordinates'");
    if ($indexStmt->rowCount() === 0) {
        $db->exec("ALTER TABLE service_providers ADD INDEX idx_coordinates (latitude, longitude)");
        echo "✓ Added geospatial index\n";
    } else {
        echo "✓ Geospatial index already exists\n";
    }
    
    echo "\n[2/3] Creating location_coordinates reference table...\n";
    
    // Create location_coordinates table if it doesn't exist
    $db->exec("
        CREATE TABLE IF NOT EXISTS location_coordinates (
            id INT PRIMARY KEY AUTO_INCREMENT,
            location_name VARCHAR(100) NOT NULL UNIQUE,
            district VARCHAR(50),
            sector VARCHAR(50),
            latitude DECIMAL(10, 8) NOT NULL,
            longitude DECIMAL(11, 8) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_location_name (location_name),
            INDEX idx_district (district),
            INDEX idx_sector (sector)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✓ Created location_coordinates table\n";
    
    echo "\n[3/3] Inserting Rwanda location coordinates...\n";
    
    // Insert Rwanda locations with coordinates
    $locations = [
        // Kigali districts
        ['Kigali', 'Kigali', null, -1.9536, 30.0588],
        ['Gasabo', 'Gasabo', null, -1.9485, 30.1234],
        ['Kicukiro', 'Kicukiro', null, -1.9655, 30.0345],
        ['Nyarugenge', 'Nyarugenge', null, -1.9508, 30.0557],
        
        // Gasabo sectors
        ['Kimironko', 'Gasabo', 'Kimironko', -1.9452, 30.1567],
        ['Remera', 'Gasabo', 'Remera', -1.9389, 30.0945],
        ['Kacyiru', 'Gasabo', 'Kacyiru', -1.9578, 30.1089],
        ['Nyarutarama', 'Gasabo', 'Nyarutarama', -1.9612, 30.1234],
        ['Gisozi', 'Gasabo', 'Gisozi', -1.9434, 30.0876],
        
        // Kicukiro sectors
        ['Gikondo', 'Kicukiro', 'Gikondo', -1.9678, 30.0123],
        ['Nyarugunga', 'Kicukiro', 'Nyarugunga', -1.9789, 30.0234],
        ['Gahanga', 'Kicukiro', 'Gahanga', -1.9845, 30.0456],
        
        // Nyarugenge sectors
        ['Nyamirambo', 'Nyarugenge', 'Nyamirambo', -1.9601, 30.0234],
        ['Muhima', 'Nyarugenge', 'Muhima', -1.9545, 30.0489],
        ['Kimisagara', 'Nyarugenge', 'Kimisagara', -1.9634, 30.0345],
        
        // Other districts
        ['Musanze', 'Musanze', null, -1.4977, 29.6371],
        ['Rubavu', 'Rubavu', null, -2.0597, 29.2554],
        ['Gisenyi', 'Rubavu', 'Gisenyi', -2.0639, 29.2534],
        ['Huye', 'Huye', null, -2.6047, 29.7406],
        ['Muhanga', 'Muhanga', null, -2.0060, 30.4701],
        ['Gitarama', 'Muhanga', 'Gitarama', -2.0082, 30.4756],
        ['Nyanza', 'Nyanza', null, -2.4299, 29.9387],
        ['Rusizi', 'Rusizi', null, -2.5066, 29.0328],
        ['Cyangugu', 'Rusizi', 'Cyangugu', -2.4911, 29.2511],
        ['Rwamagana', 'Bugesera', null, -2.1404, 30.4653],
        ['Karongi', 'Karongi', null, -2.0641, 29.2549],
        ['Kibuye', 'Karongi', 'Kibuye', -2.0660, 29.2583],
    ];
    
    $insertStmt = $db->prepare("
        INSERT IGNORE INTO location_coordinates (location_name, district, sector, latitude, longitude)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $count = 0;
    foreach ($locations as $loc) {
        $insertStmt->execute($loc);
        $count++;
    }
    
    echo "✓ Inserted $count location coordinates\n";
    
    echo "\n================================\n";
    echo "✓ Migration completed successfully!\n";
    echo "\nNext steps:\n";
    echo "1. Verify that service_providers now has latitude/longitude columns\n";
    echo "2. Update provider profiles with their coordinates\n";
    echo "3. Run smart booking and providers will be sorted by distance + rating\n";
    
} catch (Exception $e) {
    echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "</pre>";
?>