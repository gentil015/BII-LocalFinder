/**
 * Provider Requirements JavaScript Helper
 * 
 * Provides client-side utilities for interacting with provider requirements
 * Including real-time updates, progress tracking, and status displays
 */

class ProviderRequirements {
    constructor(providerId, apiUrl = '/api/provider-requirements.php') {
        this.providerId = providerId;
        this.apiUrl = apiUrl;
        this.data = null;
        this.updateInterval = null;
    }

    /**
     * Fetch requirements status from server
     */
    async fetch(action = 'status') {
        try {
            const response = await fetch(
                `${this.apiUrl}?provider_id=${this.providerId}&action=${action}`
            );
            const data = await response.json();
            
            if (data.success) {
                this.data = data;
                return data;
            } else {
                console.error('Error fetching requirements:', data.error);
                return null;
            }
        } catch (error) {
            console.error('Fetch error:', error);
            return null;
        }
    }

    /**
     * Get completion percentage
     */
    getPercentage() {
        return this.data?.completion_percentage || 0;
    }

    /**
     * Check if profile is complete
     */
    isComplete() {
        return this.data?.is_complete || false;
    }

    /**
     * Get count of completed requirements
     */
    getCount() {
        return this.data?.count || { completed: 0, total: 5 };
    }

    /**
     * Get next incomplete requirement
     */
    getNextStep() {
        return this.data?.next_step || null;
    }

    /**
     * Update badge HTML in DOM
     */
    async updateBadge(selector = '.profile-completion-badge') {
        const response = await this.fetch('badge');
        if (response?.badge_html) {
            const element = document.querySelector(selector);
            if (element) {
                element.outerHTML = response.badge_html;
            }
        }
    }

    /**
     * Update checklist HTML in DOM
     */
    async updateChecklist(selector = '.provider-requirements-checklist', full = false) {
        const action = full ? 'checklist_full' : 'checklist_mini';
        const response = await this.fetch(action);
        if (response?.html) {
            const element = document.querySelector(selector);
            if (element) {
                element.innerHTML = response.html;
            }
        }
    }

    /**
     * Update progress bar
     */
    async updateProgress(selector = '.checklist-progress-bar .progress-bar-fill') {
        await this.fetch('check');
        const percentage = this.getPercentage();
        const element = document.querySelector(selector);
        if (element) {
            element.style.width = percentage + '%';
        }
    }

    /**
     * Display notification if requirements changed
     */
    async checkAndNotify() {
        const oldPercentage = this.getPercentage();
        await this.fetch('status');
        const newPercentage = this.getPercentage();

        if (newPercentage > oldPercentage) {
            this.showNotification(
                `Great! You're ${newPercentage}% complete. ${5 - this.getCount().completed} more to go!`,
                'success'
            );
        }

        if (newPercentage === 100) {
            this.showNotification(
                'Congratulations! Your profile is complete!',
                'success'
            );
        }
    }

    /**
     * Show notification toast
     */
    showNotification(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `alert alert-${type} alert-dismissible fade show`;
        toast.setAttribute('role', 'alert');
        toast.innerHTML = `
            <strong>${type === 'success' ? '✓' : 'ℹ'}</strong> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        const container = document.querySelector('body');
        container.insertBefore(toast, container.firstChild);
        
        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            toast.remove();
        }, 5000);
    }

    /**
     * Start auto-refresh (checks every X ms)
     */
    startAutoRefresh(interval = 30000) {
        this.updateInterval = setInterval(async () => {
            await this.checkAndNotify();
            await this.updateProgress();
        }, interval);
    }

    /**
     * Stop auto-refresh
     */
    stopAutoRefresh() {
        if (this.updateInterval) {
            clearInterval(this.updateInterval);
            this.updateInterval = null;
        }
    }

    /**
     * Get all requirements details
     */
    getDetails() {
        return this.data?.details || [];
    }

    /**
     * Check if provider can receive bookings (>= 80%)
     */
    async canReceiveBookings() {
        const response = await this.fetch('ready_for_bookings');
        return response?.ready || false;
    }

    /**
     * Display checklist items with checkmarks
     */
    renderRequirementsList() {
        const details = this.getDetails();
        let html = '<ul class="requirements-list">';
        
        details.forEach(req => {
            const icon = req.completed ? 
                '<i class="fas fa-check-circle text-success"></i>' :
                '<i class="fas fa-circle text-secondary"></i>';
            html += `
                <li class="${req.completed ? 'completed' : 'incomplete'}">
                    ${icon} <span>${req.name}</span>
                </li>
            `;
        });
        
        html += '</ul>';
        return html;
    }

    /**
     * Generate summary text
     */
    getSummaryText() {
        const count = this.getCount();
        const remaining = count.total - count.completed;
        
        if (remaining === 0) {
            return `✓ All ${count.total} requirements complete!`;
        }
        
        if (remaining === 1) {
            return `${count.completed}/${count.total} complete. 1 more to go!`;
        }
        
        return `${count.completed}/${count.total} complete. ${remaining} more to go!`;
    }

    /**
     * Log requirement status for debugging
     */
    logStatus() {
        console.group(`Provider ${this.providerId} Requirements`);
        console.log('Percentage:', this.getPercentage() + '%');
        console.log('Complete:', this.isComplete());
        console.log('Count:', this.getCount());
        console.log('Next Step:', this.getNextStep());
        console.log('Full Data:', this.data);
        console.groupEnd();
    }
}

/**
 * Initialize provider requirements for current provider
 * Call this on page load
 */
async function initProviderRequirements(providerId) {
    const req = new ProviderRequirements(providerId);
    await req.fetch('status');
    return req;
}

/**
 * Watch for requirement changes and update UI
 */
function watchProviderRequirements(providerId, updateSelectors = {}) {
    const req = new ProviderRequirements(providerId);
    
    // Update initial state
    req.fetch('status').then(() => {
        if (updateSelectors.progress) req.updateProgress(updateSelectors.progress);
        if (updateSelectors.badge) req.updateBadge(updateSelectors.badge);
    });
    
    // Auto-refresh every 30 seconds
    req.startAutoRefresh(30000);
    
    return req;
}

/**
 * Simple one-time check
 */
async function checkProviderCompletion(providerId) {
    const req = new ProviderRequirements(providerId);
    await req.fetch('check');
    return {
        percentage: req.getPercentage(),
        count: req.getCount(),
        complete: req.isComplete()
    };
}

/**
 * Handle form submission - show what's needed
 */
function showMissingRequirements(providerId) {
    const req = new ProviderRequirements(providerId);
    
    req.fetch('requirements').then(() => {
        const details = req.getDetails();
        const missing = details.filter(d => !d.completed);
        
        if (missing.length === 0) {
            req.showNotification('Your profile is complete!', 'success');
            return;
        }
        
        let message = '<strong>Complete these to receive bookings:</strong><ul>';
        missing.forEach(req => {
            message += `<li>${req.name}: ${req.help_text}</li>`;
        });
        message += '</ul>';
        
        req.showNotification(message, 'warning');
    });
}

// Export for use in modules if needed
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { 
        ProviderRequirements, 
        initProviderRequirements, 
        watchProviderRequirements,
        checkProviderCompletion,
        showMissingRequirements
    };
}
