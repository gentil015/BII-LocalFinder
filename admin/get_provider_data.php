<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check admin access
if (!isLoggedIn() || !isAdmin()) {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied');
}

$db = Database::getInstance()->getConnection();
$action = $_GET['action'] ?? '';
$provider_id = intval($_GET['id'] ?? 0);

if (!$provider_id) {
    header('HTTP/1.1 400 Bad Request');
    exit('Invalid provider ID');
}

// Function to get provider details
function getProviderDetails($db, $provider_id) {
    $stmt = $db->prepare("
        SELECT sp.*, u.full_name, u.email, u.phone, u.profile_image, u.is_verified as user_verified,
               u.created_at as user_created, u.updated_at as user_updated,
               GROUP_CONCAT(DISTINCT c.id) as category_ids,
               GROUP_CONCAT(DISTINCT c.name) as category_names
        FROM service_providers sp
        JOIN users u ON sp.user_id = u.id
        LEFT JOIN provider_services ps ON sp.id = ps.provider_id
        LEFT JOIN categories c ON ps.category_id = c.id
        WHERE sp.id = ?
        GROUP BY sp.id
    ");
    $stmt->execute([$provider_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Function to get provider stats
function getProviderStats($db, $provider_id) {
    $stats = [];
    
    // Completed jobs
    $stmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE provider_id = ? AND status = 'completed'");
    $stmt->execute([$provider_id]);
    $stats['completed_jobs'] = $stmt->fetchColumn();
    
    // Pending jobs
    $stmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE provider_id = ? AND status = 'pending'");
    $stmt->execute([$provider_id]);
    $stats['pending_jobs'] = $stmt->fetchColumn();
    
    // Total earnings (estimated)
    $stmt = $db->prepare("SELECT SUM(hourly_rate) as total_earnings FROM bookings b JOIN service_providers sp ON b.provider_id = sp.id WHERE b.provider_id = ? AND b.status = 'completed'");
    $stmt->execute([$provider_id]);
    $stats['total_earnings'] = $stmt->fetchColumn() ?? 0;
    
    // Complaints received
    $stmt = $db->prepare("SELECT COUNT(*) FROM reports WHERE reported_user_id = (SELECT user_id FROM service_providers WHERE id = ?)");
    $stmt->execute([$provider_id]);
    $stats['complaints_received'] = $stmt->fetchColumn();
    
    return $stats;
}

function getProviderBookingStats($db, $provider_id) {
    $stats = [];

    $stmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE provider_id = ? AND status = 'completed'");
    $stmt->execute([$provider_id]);
    $stats['completed'] = (int)$stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE provider_id = ? AND status = 'pending'");
    $stmt->execute([$provider_id]);
    $stats['pending'] = (int)$stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE provider_id = ? AND status IN ('confirmed', 'pending') AND preferred_date >= CURDATE()");
    $stmt->execute([$provider_id]);
    $stats['upcoming'] = (int)$stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE provider_id = ? AND status = 'cancelled'");
    $stmt->execute([$provider_id]);
    $stats['cancelled'] = (int)$stmt->fetchColumn();

    return $stats;
}

function getProviderCategories($db, $provider_id) {
    $stmt = $db->prepare("SELECT c.id, c.name FROM categories c JOIN provider_services ps ON ps.category_id = c.id WHERE ps.provider_id = ?");
    $stmt->execute([$provider_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getProviderReviews($db, $provider_id) {
    $stmt = $db->prepare("SELECT r.*, u.full_name as client_name, u.profile_image as client_image FROM reviews r JOIN users u ON r.client_id = u.id WHERE r.provider_id = ? ORDER BY r.created_at DESC LIMIT 10");
    $stmt->execute([$provider_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getProviderSchedule($db, $provider_id) {
    $stmt = $db->prepare("SELECT working_days, working_hours_start, working_hours_end, break_start, break_end, slot_duration, buffer_time, max_daily_bookings, booking_lead_time, cancellation_cutoff FROM service_providers WHERE id = ?");
    $stmt->execute([$provider_id]);
    $schedule = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $db->prepare("SELECT * FROM provider_time_off WHERE provider_id = ? AND end_date >= CURDATE() ORDER BY start_date ASC LIMIT 10");
    $stmt->execute([$provider_id]);
    $schedule['time_off'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->prepare("SELECT * FROM provider_availability WHERE provider_id = ? AND date >= CURDATE() ORDER BY date ASC LIMIT 10");
    $stmt->execute([$provider_id]);
    $schedule['availability_exceptions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $schedule;
}

function getProviderGallery($db, $provider_id) {
    try {
        $stmt = $db->prepare("SELECT image_path, title FROM portfolio_images WHERE provider_id = ? AND is_active = 1 ORDER BY display_order ASC, uploaded_at DESC LIMIT 12");
        $stmt->execute([$provider_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

function getProviderComplaints($db, $provider_id) {
    $stmt = $db->prepare("SELECT r.*, u.full_name as reporter_name, u.email as reporter_email FROM reports r LEFT JOIN users u ON u.id = r.reporter_id WHERE r.reported_user_id = (SELECT user_id FROM service_providers WHERE id = ?) ORDER BY r.created_at DESC LIMIT 10");
    $stmt->execute([$provider_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

switch ($action) {
    case 'profile':
    case 'verification':
    case 'ranking':
    case 'financial':
        $provider = getProviderDetails($db, $provider_id);
        if ($provider) {
            header('Content-Type: application/json');
            echo json_encode($provider);
        } else {
            header('HTTP/1.1 404 Not Found');
            echo json_encode(['error' => 'Provider not found']);
        }
        break;
        
    case 'categories':
        $provider = getProviderDetails($db, $provider_id);
        if ($provider) {
            header('Content-Type: application/json');
            echo json_encode($provider);
        } else {
            header('HTTP/1.1 404 Not Found');
            echo json_encode(['error' => 'Provider not found']);
        }
        break;
        
    case 'full_profile':
        $provider = getProviderDetails($db, $provider_id);
        if (!$provider) {
            header('HTTP/1.1 404 Not Found');
            echo json_encode(['error' => 'Provider not found']);
            break;
        }

        $stats = getProviderStats($db, $provider_id);
        $booking_stats = getProviderBookingStats($db, $provider_id);
        $schedule = getProviderSchedule($db, $provider_id);
        $categories = getProviderCategories($db, $provider_id);
        $reviews = getProviderReviews($db, $provider_id);
        $gallery = getProviderGallery($db, $provider_id);
        $complaints = getProviderComplaints($db, $provider_id);

        header('Content-Type: application/json');
        echo json_encode([
            'provider' => $provider,
            'stats' => $stats,
            'booking' => $booking_stats,
            'schedule' => $schedule,
            'categories' => $categories,
            'reviews' => $reviews,
            'gallery' => $gallery,
            'complaints' => $complaints,
        ]);
        break;
        
    case 'details':
        $provider = getProviderDetails($db, $provider_id);
        $stats = getProviderStats($db, $provider_id);
        
        if ($provider) {
            header('Content-Type: text/html');
            ?>
            <div class="details-grid">
                <div class="detail-item">
                    <div class="detail-label">Full Name</div>
                    <div class="detail-value"><?php echo htmlspecialchars($provider['full_name']); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Email</div>
                    <div class="detail-value"><?php echo htmlspecialchars($provider['email']); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Phone</div>
                    <div class="detail-value"><?php echo htmlspecialchars($provider['phone']); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Profession</div>
                    <div class="detail-value"><?php echo htmlspecialchars($provider['profession']); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Hourly Rate</div>
                    <div class="detail-value">RWF <?php echo number_format($provider['hourly_rate']); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Experience</div>
                    <div class="detail-value"><?php echo $provider['experience_years']; ?> years</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Location</div>
                    <div class="detail-value"><?php echo htmlspecialchars($provider['location']); ?>, <?php echo htmlspecialchars($provider['district']); ?>, <?php echo htmlspecialchars($provider['sector']); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Availability</div>
                    <div class="detail-value"><?php echo ucfirst($provider['availability']); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Verification Level</div>
                    <div class="detail-value"><?php echo ucfirst($provider['verification_level']); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Categories</div>
                    <div class="detail-value"><?php echo htmlspecialchars($provider['category_names'] ?? 'None'); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Status</div>
                    <div class="detail-value">
                        <?php echo $provider['is_active'] ? 'Active' : 'Inactive'; ?>
                        <?php echo $provider['is_banned'] ? ' (Banned)' : ''; ?>
                        <?php echo $provider['is_featured'] ? ' (Featured)' : ''; ?>
                    </div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Member Since</div>
                    <div class="detail-value"><?php echo date('M j, Y', strtotime($provider['user_created'])); ?></div>
                </div>
            </div>
            
            <div class="mt-4">
                <h5>Performance Statistics</h5>
                <div class="details-grid">
                    <div class="detail-item">
                        <div class="detail-label">Completed Jobs</div>
                        <div class="detail-value"><?php echo $stats['completed_jobs']; ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Pending Jobs</div>
                        <div class="detail-value"><?php echo $stats['pending_jobs']; ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Total Earnings</div>
                        <div class="detail-value">RWF <?php echo number_format($stats['total_earnings']); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Complaints Received</div>
                        <div class="detail-value"><?php echo $stats['complaints_received']; ?></div>
                    </div>
                </div>
            </div>
            
            <?php if ($provider['bio']): ?>
            <div class="mt-4">
                <h5>Bio</h5>
                <p><?php echo htmlspecialchars($provider['bio']); ?></p>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($provider['verification_notes'])): ?>
            <div class="mt-4">
                <h5>Verification Notes</h5>
                <p><?php echo htmlspecialchars($provider['verification_notes']); ?></p>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($provider['ban_reason'])): ?>
            <div class="mt-4">
                <h5 class="text-danger">Ban Reason</h5>
                <p><?php echo htmlspecialchars($provider['ban_reason']); ?></p>
            </div>
            <?php endif; ?>
            <?php
        } else {
            echo '<p>Provider not found</p>';
        }
        break;
        
    default:
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['error' => 'Invalid action']);
        break;
}
?>