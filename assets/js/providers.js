// Enhanced providers.js
class ProvidersPage {
    constructor() {
        this.init();
    }

    init() {
        this.setupIntersectionObserver();
        this.setupAdvancedFilters();
        this.setupSmoothAnimations();
        this.setupBookingEnhancements();
        this.setupSorting();
    }

    setupIntersectionObserver() {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    
                    // Stagger animation for cards
                    const delay = Array.from(entry.target.parentNode.children).indexOf(entry.target) * 100;
                    entry.target.style.transitionDelay = `${delay}ms`;
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        });

        document.querySelectorAll('.provider-card').forEach(card => {
            observer.observe(card);
        });
    }

    setupAdvancedFilters() {
        const form = document.querySelector('.search-filter-form');
        const inputs = form.querySelectorAll('input, select');
        
        inputs.forEach(input => {
            // Real-time validation
            input.addEventListener('input', this.debounce(() => {
                this.validateField(input);
            }, 300));

            // Focus effects
            input.addEventListener('focus', () => {
                input.parentElement.classList.add('focused');
            });

            input.addEventListener('blur', () => {
                input.parentElement.classList.remove('focused');
            });
        });
    }

    setupSmoothAnimations() {
        // Parallax effect for header
        window.addEventListener('scroll', () => {
            const scrolled = window.pageYOffset;
            const parallax = document.querySelector('.page-header');
            if (parallax) {
                parallax.style.transform = `translateY(${scrolled * 0.5}px)`;
            }
        });

        // Enhanced button hover effects
        document.querySelectorAll('.btn-primary, .btn-secondary').forEach(btn => {
            btn.addEventListener('mousemove', (e) => {
                const rect = btn.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                
                const centerX = rect.width / 2;
                const centerY = rect.height / 2;
                
                const angleX = (y - centerY) / 10;
                const angleY = (centerX - x) / 10;
                
                btn.style.transform = `perspective(1000px) rotateX(${angleX}deg) rotateY(${angleY}deg)`;
            });

            btn.addEventListener('mouseleave', () => {
                btn.style.transform = 'perspective(1000px) rotateX(0) rotateY(0)';
            });
        });
    }

    setupBookingEnhancements() {
        const modal = document.getElementById('bookingModal');
        
        if (modal) {
            // Prevent background scroll when modal is open
            modal.addEventListener('show', () => {
                document.body.style.overflow = 'hidden';
            });

            modal.addEventListener('hide', () => {
                document.body.style.overflow = '';
            });

            // Enhanced form validation
            const form = modal.querySelector('form');
            form.addEventListener('submit', (e) => {
                if (!this.validateBookingForm(form)) {
                    e.preventDefault();
                    this.showFormErrors(form);
                }
            });
        }
    }

    setupSorting() {
        const sortDropdown = document.querySelector('.sort-dropdown');
        if (sortDropdown) {
            sortDropdown.addEventListener('change', (e) => {
                // Add loading state
                const providersGrid = document.querySelector('.providers-grid');
                providersGrid.classList.add('loading');
                
                setTimeout(() => {
                    providersGrid.classList.remove('loading');
                }, 500);
            });
        }
    }

    validateBookingForm(form) {
        let isValid = true;
        const inputs = form.querySelectorAll('[required]');

        inputs.forEach(input => {
            if (!input.value.trim()) {
                isValid = false;
                this.showFieldError(input, 'This field is required');
            } else {
                this.clearFieldError(input);
            }

            // Date validation
            if (input.type === 'date') {
                const selectedDate = new Date(input.value);
                const today = new Date();
                today.setHours(0, 0, 0, 0);

                if (selectedDate < today) {
                    isValid = false;
                    this.showFieldError(input, 'Please select a future date');
                }
            }
        });

        return isValid;
    }

    showFieldError(input, message) {
        this.clearFieldError(input);
        
        const error = document.createElement('div');
        error.className = 'field-error';
        error.textContent = message;
        error.style.cssText = `
            color: var(--danger-color);
            font-size: 0.875rem;
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        `;

        input.parentElement.appendChild(error);
        input.style.borderColor = 'var(--danger-color)';
    }

    clearFieldError(input) {
        const existingError = input.parentElement.querySelector('.field-error');
        if (existingError) {
            existingError.remove();
        }
        input.style.borderColor = '';
    }

    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new ProvidersPage();
});

// Global functions for HTML onclick
window.openBookingModal = function(providerId, providerName, profession) {
    document.getElementById('modalProviderId').value = providerId;
    document.getElementById('modalProviderName').textContent = providerName;
    document.getElementById('modalProviderProfession').textContent = profession;
    
    const modal = document.getElementById('bookingModal');
    modal.classList.add('active');
    modal.dispatchEvent(new Event('show'));
};

window.closeBookingModal = function() {
    const modal = document.getElementById('bookingModal');
    modal.classList.remove('active');
    modal.dispatchEvent(new Event('hide'));
};

// Close modal on outside click
document.getElementById('bookingModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeBookingModal();
    }
});

// Enhanced alert auto-dismiss
document.querySelectorAll('.alert').forEach(alert => {
    setTimeout(() => {
        alert.style.opacity = '0';
        alert.style.transform = 'translateX(100%)';
        setTimeout(() => alert.remove(), 300);
    }, 5000);
});

// Mobile navigation
document.querySelector('.hamburger')?.addEventListener('click', function() {
    document.querySelector('.nav-menu').classList.toggle('active');
    this.classList.toggle('active');
});