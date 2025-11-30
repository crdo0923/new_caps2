// ========================================
// AUTH PAGE FUNCTIONALITY
// ========================================

// Toggle between Login and Register forms
document.addEventListener('DOMContentLoaded', function() {
    const toggleButtons = document.querySelectorAll('.toggle-btn');
    const loginForm = document.getElementById('login-form');
    const registerForm = document.getElementById('register-form');

    toggleButtons.forEach(button => {
        button.addEventListener('click', function() {
            const formType = this.getAttribute('data-form');
            
            // Remove active class from all buttons
            toggleButtons.forEach(btn => btn.classList.remove('active'));
            
            // Add active class to clicked button
            this.classList.add('active');
            
            // Toggle forms
            if (formType === 'login') {
                loginForm.classList.add('active');
                registerForm.classList.remove('active');
            } else {
                registerForm.classList.add('active');
                loginForm.classList.remove('active');
            }
        });
    });
});

// ========================================
// PASSWORD TOGGLE VISIBILITY
// ========================================
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const button = input.nextElementSibling;
    
    if (input.type === 'password') {
        input.type = 'text';
        button.textContent = '🙈';
    } else {
        input.type = 'password';
        button.textContent = '👁️';
    }
}

// ========================================
// FORM VALIDATION
// ========================================

// Real-time email validation
const emailInputs = document.querySelectorAll('input[type="email"]');
emailInputs.forEach(input => {
    input.addEventListener('blur', function() {
        validateEmail(this);
    });
});

function validateEmail(input) {
    const email = input.value;
    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    
    if (!emailPattern.test(email)) {
        input.style.borderColor = 'var(--danger)';
        showError(input, 'Please enter a valid email address');
    } else {
        input.style.borderColor = 'var(--success)';
        removeError(input);
    }
}

// Password strength checker
const passwordInputs = document.querySelectorAll('input[type="password"]');
passwordInputs.forEach(input => {
    if (input.id.includes('reg-password') && !input.id.includes('confirm')) {
        input.addEventListener('input', function() {
            checkPasswordStrength(this);
        });
    }
});

function checkPasswordStrength(input) {
    const password = input.value;
    let strength = 0;
    
    // Check length
    if (password.length >= 8) strength++;
    if (password.length >= 12) strength++;
    
    // Check for numbers
    if (/\d/.test(password)) strength++;
    
    // Check for lowercase and uppercase
    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
    
    // Check for special characters
    if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) strength++;
    
    // Update border color based on strength
    if (strength <= 2) {
        input.style.borderColor = 'var(--danger)';
    } else if (strength <= 3) {
        input.style.borderColor = 'var(--warning)';
    } else {
        input.style.borderColor = 'var(--success)';
    }
}

// Confirm password validation
const confirmPassword = document.getElementById('reg-confirm');
if (confirmPassword) {
    confirmPassword.addEventListener('input', function() {
        const password = document.getElementById('reg-password').value;
        const confirm = this.value;
        
        if (password !== confirm) {
            this.style.borderColor = 'var(--danger)';
            showError(this, 'Passwords do not match');
        } else {
            this.style.borderColor = 'var(--success)';
            removeError(this);
        }
    });
}

// ========================================
// ERROR HANDLING
// ========================================
function showError(input, message) {
    removeError(input);
    
    const errorDiv = document.createElement('div');
    errorDiv.className = 'error-message';
    errorDiv.textContent = message;
    errorDiv.style.color = 'var(--danger)';
    errorDiv.style.fontSize = '0.85rem';
    errorDiv.style.marginTop = '0.5rem';
    
    input.parentElement.parentElement.appendChild(errorDiv);
}

function removeError(input) {
    const errorMessage = input.parentElement.parentElement.querySelector('.error-message');
    if (errorMessage) {
        errorMessage.remove();
    }
}

// ========================================
// FORM SUBMISSION - CLIENT-SIDE VALIDATION ONLY
// Forms will submit to PHP backend naturally
// ========================================
const loginFormElement = document.querySelector('#login-form .auth-form');
const registerFormElement = document.querySelector('#register-form .auth-form');

if (loginFormElement) {
    loginFormElement.addEventListener('submit', function(e) {
        const email = document.getElementById('login-email').value;
        const password = document.getElementById('login-password').value;
        
        // Basic validation only - don't prevent submission
        if (!email || !password) {
            e.preventDefault();
            alert('Please fill in all fields');
            return;
        }
        
        // Show loading state
        const submitBtn = this.querySelector('.btn-submit');
        submitBtn.innerHTML = '<span>Logging in...</span>';
        submitBtn.disabled = true;
        
        // Let form submit naturally to PHP backend
        // PHP will handle the actual authentication
    });
}

if (registerFormElement) {
    registerFormElement.addEventListener('submit', function(e) {
        const password = document.getElementById('reg-password').value;
        const confirmPass = document.getElementById('reg-confirm').value;
        const terms = this.querySelector('input[name="terms"]').checked;
        
        // Client-side validation before submitting to PHP
        if (password !== confirmPass) {
            e.preventDefault();
            alert('Passwords do not match!');
            return;
        }
        
        if (password.length < 8) {
            e.preventDefault();
            alert('Password must be at least 8 characters long');
            return;
        }
        
        if (!terms) {
            e.preventDefault();
            alert('Please accept the Terms & Conditions');
            return;
        }
        
        // Show loading state
        const submitBtn = this.querySelector('.btn-submit');
        submitBtn.innerHTML = '<span>Creating account...</span>';
        submitBtn.disabled = true;
        
        // Let form submit naturally to PHP backend
        // PHP will handle the actual registration and database insertion
    });
}

// ========================================
// FORGOT PASSWORD
// ========================================
const forgotLink = document.querySelector('.forgot-link');
if (forgotLink) {
    forgotLink.addEventListener('click', function(e) {
        e.preventDefault();
        const email = prompt('Enter your email address:');
        if (email) {
            alert(`Password reset link will be sent to: ${email}`);
            // TODO: Implement actual forgot password logic with PHP backend
        }
    });
}