<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

$db = Database::getInstance()->getConnection();
$format = $_GET['export'] ?? 'csv';
$filter_status = $_GET['status'] ?? 'all';

// Build query
$query = "
    SELECT 
        vd.id,
        vd.document_type,
        vd.document_path,
        vd.status,
        vd.uploaded_at,
        vd.reviewed_at,
        vd.notes AS rejection_reason,
        u.full_name as provider_name,
        u.email as provider_email,
        sp.profession,
        sp.verification_level,
        u2.full_name as reviewer_name
    FROM verification_documents vd
    JOIN service_providers sp ON vd.provider_id = sp.id
    JOIN users u ON sp.user_id = u.id
    LEFT JOIN users u2 ON vd.reviewer_id = u2.id
    WHERE 1=1
";

$params = [];
if ($filter_status !== 'all') {
    $query .= " AND vd.status = ?";
    $params[] = $filter_status;
}

$query .= " ORDER BY vd.uploaded_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$verifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($format === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="verifications_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Headers
    fputcsv($output, [
        'ID', 'Provider Name', 'Email', 'Profession', 
        'Document Type', 'Status', 'Verification Level',
        'Uploaded At', 'Reviewed At', 'Reviewer', 'Rejection Reason'
    ]);
    
    // Data
    foreach ($verifications as $row) {
        fputcsv($output, [
            $row['id'],
            $row['provider_name'],
            $row['provider_email'],
            $row['profession'],
            $row['document_type'],
            $row['status'],
            $row['verification_level'],
            $row['uploaded_at'],
            $row['reviewed_at'],
            $row['reviewer_name'],
            $row['rejection_reason']
        ]);
    }
    
    fclose($output);
    exit;
}
?>