<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied');
}

$db = Database::getInstance()->getConnection();

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    // Get client basic info
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND user_type = 'client'");
    $stmt->execute([$id]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($client) {
        // Get client stats
        $stats = [];
        
        // Bookings count
        $stmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE client_id = ?");
        $stmt->execute([$id]);
        $stats['total_bookings'] = $stmt->fetchColumn();
        
        // Reviews count
        $stmt = $db->prepare("SELECT COUNT(*) FROM reviews WHERE client_id = ?");
        $stmt->execute([$id]);
        $stats['total_reviews'] = $stmt->fetchColumn();
        
        // Reports count
        $stmt = $db->prepare("SELECT COUNT(*) FROM reports WHERE reporter_id = ?");
        $stmt->execute([$id]);
        $stats['reports_filed'] = $stmt->fetchColumn();
        
        // Recent bookings
        $stmt = $db->prepare("SELECT * FROM bookings WHERE client_id = ? ORDER BY created_at DESC LIMIT 5");
        $stmt->execute([$id]);
        $recent_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $response = [
            'client' => $client,
            'stats' => $stats,
            'recent_bookings' => $recent_bookings
        ];
        
        header('Content-Type: application/json');
        echo json_encode($response);
    } else {
        header('HTTP/1.1 404 Not Found');
        echo json_encode(['error' => 'Client not found']);
    }
} else {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Client ID required']);
}
?>