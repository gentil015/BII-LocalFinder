// login.js - Advanced Interactions
class LoginPage {
    constructor() {
        this.init();
    }

    init() {
        this.setupPasswordToggle();
        this.setupFormValidation();
        this.setupAnimations();
        this.setupSocialLogin();
    }

    setupPasswordToggle() {
        const passwordInput = document.querySelector('input[name="password"]');
        const toggleBtn = document.createElement('button');
        toggleBtn.type = 'button';
        toggleBtn.className = 'toggle-password';
        toggleBtn.innerHTML = '<i class="fas fa-eye"></i>';
        
        const passwordGroup = document.querySelector('.form-group:has(input[name="password"])');
        if (passwordGroup && passwordInput) {
            const wrapper = document.createElement('div');
            wrapper.className = 'password-input';
            passwordInput.parentNode.insertBefore(wrapper, passwordInput);
            wrapper.appendChild(passwordInput);
            wrapper.appendChild(toggleBtn);

            toggleBtn.addEventListener('click', () => {
                const type = passwordInput.type === 'password' ? 'text' : 'password';
                passwordInput.type = type;
                toggleBtn.innerHTML = type === 'password' ? 
                    '<i class="fas fa-eye"></i>' : 
                    '<i class="fas fa-eye-slash"></i>';
            });
        }
    }

    setupFormValidation() {
        const form = document.getElementById('loginForm');
        const emailInput = form.querySelector('input[name="email"]');
        const passwordInput = form.querySelector('input[name="password"]');
        const submitBtn = form.querySelector('button[type="submit"]');

        // Real-time validation
        emailInput.addEventListener('input', this.debounce(() => {
            this.validateEmail(emailInput);
        }, 300));

        passwordInput.addEventListener('input', this.debounce(() => {
            this.validatePassword(passwordInput);
        }, 300));

        // Form submission enhancement
        form.addEventListener('submit', (e) => {
            if (!this.validateForm(form)) {
                e.preventDefault();
                this.showFormErrors(form);
            } else {
                // Add loading state
                submitBtn.classList.add('loading');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging in...';
            }
        });
    }

    validateEmail(input) {
        const email = input.value.trim();
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        
        if (email && !emailRegex.test(email)) {
            this.showFieldError(input, 'Please enter a valid email address');
            return false;
        } else {
            this.clearFieldError(input);
            return true;
        }
    }

    validatePassword(input) {
        const password = input.value;
        
        if (password && password.length < 6) {
            this.showFieldError(input, 'Password must be at least 6 characters');
            return false;
        } else {
            this.clearFieldError(input);
            return true;
        }
    }

    validateForm(form) {
        let isValid = true;
        const emailInput = form.querySelector('input[name="email"]');
        const passwordInput = form.querySelector('input[name="password"]');

        if (!this.validateEmail(emailInput)) isValid = false;
        if (!this.validatePassword(passwordInput)) isValid = false;

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

        input.parentNode.appendChild(error);
        input.style.borderColor = 'var(--danger-color)';
    }

    clearFieldError(input) {
        const existingError = input.parentNode.querySelector('.field-error');
        if (existingError) {
            existingError.remove();
        }
        input.style.borderColor = '';
    }

    showFormErrors(form) {
        const alert = document.createElement('div');
        alert.className = 'alert alert-danger';
        alert.innerHTML = '<p>Please fix the errors above before submitting.</p>';
        
        const existingAlert = form.querySelector('.alert');
        if (existingAlert) {
            existingAlert.remove();
        }
        
        form.insertBefore(alert, form.firstChild);
        
        // Auto-remove alert after 5 seconds
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    }

    setupAnimations() {
        // Add focus border to inputs
        const inputs = document.querySelectorAll('.form-group input');
        inputs.forEach(input => {
            const focusBorder = document.createElement('div');
            focusBorder.className = 'focus-border';
            input.parentNode.appendChild(focusBorder);

            input.addEventListener('focus', () => {
                focusBorder.style.transform = 'scaleX(1)';
            });

            input.addEventListener('blur', () => {
                if (!input.value) {
                    focusBorder.style.transform = 'scaleX(0)';
                }
            });
        });
    }

    setupSocialLogin() {
        // Add social login buttons if needed
        const socialLoginSection = document.createElement('div');
        socialLoginSection.className = 'social-login';
        socialLoginSection.innerHTML = `
            <div class="social-divider">Or continue with</div>
            <div class="social-buttons">
                <button type="button" class="social-btn google">
                    <i class="fab fa-google"></i>
                    <span>Google</span>
                </button>
                <button type="button" class="social-btn facebook">
                    <i class="fab fa-facebook-f"></i>
                    <span>Facebook</span>
                </button>
            </div>
        `;

        const form = document.getElementById('loginForm');
        form.parentNode.insertBefore(socialLoginSection, form.nextSibling);

        // Add social login handlers
        document.querySelectorAll('.social-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                this.handleSocialLogin(btn.classList.contains('google') ? 'google' : 'facebook');
            });
        });
    }

    handleSocialLogin(provider) {
        // Implement social login logic here
        console.log(`Social login with ${provider}`);
        // Redirect to social auth endpoint
        // window.location.href = `auth/${provider}.php`;
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
    new LoginPage();
});

// Auto-dismiss alerts
document.querySelectorAll('.alert').forEach(alert => {
    setTimeout(() => {
        alert.style.opacity = '0';
        alert.style.transform = 'translateX(100%)';
        setTimeout(() => alert.remove(), 300);
    }, 5000);
});

// Enhanced input interactions
document.querySelectorAll('.form-group input').forEach(input => {
    input.addEventListener('focus', function() {
        this.parentElement.classList.add('focused');
    });

    input.addEventListener('blur', function() {
        if (!this.value) {
            this.parentElement.classList.remove('focused');
        }
    });

    // Auto-focus first input
    if (input.name === 'email') {
        setTimeout(() => input.focus(), 500);
    }
});