/*=============== SWIPER INITIALIZATION ===============*/
const swiperRegister = new Swiper('.register__swiper', {
    loop: true,
    spaceBetween: 24,
    grabCursor: true,
    speed: 800,
    effect: 'fade',
    fadeEffect: {
        crossFade: true
    },
    pagination: {
        el: '.swiper-pagination',
        clickable: true,
    },
    autoplay: {
        delay: 4000,
        disableOnInteraction: false,
        pauseOnMouseEnter: true,
    }
});

/*=============== SMOOTH SCROLLING FOR FORM ===============*/
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.register__form');
    const formContent = document.querySelector('.register__content');
    
    if (form) {
        // Smooth scroll with momentum
        let isScrolling = false;
        let scrollTimeout;
        
        form.addEventListener('scroll', function() {
            if (!isScrolling) {
                isScrolling = true;
                this.style.scrollBehavior = 'smooth';
            }
            
            // Clear the previous timeout
            clearTimeout(scrollTimeout);
            
            // Set a new timeout
            scrollTimeout = setTimeout(() => {
                isScrolling = false;
            }, 150);
        }, { passive: true });

        // Auto-scroll to first error field
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('error')) {
            const firstErrorField = document.querySelector('.register__input.error');
            if (firstErrorField) {
                setTimeout(() => {
                    firstErrorField.scrollIntoView({ 
                        behavior: 'smooth', 
                        block: 'center' 
                    });
                    firstErrorField.focus();
                }, 100);
            }
        }
    }

    /*=============== INPUT FOCUS SCROLL ===============*/
    const inputs = document.querySelectorAll('.register__input');
    
    inputs.forEach((input, index) => {
        input.addEventListener('focus', function() {
            // Ensure the input is fully visible when focused
            const rect = this.getBoundingClientRect();
            const formRect = form.getBoundingClientRect();
            
            if (rect.bottom > formRect.bottom || rect.top < formRect.top) {
                this.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'center' 
                });
            }
        });
    });

    /*=============== KEYBOARD NAVIGATION ===============*/
    form.addEventListener('keydown', function(e) {
        if (e.key === 'Tab') {
            // Smooth scroll to next/prev input on tab
            setTimeout(() => {
                const activeElement = document.activeElement;
                if (activeElement.classList.contains('register__input')) {
                    activeElement.scrollIntoView({ 
                        behavior: 'smooth', 
                        block: 'center' 
                    });
                }
            }, 50);
        }
    });
});

/*=============== INPUT ANIMATIONS ===============*/
const registerInputs = document.querySelectorAll('.register__input');

registerInputs.forEach(input => {
    // Add floating label effect on focus
    input.addEventListener('focus', function() {
        const box = this.closest('.register__box');
        const icon = box.querySelector('i:not(.register__eye)');
        if (icon) {
            icon.style.color = 'var(--first-color)';
            icon.style.transform = 'scale(1.1)';
            icon.style.transition = 'all 0.2s ease';
        }
        
        // Add subtle pulse effect to the box
        box.style.transform = 'scale(1.01)';
        box.style.transition = 'transform 0.2s ease';
    });

    input.addEventListener('blur', function() {
        const box = this.closest('.register__box');
        const icon = box.querySelector('i:not(.register__eye)');
        if (icon && !this.value) {
            icon.style.color = 'var(--text-color)';
            icon.style.transform = 'scale(1)';
        } else if (icon && this.value) {
            icon.style.color = 'var(--first-color)';
        }
        
        // Reset scale
        box.style.transform = 'scale(1)';
    });

    // Add validation styles
    input.addEventListener('invalid', function(e) {
        e.preventDefault();
        this.classList.add('error');
        
        // Scroll to the error field
        setTimeout(() => {
            this.scrollIntoView({ 
                behavior: 'smooth', 
                block: 'center' 
            });
        }, 100);
    });

    // Remove error class on input
    input.addEventListener('input', function() {
        if (this.classList.contains('error')) {
            this.classList.remove('error');
        }
    });
});

/*=============== PASSWORD STRENGTH WITH SMOOTH UPDATES ===============*/
const passwordInput = document.getElementById('password');
const strengthBars = document.querySelectorAll('.strength-bar');
const strengthText = document.getElementById('strengthText');
const requirements = document.querySelectorAll('.requirement');

if (passwordInput) {
    passwordInput.addEventListener('input', function() {
        const val = this.value;
        let strength = 0;
        
        // Check requirements with smooth transitions
        const checks = {
            length: val.length >= 8,
            uppercase: /[A-Z]/.test(val),
            lowercase: /[a-z]/.test(val),
            number: /[0-9]/.test(val)
        };
        
        // Update requirements with animation
        requirements.forEach(req => {
            const icon = req.querySelector('i');
            if (icon) {
                icon.style.transition = 'all 0.3s ease';
            }
        });
        
        // Calculate strength
        Object.values(checks).forEach(check => {
            if (check) strength++;
        });
        
        // Animate strength bars
        strengthBars.forEach((bar, index) => {
            bar.style.transition = 'all 0.3s ease';
            if (index < strength) {
                bar.classList.add('active');
                bar.style.transform = 'scaleY(1.2)';
                setTimeout(() => {
                    bar.style.transform = 'scaleY(1)';
                }, 200);
            } else {
                bar.classList.remove('active');
            }
        });
        
        // Update strength text with animation
        if (val.length === 0) {
            strengthText.textContent = 'Enter password';
            strengthText.style.color = 'var(--text-color)';
        } else if (strength <= 1) {
            strengthText.textContent = 'Weak password';
            strengthText.style.color = 'var(--error-color)';
        } else if (strength <= 2) {
            strengthText.textContent = 'Fair password';
            strengthText.style.color = '#f97316';
        } else if (strength <= 3) {
            strengthText.textContent = 'Good password';
            strengthText.style.color = '#eab308';
        } else {
            strengthText.textContent = 'Strong password';
            strengthText.style.color = 'var(--success-color)';
        }
    });
}

/*=============== PASSWORD MATCH WITH VISUAL FEEDBACK ===============*/
const confirmPassword = document.getElementById('confirm_password');
const passwordMatch = document.getElementById('passwordMatch');

if (confirmPassword && passwordInput) {
    confirmPassword.addEventListener('input', function() {
        const matchDiv = document.getElementById('passwordMatch');
        
        if (this.value.length === 0) {
            matchDiv.innerHTML = '';
            return;
        }
        
        // Animate the match message
        matchDiv.style.transition = 'all 0.3s ease';
        matchDiv.style.opacity = '0';
        
        setTimeout(() => {
            if (passwordInput.value === this.value) {
                matchDiv.innerHTML = '<i class="ri-checkbox-circle-fill"></i> Passwords match';
                matchDiv.style.color = 'var(--success-color)';
            } else {
                matchDiv.innerHTML = '<i class="ri-error-warning-line"></i> Passwords do not match';
                matchDiv.style.color = 'var(--error-color)';
            }
            matchDiv.style.opacity = '1';
        }, 150);
    });
}

/*=============== SMOOTH FORM SUBMISSION ===============*/
const registerForm = document.getElementById('registerForm');
if (registerForm) {
    registerForm.addEventListener('submit', function(e) {
        const btn = document.getElementById('registerBtn');
        const spinner = document.getElementById('registerSpinner');
        const btnText = btn.querySelector('span');
        
        // Check if there are any visible errors
        const errorFields = document.querySelectorAll('.register__input.error');
        if (errorFields.length > 0) {
            e.preventDefault();
            errorFields[0].scrollIntoView({ 
                behavior: 'smooth', 
                block: 'center' 
            });
            errorFields[0].focus();
            return false;
        }
        
        // Animate button on submit
        btn.style.transform = 'scale(0.98)';
        btn.style.transition = 'transform 0.2s ease';
        
        setTimeout(() => {
            btn.style.transform = 'scale(1)';
        }, 200);
        
        // Show loading state
        btn.disabled = true;
        btnText.style.opacity = '0';
        spinner.style.display = 'inline-block';
    });
}

/*=============== SCROLL TO TOP OF FORM ON VALIDATION ERROR ===============*/
// Add this to show validation summary
function showValidationErrors(errors) {
    const form = document.querySelector('.register__form');
    const errorContainer = document.createElement('div');
    errorContainer.className = 'validation-errors';
    errorContainer.style.cssText = `
        background: var(--error-bg);
        color: var(--error-color);
        padding: 1rem;
        border-radius: 12px;
        margin-bottom: 1rem;
        font-size: 0.875rem;
        animation: slideDown 0.3s ease;
    `;
    
    errors.forEach(error => {
        const errorItem = document.createElement('div');
        errorItem.style.cssText = `
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        `;
        errorItem.innerHTML = `<i class="ri-error-warning-line"></i> ${error}`;
        errorContainer.appendChild(errorItem);
    });
    
    // Insert at top of form
    form.insertBefore(errorContainer, form.firstChild);
    
    // Scroll to top of form
    form.scrollTo({ top: 0, behavior: 'smooth' });
    
    // Remove after 5 seconds
    setTimeout(() => {
        errorContainer.style.animation = 'slideDown 0.3s reverse';
        setTimeout(() => errorContainer.remove(), 300);
    }, 5000);
}

/*=============== PREVENT FORM RESUBMISSION ===============*/
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}

/*=============== ADD CUSTOM SCROLLBAR BEHAVIOR ===============*/
// Hide scrollbar when not scrolling
let scrollTimeout;
const form = document.querySelector('.register__form');

if (form) {
    form.addEventListener('scroll', function() {
        this.classList.add('scrolling');
        
        clearTimeout(scrollTimeout);
        scrollTimeout = setTimeout(() => {
            this.classList.remove('scrolling');
        }, 1000);
    }, { passive: true });
}