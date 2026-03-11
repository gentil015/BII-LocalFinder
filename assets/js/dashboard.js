// assets/js/dashboard.js

document.addEventListener('DOMContentLoaded', function() {
    // Mobile sidebar toggle
    const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (mobileMenuToggle && sidebar) {
        mobileMenuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            this.innerHTML = sidebar.classList.contains('active') 
                ? '<i class="fas fa-times"></i>' 
                : '<i class="fas fa-bars"></i>';
        });
    }
    
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024 && 
            !sidebar.contains(e.target) && 
            !mobileMenuToggle.contains(e.target) && 
            sidebar.classList.contains('active')) {
            sidebar.classList.remove('active');
            mobileMenuToggle.innerHTML = '<i class="fas fa-bars"></i>';
        }
    });
    
    // Navbar scroll effect
    const navbar = document.querySelector('.navbar');
    
    window.addEventListener('scroll', function() {
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });
    
    // Animate stat cards on scroll
    const statCards = document.querySelectorAll('.stat-card');
    
    const observerOptions = {
        threshold: 0.2,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.animationDelay = `${entry.target.dataset.delay || 0}ms`;
                entry.target.classList.add('animate-in');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);
    
    statCards.forEach((card, index) => {
        card.dataset.delay = index * 100;
        observer.observe(card);
    });
    
    // Availability status change with confirmation
    const availabilitySelect = document.querySelector('select[name="availability"]');
    
    if (availabilitySelect) {
        availabilitySelect.addEventListener('change', function() {
            const newStatus = this.value;
            const statusText = this.options[this.selectedIndex].text;
            
            if (confirm(`Are you sure you want to set your status to "${statusText}"?`)) {
                showLoadingState(this);
                this.form.submit();
            } else {
                this.value = this.dataset.previousValue || 'available';
            }
        });
        
        // Store initial value
        availabilitySelect.dataset.previousValue = availabilitySelect.value;
    }
    
    function showLoadingState(element) {
        element.disabled = true;
        element.style.opacity = '0.7';
        element.parentNode.classList.add('loading');
    }
    
    // Simulate real-time updates
    function updateStatsPeriodically() {
        setInterval(() => {
            // This would typically make an API call to get updated stats
            console.log('Checking for updates...');
        }, 30000); // Check every 30 seconds
    }
    
    // Initialize real-time updates
    updateStatsPeriodically();
    
    // Add keyboard navigation for sidebar
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar.classList.contains('active')) {
            sidebar.classList.remove('active');
            mobileMenuToggle.innerHTML = '<i class="fas fa-bars"></i>';
        }
    });
    
    // Add hover effects to booking items
    const bookingItems = document.querySelectorAll('.booking-item');
    bookingItems.forEach(item => {
        item.addEventListener('mouseenter', function() {
            this.style.transform = 'translateX(8px)';
        });
        
        item.addEventListener('mouseleave', function() {
            this.style.transform = 'translateX(0)';
        });
    });
});

// Additional styles for animations
const additionalStyles = `
    .stat-card {
        opacity: 0;
        transform: translateY(20px);
        transition: opacity 0.6s ease, transform 0.6s ease;
    }
    
    .stat-card.animate-in {
        opacity: 1;
        transform: translateY(0);
    }
    
    .booking-item {
        animation: slideIn 0.5s ease;
    }
    
    .sidebar {
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
`;

// Inject additional styles
const styleSheet = document.createElement('style');
styleSheet.textContent = additionalStyles;
document.head.appendChild(styleSheet);