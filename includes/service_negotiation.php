<?php
/**
 * Service Offer & Negotiation Functions
 * Handles Offer-Counteroffer negotiation system
 */

class ServiceNegotiation {
    private $db;
    const OFFER_EXPIRY_MINUTES = 30;
    const MAX_ROUNDS = 3;
    
    public function __construct($connection) {
        $this->db = $connection;
    }
    
    /**
     * Create initial offer from client
     */
    public function createOffer($booking_id, $service_id, $client_id, $provider_id, $offered_price) {
        try {
            $expires_at = date('Y-m-d H:i:s', strtotime('+' . self::OFFER_EXPIRY_MINUTES . ' minutes'));
            
            $stmt = $this->db->prepare("
                INSERT INTO service_offers 
                (booking_id, service_id, client_id, provider_id, offered_price, status, round_number, expires_at)
                VALUES (?, ?, ?, ?, ?, 'pending', 1, ?)
            ");
            
            if (!$stmt->execute([$booking_id, $service_id, $client_id, $provider_id, $offered_price, $expires_at])) {
                throw new Exception("Failed to insert offer: " . implode(", ", $stmt->errorInfo()));
            }
            
            $offer_id = $this->db->lastInsertId();
            
            // Log to history
            $this->logNegotiationHistory(
                $booking_id, $offer_id, null, 'offer_created',
                $offered_price, $client_id, 'client', 
                'Client created initial offer'
            );
            
            return $offer_id;
        } catch (Exception $e) {
            error_log("Create offer error: " . $e->getMessage());
            throw $e; // Rethrow so API can catch and report
        }
    }
    
    /**
     * Provider accepts client's offer
     */
    public function acceptOffer($offer_id, $provider_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM service_offers WHERE id = ? AND provider_id = ? AND status = 'pending'
            ");
            $stmt->execute([$offer_id, $provider_id]);
            $offer = $stmt->fetch();
            
            if (!$offer) {
                return ['success' => false, 'message' => 'Offer not found or already processed'];
            }
            
            // Check if offer expired
            if (strtotime($offer['expires_at']) < time()) {
                $this->db->prepare("UPDATE service_offers SET status = 'expired' WHERE id = ?")
                    ->execute([$offer_id]);
                return ['success' => false, 'message' => 'Offer has expired'];
            }
            
            // Update offer status
            $stmt = $this->db->prepare("
                UPDATE service_offers 
                SET status = 'accepted', responded_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$offer_id]);
            
            // Update booking responded_at for ML tracking
            $this->db->prepare("UPDATE bookings SET responded_at = NOW() WHERE id = ?")
                ->execute([$offer['booking_id']]);
            
            // Create finalized price
            $this->finalizePrice(
                $offer['booking_id'],
                $offer['service_id'],
                $offer['client_id'],
                $offer['provider_id'],
                $offer['offered_price'],
                $offer['id'],
                null,
                $offer['round_number']
            );
            
            // Log to history
            $this->logNegotiationHistory(
                $offer['booking_id'], $offer_id, null, 'offer_accepted',
                $offer['offered_price'], $provider_id, 'provider',
                'Provider accepted the offer'
            );
            
            // Update booking amount
            $this->db->prepare("UPDATE bookings SET amount = ? WHERE id = ?")
                ->execute([$offer['offered_price'], $offer['booking_id']]);
            
            return ['success' => true, 'message' => 'Offer accepted successfully'];
        } catch (Exception $e) {
            error_log("Accept offer error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to accept offer'];
        }
    }
    
    /**
     * Provider sends counter-offer
     */
    public function createCounterOffer($offer_id, $service_id, $provider_id, $client_id, $proposed_price, $notes = '') {
        try {
            // Check if max rounds reached
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as counter_count FROM service_counteroffers 
                WHERE offer_id = ? AND status != 'expired'
            ");
            $stmt->execute([$offer_id]);
            $round_count = $stmt->fetch()['counter_count'] + 1;
            
            if ($round_count > self::MAX_ROUNDS) {
                return ['success' => false, 'message' => 'Maximum negotiation rounds reached'];
            }
            
            $expires_at = date('Y-m-d H:i:s', strtotime('+' . self::OFFER_EXPIRY_MINUTES . ' minutes'));
            
            $stmt = $this->db->prepare("
                INSERT INTO service_counteroffers 
                (offer_id, service_id, provider_id, client_id, proposed_price, status, round_number, expires_at, response_notes)
                VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, ?)
            ");
            
            $stmt->execute([$offer_id, $service_id, $provider_id, $client_id, $proposed_price, $round_count, $expires_at, $notes]);
            $counteroffer_id = $this->db->lastInsertId();
            
            // Update original offer status
            $this->db->prepare("UPDATE service_offers SET status = 'pending' WHERE id = ?")
                ->execute([$offer_id]);
            
            // Log to history
            $stmt = $this->db->prepare("SELECT booking_id FROM service_offers WHERE id = ?");
            $stmt->execute([$offer_id]);
            $booking_id = $stmt->fetch()['booking_id'];
            
            $this->logNegotiationHistory(
                $booking_id, $offer_id, $counteroffer_id, 'counteroffer_created',
                $proposed_price, $provider_id, 'provider',
                'Provider sent counter-offer: ' . $notes
            );
            
            return ['success' => true, 'message' => 'Counter-offer sent', 'counteroffer_id' => $counteroffer_id];
        } catch (Exception $e) {
            error_log("Create counter-offer error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to create counter-offer'];
        }
    }
    
    /**
     * Client accepts counter-offer
     */
    public function acceptCounterOffer($counteroffer_id, $client_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT co.*, so.booking_id FROM service_counteroffers co
                JOIN service_offers so ON co.offer_id = so.id
                WHERE co.id = ? AND co.client_id = ? AND co.status = 'pending'
            ");
            $stmt->execute([$counteroffer_id, $client_id]);
            $counteroffer = $stmt->fetch();
            
            if (!$counteroffer) {
                return ['success' => false, 'message' => 'Counter-offer not found or already processed'];
            }
            
            // Check if expired
            if (strtotime($counteroffer['expires_at']) < time()) {
                $this->db->prepare("UPDATE service_counteroffers SET status = 'expired' WHERE id = ?")
                    ->execute([$counteroffer_id]);
                return ['success' => false, 'message' => 'Counter-offer has expired'];
            }
            
            // Update counter-offer status
            $stmt = $this->db->prepare("
                UPDATE service_counteroffers 
                SET status = 'accepted', responded_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$counteroffer_id]);
            
            // Mark parent offer as accepted
            $this->db->prepare("UPDATE service_offers SET status = 'accepted' WHERE id = ?")
                ->execute([$counteroffer['offer_id']]);
            
            // Create finalized price
            $this->finalizePrice(
                $counteroffer['booking_id'],
                $counteroffer['service_id'],
                $counteroffer['client_id'],
                $counteroffer['provider_id'],
                $counteroffer['proposed_price'],
                $counteroffer['offer_id'],
                $counteroffer_id,
                $counteroffer['round_number']
            );
            
            // Log to history
            $this->logNegotiationHistory(
                $counteroffer['booking_id'], $counteroffer['offer_id'], $counteroffer_id, 
                'counteroffer_accepted', $counteroffer['proposed_price'], 
                $client_id, 'client', 'Client accepted the counter-offer'
            );
            
            // Update booking amount
            $this->db->prepare("UPDATE bookings SET amount = ? WHERE id = ?")
                ->execute([$counteroffer['proposed_price'], $counteroffer['booking_id']]);
            
            return ['success' => true, 'message' => 'Counter-offer accepted successfully'];
        } catch (Exception $e) {
            error_log("Accept counter-offer error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to accept counter-offer'];
        }
    }
    
    /**
     * Client rejects counter-offer (can create new offer in next round)
     */
    public function rejectCounterOffer($counteroffer_id, $client_id, $notes = '') {
        try {
            $stmt = $this->db->prepare("
                SELECT co.*, so.booking_id FROM service_counteroffers co
                JOIN service_offers so ON co.offer_id = so.id
                WHERE co.id = ? AND co.client_id = ? AND co.status = 'pending'
            ");
            $stmt->execute([$counteroffer_id, $client_id]);
            $counteroffer = $stmt->fetch();
            
            if (!$counteroffer) {
                return ['success' => false, 'message' => 'Counter-offer not found'];
            }
            
            // Update counter-offer
            $this->db->prepare("
                UPDATE service_counteroffers 
                SET status = 'rejected', responded_at = NOW(), response_notes = ?
                WHERE id = ?
            ")->execute([$notes, $counteroffer_id]);
            
            // Log to history
            $this->logNegotiationHistory(
                $counteroffer['booking_id'], $counteroffer['offer_id'], $counteroffer_id,
                'counteroffer_rejected', $counteroffer['proposed_price'],
                $client_id, 'client', 'Client rejected counter-offer: ' . $notes
            );
            
            return ['success' => true, 'message' => 'Counter-offer rejected'];
        } catch (Exception $e) {
            error_log("Reject counter-offer error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to reject counter-offer'];
        }
    }
    
    /**
     * Provider rejects offer and can send counter-offer instead
     */
    public function rejectOffer($offer_id, $provider_id, $notes = '') {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM service_offers WHERE id = ? AND provider_id = ? AND status = 'pending'
            ");
            $stmt->execute([$offer_id, $provider_id]);
            $offer = $stmt->fetch();
            
            if (!$offer) {
                return ['success' => false, 'message' => 'Offer not found'];
            }
            
            // Update offer
            $this->db->prepare("
                UPDATE service_offers 
                SET status = 'rejected', responded_at = NOW(), response_notes = ?
                WHERE id = ?
            ")->execute([$notes, $offer_id]);
            
            // Update booking responded_at for ML tracking
            $this->db->prepare("UPDATE bookings SET responded_at = NOW() WHERE id = ?")
                ->execute([$offer['booking_id']]);
            
            // Log to history
            $this->logNegotiationHistory(
                $offer['booking_id'], $offer_id, null, 'offer_rejected',
                $offer['offered_price'], $provider_id, 'provider',
                'Provider rejected offer: ' . $notes
            );
            
            return ['success' => true, 'message' => 'Offer rejected'];
        } catch (Exception $e) {
            error_log("Reject offer error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to reject offer'];
        }
    }
    
    /**
     * Get active offer for booking
     */
    public function getActiveOffer($booking_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM service_offers 
                WHERE booking_id = ? AND status IN ('pending', 'accepted')
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$booking_id]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Get offer error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get latest counter-offer for an offer
     */
    public function getLatestCounterOffer($offer_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM service_counteroffers 
                WHERE offer_id = ? 
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$offer_id]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Get counter-offer error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get negotiation history for a booking
     */
    public function getNegotiationHistory($booking_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM negotiation_history 
                WHERE booking_id = ?
                ORDER BY created_at DESC
            ");
            $stmt->execute([$booking_id]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Get negotiation history error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Finalize negotiated price
     */
    public function finalizePrice($booking_id, $service_id, $client_id, $provider_id, $finalized_price, $offer_id, $counteroffer_id, $round_number) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO finalized_service_prices
                (booking_id, service_id, client_id, provider_id, finalized_price, negotiation_rounds, client_final_offer_id, provider_final_counteroffer_id, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')
                ON DUPLICATE KEY UPDATE
                finalized_price = VALUES(finalized_price),
                negotiation_rounds = VALUES(negotiation_rounds),
                provider_final_counteroffer_id = VALUES(provider_final_counteroffer_id),
                updated_at = NOW()
            ");
            
            $stmt->execute([$booking_id, $service_id, $client_id, $provider_id, $finalized_price, $round_number, $offer_id, $counteroffer_id]);
            
            return true;
        } catch (Exception $e) {
            error_log("Finalize price error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get finalized price for booking
     */
    public function getFinalizedPrice($booking_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM finalized_service_prices 
                WHERE booking_id = ? AND status = 'active'
            ");
            $stmt->execute([$booking_id]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Get finalized price error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Auto-expire old offers and counter-offers
     */
    public static function autoExpireOffers($connection) {
        try {
            // Expire old offers
            $connection->exec("
                UPDATE service_offers 
                SET status = 'expired' 
                WHERE status = 'pending' AND expires_at < NOW()
            ");
            
            // Expire old counter-offers
            $connection->exec("
                UPDATE service_counteroffers 
                SET status = 'expired' 
                WHERE status = 'pending' AND expires_at < NOW()
            ");
            
            return true;
        } catch (Exception $e) {
            error_log("Auto-expire offers error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log negotiation history
     */
    private function logNegotiationHistory($booking_id, $offer_id, $counteroffer_id, $action_type, $price, $actor_id, $actor_type, $notes) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO negotiation_history
                (booking_id, offer_id, counteroffer_id, action_type, price_offered, actor_id, actor_type, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([$booking_id, $offer_id, $counteroffer_id, $action_type, $price, $actor_id, $actor_type, $notes]);
            return true;
        } catch (Exception $e) {
            error_log("Log history error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Can create new offer (check round limits)
     */
    public function canCreateNewOffer($booking_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT MAX(round_number) as max_round FROM service_offers 
                WHERE booking_id = ?
            ");
            $stmt->execute([$booking_id]);
            $result = $stmt->fetch();
            $max_round = $result['max_round'] ?? 0;
            
            return $max_round < self::MAX_ROUNDS;
        } catch (Exception $e) {
            error_log("Check offer rounds error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get offer negotiation status
     */
    public function getNegotiationStatus($booking_id) {
        try {
            $offer = $this->getActiveOffer($booking_id);
            
            if (!$offer) {
                return ['status' => 'no_offer', 'message' => 'No active offer'];
            }
            
            if ($offer['status'] === 'accepted') {
                $finalized = $this->getFinalizedPrice($booking_id);
                return [
                    'status' => 'finalized',
                    'message' => 'Price negotiation completed',
                    'finalized_price' => $finalized['finalized_price'],
                    'rounds' => $finalized['negotiation_rounds']
                ];
            }
            
            // Check for pending counter-offers
            $counteroffer = $this->getLatestCounterOffer($offer['id']);
            
            if ($counteroffer && $counteroffer['status'] === 'pending') {
                $time_remaining = ceil((strtotime($counteroffer['expires_at']) - time()) / 60);
                return [
                    'status' => 'counter_pending',
                    'message' => 'Awaiting response to counter-offer',
                    'time_remaining_minutes' => $time_remaining,
                    'proposed_price' => $counteroffer['proposed_price'],
                    'round' => $counteroffer['round_number']
                ];
            }
            
            // Offer is pending
            $time_remaining = ceil((strtotime($offer['expires_at']) - time()) / 60);
            return [
                'status' => 'offer_pending',
                'message' => 'Awaiting provider response',
                'time_remaining_minutes' => $time_remaining,
                'offered_price' => $offer['offered_price'],
                'round' => $offer['round_number']
            ];
        } catch (Exception $e) {
            error_log("Get negotiation status error: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Failed to get status'];
        }
    }
}
