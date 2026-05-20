<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/config.php';
require_once 'includes/session_timeout.php'; 

// Detect AJAX
$isAjax = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']));

// ✅ SINGLE SOURCE OF TRUTH
if (
    empty($_SESSION['authenticated']) ||
    empty($_SESSION['school_id']) ||
    empty($_SESSION['teacher_id'])
) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized session'
        ]);
        exit;
    }

    header('Location: login.php');
    exit;
}

// Safe session values
$school_id  = (int) $_SESSION['school_id'];
$teacher_id = (int) $_SESSION['teacher_id'];

// DB
if (!isset($db)) {
    require_once 'config/database.php';
    $database = new Database();
    $db = $database->getConnection();
}


// Check if only content is requested (AJAX call)
$content_only = isset($_GET['content_only']) && $_GET['content_only'] === 'true';

// Database connection
require_once 'config/config.php';

// Initialize variables
$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['user_role'] ?? 'User';
$school_name = $_SESSION['school_name'] ?? 'School';

// If content only is requested, output only the content
if ($content_only) {
    // Output help content only
    outputHelpContent();
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help Center - EduScore</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
/* Help Center Styles - Matching Utility Page */
.help-container {
    padding: 20px;
    min-height: calc(100vh - 70px);
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
}

.help-header {
    margin-bottom: 30px;
    text-align: center;
}

.help-header .page-title {
    color: #1e40af;
    font-size: 28px;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.help-header .page-description {
    color: #64748b;
    font-size: 16px;
    max-width: 600px;
    margin: 0 auto;
}

.help-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 25px;
    max-width: 1400px;
    margin: 0 auto;
}

@media (max-width: 1024px) {
    .help-grid {
        grid-template-columns: 1fr;
    }
}

.help-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
    overflow: hidden;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    border: 1px solid #e2e8f0;
}

.help-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.12);
}

.card-header {
    background: linear-gradient(135deg, #7c3aed 0%, #8b5cf6 100%);
    color: white;
    padding: 20px 25px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.header-icon {
    width: 50px;
    height: 50px;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
}

.header-content {
    flex: 1;
    min-width: 0;
}

.header-content h2 {
    margin: 0 0 5px 0;
    font-size: 18px;
    font-weight: 600;
}

.header-content p {
    margin: 0;
    font-size: 14px;
    opacity: 0.9;
}

.card-body {
    padding: 25px;
}

/* AI Assistant Card */
.ai-assistant-container {
    height: 500px;
    display: flex;
    flex-direction: column;
}

.ai-chat-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #e2e8f0;
}

.ai-avatar {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 22px;
}

.ai-status {
    flex: 1;
}

.ai-status h3 {
    margin: 0 0 5px 0;
    color: #1e293b;
    font-size: 16px;
    font-weight: 600;
}

.status-indicator {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #10b981;
    font-size: 13px;
    font-weight: 500;
}

.status-dot {
    width: 8px;
    height: 8px;
    background: #10b981;
    border-radius: 50%;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { opacity: 0.5; }
    50% { opacity: 1; }
    100% { opacity: 0.5; }
}

.chat-container {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 15px;
    overflow-y: auto;
    padding-right: 10px;
    margin-bottom: 15px;
    max-height: 350px;
}

.chat-message {
    display: flex;
    gap: 12px;
    max-width: 85%;
}

.chat-message.user {
    align-self: flex-end;
    flex-direction: row-reverse;
}

.avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #64748b;
    font-size: 14px;
    flex-shrink: 0;
}

.ai-message .avatar {
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    color: white;
}

.message-content {
    background: #f8fafc;
    padding: 12px 16px;
    border-radius: 12px;
    border-top-left-radius: 4px;
    color: #475569;
    font-size: 14px;
    line-height: 1.5;
}

.user .message-content {
    background: linear-gradient(135deg, #3b82f6, #1e40af);
    color: white;
    border-top-left-radius: 12px;
    border-top-right-radius: 4px;
}

.message-time {
    font-size: 11px;
    color: #94a3b8;
    margin-top: 5px;
    text-align: right;
}

.user .message-time {
    color: rgba(255, 255, 255, 0.7);
}

.chat-input-container {
    display: flex;
    gap: 10px;
    padding-top: 15px;
    border-top: 1px solid #e2e8f0;
}

.chat-input {
    flex: 1;
    padding: 12px 16px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.3s ease;
    background: white;
}

.chat-input:focus {
    outline: none;
    border-color: #8b5cf6;
    box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
}

.send-btn {
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    color: white;
    border: none;
    border-radius: 8px;
    padding: 0 20px;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    font-weight: 500;
}

.send-btn:hover:not(:disabled) {
    background: linear-gradient(135deg, #7c3aed, #6d28d9);
    transform: translateY(-2px);
}

.send-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Quick Help Card */
.quick-help-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
    margin-bottom: 25px;
}

.help-category {
    background: #f8fafc;
    border-radius: 12px;
    padding: 20px;
    border: 2px solid #e2e8f0;
    cursor: pointer;
    transition: all 0.3s ease;
}

.help-category:hover {
    background: #eff6ff;
    border-color: #93c5fd;
    transform: translateY(-3px);
}

.category-icon {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, #3b82f6, #1e40af);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 20px;
    margin-bottom: 15px;
}

.category-content h4 {
    margin: 0 0 8px 0;
    color: #1e293b;
    font-size: 15px;
    font-weight: 600;
}

.category-content p {
    margin: 0 0 12px 0;
    color: #64748b;
    font-size: 13px;
    line-height: 1.4;
}

.article-count {
    color: #8b5cf6;
    font-size: 12px;
    font-weight: 500;
}

/* FAQ Section */
.faq-section {
    margin-top: 25px;
}

.faq-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 15px;
}

.faq-header h3 {
    margin: 0;
    color: #1e293b;
    font-size: 16px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.faq-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.faq-item {
    background: #f8fafc;
    border-radius: 8px;
    padding: 15px;
    cursor: pointer;
    transition: all 0.3s ease;
    border: 1px solid #e2e8f0;
}

.faq-item:hover {
    background: #eff6ff;
    border-color: #93c5fd;
}

.faq-question {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.faq-question h4 {
    margin: 0;
    color: #475569;
    font-size: 14px;
    font-weight: 500;
    flex: 1;
}

.faq-toggle {
    color: #94a3b8;
    transition: transform 0.3s ease;
}

.faq-answer {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease;
    margin-top: 0;
}

.faq-answer p {
    margin: 10px 0 0 0;
    color: #64748b;
    font-size: 13px;
    line-height: 1.5;
    padding-left: 24px;
}

.faq-item.active {
    background: #eff6ff;
    border-color: #93c5fd;
}

.faq-item.active .faq-answer {
    max-height: 200px;
}

.faq-item.active .faq-toggle {
    transform: rotate(180deg);
}

/* Resources Card */
.resources-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 25px;
}

.resource-item {
    background: #f8fafc;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.resource-item:hover {
    background: #eff6ff;
    border-color: #93c5fd;
    transform: translateY(-3px);
}

.resource-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #10b981, #059669);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
    margin: 0 auto 15px;
}

.resource-content h4 {
    margin: 0 0 5px 0;
    color: #1e293b;
    font-size: 14px;
    font-weight: 600;
}

.resource-content p {
    margin: 0;
    color: #64748b;
    font-size: 12px;
}

/* Support Options */
.support-options {
    margin-top: 25px;
    padding-top: 25px;
    border-top: 1px solid #e2e8f0;
}

.support-options h3 {
    margin: 0 0 15px 0;
    color: #1e293b;
    font-size: 16px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.support-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
}

@media (max-width: 768px) {
    .support-grid {
        grid-template-columns: 1fr;
    }
}

.support-option {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 15px;
    background: #f8fafc;
    border-radius: 10px;
    transition: all 0.3s ease;
    cursor: pointer;
}

.support-option:hover {
    background: #eff6ff;
    transform: translateX(5px);
}

.option-icon {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #3b82f6, #1e40af);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 16px;
    flex-shrink: 0;
}

.option-content h4 {
    margin: 0 0 3px 0;
    color: #475569;
    font-size: 14px;
    font-weight: 500;
}

.option-content p {
    margin: 0;
    color: #64748b;
    font-size: 12px;
}

/* Card Footer */
.card-footer {
    padding: 20px 25px;
    border-top: 1px solid #e2e8f0;
    display: flex;
    gap: 12px;
    background: #f8fafc;
}

/* Buttons */
.btn {
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-primary {
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    color: white;
    border: 1px solid transparent;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #7c3aed, #6d28d9);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
}

.btn-outline {
    background: transparent;
    color: #475569;
    border: 2px solid #e2e8f0;
}

.btn-outline:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
    color: #1e293b;
}

/* Typing Indicator */
.typing-indicator {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 16px;
    background: #f8fafc;
    border-radius: 12px;
    border-top-left-radius: 4px;
    width: fit-content;
}

.typing-dot {
    width: 8px;
    height: 8px;
    background: #94a3b8;
    border-radius: 50%;
    animation: typing 1.4s infinite ease-in-out;
}

.typing-dot:nth-child(2) {
    animation-delay: 0.2s;
}

.typing-dot:nth-child(3) {
    animation-delay: 0.4s;
}

@keyframes typing {
    0%, 60%, 100% { transform: translateY(0); }
    30% { transform: translateY(-5px); }
}

/* Quick Actions */
.quick-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 15px;
}

.quick-action-btn {
    background: #eff6ff;
    border: 1px solid #dbeafe;
    color: #1e40af;
    padding: 8px 12px;
    border-radius: 20px;
    font-size: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.quick-action-btn:hover {
    background: #dbeafe;
    transform: translateY(-1px);
}

/* Scrollbar Styling */
.chat-container::-webkit-scrollbar,
.help-content::-webkit-scrollbar {
    width: 6px;
}

.chat-container::-webkit-scrollbar-track,
.help-content::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 3px;
}

.chat-container::-webkit-scrollbar-thumb,
.help-content::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 3px;
}

.chat-container::-webkit-scrollbar-thumb:hover,
.help-content::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.modal.active {
    display: flex;
}

.modal-content {
    background: white;
    border-radius: 16px;
    max-width: 600px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: modalSlideIn 0.3s ease;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-header {
    padding: 20px 25px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.modal-header h3 {
    margin: 0;
    color: #1e293b;
    font-size: 18px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    color: #64748b;
    cursor: pointer;
    padding: 5px;
    line-height: 1;
}

.modal-close:hover {
    color: #ef4444;
}

.modal-body {
    padding: 25px;
}

.modal-footer {
    padding: 20px 25px;
    border-top: 1px solid #e2e8f0;
    display: flex;
    gap: 12px;
    justify-content: flex-end;
}

/* Response Options */
.response-options {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 10px;
}

.response-option {
    background: #eff6ff;
    border: 1px solid #dbeafe;
    color: #1e40af;
    padding: 6px 10px;
    border-radius: 6px;
    font-size: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.response-option:hover {
    background: #dbeafe;
    transform: translateY(-1px);
}

/* Utility Classes */
.text-success { color: #10b981; }
.text-warning { color: #f59e0b; }
.text-error { color: #ef4444; }
.text-purple { color: #8b5cf6; }

/* Responsive Improvements */
@media (max-width: 768px) {
    .help-container {
        padding: 15px;
    }
    
    .quick-help-grid,
    .resources-grid {
        grid-template-columns: 1fr;
    }
    
    .chat-message {
        max-width: 95%;
    }
    
    .support-grid {
        grid-template-columns: 1fr;
    }
    
    .card-footer {
        flex-direction: column;
    }
    
    .modal-footer {
        flex-direction: column;
    }
    
    .modal-footer .btn {
        width: 100%;
        justify-content: center;
    }
}
    </style>
</head>
<body>
    <!-- Include header -->
    <?php include 'includes/header.php'; ?>
    
    <!-- Include sidebar -->
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <?php outputHelpContent(); ?>
    </div>

    <!-- Help Ticket Modal -->
    <div class="modal" id="helpTicketModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-ticket-alt"></i> Create Support Ticket</h3>
                <button class="modal-close" onclick="closeTicketModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="ticketForm" class="modal-form">
                    <div class="form-group">
                        <label for="ticketSubject">
                            <i class="fas fa-heading"></i> Subject *
                        </label>
                        <input type="text" id="ticketSubject" name="subject" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="ticketCategory">
                            <i class="fas fa-tag"></i> Category *
                        </label>
                        <select id="ticketCategory" name="category" required class="form-select">
                            <option value="">Select a category</option>
                            <option value="technical">Technical Issue</option>
                            <option value="feature">Feature Request</option>
                            <option value="bug">Bug Report</option>
                            <option value="account">Account Issue</option>
                            <option value="billing">Billing & Payment</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="ticketPriority">
                            <i class="fas fa-flag"></i> Priority
                        </label>
                        <select id="ticketPriority" name="priority" class="form-select">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="ticketDescription">
                            <i class="fas fa-align-left"></i> Description *
                        </label>
                        <textarea id="ticketDescription" name="description" rows="6" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="ticketAttachments">
                            <i class="fas fa-paperclip"></i> Attachments
                        </label>
                        <div class="upload-area" onclick="document.getElementById('ticketFile').click()">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p>Drag & drop files here or click to browse</p>
                            <input type="file" id="ticketFile" multiple style="display: none;">
                        </div>
                        <div id="fileList" class="file-list"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeTicketModal()">Cancel</button>
                <button class="btn btn-primary" onclick="submitTicket()">
                    <i class="fas fa-paper-plane"></i> Submit Ticket
                </button>
            </div>
        </div>
    </div>

    <script src="assets/js/help.js"></script>
</body>
</html>

<?php
// Function to output help content
function outputHelpContent() {
    ?>
    <div class="help-container">
        <div class="help-header">
            <h1 class="page-title">
                <i class="fas fa-robot"></i> AI Help Center
            </h1>
            <p class="page-description">Get instant assistance from our AI assistant or explore helpful resources</p>
        </div>

        <div class="help-grid">
            <!-- AI Assistant Card -->
            <div class="help-card ai-assistant-card">
                <div class="card-header">
                    <div class="header-icon">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div class="header-content">
                        <h2>AI Assistant</h2>
                        <p>Instant automated help 24/7</p>
                    </div>
                </div>
                
                <div class="card-body">
                    <div class="ai-chat-header">
                        <div class="ai-avatar">
                            <i class="fas fa-brain"></i>
                        </div>
                        <div class="ai-status">
                            <h3>EduScore Assistant</h3>
                            <div class="status-indicator">
                                <div class="status-dot"></div>
                                <span>Online & Ready to Help</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="chat-container" id="chatContainer">
                        <!-- Welcome message -->
                        <div class="chat-message ai-message">
                            <div class="avatar">
                                <i class="fas fa-brain"></i>
                            </div>
                            <div class="message-content">
                                Hello! I'm your EduScore AI assistant. I can help you with:
                                <ul style="margin: 8px 0 8px 20px;">
                                    <li>Technical issues and errors</li>
                                    <li>Feature explanations</li>
                                    <li>Step-by-step guides</li>
                                    <li>System troubleshooting</li>
                                    <li>Best practices and tips</li>
                                </ul>
                                What can I help you with today?
                                
                                <!-- Quick action buttons -->
                                <div class="quick-actions">
                                    <button class="quick-action-btn" onclick="askQuickQuestion('How do I add a new student?')">Add Student</button>
                                    <button class="quick-action-btn" onclick="askQuickQuestion('How to generate reports?')">Generate Reports</button>
                                    <button class="quick-action-btn" onclick="askQuickQuestion('Reset password help')">Reset Password</button>
                                    <button class="quick-action-btn" onclick="askQuickQuestion('Export data to Excel')">Export Data</button>
                                </div>
                            </div>
                            <div class="message-time">Just now</div>
                        </div>
                    </div>
                    
                    <div class="chat-input-container">
                        <input type="text" 
                               class="chat-input" 
                               id="chatInput" 
                               placeholder="Type your question here..."
                               onkeypress="handleChatInput(event)">
                        <button class="send-btn" onclick="sendMessage()" id="sendBtn">
                            <i class="fas fa-paper-plane"></i> Send
                        </button>
                    </div>
                </div>
            </div>

            <!-- Quick Help & Resources Card -->
            <div class="help-card resources-card">
                <div class="card-header">
                    <div class="header-icon">
                        <i class="fas fa-life-ring"></i>
                    </div>
                    <div class="header-content">
                        <h2>Quick Help & Resources</h2>
                        <p>Instant access to guides and documentation</p>
                    </div>
                </div>
                
                <div class="card-body">
                    <div class="quick-help-grid">
                        <div class="help-category" onclick="loadCategory('getting-started')">
                            <div class="category-icon">
                                <i class="fas fa-play-circle"></i>
                            </div>
                            <div class="category-content">
                                <h4>Getting Started</h4>
                                <p>New user guides and setup instructions</p>
                                <div class="article-count">12 articles</div>
                            </div>
                        </div>
                        
                        <div class="help-category" onclick="loadCategory('student-management')">
                            <div class="category-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="category-content">
                                <h4>Student Management</h4>
                                <p>Manage students, classes, and enrollments</p>
                                <div class="article-count">18 articles</div>
                            </div>
                        </div>
                        
                        <div class="help-category" onclick="loadCategory('grading')">
                            <div class="category-icon">
                                <i class="fas fa-graduation-cap"></i>
                            </div>
                            <div class="category-content">
                                <h4>Grading & Reports</h4>
                                <p>Grading systems and report generation</p>
                                <div class="article-count">15 articles</div>
                            </div>
                        </div>
                        
                        <div class="help-category" onclick="loadCategory('technical')">
                            <div class="category-icon">
                                <i class="fas fa-cogs"></i>
                            </div>
                            <div class="category-content">
                                <h4>Technical Support</h4>
                                <p>Troubleshooting and technical issues</p>
                                <div class="article-count">22 articles</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- FAQ Section -->
                    <div class="faq-section">
                        <div class="faq-header">
                            <h3><i class="fas fa-question-circle"></i> Frequently Asked Questions</h3>
                            <button class="btn btn-outline btn-sm" onclick="viewAllFAQs()">
                                <i class="fas fa-list"></i> View All
                            </button>
                        </div>
                        
                        <div class="faq-list" id="faqList">
                            <div class="faq-item" onclick="toggleFAQ(this)">
                                <div class="faq-question">
                                    <h4>How do I reset my password?</h4>
                                    <i class="fas fa-chevron-down faq-toggle"></i>
                                </div>
                                <div class="faq-answer">
                                    <p>Go to Settings → Account Security → Reset Password. You'll receive an email with instructions. For immediate reset, contact your system administrator.</p>
                                </div>
                            </div>
                            
                            <div class="faq-item" onclick="toggleFAQ(this)">
                                <div class="faq-question">
                                    <h4>How can I export student data to Excel?</h4>
                                    <i class="fas fa-chevron-down faq-toggle"></i>
                                </div>
                                <div class="faq-answer">
                                    <p>Navigate to Reports → Export Data → Select Excel format. You can filter by class, date range, or specific student groups before exporting.</p>
                                </div>
                            </div>
                            
                            <div class="faq-item" onclick="toggleFAQ(this)">
                                <div class="faq-question">
                                    <h4>What should I do if grades aren't saving?</h4>
                                    <i class="fas fa-chevron-down faq-toggle"></i>
                                </div>
                                <div class="faq-answer">
                                    <p>Check your internet connection first. Clear browser cache (Ctrl+Shift+Delete). If issue persists, try a different browser or contact technical support.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Support Options -->
                    <div class="support-options">
                        <h3><i class="fas fa-headset"></i> Support Options</h3>
                        <div class="support-grid">
                            <div class="support-option" onclick="openTicketModal()">
                                <div class="option-icon">
                                    <i class="fas fa-ticket-alt"></i>
                                </div>
                                <div class="option-content">
                                    <h4>Submit Ticket</h4>
                                    <p>Create a support ticket for complex issues</p>
                                </div>
                            </div>
                            
                            <div class="support-option" onclick="openLiveChat()">
                                <div class="option-icon">
                                    <i class="fas fa-comments"></i>
                                </div>
                                <div class="option-content">
                                    <h4>Live Chat</h4>
                                    <p>Chat with a human agent (9 AM - 5 PM)</p>
                                </div>
                            </div>
                            
                            <div class="support-option" onclick="callSupport()">
                                <div class="option-icon">
                                    <i class="fas fa-phone"></i>
                                </div>
                                <div class="option-content">
                                    <h4>Call Support</h4>
                                    <p>+1 (800) 123-4567</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card-footer">
                    <button class="btn btn-outline" onclick="clearChat()">
                        <i class="fas fa-eraser"></i> Clear Chat
                    </button>
                    <button class="btn btn-primary" onclick="suggestHelp()">
                        <i class="fas fa-lightbulb"></i> Suggest Help
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php
}
?>

<script>
// help.js
let chatMessages = [];
let isTyping = false;

// AI Responses Database
const aiResponses = {
    'add student': {
        message: "To add a new student:\n\n1. Go to **Students → Add New Student**\n2. Fill in the student's personal information\n3. Select the class and section\n4. Upload photo (optional)\n5. Click **Save Student**\n\n💡 **Tip**: You can bulk import students using Excel template from the Import section.",
        followUp: ["How to edit student information?", "What information is required?", "Can I add parent details?"]
    },
    'generate reports': {
        message: "To generate reports:\n\n1. Navigate to **Reports → Generate Reports**\n2. Select report type (Progress, Transcript, Attendance)\n3. Choose class, term, and date range\n4. Select output format (PDF, Excel, Print)\n5. Click **Generate Report**\n\n📊 **Available Reports**: Progress Cards, Transcripts, Attendance Sheets, Fee Reports",
        followUp: ["Custom report templates?", "Schedule automatic reports?", "Export options?"]
    },
    'reset password': {
        message: "Password reset options:\n\n**Self-Service**:\n1. Click **Forgot Password** on login page\n2. Enter your registered email\n3. Check email for reset link\n4. Create new password\n\n**Admin Reset**:\nContact your system administrator for immediate reset.\n\n🔒 **Security Tip**: Use strong passwords with mix of letters, numbers, and symbols.",
        followUp: ["Password requirements?", "Account locked?", "Change password?"]
    },
    'export data': {
        message: "Export data to Excel/CSV:\n\n**Single Table Export**:\n1. Go to any data table (Students, Teachers, etc.)\n2. Click **Export** button\n3. Choose format (Excel, CSV)\n4. Select columns to include\n\n**Bulk Export**:\n1. **Reports → Data Export**\n2. Choose data sets to export\n3. Apply filters if needed\n4. Download or schedule export\n\n📈 **Note**: Exports include current filters and sorting.",
        followUp: ["Import data?", "Custom export fields?", "Automated exports?"]
    },
    'default': {
        message: "I understand you're asking about \"{query}\". While I process your specific question, here are some general tips:\n\n• Check our **Knowledge Base** for detailed guides\n• Use **Quick Help** categories for common tasks\n• **Submit a ticket** if you need personalized assistance\n\nCan you provide more details about what you're trying to accomplish?",
        followUp: ["Browse knowledge base", "Submit support ticket", "Live chat option"]
    }
};

// Initialize chat
document.addEventListener('DOMContentLoaded', function() {
    loadPreviousChat();
    initializeQuickActions();
});

function sendMessage() {
    const input = document.getElementById('chatInput');
    const message = input.value.trim();
    
    if (!message) return;
    
    // Add user message
    addMessage(message, 'user');
    input.value = '';
    
    // Show typing indicator
    showTypingIndicator();
    
    // Simulate AI processing
    setTimeout(() => {
        removeTypingIndicator();
        processAIResponse(message.toLowerCase());
    }, 1000 + Math.random() * 2000);
}

function handleChatInput(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
}

function addMessage(text, sender) {
    const chatContainer = document.getElementById('chatContainer');
    const messageDiv = document.createElement('div');
    messageDiv.className = `chat-message ${sender}`;
    
    const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    
    if (sender === 'user') {
        messageDiv.innerHTML = `
            <div class="avatar">
                <i class="fas fa-user"></i>
            </div>
            <div class="message-content">${escapeHtml(text)}</div>
            <div class="message-time">${time}</div>
        `;
    } else {
        messageDiv.innerHTML = `
            <div class="avatar">
                <i class="fas fa-brain"></i>
            </div>
            <div class="message-content">${formatResponse(text)}</div>
            <div class="message-time">${time}</div>
        `;
    }
    
    chatContainer.appendChild(messageDiv);
    chatContainer.scrollTop = chatContainer.scrollHeight;
    
    // Save to messages array
    chatMessages.push({ text, sender, time });
    saveChat();
}

function showTypingIndicator() {
    const chatContainer = document.getElementById('chatContainer');
    const typingDiv = document.createElement('div');
    typingDiv.className = 'chat-message ai-message';
    typingDiv.id = 'typingIndicator';
    typingDiv.innerHTML = `
        <div class="avatar">
            <i class="fas fa-brain"></i>
        </div>
        <div class="typing-indicator">
            <div class="typing-dot"></div>
            <div class="typing-dot"></div>
            <div class="typing-dot"></div>
        </div>
    `;
    
    chatContainer.appendChild(typingDiv);
    chatContainer.scrollTop = chatContainer.scrollHeight;
    isTyping = true;
}

function removeTypingIndicator() {
    const typingIndicator = document.getElementById('typingIndicator');
    if (typingIndicator) {
        typingIndicator.remove();
    }
    isTyping = false;
}

function processAIResponse(query) {
    let response = aiResponses.default;
    
    // Check for keywords in query
    for (const [key, data] of Object.entries(aiResponses)) {
        if (query.includes(key) && key !== 'default') {
            response = data;
            break;
        }
    }
    
    // Replace placeholder if default response
    if (response === aiResponses.default) {
        response.message = response.message.replace('{query}', query);
    }
    
    // Add AI message
    addMessage(response.message, 'ai');
    
    // Add follow-up options if available
    if (response.followUp) {
        setTimeout(() => {
            addFollowUpOptions(response.followUp);
        }, 500);
    }
}

function addFollowUpOptions(options) {
    const chatContainer = document.getElementById('chatContainer');
    const optionsDiv = document.createElement('div');
    optionsDiv.className = 'chat-message ai-message';
    
    let optionsHtml = '<div class="response-options">';
    options.forEach(option => {
        optionsHtml += `<button class="response-option" onclick="askQuickQuestion('${escapeHtml(option)}')">${escapeHtml(option)}</button>`;
    });
    optionsHtml += '</div>';
    
    optionsDiv.innerHTML = `
        <div class="avatar">
            <i class="fas fa-brain"></i>
        </div>
        <div class="message-content">
            <p>Would you like to know more about:</p>
            ${optionsHtml}
        </div>
    `;
    
    chatContainer.appendChild(optionsDiv);
    chatContainer.scrollTop = chatContainer.scrollHeight;
}

function askQuickQuestion(question) {
    document.getElementById('chatInput').value = question;
    sendMessage();
}

function formatResponse(text) {
    // Convert markdown-like syntax to HTML
    return text
        .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
        .replace(/\n/g, '<br>')
        .replace(/•/g, '•')
        .replace(/(\d+\.)\s/g, '<br>$1 ')
        .replace(/💡/g, '💡')
        .replace(/📊/g, '📊')
        .replace(/🔒/g, '🔒')
        .replace(/📈/g, '📈');
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function saveChat() {
    localStorage.setItem('eduscore_chat', JSON.stringify(chatMessages));
}

function loadPreviousChat() {
    const saved = localStorage.getItem('eduscore_chat');
    if (saved) {
        chatMessages = JSON.parse(saved);
        // Could load last few messages if desired
    }
}

function clearChat() {
    if (confirm('Clear chat history?')) {
        chatMessages = [];
        localStorage.removeItem('eduscore_chat');
        document.getElementById('chatContainer').innerHTML = `
            <div class="chat-message ai-message">
                <div class="avatar">
                    <i class="fas fa-brain"></i>
                </div>
                <div class="message-content">
                    Chat cleared! How can I help you today?
                    <div class="quick-actions">
                        <button class="quick-action-btn" onclick="askQuickQuestion('How do I add a new student?')">Add Student</button>
                        <button class="quick-action-btn" onclick="askQuickQuestion('How to generate reports?')">Generate Reports</button>
                        <button class="quick-action-btn" onclick="askQuickQuestion('Reset password help')">Reset Password</button>
                        <button class="quick-action-btn" onclick="askQuickQuestion('Export data to Excel')">Export Data</button>
                    </div>
                </div>
                <div class="message-time">Just now</div>
            </div>
        `;
    }
}

function toggleFAQ(item) {
    const allItems = document.querySelectorAll('.faq-item');
    allItems.forEach(faq => {
        if (faq !== item) {
            faq.classList.remove('active');
        }
    });
    
    item.classList.toggle('active');
}

function loadCategory(category) {
    const categories = {
        'getting-started': 'Loading Getting Started guides...',
        'student-management': 'Loading Student Management resources...',
        'grading': 'Loading Grading & Reports documentation...',
        'technical': 'Loading Technical Support articles...'
    };
    
    askQuickQuestion(categories[category] || 'Show me help articles');
}

function openTicketModal() {
    document.getElementById('helpTicketModal').classList.add('active');
}

function closeTicketModal() {
    document.getElementById('helpTicketModal').classList.remove('active');
    document.getElementById('ticketForm').reset();
    document.getElementById('fileList').innerHTML = '';
}

function submitTicket() {
    const subject = document.getElementById('ticketSubject').value;
    const category = document.getElementById('ticketCategory').value;
    const description = document.getElementById('ticketDescription').value;
    
    if (!subject || !category || !description) {
        alert('Please fill in all required fields.');
        return;
    }
    
    // Simulate ticket submission
    const ticketData = {
        subject,
        category,
        priority: document.getElementById('ticketPriority').value,
        description,
        timestamp: new Date().toISOString(),
        status: 'open'
    };
    
    // Show success message
    addMessage(`Support ticket submitted successfully! Ticket #${Math.floor(Math.random() * 10000)} created. Our team will contact you within 24 hours.`, 'ai');
    
    closeTicketModal();
    
    // In real implementation, send to server
    // fetch('api/submit_ticket.php', {
    //     method: 'POST',
    //     headers: {'Content-Type': 'application/json'},
    //     body: JSON.stringify(ticketData)
    // });
}

function openLiveChat() {
    addMessage("Connecting you to a live agent... (This feature would connect to a live chat service in production)", 'ai');
}

function callSupport() {
    addMessage("Our support number is +1 (800) 123-4567. Available Monday-Friday, 9 AM - 5 PM EST.", 'ai');
}

function suggestHelp() {
    const suggestions = [
        "Based on your usage pattern, I suggest checking out the 'Advanced Reporting' guide.",
        "Many users find the 'Bulk Import' feature helpful for managing large student lists.",
        "Consider setting up automated backups in Utility Settings for data protection."
    ];
    
    const randomSuggestion = suggestions[Math.floor(Math.random() * suggestions.length)];
    addMessage(randomSuggestion, 'ai');
}

function viewAllFAQs() {
    askQuickQuestion('Show me all frequently asked questions');
}

function initializeQuickActions() {
    // Add file upload handler for ticket attachments
    const ticketFile = document.getElementById('ticketFile');
    if (ticketFile) {
        ticketFile.addEventListener('change', handleFileUpload);
    }
    
    // Add drag and drop for chat
    const uploadArea = document.querySelector('.upload-area');
    if (uploadArea) {
        uploadArea.addEventListener('dragover', handleDragOver);
        uploadArea.addEventListener('drop', handleDrop);
    }
}

function handleFileUpload(e) {
    const files = e.target.files;
    const fileList = document.getElementById('fileList');
    fileList.innerHTML = '';
    
    for (let file of files) {
        const fileItem = document.createElement('div');
        fileItem.className = 'file-item';
        fileItem.innerHTML = `
            <i class="fas fa-paperclip"></i>
            ${file.name} (${(file.size / 1024).toFixed(1)} KB)
        `;
        fileList.appendChild(fileItem);
    }
}

function handleDragOver(e) {
    e.preventDefault();
    e.currentTarget.style.borderColor = '#8b5cf6';
    e.currentTarget.style.background = '#f8fafc';
}

function handleDrop(e) {
    e.preventDefault();
    e.currentTarget.style.borderColor = '#cbd5e1';
    e.currentTarget.style.background = 'white';
    
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        // Handle dropped files
        addMessage(`File(s) dropped: ${files.length} file(s) attached. In production, these would be uploaded.`, 'user');
    }
}
</script>