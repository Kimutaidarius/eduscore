/**
 * Session Timeout JavaScript
 * Handles client-side session timeout warnings and auto-redirect
 */

// Session timeout in milliseconds (should match PHP constant * 1000)
const SESSION_TIMEOUT = 1800000; // 30 minutes
const WARNING_TIME = 300000; // 5 minutes before timeout

let timeoutTimer;
let warningTimer;

// Function to reset timers on user activity
function resetSessionTimers() {
    clearTimeout(timeoutTimer);
    clearTimeout(warningTimer);
    
    // Set warning timer
    warningTimer = setTimeout(() => {
        showSessionWarning();
    }, SESSION_TIMEOUT - WARNING_TIME);
    
    // Set timeout timer
    timeoutTimer = setTimeout(() => {
        handleSessionTimeout();
    }, SESSION_TIMEOUT);
}

// Function to show session warning
function showSessionWarning() {
    const warning = document.createElement('div');
    warning.className = 'session-warning show';
    warning.id = 'sessionWarning';
    warning.innerHTML = `
        <div class="warning-content">
            <i class="fas fa-clock"></i>
            <span>Your session will expire in 5 minutes due to inactivity.</span>
            <button onclick="extendSession()" class="btn-warning">
                <i class="fas fa-hourglass-half"></i> Stay Logged In
            </button>
        </div>
    `;
    document.body.appendChild(warning);
}

// Function to extend session
function extendSession() {
    // Send AJAX request to extend session
    fetch('includes/extend_session.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remove warning if it exists
                const warning = document.getElementById('sessionWarning');
                if (warning) {
                    warning.remove();
                }
                // Reset timers
                resetSessionTimers();
                
                // Show confirmation toast
                showToast('Session extended successfully', 'success');
            }
        })
        .catch(error => {
            console.error('Failed to extend session:', error);
        });
}

// Function to handle session timeout
function handleSessionTimeout() {
    // Show timeout message
    const timeoutMsg = document.createElement('div');
    timeoutMsg.className = 'session-timeout';
    timeoutMsg.innerHTML = `
        <div class="timeout-content">
            <i class="fas fa-hourglass-end"></i>
            <h3>Session Expired</h3>
            <p>Your session has timed out due to inactivity.</p>
            <button onclick="redirectToLogin()" class="btn-timeout">
                <i class="fas fa-sign-in-alt"></i> Login Again
            </button>
        </div>
    `;
    document.body.appendChild(timeoutMsg);
    
    // Auto redirect after 3 seconds
    setTimeout(() => {
        window.location.href = 'login.php?timeout=1';
    }, 3000);
}

// Function to redirect to login
function redirectToLogin() {
    window.location.href = 'login.php?timeout=1';
}

// Function to show toast message
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast-notification toast-${type}`;
    toast.innerHTML = `
        <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-info-circle'}"></i>
        <span>${message}</span>
    `;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 3000);
}

// Reset timers on user activity
['click', 'keypress', 'scroll', 'mousemove'].forEach(event => {
    document.addEventListener(event, resetSessionTimers);
});

// Initialize timers when page loads
document.addEventListener('DOMContentLoaded', resetSessionTimers);