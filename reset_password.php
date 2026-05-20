<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/animate-css/animate.min.css">
    <title>EduScore - Reset Password</title>
    <style>
        /* Re-using some styles from index.php for consistency */
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(to bottom right, #e0f2fe, #f0f8ff);
            color: #333;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center; /* Center content vertically */
            padding: 20px;
            margin: 0;
            box-sizing: border-box;
            overflow-x: hidden;
            font-size: 0.95em;
        }

        .reset-password-container {
            background: #fff;
            padding: 40px;
            border-radius: 12px;
            width: 90%;
            max-width: 450px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
            text-align: center;
            animation: fadeIn 0.5s ease-out;
        }

        .reset-password-container h2 {
            margin-top: 0;
            margin-bottom: 25px;
            color: #007bff;
            font-size: 2em;
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #444;
        }

        input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 1em;
            font-family: 'Inter', sans-serif;
            color: #333;
            background-color: #fff;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        input[type="password"]:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
            outline: none;
        }

        .form-actions {
            margin-top: 30px;
            text-align: center;
        }

        .submit-btn {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 12px 30px;
            font-size: 1.1em;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            font-weight: 700;
            text-transform: uppercase;
        }
        .submit-btn:hover {
            background-color: #0056b3;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.15);
        }

        .back-to-login {
            display: block;
            margin-top: 20px;
            color: #007bff;
            text-decoration: none;
            font-size: 0.95em;
        }
        .back-to-login:hover {
            text-decoration: underline;
        }

        /* Loading and Status Message Modals (copied from index.php) */
        .loading-modal-overlay, .status-message-modal-overlay {
            display: none;
            position: fixed;
            z-index: 1002;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(8px);
            justify-content: center;
            align-items: center;
            flex-direction: column;
            color: white;
            font-size: 1.2em;
        }

        .loading-spinner {
            border: 8px solid #f3f3f3;
            border-top: 8px solid #007bff;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .status-message-box {
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
            text-align: center;
            position: relative;
            animation: zoomIn 0.3s ease-out;
        }

        .status-message-box .message-header {
            padding: 15px 20px;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
            margin: -30px -30px 20px -30px;
            color: #fff;
            font-weight: bold;
            font-size: 1.3em;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .status-message-box .message-body {
            padding: 0 10px;
            margin-bottom: 20px;
            color: #333;
            font-size: 1em;
        }

        .status-message-box .message-buttons {
            text-align: center;
            margin-top: 20px;
        }

        .status-message-box .message-buttons button {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1em;
            margin: 0 5px;
            transition: background-color 0.3s ease;
        }

        .status-message-box.success .message-header { background-color: #28a745; }
        .status-message-box.error .message-header { background-color: #dc3545; }
        .status-message-box.info .message-header { background-color: #17a2b8; }
        .status-message-box.warning .message-header { background-color: #ffc107; color: #333; }

        .status-message-box .message-buttons .ok-btn {
            background-color: #007bff;
            color: white;
        }
        .status-message-box .message-buttons .ok-btn:hover {
            background-color: #0056b3;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="reset-password-container">
        <h2>Reset Your Password</h2>
        <p id="message" style="color: red; margin-bottom: 15px;"></p>
        <form id="resetPasswordForm">
            <div class="form-group">
                <label for="newPassword">New Password:</label>
                <input type="password" id="newPassword" name="new_password" required placeholder="Enter your new password">
            </div>
            <div class="form-group">
                <label for="confirmNewPassword">Confirm New Password:</label>
                <input type="password" id="confirmNewPassword" name="confirm_new_password" required placeholder="Confirm your new password">
            </div>
            <div class="form-actions">
                <button type="submit" class="submit-btn">Reset Password</button>
            </div>
            <a href="index.php" class="back-to-login">Back to Login</a>
        </form>
    </div>

    <!-- Loading Modal -->
    <div id="loadingModal" class="loading-modal-overlay">
        <div class="loading-spinner"></div>
        <p id="loadingMessage">Processing...</p>
    </div>

    <!-- Status Message Modal (for Success, Error, Info, Warning) -->
    <div id="statusMessageModal" class="status-message-modal-overlay">
        <div class="status-message-box">
            <div class="message-header">
                <span id="statusModalTitle">Message</span>
                <button class="modal-close-btn" onclick="closeStatusMessageModal()">&times;</button>
            </div>
            <div class="message-body">
                <p id="statusModalText"></p>
            </div>
            <div class="message-buttons">
                <button class="ok-btn" onclick="closeStatusMessageModal()">OK</button>
            </div>
        </div>
    </div>

    <script>
        let statusMessageTimeout;
        let loadingTimeout;

        // --- New Status Message Modal Functions (copied from index.php) ---
        window.showStatusMessage = function(title, message, type = 'info', autoClose = true) {
            const statusModal = document.getElementById('statusMessageModal');
            const header = statusModal.querySelector('.message-header');
            const titleElement = document.getElementById('statusModalTitle');
            const textElement = document.getElementById('statusModalText');

            clearTimeout(statusMessageTimeout);

            header.className = 'message-header';
            header.classList.add(type);
            titleElement.textContent = title;
            textElement.textContent = message;

            statusModal.style.display = 'flex';

            if (autoClose) {
                statusMessageTimeout = setTimeout(() => {
                    closeStatusMessageModal();
                }, 5000);
            }
        }

        window.closeStatusMessageModal = function() {
            const statusModal = document.getElementById('statusMessageModal');
            statusModal.style.display = 'none';
            clearTimeout(statusMessageTimeout);
        }

        // --- New Loading Modal Functions (copied from index.php) ---
        window.showLoadingModal = function(message = 'Processing...') {
            const loadingModal = document.getElementById('loadingModal');
            const loadingMessageElement = document.getElementById('loadingMessage');
            loadingMessageElement.textContent = message;
            loadingModal.style.display = 'flex';
            loadingTimeout = setTimeout(() => {
                hideLoadingModal();
                showStatusMessage('Error!', 'Operation timed out. Please try again.', 'error');
            }, 30000);
        }

        window.hideLoadingModal = function() {
            const loadingModal = document.getElementById('loadingModal');
            loadingModal.style.display = 'none';
            clearTimeout(loadingTimeout);
        }

        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const token = urlParams.get('token');
            const messageElement = document.getElementById('message');
            const resetForm = document.getElementById('resetPasswordForm');

            if (!token) {
                messageElement.textContent = 'Invalid or missing password reset token. Please request a new one from the login page.';
                messageElement.style.color = 'red';
                resetForm.style.display = 'none'; // Hide the form if no token
                return;
            }

            resetForm.addEventListener('submit', async function(e) {
                e.preventDefault();

                const newPassword = document.getElementById('newPassword').value;
                const confirmNewPassword = document.getElementById('confirmNewPassword').value;

                if (newPassword.length < 6) {
                    showStatusMessage('Validation Error!', 'New password must be at least 6 characters long.', 'error');
                    return;
                }
                if (newPassword !== confirmNewPassword) {
                    showStatusMessage('Validation Error!', 'New passwords do not match.', 'error');
                    return;
                }

                showLoadingModal('Resetting password...');

                try {
                    const response = await fetch('api/handle_password_reset.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'reset_password',
                            token: token,
                            new_password: newPassword
                        })
                    });

                    const data = await response.json();
                    hideLoadingModal();

                    if (response.ok && data.success) {
                        showStatusMessage('Success!', data.message, 'success');
                        // Redirect to login page after successful reset
                        setTimeout(() => {
                            window.location.href = 'index.php';
                        }, 2000);
                    } else {
                        showStatusMessage('Error!', data.message || 'Password reset failed.', 'error');
                    }
                } catch (error) {
                    hideLoadingModal();
                    console.error("Password reset fetch error:", error);
                    showStatusMessage('Error!', 'A technical error occurred during password reset. Please try again.', 'error');
                }
            });
        });
    </script>
</body>
</html>