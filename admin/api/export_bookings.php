<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied');
}

$db = Database::getInstance()->getConnection();
$format = $_GET['format'] ?? 'csv';

// Get booking data with filters
$query = "
    SELECT b.*, u1.full_name as client_name, u2.full_name as provider_name, sp.profession
    FROM bookings b
    JOIN users u1 ON b.client_id = u1.id
    JOIN service_providers sp ON b.provider_id = sp.id
    JOIN users u2 ON sp.user_id = u2.id
    ORDER BY b.created_at DESC
";

$bookings = $db->query($query)->fetchAll();

if ($format === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="bookings_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Client', 'Provider', 'Service', 'Status', 'Amount', 'Created']);
    
    foreach ($bookings as $booking) {
        fputcsv($output, [
            $booking['id'],
            $booking['client_name'],
            $booking['provider_name'],
            substr($booking['service_description'], 0, 50),
            $booking['status'],
            $booking['hourly_rate'] ? 'RWF ' . $booking['hourly_rate'] : 'N/A',
            $booking['created_at']
        ]);
    }
    fclose($output);
}
?>