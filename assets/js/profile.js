// assets/js/profile.js

document.addEventListener('DOMContentLoaded', function() {
  // Mobile navigation toggle
  const hamburger = document.querySelector('.hamburger');
  const navMenu = document.querySelector('.nav-menu');
  
  if (hamburger) {
    hamburger.addEventListener('click', function() {
      hamburger.classList.toggle('active');
      navMenu.classList.toggle('active');
    });
  }
  
  // Navbar scroll effect
  const navbar = document.querySelector('.navbar');
  
  window.addEventListener('scroll', function() {
    if (window.scrollY > 50) {
      navbar.classList.add('scrolled');
    } else {
      navbar.classList.remove('scrolled');
    }
  });
  
  // Close mobile menu when clicking on a link
  const navLinks = document.querySelectorAll('.nav-menu a');
  
  navLinks.forEach(link => {
    link.addEventListener('click', function() {
      hamburger.classList.remove('active');
      navMenu.classList.remove('active');
    });
  });
  
  // Auto-dismiss alerts with enhanced animation
  const alerts = document.querySelectorAll('.alert');
  
  alerts.forEach(alert => {
    setTimeout(() => {
      alert.style.opacity = '0';
      alert.style.transform = 'translateX(-100%)';
      setTimeout(() => alert.remove(), 300);
    }, 5000);
  });
  
  // Form validation and enhancement
  const bookingForm = document.querySelector('form');
  
  if (bookingForm) {
    const preferredDateInput = bookingForm.querySelector('input[name="preferred_date"]');
    
    // Set minimum date to today
    if (preferredDateInput) {
      preferredDateInput.min = new Date().toISOString().split('T')[0];
    }
    
    // Add loading state to form submission
    bookingForm.addEventListener('submit', function(e) {
      const submitButton = this.querySelector('button[type="submit"]');
      if (submitButton) {
        submitButton.classList.add('loading');
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending Request...';
      }
    });
  }
  
  // Animate rating bars on scroll
  const ratingBars = document.querySelectorAll('.rating-bar-fill');
  
  const observerOptions = {
    threshold: 0.5,
    rootMargin: '0px 0px -50px 0px'
  };
  
  const observer = new IntersectionObserver(function(entries) {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const width = entry.target.style.width;
        entry.target.style.width = '0';
        setTimeout(() => {
          entry.target.style.width = width;
        }, 300);
        observer.unobserve(entry.target);
      }
    });
  }, observerOptions);
  
  ratingBars.forEach(bar => {
    observer.observe(bar);
  });
  
  // Add smooth scrolling for anchor links
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
      e.preventDefault();
      const target = document.querySelector(this.getAttribute('href'));
      if (target) {
        target.scrollIntoView({
          behavior: 'smooth',
          block: 'start'
        });
      }
    });
  });
  
  // Enhance review items with hover effects
  const reviewItems = document.querySelectorAll('.review-item');
  
  reviewItems.forEach(item => {
    item.addEventListener('mouseenter', function() {
      this.style.transform = 'translateY(-5px)';
      this.style.boxShadow = 'var(--shadow-lg)';
    });
    
    item.addEventListener('mouseleave', function() {
      this.style.transform = 'translateY(0)';
      this.style.boxShadow = 'var(--shadow)';
    });
  });
  
  // Add parallax effect to profile hero
  window.addEventListener('scroll', function() {
    const scrolled = window.pageYOffset;
    const profileHero = document.querySelector('.profile-hero');
    if (profileHero) {
      profileHero.style.transform = `translateY(${scrolled * 0.5}px)`;
    }
  });
});

// Additional styles for enhanced animations
const additionalStyles = `
  .rating-bar-fill {
    transition: width 1.5s cubic-bezier(0.4, 0, 0.2, 1);
  }
  
  .review-item {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  }
  
  .profile-hero {
    transition: transform 0.1s ease-out;
  }
  
  .category-badge {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  }
`;

// Inject additional styles
const styleSheet = document.createElement('style');
styleSheet.textContent = additionalStyles;
document.head.appendChild(styleSheet);