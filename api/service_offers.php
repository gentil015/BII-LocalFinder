<?php
/**
 * Service Offer API Endpoint
 * Handles all offer and counter-offer operations
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/chat.php';
require_once '../includes/service_negotiation.php';

header('Content-Type: application/json');

// Verify user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$db = Database::getInstance()->getConnection();
$negotiation = new ServiceNegotiation($db);
$user_id = $_SESSION['user_id'];

// Auto-expire old offers
ServiceNegotiation::autoExpireOffers($db);

$action = isset($_POST['action']) ? sanitize($_POST['action']) : (isset($_GET['action']) ? sanitize($_GET['action']) : '');

try {
    switch ($action) {
        // CLIENT: Create initial offer (direct from service listing)
        case 'create_offer':
            if (!isLoggedIn() || isProvider()) {
                throw new Exception('Only clients can create offers');
            }
            
            $service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : 0;
            $offered_price = isset($_POST['offered_price']) ? floatval($_POST['offered_price']) : 0;
            $notes = isset($_POST['notes']) ? sanitize($_POST['notes']) : '';
            
            if (!$service_id || $offered_price <= 0) {
                throw new Exception('Missing or invalid required fields');
            }
            
            // Verify service exists and get provider info
            $stmt = $db->prepare("SELECT ps.*, sp.id as service_provider_id, sp.user_id as provider_user_id FROM provider_services ps
                                 JOIN service_providers sp ON ps.provider_id = sp.id
                                 WHERE ps.id = ? AND ps.is_available = 1");
            $stmt->execute([$service_id]);
            $service = $stmt->fetch();
            
            if (!$service) {
                throw new Exception('Service not found or not available');
            }
            
            $service_provider_id = $service['service_provider_id'];
            $provider_user_id = $service['provider_user_id'];
            
            // Check if service is negotiable
            if (!$service['negotiable']) {
                throw new Exception('This service does not allow price negotiation');
            }
            
            // Validate price is within range
            $min_price = $service['min_price'] ?? $service['price'];
            $max_price = $service['max_price'] ?? $service['price'];
            
            if ($offered_price < $min_price || $offered_price > $max_price) {
                throw new Exception("Price must be between RWF " . number_format($min_price, 0) . " and RWF " . number_format($max_price, 0));
            }
            
            // Create a temporary booking record for tracking the offer
            try {
                $stmt = $db->prepare("
                    INSERT INTO bookings (client_id, provider_id, service_id, service_description, preferred_date, status, created_at)
                    VALUES (?, ?, ?, ?, NOW(), 'offer_pending', NOW())
                ");
                $stmt->execute([$user_id, $service_provider_id, $service_id, $notes ?: 'Price negotiation offer']);
                $booking_id = $db->lastInsertId();
                // start a simple chat record so conversation exists for this negotiation
                sendMessage($user_id, $provider_user_id, "Price negotiation started (booking #" . $booking_id . ")");
            } catch (Exception $e) {
                throw new Exception('Failed to create booking record: ' . $e->getMessage());
            }
            
            // Create the offer
            try {
                $offer_id = $negotiation->createOffer($booking_id, $service_id, $user_id, $provider_user_id, $offered_price);
                
                if (!$offer_id) {
                    throw new Exception('Failed to create offer - invalid response');
                }
            } catch (Exception $e) {
                throw new Exception('Failed to create offer: ' . $e->getMessage());
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Offer sent successfully! The provider will review your offer soon.',
                'offer_id' => $offer_id,
                'booking_id' => $booking_id
            ]);
            break;
        
        // PROVIDER: Accept offer
        case 'accept_offer':
            if (!isProvider()) {
                throw new Exception('Only providers can accept offers');
            }
            
            $offer_id = isset($_POST['offer_id']) ? intval($_POST['offer_id']) : 0;
            
            if (!$offer_id) {
                throw new Exception('Offer ID required');
            }
            
            // Verify provider owns the offer
            $stmt = $db->prepare("SELECT provider_id FROM service_offers WHERE id = ?");
            $stmt->execute([$offer_id]);
            $offer = $stmt->fetch();
            
            if (!$offer || $offer['provider_id'] != $user_id) {
                throw new Exception('Offer not found');
            }
            
            $result = $negotiation->acceptOffer($offer_id, $user_id);
            
            if (!$result['success']) {
                throw new Exception($result['message']);
            }
            
            echo json_encode($result);
            break;
        
        // PROVIDER: Reject offer and send counter-offer
        case 'counteroffer':
            if (!isProvider()) {
                throw new Exception('Only providers can send counter-offers');
            }
            
            $offer_id = isset($_POST['offer_id']) ? intval($_POST['offer_id']) : 0;
            $service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : 0;
            $proposed_price = isset($_POST['proposed_price']) ? floatval($_POST['proposed_price']) : 0;
            $notes = isset($_POST['notes']) ? sanitize($_POST['notes']) : '';
            
            if (!$offer_id || !$service_id || $proposed_price <= 0) {
                throw new Exception('Missing or invalid required fields');
            }
            
            // Verify offer
            $stmt = $db->prepare("SELECT client_id FROM service_offers WHERE id = ? AND provider_id = ?");
            $stmt->execute([$offer_id, $user_id]);
            $offer = $stmt->fetch();
            
            if (!$offer) {
                throw new Exception('Offer not found');
            }
            
            $result = $negotiation->createCounterOffer($offer_id, $service_id, $user_id, $offer['client_id'], $proposed_price, $notes);
            
            if (!$result['success']) {
                throw new Exception($result['message']);
            }
            
            echo json_encode($result);
            break;
        
        // CLIENT: Accept counter-offer
        case 'accept_counteroffer':
            if (isProvider()) {
                throw new Exception('Only clients can accept counter-offers');
            }
            
            $counteroffer_id = isset($_POST['counteroffer_id']) ? intval($_POST['counteroffer_id']) : 0;
            
            if (!$counteroffer_id) {
                throw new Exception('Counter-offer ID required');
            }
            
            $result = $negotiation->acceptCounterOffer($counteroffer_id, $user_id);
            
            if (!$result['success']) {
                throw new Exception($result['message']);
            }
            
            echo json_encode($result);
            break;
        
        // CLIENT: Reject counter-offer
        case 'reject_counteroffer':
            if (isProvider()) {
                throw new Exception('Only clients can reject counter-offers');
            }
            
            $counteroffer_id = isset($_POST['counteroffer_id']) ? intval($_POST['counteroffer_id']) : 0;
            $notes = isset($_POST['notes']) ? sanitize($_POST['notes']) : '';
            
            if (!$counteroffer_id) {
                throw new Exception('Counter-offer ID required');
            }
            
            $result = $negotiation->rejectCounterOffer($counteroffer_id, $user_id, $notes);
            
            if (!$result['success']) {
                throw new Exception($result['message']);
            }
            
            echo json_encode($result);
            break;
        
        // PROVIDER: Reject offer
        case 'reject_offer':
            if (!isProvider()) {
                throw new Exception('Only providers can reject offers');
            }
            
            $offer_id = isset($_POST['offer_id']) ? intval($_POST['offer_id']) : 0;
            $notes = isset($_POST['notes']) ? sanitize($_POST['notes']) : '';
            
            if (!$offer_id) {
                throw new Exception('Offer ID required');
            }
            
            $result = $negotiation->rejectOffer($offer_id, $user_id, $notes);
            
            if (!$result['success']) {
                throw new Exception($result['message']);
            }
            
            echo json_encode($result);
            break;
        
        // GET: Negotiation status
        case 'get_status':
            $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
            
            if (!$booking_id) {
                throw new Exception('Booking ID required');
            }
            
            $status = $negotiation->getNegotiationStatus($booking_id);
            
            echo json_encode([
                'success' => true,
                'status' => $status
            ]);
            break;
        
        // GET: Negotiation history
        case 'get_history':
            $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
            
            if (!$booking_id) {
                throw new Exception('Booking ID required');
            }
            
            $history = $negotiation->getNegotiationHistory($booking_id);
            
            echo json_encode([
                'success' => true,
                'history' => $history
            ]);
            break;
        
        // GET: Finalized price
        case 'get_finalized_price':
            $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
            
            if (!$booking_id) {
                throw new Exception('Booking ID required');
            }
            
            $finalized = $negotiation->getFinalizedPrice($booking_id);
            
            echo json_encode([
                'success' => true,
                'finalized_price' => $finalized
            ]);
            break;
        
        // GET: Services for provider
        case 'get_services':
            // Get all services created by the logged-in provider
            if (!isProvider()) {
                throw new Exception('Only providers can retrieve services');
            }
            
            // Get provider ID from user ID
            $stmt = $db->prepare("SELECT id FROM service_providers WHERE user_id = ? LIMIT 1");
            $stmt->execute([$user_id]);
            $provider = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$provider) {
                echo json_encode([
                    'success' => true,
                    'services' => []
                ]);
                break;
            }
            
            $stmt = $db->prepare("
                SELECT id, name, description, price, negotiable, min_price, max_price, is_available
                FROM provider_services 
                WHERE provider_id = ? AND is_available = 1
                ORDER BY name ASC
            ");
            $stmt->execute([$provider['id']]);
            $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if ($services === false) {
                $services = [];
            }
            
            echo json_encode([
                'success' => true,
                'services' => $services
            ]);
            break;
        
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
