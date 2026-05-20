<?php
// spinner.php - Loading spinner functions
?>
<script>
function showSpinner() {
    // Create spinner element if it doesn't exist
    let spinner = document.getElementById('globalSpinner');
    if (!spinner) {
        spinner = document.createElement('div');
        spinner.id = 'globalSpinner';
        spinner.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center;
        `;
        spinner.innerHTML = `
            <div style="background: white; padding: 20px; border-radius: 10px; text-align: center;">
                <i class="fas fa-spinner fa-spin" style="font-size: 40px; color: #0b2c4d;"></i>
                <p style="margin-top: 10px;">Processing...</p>
            </div>
        `;
        document.body.appendChild(spinner);
    } else {
        spinner.style.display = 'flex';
    }
}

function hideSpinner() {
    const spinner = document.getElementById('globalSpinner');
    if (spinner) {
        spinner.style.display = 'none';
    }
}
</script>