<?php
require_once 'config/database.php';
$db = Database::getInstance()->getConnection();

// Test query to check if responded_at is being calculated correctly
$stmt = $db->prepare('
    SELECT
        sp.id,
        sp.full_name,
        COALESCE(AVG(TIMESTAMPDIFF(HOUR, b.created_at, b.responded_at)), 24.0) AS avg_response_time
    FROM service_providers sp
    LEFT JOIN bookings b ON b.provider_id = sp.id AND b.responded_at IS NOT NULL
    WHERE sp.is_active = 1
    GROUP BY sp.id, sp.full_name
    LIMIT 5
');
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo 'Testing ML response time calculation:\n';
foreach ($results as $result) {
    echo 'Provider: ' . $result['full_name'] . ' - Avg Response Time: ' . round($result['avg_response_time'], 2) . ' hours\n';
}

// Also check how many bookings have responded_at set
$stmt = $db->prepare('SELECT COUNT(*) as total_bookings, COUNT(responded_at) as responded_bookings FROM bookings');
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
echo '\nBooking Response Stats:\n';
echo 'Total Bookings: ' . $stats['total_bookings'] . '\n';
echo 'Bookings with Response Time: ' . $stats['responded_bookings'] . '\n';
?>