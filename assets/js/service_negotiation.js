/**
 * Service Negotiation System JavaScript
 * Handles UI interactions for offer and counter-offer system
 */

class ServiceNegotiationUI {
    constructor(bookingId) {
        this.bookingId = bookingId;
        this.apiEndpoint = '../api/service_offers.php';
        this.init();
    }
    
    init() {
        this.attachEventListeners();
        this.updateNegotiationStatus();
        // Refresh status every 30 seconds
        setInterval(() => this.updateNegotiationStatus(), 30000);
    }
    
    attachEventListeners() {
        // Create Offer
        document.getElementById('createOfferBtn')?.addEventListener('click', (e) => {
            e.preventDefault();
            this.handleCreateOffer();
        });
        
        // Accept Offer
        document.querySelectorAll('[data-action="accept-offer"]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const offerId = btn.dataset.offerId;
                this.handleAcceptOffer(offerId);
            });
        });
        
        // Reject Offer
        document.querySelectorAll('[data-action="reject-offer"]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const offerId = btn.dataset.offerId;
                this.handleRejectOffer(offerId);
            });
        });
        
        // Send Counter-Offer
        document.querySelectorAll('[data-action="counteroffer"]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const offerId = btn.dataset.offerId;
                const serviceId = btn.dataset.serviceId;
                this.showCounterOfferForm(offerId, serviceId);
            });
        });
        
        // Accept Counter-Offer
        document.querySelectorAll('[data-action="accept-counteroffer"]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const counterOfferId = btn.dataset.counterofferId;
                this.handleAcceptCounterOffer(counterOfferId);
            });
        });
        
        // Reject Counter-Offer
        document.querySelectorAll('[data-action="reject-counteroffer"]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const counterOfferId = btn.dataset.counterofferId;
                this.showRejectCounterOfferForm(counterOfferId);
            });
        });
        
        // Toggle Negotiable Checkbox
        const negotiableCheckbox = document.getElementById('negotiableCheckbox');
        if (negotiableCheckbox) {
            negotiableCheckbox.addEventListener('change', (e) => {
                this.toggleNegotiableFields(e.target.checked);
            });
        }
    }
    
    async handleCreateOffer() {
        const serviceId = document.getElementById('serviceId')?.value;
        const offeredPrice = document.getElementById('offeredPrice')?.value;
        
        if (!serviceId || !offeredPrice) {
            this.showAlert('Please fill in all required fields', 'error');
            return;
        }
        
        if (offeredPrice <= 0) {
            this.showAlert('Price must be greater than 0', 'error');
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'create_offer');
        formData.append('booking_id', this.bookingId);
        formData.append('service_id', serviceId);
        formData.append('offered_price', offeredPrice);
        
        try {
            const response = await this.makeRequest(formData);
            
            if (response.success) {
                this.showAlert('Offer created successfully! Provider will respond within 30 minutes.', 'success');
                this.updateNegotiationStatus();
                // Clear form
                document.getElementById('offeredPrice').value = '';
            } else {
                this.showAlert(response.message || 'Failed to create offer', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            this.showAlert('An error occurred. Please try again.', 'error');
        }
    }
    
    async handleAcceptOffer(offerId) {
        if (!confirm('Are you sure you want to accept this offer? The price will be locked.')) {
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'accept_offer');
        formData.append('offer_id', offerId);
        
        try {
            const response = await this.makeRequest(formData);
            
            if (response.success) {
                this.showAlert('Offer accepted! Service booking is confirmed at the agreed price.', 'success');
                this.updateNegotiationStatus();
            } else {
                this.showAlert(response.message || 'Failed to accept offer', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            this.showAlert('An error occurred. Please try again.', 'error');
        }
    }
    
    async handleRejectOffer(offerId) {
        const notes = prompt('Please provide a reason for rejection:');
        if (notes === null) return;
        
        const formData = new FormData();
        formData.append('action', 'reject_offer');
        formData.append('offer_id', offerId);
        formData.append('notes', notes);
        
        try {
            const response = await this.makeRequest(formData);
            
            if (response.success) {
                this.showAlert('Offer rejected. You can now send a counter-offer.', 'warning');
                this.updateNegotiationStatus();
            } else {
                this.showAlert(response.message || 'Failed to reject offer', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            this.showAlert('An error occurred. Please try again.', 'error');
        }
    }
    
    showCounterOfferForm(offerId, serviceId) {
        const modal = document.getElementById('counterOfferModal');
        if (!modal) return;
        
        document.getElementById('counterOfferOfferId').value = offerId;
        document.getElementById('counterOfferServiceId').value = serviceId;
        
        const form = document.getElementById('counterOfferForm');
        form.onsubmit = (e) => {
            e.preventDefault();
            this.handleSubmitCounterOffer();
        };
        
        // Show modal
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
    }
    
    async handleSubmitCounterOffer() {
        const offerId = document.getElementById('counterOfferOfferId').value;
        const serviceId = document.getElementById('counterOfferServiceId').value;
        const proposedPrice = document.getElementById('counterOfferPrice')?.value;
        const notes = document.getElementById('counterOfferNotes')?.value || '';
        
        if (!proposedPrice || proposedPrice <= 0) {
            this.showAlert('Please enter a valid price', 'error');
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'counteroffer');
        formData.append('offer_id', offerId);
        formData.append('service_id', serviceId);
        formData.append('proposed_price', proposedPrice);
        formData.append('notes', notes);
        
        try {
            const response = await this.makeRequest(formData);
            
            if (response.success) {
                this.showAlert('Counter-offer sent! Client will respond within 30 minutes.', 'success');
                this.updateNegotiationStatus();
                
                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('counterOfferModal'));
                modal.hide();
                
                // Clear form
                document.getElementById('counterOfferForm').reset();
            } else {
                this.showAlert(response.message || 'Failed to send counter-offer', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            this.showAlert('An error occurred. Please try again.', 'error');
        }
    }
    
    async handleAcceptCounterOffer(counterOfferId) {
        if (!confirm('Are you sure you want to accept this counter-offer? The price will be locked.')) {
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'accept_counteroffer');
        formData.append('counteroffer_id', counterOfferId);
        
        try {
            const response = await this.makeRequest(formData);
            
            if (response.success) {
                this.showAlert('Counter-offer accepted! Service booking is confirmed at the agreed price.', 'success');
                this.updateNegotiationStatus();
            } else {
                this.showAlert(response.message || 'Failed to accept counter-offer', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            this.showAlert('An error occurred. Please try again.', 'error');
        }
    }
    
    showRejectCounterOfferForm(counterOfferId) {
        const notes = prompt('Please provide a reason for rejection (optional):');
        if (notes === null) return;
        
        const formData = new FormData();
        formData.append('action', 'reject_counteroffer');
        formData.append('counteroffer_id', counterOfferId);
        formData.append('notes', notes);
        
        this.submitRejectCounterOffer(formData);
    }
    
    async submitRejectCounterOffer(formData) {
        try {
            const response = await this.makeRequest(formData);
            
            if (response.success) {
                this.showAlert('Counter-offer rejected. You can send a new offer in the next round.', 'warning');
                this.updateNegotiationStatus();
            } else {
                this.showAlert(response.message || 'Failed to reject counter-offer', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            this.showAlert('An error occurred. Please try again.', 'error');
        }
    }
    
    async updateNegotiationStatus() {
        const formData = new FormData();
        formData.append('action', 'get_status');
        formData.append('booking_id', this.bookingId);
        
        try {
            const response = await this.makeRequest(formData);
            
            if (response.success) {
                this.renderStatus(response.status);
                this.startTimers();
            }
        } catch (error) {
            console.error('Error updating status:', error);
        }
    }
    
    renderStatus(status) {
        const container = document.getElementById('negotiationStatusContainer');
        if (!container) return;
        
        let html = '';
        
        switch (status.status) {
            case 'finalized':
                html = `
                    <div class="negotiation-alert success">
                        <i class="fas fa-check-circle"></i>
                        <div>
                            <strong>Price Finalized</strong>
                            <p>Agreed Price: <strong class="text-success">RWF ${this.formatPrice(status.finalized_price)}</strong></p>
                            <small>Negotiation completed in ${status.rounds} round(s)</small>
                        </div>
                    </div>
                `;
                break;
            
            case 'offer_pending':
                html = `
                    <div class="negotiation-alert warning">
                        <i class="fas fa-hourglass-half"></i>
                        <div>
                            <strong>Waiting for Provider Response</strong>
                            <p>Your offer: <strong>RWF ${this.formatPrice(status.offered_price)}</strong></p>
                            <p>Expires in: <span class="time-remaining" data-expires="${new Date(Date.now() + status.time_remaining_minutes * 60000).toISOString()}"></span></p>
                        </div>
                    </div>
                `;
                break;
            
            case 'counter_pending':
                html = `
                    <div class="negotiation-alert info">
                        <i class="fas fa-exchange-alt"></i>
                        <div>
                            <strong>Counter-Offer Received</strong>
                            <p>Provider's counter offer: <strong class="text-success">RWF ${this.formatPrice(status.proposed_price)}</strong></p>
                            <p>Expires in: <span class="time-remaining" data-expires="${new Date(Date.now() + status.time_remaining_minutes * 60000).toISOString()}"></span></p>
                        </div>
                    </div>
                `;
                break;
            
            default:
                html = `<p class="text-muted">No active negotiation</p>`;
        }
        
        container.innerHTML = html;
    }
    
    startTimers() {
        document.querySelectorAll('.time-remaining').forEach(el => {
            const updateTimer = () => {
                const expiresAt = new Date(el.dataset.expires);
                const now = new Date();
                const diffMs = expiresAt - now;
                
                if (diffMs <= 0) {
                    el.textContent = 'Expired';
                    el.style.color = '#dc3545';
                    this.updateNegotiationStatus();
                } else {
                    const minutes = Math.floor(diffMs / 60000);
                    const seconds = Math.floor((diffMs % 60000) / 1000);
                    el.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
                }
            };
            
            updateTimer();
            setInterval(updateTimer, 1000);
        });
    }
    
    toggleNegotiableFields(isChecked) {
        const minPrice = document.getElementById('minPrice');
        const maxPrice = document.getElementById('maxPrice');
        
        if (!minPrice || !maxPrice) return;
        
        minPrice.parentElement.style.display = isChecked ? 'block' : 'none';
        maxPrice.parentElement.style.display = isChecked ? 'block' : 'none';
        
        if (isChecked) {
            minPrice.required = true;
            maxPrice.required = true;
        } else {
            minPrice.required = false;
            maxPrice.required = false;
        }
    }
    
    showAlert(message, type = 'info') {
        const container = document.getElementById('negotiationAlertContainer');
        if (!container) return;
        
        const alert = document.createElement('div');
        alert.className = `negotiation-alert ${type}`;
        alert.innerHTML = `
            <i class="fas fa-${type === 'error' ? 'exclamation-circle' : type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'}"></i>
            <div>${message}</div>
        `;
        
        container.innerHTML = '';
        container.appendChild(alert);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            alert.remove();
        }, 5000);
    }
    
    async makeRequest(formData) {
        const response = await fetch(this.apiEndpoint, {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        return await response.json();
    }
    
    formatPrice(price) {
        return new Intl.NumberFormat('en-US', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 2
        }).format(price);
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    const bookingId = document.body.dataset.bookingId;
    if (bookingId) {
        window.negotiationUI = new ServiceNegotiationUI(bookingId);
    }
});
