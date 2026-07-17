<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check admin access
if (!isLoggedIn() || !isAdmin()) {
    redirect('../../login.php');
}

$db = Database::getInstance()->getConnection();

// Get export parameters
$format = $_GET['format'] ?? 'pdf';
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query for clients
$query = "SELECT * FROM users WHERE user_type = 'client'";
$params = [];

if (!empty($search)) {
    $query .= " AND (full_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
}

if (!empty($status_filter)) {
    $query .= " AND is_verified = ?";
    $params[] = $status_filter === 'verified' ? 1 : 0;
}

if (!empty($date_from)) {
    $query .= " AND DATE(created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND DATE(created_at) <= ?";
    $params[] = $date_to;
}

$query .= " ORDER BY created_at DESC";

// Execute query
$stmt = $db->prepare($query);
$stmt->execute($params);
$clients = $stmt->fetchAll();

// Function to get client statistics
function getClientStats($db, $client_id) {
    $stats = [];
    
    // Bookings count
    $stmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE client_id = ?");
    $stmt->execute([$client_id]);
    $stats['total_bookings'] = $stmt->fetchColumn();
    
    // Reviews count
    $stmt = $db->prepare("SELECT COUNT(*) FROM reviews WHERE client_id = ?");
    $stmt->execute([$client_id]);
    $stats['total_reviews'] = $stmt->fetchColumn();
    
    // Reports count
    $stmt = $db->prepare("SELECT COUNT(*) FROM reports WHERE reporter_id = ?");
    $stmt->execute([$client_id]);
    $stats['reports_filed'] = $stmt->fetchColumn();
    
    return $stats;
}

// Generate statistics for export
$total_clients = count($clients);
$verified_clients = count(array_filter($clients, function($client) {
    return $client['is_verified'];
}));
$pending_clients = $total_clients - $verified_clients;

// Export based on format
switch ($format) {
    case 'pdf':
        exportPDF($clients, $total_clients, $verified_clients, $pending_clients);
        break;
    case 'excel':
        exportExcel($clients, $total_clients, $verified_clients, $pending_clients);
        break;
    case 'csv':
        exportCSV($clients, $total_clients, $verified_clients, $pending_clients);
        break;
    case 'docx':
        exportDOCX($clients, $total_clients, $verified_clients, $pending_clients);
        break;
    default:
        exportPDF($clients, $total_clients, $verified_clients, $pending_clients);
}

function exportPDF($clients, $total_clients, $verified_clients, $pending_clients) {
    require_once '../../vendor/autoload.php'; // Make sure to install TCPDF via composer
    
    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('BII LocalFinder');
    $pdf->SetAuthor('BII LocalFinder');
    $pdf->SetTitle('Clients Report');
    $pdf->SetSubject('Clients Management Report');
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Clients Management Report', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 10, 'Generated on: ' . date('Y-m-d H:i:s'), 0, 1, 'C');
    $pdf->Ln(10);
    
    // Statistics
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Summary Statistics', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, "Total Clients: {$total_clients}", 0, 1);
    $pdf->Cell(0, 6, "Verified Clients: {$verified_clients}", 0, 1);
    $pdf->Cell(0, 6, "Pending Clients: {$pending_clients}", 0, 1);
    $pdf->Ln(10);
    
    // Table header
    $pdf->SetFont('helvetica', 'B', 10);
    $header = array('ID', 'Name', 'Email', 'Phone', 'Status', 'Bookings', 'Reviews', 'Reports', 'Registered');
    $w = array(15, 40, 50, 35, 25, 25, 25, 25, 30);
    
    for($i = 0; $i < count($header); $i++) {
        $pdf->Cell($w[$i], 7, $header[$i], 1, 0, 'C');
    }
    $pdf->Ln();
    
    // Table data
    $pdf->SetFont('helvetica', '', 8);
    foreach($clients as $client) {
        $stats = getClientStats($GLOBALS['db'], $client['id']);
        $pdf->Cell($w[0], 6, $client['id'], 'LR', 0, 'C');
        $pdf->Cell($w[1], 6, substr($client['full_name'], 0, 25), 'LR', 0, 'L');
        $pdf->Cell($w[2], 6, substr($client['email'], 0, 30), 'LR', 0, 'L');
        $pdf->Cell($w[3], 6, $client['phone'] ?? 'N/A', 'LR', 0, 'C');
        $pdf->Cell($w[4], 6, $client['is_verified'] ? 'Active' : 'Suspended', 'LR', 0, 'C');
        $pdf->Cell($w[5], 6, $stats['total_bookings'], 'LR', 0, 'C');
        $pdf->Cell($w[6], 6, $stats['total_reviews'], 'LR', 0, 'C');
        $pdf->Cell($w[7], 6, $stats['reports_filed'], 'LR', 0, 'C');
        $pdf->Cell($w[8], 6, date('M d, Y', strtotime($client['created_at'])), 'LR', 0, 'C');
        $pdf->Ln();
    }
    
    // Closing line
    $pdf->Cell(array_sum($w), 0, '', 'T');
    
    // Output PDF
    $pdf->Output('clients_report_' . date('Y-m-d') . '.pdf', 'D');
    exit;
}

function exportExcel($clients, $total_clients, $verified_clients, $pending_clients) {
    require_once '../vendor/autoload.php'; // Make sure to install PhpSpreadsheet via composer
    
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Set document properties
    $spreadsheet->getProperties()
        ->setCreator('BII LocalFinder')
        ->setTitle('Clients Report')
        ->setSubject('Clients Management Report');
    
    // Header row
    $sheet->setCellValue('A1', 'Clients Management Report');
    $sheet->mergeCells('A1:I1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal('center');
    
    $sheet->setCellValue('A2', 'Generated on: ' . date('Y-m-d H:i:s'));
    $sheet->mergeCells('A2:I2');
    $sheet->getStyle('A2')->getAlignment()->setHorizontal('center');
    
    // Statistics
    $sheet->setCellValue('A4', 'Summary Statistics');
    $sheet->getStyle('A4')->getFont()->setBold(true);
    $sheet->setCellValue('A5', "Total Clients: {$total_clients}");
    $sheet->setCellValue('A6', "Verified Clients: {$verified_clients}");
    $sheet->setCellValue('A7', "Pending Clients: {$pending_clients}");
    
    // Table header
    $headers = ['ID', 'Name', 'Email', 'Phone', 'Status', 'Bookings', 'Reviews', 'Reports', 'Registered Date'];
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . '9', $header);
        $sheet->getStyle($col . '9')->getFont()->setBold(true);
        $col++;
    }
    
    // Table data
    $row = 10;
    foreach ($clients as $client) {
        $stats = getClientStats($GLOBALS['db'], $client['id']);
        $sheet->setCellValue('A' . $row, $client['id']);
        $sheet->setCellValue('B' . $row, $client['full_name']);
        $sheet->setCellValue('C' . $row, $client['email']);
        $sheet->setCellValue('D' . $row, $client['phone'] ?? 'N/A');
        $sheet->setCellValue('E' . $row, $client['is_verified'] ? 'Active' : 'Suspended');
        $sheet->setCellValue('F' . $row, $stats['total_bookings']);
        $sheet->setCellValue('G' . $row, $stats['total_reviews']);
        $sheet->setCellValue('H' . $row, $stats['reports_filed']);
        $sheet->setCellValue('I' . $row, date('Y-m-d', strtotime($client['created_at'])));
        $row++;
    }
    
    // Auto-size columns
    foreach (range('A', 'I') as $column) {
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }
    
    // Set headers for download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="clients_report_' . date('Y-m-d') . '.xlsx"');
    header('Cache-Control: max-age=0');
    
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save('php://output');
    exit;
}

function exportCSV($clients, $total_clients, $verified_clients, $pending_clients) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="clients_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fputs($output, "\xEF\xBB\xBF");
    
    // Header
    fputcsv($output, ['Clients Management Report']);
    fputcsv($output, ['Generated on:', date('Y-m-d H:i:s')]);
    fputcsv($output, []); // Empty line
    fputcsv($output, ['Summary Statistics']);
    fputcsv($output, ['Total Clients:', $total_clients]);
    fputcsv($output, ['Verified Clients:', $verified_clients]);
    fputcsv($output, ['Pending Clients:', $pending_clients]);
    fputcsv($output, []); // Empty line
    
    // Data header
    fputcsv($output, [
        'ID', 'Name', 'Email', 'Phone', 'Status', 
        'Bookings', 'Reviews', 'Reports', 'Registered Date'
    ]);
    
    // Data rows
    foreach ($clients as $client) {
        $stats = getClientStats($GLOBALS['db'], $client['id']);
        fputcsv($output, [
            $client['id'],
            $client['full_name'],
            $client['email'],
            $client['phone'] ?? 'N/A',
            $client['is_verified'] ? 'Active' : 'Suspended',
            $stats['total_bookings'],
            $stats['total_reviews'],
            $stats['reports_filed'],
            date('Y-m-d', strtotime($client['created_at']))
        ]);
    }
    
    fclose($output);
    exit;
}

function exportDOCX($clients, $total_clients, $verified_clients, $pending_clients) {
    require_once '../vendor/autoload.php'; // Make sure to install PhpWord via composer
    
    $phpWord = new \PhpOffice\PhpWord\PhpWord();
    
    // Add a section
    $section = $phpWord->addSection();
    
    // Title
    $section->addText(
        'Clients Management Report',
        ['bold' => true, 'size' => 16],
        ['alignment' => 'center']
    );
    
    $section->addText(
        'Generated on: ' . date('Y-m-d H:i:s'),
        ['size' => 10],
        ['alignment' => 'center']
    );
    
    $section->addTextBreak(2);
    
    // Statistics
    $section->addText('Summary Statistics', ['bold' => true]);
    $section->addText("Total Clients: {$total_clients}");
    $section->addText("Verified Clients: {$verified_clients}");
    $section->addText("Pending Clients: {$pending_clients}");
    $section->addTextBreak(2);
    
    // Create table
    $table = $section->addTable([
        'borderSize' => 6,
        'borderColor' => '000000',
        'cellMargin' => 50
    ]);
    
    // Table header
    $table->addRow();
    $headers = ['ID', 'Name', 'Email', 'Phone', 'Status', 'Bookings', 'Reviews', 'Reports', 'Registered'];
    foreach ($headers as $header) {
        $table->addCell(1000)->addText($header, ['bold' => true]);
    }
    
    // Table data
    foreach ($clients as $client) {
        $stats = getClientStats($GLOBALS['db'], $client['id']);
        $table->addRow();
        $table->addCell(1000)->addText($client['id']);
        $table->addCell(2000)->addText($client['full_name']);
        $table->addCell(2500)->addText($client['email']);
        $table->addCell(1500)->addText($client['phone'] ?? 'N/A');
        $table->addCell(1200)->addText($client['is_verified'] ? 'Active' : 'Suspended');
        $table->addCell(1000)->addText($stats['total_bookings']);
        $table->addCell(1000)->addText($stats['total_reviews']);
        $table->addCell(1000)->addText($stats['reports_filed']);
        $table->addCell(1500)->addText(date('M d, Y', strtotime($client['created_at'])));
    }
    
    // Save file
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="clients_report_' . date('Y-m-d') . '.docx"');
    header('Cache-Control: max-age=0');
    
    $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
    $objWriter->save('php://output');
    exit;
}
?>