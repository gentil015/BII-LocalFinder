<?php
require_once 'config/database.php';

$db = Database::getInstance()->getConnection();

// Check for negotiable services
$stmt = $db->query("
    SELECT ps.id, ps.name, ps.negotiable, ps.price, ps.min_price, ps.max_price, ps.provider_id
    FROM provider_services ps 
    WHERE ps.negotiable = 1 
    LIMIT 10
");

$negotiable_services = $stmt->fetchAll();

echo "=== NEGOTIABLE SERVICES ===\n";
if (empty($negotiable_services)) {
    echo "❌ NO NEGOTIABLE SERVICES FOUND IN DATABASE!\n";
} else {
    echo "✅ Found " . count($negotiable_services) . " negotiable services:\n";
    foreach ($negotiable_services as $service) {
        echo "\n" . str_repeat("-", 50) . "\n";
        echo "ID: " . $service['id'] . "\n";
        echo "Name: " . $service['name'] . "\n";
        echo "Provider ID: " . $service['provider_id'] . "\n";
        echo "Negotiable: " . ($service['negotiable'] ? 'YES' : 'NO') . "\n";
        echo "Base Price: " . $service['price'] . "\n";
        echo "Min Price: " . $service['min_price'] . "\n";
        echo "Max Price: " . $service['max_price'] . "\n";
    }
}

echo "\n\n=== ALL SERVICES (FIRST 10) ===\n";
$stmt = $db->query("
    SELECT ps.id, ps.name, ps.negotiable, ps.price, ps.provider_id
    FROM provider_services ps 
    LIMIT 10
");

$all_services = $stmt->fetchAll();
echo "Total services in database: " . count($all_services) . "\n";
foreach ($all_services as $service) {
    $neg_label = $service['negotiable'] ? '✅ NEGOTIABLE' : '❌ FIXED';
    echo $neg_label . " | ID: " . $service['id'] . " | " . $service['name'] . " | Provider: " . $service['provider_id'] . "\n";
}
?>
