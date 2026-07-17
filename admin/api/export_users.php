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

// Get user data
$users = $db->query("
    SELECT u.*, 
           COUNT(b.id) as booking_count,
           (SELECT COUNT(*) FROM reviews r WHERE r.client_id = u.id) as review_count
    FROM users u
    LEFT JOIN bookings b ON u.id = b.client_id
    GROUP BY u.id
    ORDER BY u.created_at DESC
")->fetchAll();

if ($format === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="users_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Name', 'Email', 'Phone', 'Type', 'Verified', 'Bookings', 'Reviews', 'Registered']);
    
    foreach ($users as $user) {
        fputcsv($output, [
            $user['id'],
            $user['full_name'],
            $user['email'],
            $user['phone'],
            $user['user_type'],
            $user['is_verified'] ? 'Yes' : 'No',
            $user['booking_count'],
            $user['review_count'],
            $user['created_at']
        ]);
    }
    fclose($output);
} else {
    // PDF export would be implemented with a PDF library like TCPDF
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="users_report_' . date('Y-m-d') . '.pdf"');
    // PDF generation code here
}
?>