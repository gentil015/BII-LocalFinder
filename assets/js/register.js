// assets/js/register.js

document.addEventListener('DOMContentLoaded', function() {
  const form = document.getElementById('registerForm');
  const userTypeSelect = document.getElementById('userType');
  const providerFields = document.getElementById('providerFields');
  const submitButton = form?.querySelector('button[type="submit"]');

  // Toggle provider fields
  function toggleProviderFields() {
    const isProvider = userTypeSelect.value === 'provider';
    
    if (isProvider) {
      providerFields.style.display = 'block';
      // Add animation class
      providerFields.classList.add('fields-visible');
      
      // Make provider fields required
      const providerInputs = providerFields.querySelectorAll('input[name="profession"], input[name="location"]');
      providerInputs.forEach(input => {
        input.required = true;
      });
    } else {
      providerFields.style.display = 'none';
      providerFields.classList.remove('fields-visible');
      
      // Remove required from provider fields
      const providerInputs = providerFields.querySelectorAll('input');
      providerInputs.forEach(input => {
        input.required = false;
      });
    }
  }

  // Initialize provider fields
  if (userTypeSelect && providerFields) {
    userTypeSelect.addEventListener('change', toggleProviderFields);
    toggleProviderFields(); // Initial call
  }

  // Password strength indicator
  const passwordInput = form?.querySelector('input[name="password"]');
  if (passwordInput) {
    passwordInput.addEventListener('input', function() {
      const strength = calculatePasswordStrength(this.value);
      updatePasswordStrengthIndicator(strength);
    });
  }

  // Real-time password matching
  const confirmPasswordInput = form?.querySelector('input[name="confirm_password"]');
  if (passwordInput && confirmPasswordInput) {
    confirmPasswordInput.addEventListener('input', function() {
      const passwordsMatch = passwordInput.value === this.value;
      this.style.borderColor = passwordsMatch ? 'var(--success)' : 'var(--danger)';
    });
  }

  // Form submission handler
  if (form) {
    form.addEventListener('submit', function(e) {
      if (!validateForm()) {
        e.preventDefault();
        return;
      }
      
      // Show loading state
      if (submitButton) {
        submitButton.classList.add('loading');
        submitButton.disabled = true;
        submitButton.innerHTML = 'Creating Account...';
      }
    });
  }

  // Form validation
  function validateForm() {
    let isValid = true;
    const inputs = form.querySelectorAll('input[required], select[required]');
    
    inputs.forEach(input => {
      if (!input.value.trim()) {
        showFieldError(input, 'This field is required');
        isValid = false;
      } else {
        clearFieldError(input);
      }
    });

    // Email validation
    const emailInput = form.querySelector('input[type="email"]');
    if (emailInput && emailInput.value) {
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(emailInput.value)) {
        showFieldError(emailInput, 'Please enter a valid email address');
        isValid = false;
      }
    }

    // Password validation
    if (passwordInput && passwordInput.value.length < 6) {
      showFieldError(passwordInput, 'Password must be at least 6 characters');
      isValid = false;
    }

    // Password match validation
    if (passwordInput && confirmPasswordInput && passwordInput.value !== confirmPasswordInput.value) {
      showFieldError(confirmPasswordInput, 'Passwords do not match');
      isValid = false;
    }

    return isValid;
  }

  function showFieldError(input, message) {
    clearFieldError(input);
    input.style.borderColor = 'var(--danger)';
    
    const errorElement = document.createElement('div');
    errorElement.className = 'field-error';
    errorElement.style.cssText = `
      color: var(--danger);
      font-size: 0.875rem;
      margin-top: 0.25rem;
      animation: slideIn 0.3s ease-out;
    `;
    errorElement.textContent = message;
    
    input.parentNode.appendChild(errorElement);
  }

  function clearFieldError(input) {
    const existingError = input.parentNode.querySelector('.field-error');
    if (existingError) {
      existingError.remove();
    }
    input.style.borderColor = '';
  }

  function calculatePasswordStrength(password) {
    let strength = 0;
    
    if (password.length >= 6) strength += 1;
    if (password.length >= 8) strength += 1;
    if (/[A-Z]/.test(password)) strength += 1;
    if (/[0-9]/.test(password)) strength += 1;
    if (/[^A-Za-z0-9]/.test(password)) strength += 1;
    
    return Math.min(strength, 5);
  }

  function updatePasswordStrengthIndicator(strength) {
    let strengthElement = document.getElementById('password-strength');
    
    if (!strengthElement) {
      strengthElement = document.createElement('div');
      strengthElement.id = 'password-strength';
      strengthElement.style.cssText = `
        height: 4px;
        background: var(--border-light);
        border-radius: 2px;
        margin-top: 0.5rem;
        overflow: hidden;
        position: relative;
      `;
      
      const strengthBar = document.createElement('div');
      strengthBar.id = 'password-strength-bar';
      strengthBar.style.cssText = `
        position: absolute;
        top: 0;
        left: 0;
        height: 100%;
        width: 0;
        transition: var(--transition);
        border-radius: 2px;
      `;
      
      strengthElement.appendChild(strengthBar);
      passwordInput.parentNode.appendChild(strengthElement);
    }
    
    const strengthBar = document.getElementById('password-strength-bar');
    const width = (strength / 5) * 100;
    
    let color;
    if (strength <= 2) color = 'var(--danger)';
    else if (strength <= 3) color = 'var(--accent)';
    else color = 'var(--success)';
    
    strengthBar.style.width = `${width}%`;
    strengthBar.style.background = color;
  }
});

// Add this to your existing main.js or include it separately