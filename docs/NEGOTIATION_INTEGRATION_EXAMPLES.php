<?php
/**
 * Service Negotiation Integration Examples
 * Use these examples to integrate negotiation system into your pages
 */
?>

<!-- EXAMPLE 1: Provider Services Management (Add/Edit Service Form) -->
<!-- Add this section to provider/services.php form -->

<div class="negotiation-section">
    <div class="negotiation-header">
        <i class="fas fa-handshake"></i>
        <h4>Price Negotiation Settings</h4>
    </div>
    
    <!-- Enable Negotiation Checkbox -->
    <div class="negotiable-checkbox">
        <input type="checkbox" id="negotiableCheckbox" name="negotiable" value="1">
        <label for="negotiableCheckbox">
            Allow clients to negotiate the price for this service
            <div class="form-text">
                If enabled, clients can offer a different price within your min/max range
            </div>
        </label>
    </div>
    
    <!-- Price Range Inputs (shown only if negotiable is checked) -->
    <div class="price-range-inputs" style="display: none;">
        <div class="price-input-group">
            <label for="minPrice">Minimum Price <span class="required">*</span></label>
            <input type="number" id="minPrice" name="min_price" step="0.01" min="0" 
                   placeholder="Lowest acceptable price" required>
            <small class="form-text">Clients can't offer less than this</small>
        </div>
        
        <div class="price-input-group">
            <label for="basePrice">Base Price <span class="required">*</span></label>
            <input type="number" id="basePrice" name="price" step="0.01" min="0" 
                   placeholder="Starting/reference price" required>
            <small class="form-text">Your initial price for negotiation</small>
        </div>
        
        <div class="price-input-group">
            <label for="maxPrice">Maximum Price <span class="required">*</span></label>
            <input type="number" id="maxPrice" name="max_price" step="0.01" min="0" 
                   placeholder="Highest you'll accept" required>
            <small class="form-text">Clients can't offer more than this</small>
        </div>
    </div>
    
    <!-- Negotiation Info -->
    <div class="negotiable-info">
        <strong>How Price Negotiation Works:</strong>
        <ul>
            <li>Client makes an initial offer</li>
            <li>You can accept, reject, or send a counter-offer</li>
            <li>Each offer/counter-offer expires in 30 minutes</li>
            <li>Limited to 3 negotiation rounds</li>
            <li>Once accepted, price is locked</li>
        </ul>
    </div>
</div>

<script>
    // Show/hide price range inputs based on negotiable checkbox
    document.getElementById('negotiableCheckbox')?.addEventListener('change', function(e) {
        const priceInputs = document.querySelector('.price-range-inputs');
        const negotiableInfo = document.querySelector('.negotiable-info');
        if (priceInputs) {
            priceInputs.style.display = this.checked ? 'grid' : 'none';
        }
        if (negotiableInfo) {
            negotiableInfo.style.display = this.checked ? 'block' : 'none';
        }
        
        // Update required attribute
        document.getElementById('minPrice').required = this.checked;
        document.getElementById('maxPrice').required = this.checked;
    });
    
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        const checkbox = document.getElementById('negotiableCheckbox');
        if (checkbox && checkbox.checked) {
            checkbox.dispatchEvent(new Event('change'));
        }
    });
</script>

<!-- ============================================================================ -->
<!-- EXAMPLE 2: Offer Management in Client Booking (client/provider-profile.php) -->
<!-- Add this section to the booking form area -->

<div class="booking-form">
    <!-- Negotiation Alerts -->
    <div id="negotiationAlertContainer"></div>
    
    <!-- Negotiation Status -->
    <div id="negotiationStatusContainer"></div>
    
    <!-- Offer Section -->
    <div class="offer-form">
        <h5><i class="fas fa-dollar-sign"></i> Price Negotiation</h5>
        
        <!-- Check if service is negotiable -->
        <?php if ($service['negotiable']): ?>
            <div class="negotiation-alert info">
                <i class="fas fa-info-circle"></i>
                <div>
                    This service is available for price negotiation. 
                    You can offer a price between <strong>RWF <?php echo number_format($service['min_price'], 0); ?></strong> 
                    and <strong>RWF <?php echo number_format($service['max_price'], 0); ?></strong>
                </div>
            </div>
            
            <form id="offerForm">
                <input type="hidden" name="action" value="create_offer">
                <input type="hidden" id="serviceId" name="service_id" value="<?php echo $service['id']; ?>">
                
                <div class="offer-form-group">
                    <label for="offeredPrice">Your Offer Price <span class="required">*</span></label>
                    <input type="number" id="offeredPrice" name="offered_price" 
                           min="<?php echo $service['min_price']; ?>" 
                           max="<?php echo $service['max_price']; ?>" 
                           step="0.01" required placeholder="Enter your price offer">
                    <small class="form-text">
                        Must be between RWF <?php echo number_format($service['min_price'], 0); ?> 
                        and RWF <?php echo number_format($service['max_price'], 0); ?>
                    </small>
                </div>
                
                <div class="offer-form-submit">
                    <button type="button" class="btn btn-primary" id="createOfferBtn">
                        <i class="fas fa-handshake"></i> Send Offer
                    </button>
                </div>
            </form>
        <?php else: ?>
            <div class="negotiation-alert warning">
                <i class="fas fa-tag"></i>
                <div>
                    Fixed Price: <strong class="text-success">RWF <?php echo number_format($service['price'], 0); ?></strong>
                    <p class="mt-2">This service has a fixed price and is not negotiable.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ============================================================================ -->
<!-- EXAMPLE 3: Provider - View and Respond to Offers -->
<!-- Add this to provider dashboard or bookings page -->

<?php
// In your provider controller:
require_once '../includes/service_negotiation.php';
$negotiation = new ServiceNegotiation($db);
?>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-list"></i> Pending Offers</h3>
    </div>
    
    <div class="card-body">
        <?php
        // Get provider's pending offers
        $stmt = $db->prepare("
            SELECT so.*, ps.name as service_name, u.full_name as client_name, u.profile_image
            FROM service_offers so
            JOIN provider_services ps ON so.service_id = ps.id
            JOIN users u ON so.client_id = u.id
            WHERE so.provider_id = ? AND so.status = 'pending'
            ORDER BY so.created_at DESC
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $pending_offers = $stmt->fetchAll();
        
        if (empty($pending_offers)):
        ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h4>No Pending Offers</h4>
                <p>You don't have any pending price offers from clients right now.</p>
            </div>
        <?php else: ?>
            <?php foreach ($pending_offers as $offer): ?>
                <div class="offer-card">
                    <div class="offer-header">
                        <div>
                            <h5 class="offer-title"><?php echo htmlspecialchars($offer['service_name']); ?></h5>
                            <small class="text-muted">From: <?php echo htmlspecialchars($offer['client_name']); ?></small>
                        </div>
                        <span class="offer-status pending">PENDING</span>
                    </div>
                    
                    <div class="offer-content">
                        <div class="offer-price">RWF <?php echo number_format($offer['offered_price'], 0); ?></div>
                        
                        <div class="offer-meta">
                            <div class="offer-meta-item">
                                <span class="label">Offered Date</span>
                                <span class="value"><?php echo date('M d, Y g:i A', strtotime($offer['created_at'])); ?></span>
                            </div>
                            <div class="offer-meta-item">
                                <span class="label">Expires In</span>
                                <span class="value offer-timer">
                                    <i class="fas fa-hourglass-end"></i>
                                    <span class="countdown" data-expires="<?php echo $offer['expires_at']; ?>"></span>
                                </span>
                            </div>
                            <div class="offer-meta-item">
                                <span class="label">Round</span>
                                <span class="value"><?php echo $offer['round_number']; ?>/3</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="offer-actions">
                        <button class="btn btn-primary" 
                                data-action="accept-offer" 
                                data-offer-id="<?php echo $offer['id']; ?>"
                                onclick="negotiationUI.handleAcceptOffer(<?php echo $offer['id']; ?>)">
                            <i class="fas fa-check"></i> Accept Offer
                        </button>
                        
                        <button class="btn btn-outline" 
                                data-action="counteroffer" 
                                data-offer-id="<?php echo $offer['id']; ?>"
                                data-service-id="<?php echo $offer['service_id']; ?>"
                                data-bs-toggle="modal" 
                                data-bs-target="#counterOfferModal">
                            <i class="fas fa-exchange-alt"></i> Counter Offer
                        </button>
                        
                        <button class="btn btn-secondary" 
                                data-action="reject-offer" 
                                data-offer-id="<?php echo $offer['id']; ?>"
                                onclick="negotiationUI.handleRejectOffer(<?php echo $offer['id']; ?>)">
                            <i class="fas fa-times"></i> Reject
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Counter-Offer Modal -->
<div class="modal fade" id="counterOfferModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-exchange-alt"></i> Send Counter-Offer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            
            <form id="counterOfferForm">
                <input type="hidden" id="counterOfferOfferId">
                <input type="hidden" id="counterOfferServiceId">
                
                <div class="modal-body">
                    <div class="offer-form-group">
                        <label for="counterOfferPrice">Your Counter-Offer Price <span class="required">*</span></label>
                        <input type="number" id="counterOfferPrice" step="0.01" min="0" required>
                    </div>
                    
                    <div class="offer-form-group">
                        <label for="counterOfferNotes">Message to Client (Optional)</label>
                        <textarea id="counterOfferNotes" placeholder="Explain your counter-offer..."></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Send Counter-Offer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================================ -->
<!-- EXAMPLE 4: Negotiation History Timeline -->

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-history"></i> Negotiation History</h3>
    </div>
    
    <div class="card-body">
        <?php
        $negotiation = new ServiceNegotiation($db);
        $history = $negotiation->getNegotiationHistory($booking['id']);
        
        if (empty($history)):
        ?>
            <p class="text-muted">No negotiation history for this booking yet.</p>
        <?php else: ?>
            <div class="negotiation-timeline">
                <?php foreach ($history as $event): ?>
                    <div class="timeline-item">
                        <div class="timeline-dot <?php echo strtolower($event['action_type']); ?>"></div>
                        <div class="timeline-content">
                            <div class="timeline-action">
                                <?php
                                $actions = [
                                    'offer_created' => 'Client Created Offer',
                                    'offer_accepted' => 'Provider Accepted Offer',
                                    'offer_rejected' => 'Provider Rejected Offer',
                                    'counteroffer_created' => 'Provider Created Counter-Offer',
                                    'counteroffer_accepted' => 'Client Accepted Counter-Offer',
                                    'counteroffer_rejected' => 'Client Rejected Counter-Offer',
                                    'final_agreement' => 'Price Finalized',
                                ];
                                echo $actions[$event['action_type']] ?? $event['action_type'];
                                ?>
                            </div>
                            
                            <div class="timeline-details">
                                <?php echo htmlspecialchars($event['notes']); ?>
                                <?php if ($event['actor_type'] === 'provider'): ?>
                                    <span class="badge bg-info">Provider</span>
                                <?php else: ?>
                                    <span class="badge bg-primary">Client</span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($event['price_offered']): ?>
                                <div class="timeline-price">
                                    RWF <?php echo number_format($event['price_offered'], 0); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="timeline-time">
                                <?php echo date('M d, Y g:i A', strtotime($event['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ============================================================================ -->
<!-- REQUIRED: Add to HTML Head -->

<?php
// In your page <head> section, add:
?>
<link rel="stylesheet" href="../assets/css/service_negotiation.css">
<script src="../assets/js/service_negotiation.js"></script>

<!-- Set booking ID for JavaScript initialization -->
<div data-booking-id="<?php echo $booking['id']; ?>"></div>

<!-- ============================================================================ -->
<!-- EXAMPLE 5: Quick Price Info Component -->

<div class="stat-card">
    <h3><?php echo number_format($service['price'], 0); ?></h3>
    <p><?php echo $service['payment_type'] === 'per_hour' ? 'Per Hour' : 'Per Service'; ?></p>
    
    <?php if ($service['negotiable']): ?>
        <small class="text-muted d-block mt-2">
            <i class="fas fa-handshake"></i> Negotiable
            <br>
            RWF <?php echo number_format($service['min_price'], 0); ?> 
            - 
            RWF <?php echo number_format($service['max_price'], 0); ?>
        </small>
    <?php endif; ?>
</div>
