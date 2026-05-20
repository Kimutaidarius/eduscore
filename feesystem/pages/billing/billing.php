<?php
session_start();
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    header('Location: ../../login.php');
    exit;
}
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'finance') {
    header('Location: ../../login.php?error=access_denied');
    exit;
}

require_once('../../includes/config.php');

$school_id = $_SESSION['school_id'];
$school_name = $_SESSION['school_name'] ?? '';

include_once('../../includes/header.php');
include_once('../../includes/sidebar.php');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing & Subscriptions - EduScore</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #f0f2f5 100%);
        }

        /* Modern Card Styles */
        .modern-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02), 0 1px 2px rgba(0, 0, 0, 0.03);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(255, 255, 255, 0.5);
        }

        .modern-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
        }

        /* Gradient Backgrounds */
        .gradient-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .gradient-success {
            background: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%);
        }

        .gradient-warning {
            background: linear-gradient(135deg, #f6d365 0%, #fda085 100%);
        }

        .gradient-danger {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        /* Stats Cards */
        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            transition: all 0.3s ease;
            border: 1px solid rgba(0, 0, 0, 0.05);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 1rem;
        }

        /* Plan Cards */
        .plan-card-modern {
            background: white;
            border-radius: 24px;
            padding: 2rem;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .plan-card-modern:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .plan-card-modern.popular {
            border-color: #667eea;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.2);
        }

        .plan-card-modern.current {
            background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);
            border-color: #667eea;
        }

        .popular-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        /* Pricing */
        .price {
            font-size: 3rem;
            font-weight: 700;
            color: #1a202c;
        }

        .price-period {
            font-size: 1rem;
            color: #718096;
            font-weight: 400;
        }

        /* Feature List */
        .feature-list-modern {
            list-style: none;
            padding: 0;
            margin: 1.5rem 0;
        }

        .feature-list-modern li {
            padding: 0.75rem 0;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid #edf2f7;
        }

        .feature-list-modern li:last-child {
            border-bottom: none;
        }

        .feature-list-modern li i {
            width: 20px;
            color: #48bb78;
        }

        /* Buttons */
        .btn-modern {
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.2s ease;
            cursor: pointer;
            border: none;
            font-size: 0.875rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid #e2e8f0;
            color: #4a5568;
        }

        .btn-outline:hover {
            border-color: #667eea;
            color: #667eea;
        }

        /* Invoice Table */
        .invoice-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
        }

        .invoice-table th {
            text-align: left;
            padding: 12px 16px;
            color: #718096;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .invoice-table td {
            background: white;
            padding: 16px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        /* Status Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-paid {
            background: #c6f6d5;
            color: #22543d;
        }

        .badge-pending {
            background: #fefcbf;
            color: #744210;
        }

        .badge-overdue {
            background: #fed7d7;
            color: #742a2a;
        }

        /* Modal */
        .modal-modern {
            background: white;
            border-radius: 32px;
            max-width: 500px;
            width: 90%;
            padding: 2rem;
        }

        /* Payment Methods */
        .payment-method-card {
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            padding: 1rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .payment-method-card:hover {
            border-color: #667eea;
            background: #f7fafc;
        }

        .payment-method-card.selected {
            border-color: #667eea;
            background: linear-gradient(135deg, #667eea10, #764ba210);
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fadeInUp {
            animation: fadeInUp 0.6s ease-out forwards;
        }

        /* Dark Mode Support */
        @media (prefers-color-scheme: dark) {
            body {
                background: linear-gradient(135deg, #1a202c 0%, #2d3748 100%);
            }
            
            .modern-card, .stat-card, .plan-card-modern, .invoice-table td {
                background: #2d3748;
                color: #f7fafc;
            }
            
            .price {
                color: #f7fafc;
            }
            
            .feature-list-modern li {
                border-bottom-color: #4a5568;
            }
        }
    </style>
</head>
<body>

<main class="main-content flex-grow flex flex-col">
    <header class="bg-white shadow-sm dark:bg-gray-800 dark:border-gray-700">
        <div class="px-4 py-3 flex justify-between items-center">
            <div class="flex items-center">
                <button id="mobile-toggle" class="mr-4 text-gray-500 hover:text-indigo-600 focus:outline-none lg:hidden">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                <div>
                    <h1 class="text-2xl font-bold bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent">
                        Billing & Subscriptions
                    </h1>
                    <p class="text-sm text-gray-500 mt-1">Manage your subscription and payment methods</p>
                </div>
            </div>
            <div class="flex items-center space-x-4">
                <button id="theme-toggle" class="p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700">
                    <i class="fas fa-sun text-yellow-500 dark:hidden"></i>
                    <i class="fas fa-moon text-blue-300 hidden dark:block"></i>
                </button>
                <div class="relative" id="user-menu-container">
                    <button id="user-menu-button" class="flex items-center focus:outline-none">
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['email'] ?? 'User'); ?>&background=667eea&color=fff&rounded=true&size=32" 
                             alt="User Avatar" class="w-8 h-8 rounded-full mr-2">
                        <span class="hidden md:block text-sm font-medium text-gray-700 dark:text-gray-200">
                            <?php echo htmlspecialchars($_SESSION['email'] ?? 'User'); ?>
                        </span>
                        <i class="fas fa-chevron-down text-xs ml-2 text-gray-500"></i>
                    </button>
                    <div id="user-dropdown" class="absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 border rounded-lg shadow-lg py-1 z-20 hidden">
                        <a href="../../profile.php" class="block px-4 py-2 text-sm hover:bg-gray-100"><i class="fas fa-user-circle mr-2"></i> My Profile</a>
                        <div class="border-t my-1"></div>
                        <a href="../../logout.php" class="block px-4 py-2 text-sm text-red-600"><i class="fas fa-sign-out-alt mr-2"></i> Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="flex-grow p-4 md:p-8 overflow-auto">
        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="stat-card animate-fadeInUp" style="animation-delay: 0s">
                <div class="stat-icon gradient-primary">
                    <i class="fas fa-rocket text-white"></i>
                </div>
                <p class="text-sm text-gray-500 mb-1">Current Plan</p>
                <p class="text-2xl font-bold" id="currentPlan">Loading...</p>
            </div>
            
            <div class="stat-card animate-fadeInUp" style="animation-delay: 0.1s">
                <div class="stat-icon gradient-success">
                    <i class="fas fa-users text-white"></i>
                </div>
                <p class="text-sm text-gray-500 mb-1">Active Students</p>
                <p class="text-2xl font-bold" id="activeStudents">Loading...</p>
                <div class="mt-2 h-2 bg-gray-200 rounded-full overflow-hidden">
                    <div class="h-full bg-green-500 rounded-full transition-all duration-500" id="studentProgressBar" style="width: 0%"></div>
                </div>
            </div>
            
            <div class="stat-card animate-fadeInUp" style="animation-delay: 0.2s">
                <div class="stat-icon gradient-warning">
                    <i class="fas fa-envelope text-white"></i>
                </div>
                <p class="text-sm text-gray-500 mb-1">SMS Credits</p>
                <p class="text-2xl font-bold" id="smsCredits">Loading...</p>
            </div>
            
            <div class="stat-card animate-fadeInUp" style="animation-delay: 0.3s">
                <div class="stat-icon gradient-danger">
                    <i class="fas fa-clock text-white"></i>
                </div>
                <p class="text-sm text-gray-500 mb-1">Time Remaining</p>
                <p class="text-2xl font-bold" id="countdownTimer">—</p>
            </div>
        </div>

        <!-- Current Subscription Hero -->
        <div class="gradient-primary rounded-2xl p-8 mb-8 text-white animate-fadeInUp" style="animation-delay: 0.4s">
            <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-6">
                <div>
                    <p class="text-indigo-100 text-sm mb-2">ACTIVE SUBSCRIPTION</p>
                    <h2 class="text-3xl md:text-4xl font-bold mb-2" id="currentPlanName">Loading...</h2>
                    <div class="flex items-center gap-4 mt-4">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-calendar-alt text-indigo-200"></i>
                            <span class="text-indigo-100" id="renewalDate"></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <i class="fas fa-credit-card text-indigo-200"></i>
                            <span class="text-indigo-100" id="billingCycle">per month</span>
                        </div>
                    </div>
                </div>
                <div class="text-center lg:text-right">
                    <p class="text-indigo-200 text-sm mb-1">Monthly Cost</p>
                    <p class="text-5xl font-bold mb-2" id="planPrice">KES 0</p>
                    <button id="manageSubscriptionBtn" class="px-6 py-2 bg-white text-indigo-600 rounded-xl font-semibold hover:bg-indigo-50 transition-all transform hover:scale-105">
                        <i class="fas fa-cog mr-2"></i>Manage Subscription
                    </button>
                </div>
            </div>
            <div id="trialBadge" class="mt-4"></div>
        </div>

        <!-- Outstanding Balance Alert -->
        <div id="outstandingAlert" class="hidden mb-8 animate-fadeInUp" style="animation-delay: 0.5s">
            <div class="bg-gradient-to-r from-orange-400 to-red-500 rounded-2xl p-6 text-white">
                <div class="flex items-start justify-between flex-wrap gap-4">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center">
                            <i class="fas fa-exclamation-triangle text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-lg">Outstanding Balance</h3>
                            <p class="text-orange-100">You have pending payments that require your attention</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-3xl font-bold" id="outstandingAmount">KES 0</p>
                        <button onclick="scrollToPayment()" class="mt-2 px-4 py-2 bg-white text-red-600 rounded-lg font-semibold hover:bg-gray-100 transition">
                            Pay Now <i class="fas fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Billing Summary -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="modern-card p-6 animate-fadeInUp" style="animation-delay: 0.6s">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="text-gray-500 text-sm">Total Billed (MTD)</p>
                        <p class="text-2xl font-bold" id="thisMonthBilling">KES 0</p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-chart-line text-blue-600 text-xl"></i>
                    </div>
                </div>
                <div class="text-sm text-gray-500">
                    <span class="text-green-600" id="monthlyChange">+0%</span> from last month
                </div>
            </div>
            
            <div class="modern-card p-6 animate-fadeInUp" style="animation-delay: 0.7s">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="text-gray-500 text-sm">Total Paid</p>
                        <p class="text-2xl font-bold" id="totalPaid">KES 0</p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-check-circle text-green-600 text-xl"></i>
                    </div>
                </div>
                <div class="text-sm text-gray-500">
                    Lifetime payments
                </div>
            </div>
            
            <div class="modern-card p-6 animate-fadeInUp" style="animation-delay: 0.8s">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="text-gray-500 text-sm">Overdue Amount</p>
                        <p class="text-2xl font-bold text-red-600" id="overdueAmount">KES 0</p>
                    </div>
                    <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-exclamation-circle text-red-600 text-xl"></i>
                    </div>
                </div>
                <div class="text-sm text-gray-500">
                    <span class="text-red-600" id="overdueCount">0</span> overdue invoices
                </div>
            </div>
        </div>

        <!-- Available Plans Section -->
        <div class="mb-12">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-white">Choose Your Plan</h2>
                    <p class="text-gray-500 mt-1">Select the perfect plan for your institution</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-sm text-gray-500">Billing:</span>
                    <div class="flex bg-gray-100 rounded-lg p-1">
                        <button class="px-4 py-2 rounded-lg text-sm font-medium transition" id="monthlyBtn">Monthly</button>
                        <button class="px-4 py-2 rounded-lg text-sm font-medium transition" id="yearlyBtn">Yearly</button>
                    </div>
                </div>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8" id="plansContainer">
                <!-- Plans will be loaded here -->
            </div>
        </div>

        <!-- Invoices & Payment History -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Recent Invoices -->
            <div class="modern-card animate-fadeInUp" style="animation-delay: 0.9s">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="font-bold text-lg flex items-center gap-2">
                        <i class="fas fa-file-invoice text-indigo-600"></i>
                        Recent Invoices
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="invoice-table">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="invoicesTableBody">
                            <tr><td colspan="5" class="text-center py-8 text-gray-500">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Payment History -->
            <div class="modern-card animate-fadeInUp" style="animation-delay: 1s">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="font-bold text-lg flex items-center gap-2">
                        <i class="fas fa-history text-purple-600"></i>
                        Payment History
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="invoice-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Transaction ID</th>
                                <th>Amount</th>
                                <th>Method</th>
                            </tr>
                        </thead>
                        <tbody id="paymentsTableBody">
                            <tr><td colspan="4" class="text-center py-8 text-gray-500">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Payment Modal -->
<div id="paymentModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden items-center justify-center z-50">
    <div class="modal-modern relative">
        <button onclick="closePaymentModal()" class="absolute top-6 right-6 text-gray-400 hover:text-gray-600">
            <i class="fas fa-times text-xl"></i>
        </button>
        
        <div class="text-center mb-6">
            <div class="w-16 h-16 bg-gradient-to-r from-indigo-500 to-purple-500 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-credit-card text-white text-2xl"></i>
            </div>
            <h3 class="text-2xl font-bold" id="modalTitle">Upgrade Plan</h3>
            <p class="text-gray-500 mt-1">Complete your payment to activate the plan</p>
        </div>
        
        <div class="mb-6">
            <div class="bg-gray-50 rounded-xl p-4 mb-4">
                <p class="text-sm text-gray-500">Selected Plan</p>
                <p class="font-bold text-lg" id="planName"></p>
                <p class="text-3xl font-bold text-indigo-600 mt-2" id="planPriceModal"></p>
            </div>
            
            <label class="block text-sm font-medium text-gray-700 mb-2">Payment Method</label>
            <div id="paymentMethodsContainer">
                <div class="payment-method-card" data-method="mpesa">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <i class="fab fa-cc-mastercard text-2xl text-blue-600"></i>
                            <div>
                                <p class="font-semibold">M-Pesa</p>
                                <p class="text-sm text-gray-500">Pay via M-Pesa STK Push</p>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right text-gray-400"></i>
                    </div>
                </div>
                <div class="payment-method-card" data-method="bank">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-university text-2xl text-green-600"></i>
                            <div>
                                <p class="font-semibold">Bank Transfer</p>
                                <p class="text-sm text-gray-500">Direct bank transfer</p>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right text-gray-400"></i>
                    </div>
                </div>
            </div>
            
            <div id="mpesaDetails" class="mt-4 hidden">
                <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                <input type="tel" id="phoneNumberModal" class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:border-indigo-500" placeholder="e.g., 712345678">
                <p class="text-xs text-gray-500 mt-2">You'll receive a prompt on your phone to complete payment</p>
            </div>
            
            <div id="bankDetails" class="mt-4 hidden">
                <div class="bg-blue-50 rounded-xl p-4">
                    <p class="font-semibold text-blue-900 mb-2">Bank Transfer Details</p>
                    <div class="space-y-2 text-sm">
                        <p><span class="text-gray-600">Bank:</span> <strong>Cooperative Bank</strong></p>
                        <p><span class="text-gray-600">Account Name:</span> <strong>EduScore Kenya</strong></p>
                        <p><span class="text-gray-600">Account Number:</span> <strong>0113456789000</strong></p>
                        <p><span class="text-gray-600">Reference:</span> <strong>SCHOOL-<?php echo $school_id; ?></strong></p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="flex gap-3">
            <button onclick="processPayment()" class="flex-1 btn-modern btn-primary">
                <i class="fas fa-check mr-2"></i>Confirm Payment
            </button>
            <button onclick="closePaymentModal()" class="flex-1 btn-modern btn-outline">
                Cancel
            </button>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="fixed top-24 right-4 z-50 space-y-2" id="toastContainer"></div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const schoolId = <?php echo json_encode($school_id); ?>;
let selectedPlan = { id: null, name: '', price: 0, onboardingFee: 0 };
let subscriptionData = null;
let currentBillingCycle = 'monthly';

function showToast(message, type = 'info') {
    const icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };
    
    const colors = {
        success: 'bg-green-50 border-green-500 text-green-800',
        error: 'bg-red-50 border-red-500 text-red-800',
        warning: 'bg-yellow-50 border-yellow-500 text-yellow-800',
        info: 'bg-blue-50 border-blue-500 text-blue-800'
    };
    
    const toast = $(`
        <div class="${colors[type]} border-l-4 rounded-lg shadow-lg p-4 flex items-center gap-3 animate-slideIn">
            <i class="fas ${icons[type]}"></i>
            <span class="flex-1">${message}</span>
            <button onclick="$(this).closest('div').remove()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `);
    
    $('#toastContainer').append(toast);
    setTimeout(() => toast.fadeOut(300, function() { $(this).remove(); }), 5000);
}

async function loadDashboardData() {
    try {
        const response = await fetch('/feesystem/api/billing/get_dashboard_data.php');
        const result = await response.json();
        
        if (result.success) {
            const data = result.data;
            subscriptionData = data.subscription;
            
            // Update stats
            document.getElementById('currentPlan').innerText = data.current_plan;
            document.getElementById('activeStudents').innerHTML = `${data.student_count}`;
            document.getElementById('smsCredits').innerHTML = data.sms_credits?.toLocaleString() || '0';
            document.getElementById('planPrice').innerHTML = `KES ${data.plan_price?.toLocaleString() || 0}`;
            document.getElementById('billingCycle').innerHTML = `per ${data.billing_cycle || 'month'}`;
            document.getElementById('currentPlanName').innerHTML = data.current_plan;
            document.getElementById('renewalDate').innerHTML = data.renewal_date ? `Renews on ${new Date(data.renewal_date).toLocaleDateString()}` : '';
            document.getElementById('thisMonthBilling').innerHTML = `KES ${data.this_month?.toLocaleString() || 0}`;
            document.getElementById('totalPaid').innerHTML = `KES ${data.total_paid?.toLocaleString() || 0}`;
            document.getElementById('overdueAmount').innerHTML = `KES ${data.overdue?.toLocaleString() || 0}`;
            
            if (data.is_trial) {
                document.getElementById('trialBadge').innerHTML = `
                    <div class="inline-flex items-center gap-2 bg-white/20 rounded-full px-4 py-2">
                        <i class="fas fa-gift"></i>
                        <span>Free Trial - ${data.trial_days_left} days remaining</span>
                    </div>
                `;
            }
            
            if (data.total_outstanding > 0) {
                document.getElementById('outstandingAlert').classList.remove('hidden');
                document.getElementById('outstandingAmount').innerHTML = `KES ${data.total_outstanding.toLocaleString()}`;
            }
            
            updateInvoicesTable(data.invoices);
            updatePaymentsTable(data.payment_history);
        }
    } catch (error) {
        console.error('Error loading dashboard:', error);
        showToast('Error loading dashboard data', 'error');
    }
}

function updateInvoicesTable(invoices) {
    const tbody = document.getElementById('invoicesTableBody');
    if (invoices && invoices.length > 0) {
        tbody.innerHTML = invoices.map(inv => `
            <tr>
                <td class="font-mono text-sm">${escapeHtml(inv.invoice_number)}</td>
                <td class="text-sm">${new Date(inv.invoice_date).toLocaleDateString()}</td>
                <td class="font-semibold">KES ${inv.total_amount.toLocaleString()}</td>
                <td>
                    <span class="badge badge-${inv.status.toLowerCase()}">
                        ${inv.status}
                    </span>
                </td>
                <td>
                    <button onclick="downloadInvoice(${inv.id})" class="text-indigo-600 hover:text-indigo-800">
                        <i class="fas fa-download"></i>
                    </button>
                </td>
            </tr>
        `).join('');
    } else {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-8 text-gray-500">No invoices found</td></tr>';
    }
}

function updatePaymentsTable(payments) {
    const tbody = document.getElementById('paymentsTableBody');
    if (payments && payments.length > 0) {
        tbody.innerHTML = payments.map(p => `
            <tr>
                <td class="text-sm">${new Date(p.payment_date).toLocaleDateString()}</td>
                <td class="font-mono text-xs">${escapeHtml(p.transaction_id || p.mpesa_code || '-')}</td>
                <td class="font-semibold">KES ${p.amount.toLocaleString()}</td>
                <td>
                    <span class="inline-flex items-center gap-1">
                        <i class="fas ${p.payment_method === 'mpesa' ? 'fa-mobile-alt' : 'fa-university'}"></i>
                        ${p.payment_method === 'mpesa' ? 'M-Pesa' : 'Bank Transfer'}
                    </span>
                </td>
            </tr>
        `).join('');
    } else {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-8 text-gray-500">No payment history found</td></tr>';
    }
}

async function loadPlans() {
    try {
        const response = await fetch('/feesystem/api/billing/get_plans.php');
        const data = await response.json();
        
        if (data.success && data.plans.length > 0) {
            const container = document.getElementById('plansContainer');
            const monthlyPlans = data.plans.filter(p => p.billing_cycle === 'monthly');
            const yearlyPlans = data.plans.filter(p => p.billing_cycle === 'yearly');
            const plansToShow = currentBillingCycle === 'monthly' ? monthlyPlans : yearlyPlans;
            
            container.innerHTML = plansToShow.map((plan, index) => {
                const features = plan.features || [];
                const isPopular = index === 1;
                const isCurrent = subscriptionData && subscriptionData.plan_id == plan.id;
                
                return `
                    <div class="plan-card-modern ${isPopular ? 'popular' : ''} ${isCurrent ? 'current' : ''} animate-fadeInUp" style="animation-delay: ${0.2 + index * 0.1}s">
                        ${isPopular ? '<div class="popular-badge">Most Popular</div>' : ''}
                        ${isCurrent ? '<div class="popular-badge" style="background: #48bb78;">Current Plan</div>' : ''}
                        <h3 class="text-xl font-bold mb-2">${escapeHtml(plan.name)}</h3>
                        <p class="text-gray-500 text-sm mb-4">${escapeHtml(plan.description || 'Perfect for growing schools')}</p>
                        <div class="mb-4">
                            <span class="price">KES ${plan.price_per_student.toLocaleString()}</span>
                            <span class="price-period">/student/${plan.billing_cycle}</span>
                        </div>
                        ${plan.onboarding_fee > 0 ? `<p class="text-sm text-gray-500 mb-4">+ KES ${plan.onboarding_fee.toLocaleString()} one-time setup fee</p>` : ''}
                        <ul class="feature-list-modern">
                            ${features.map(f => `<li><i class="fas fa-check-circle"></i><span>${escapeHtml(f)}</span></li>`).join('')}
                        </ul>
                        ${!isCurrent ? `
                            <button onclick="selectPlan(${plan.id}, '${escapeHtml(plan.name)}', ${plan.price_per_student}, ${plan.onboarding_fee})" 
                                    class="w-full btn-modern ${isPopular ? 'btn-primary' : 'btn-outline'}">
                                ${plan.price_per_student === 0 ? 'Start Free Trial' : 'Get Started'}
                                <i class="fas fa-arrow-right ml-2"></i>
                            </button>
                        ` : `
                            <button class="w-full btn-modern" style="background: #48bb78; color: white; cursor: default;" disabled>
                                <i class="fas fa-check mr-2"></i>Current Plan
                            </button>
                        `}
                    </div>
                `;
            }).join('');
        }
    } catch (error) {
        console.error('Error loading plans:', error);
        document.getElementById('plansContainer').innerHTML = '<div class="text-center py-8 col-span-full text-red-500">Error loading plans</div>';
    }
}

function selectPlan(id, name, price, onboardingFee) {
    selectedPlan = { id, name, price, onboardingFee };
    const totalPrice = price + onboardingFee;
    document.getElementById('modalTitle').innerHTML = 'Upgrade to ' + name;
    document.getElementById('planName').innerHTML = name;
    document.getElementById('planPriceModal').innerHTML = `KES ${totalPrice.toLocaleString()}`;
    document.getElementById('paymentModal').classList.remove('hidden');
    document.getElementById('paymentModal').style.display = 'flex';
}

function closePaymentModal() {
    document.getElementById('paymentModal').style.display = 'none';
}

// Payment method selection
document.querySelectorAll('.payment-method-card').forEach(card => {
    card.addEventListener('click', function() {
        document.querySelectorAll('.payment-method-card').forEach(c => c.classList.remove('selected'));
        this.classList.add('selected');
        
        const method = this.dataset.method;
        const mpesaDetails = document.getElementById('mpesaDetails');
        const bankDetails = document.getElementById('bankDetails');
        
        if (method === 'mpesa') {
            mpesaDetails.classList.remove('hidden');
            bankDetails.classList.add('hidden');
        } else if (method === 'bank') {
            mpesaDetails.classList.add('hidden');
            bankDetails.classList.remove('hidden');
        }
    });
});

async function processPayment() {
    const selectedMethod = document.querySelector('.payment-method-card.selected');
    if (!selectedMethod) {
        showToast('Please select a payment method', 'warning');
        return;
    }
    
    const paymentMethod = selectedMethod.dataset.method;
    const phoneNumber = document.getElementById('phoneNumberModal')?.value;
    
    if (paymentMethod === 'mpesa' && !phoneNumber) {
        showToast('Please enter your M-Pesa phone number', 'warning');
        return;
    }
    
    Swal.fire({
        title: 'Processing Payment',
        text: 'Please wait...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });
    
    try {
        const response = await fetch('/feesystem/api/billing/upgrade_plan.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                plan_id: selectedPlan.id,
                payment_method: paymentMethod,
                phone_number: phoneNumber,
                school_id: schoolId
            })
        });
        
        const data = await response.json();
        Swal.close();
        
        if (data.success) {
            Swal.fire({
                title: 'Success!',
                text: data.message || 'Your plan has been upgraded successfully.',
                icon: 'success',
                confirmButtonColor: '#667eea'
            }).then(() => location.reload());
        } else {
            Swal.fire('Error', data.message || 'Something went wrong', 'error');
        }
    } catch (error) {
        Swal.close();
        Swal.fire('Error', 'Network error. Please try again.', 'error');
    }
}

function scrollToPayment() {
    document.getElementById('plansContainer').scrollIntoView({ behavior: 'smooth' });
}

async function downloadInvoice(invoiceId) {
    window.open(`/feesystem/api/billing/download_invoice.php?id=${invoiceId}`, '_blank');
}

// Billing cycle toggle
document.getElementById('monthlyBtn')?.addEventListener('click', () => {
    currentBillingCycle = 'monthly';
    document.getElementById('monthlyBtn').classList.add('bg-indigo-600', 'text-white');
    document.getElementById('monthlyBtn').classList.remove('text-gray-700');
    document.getElementById('yearlyBtn').classList.remove('bg-indigo-600', 'text-white');
    document.getElementById('yearlyBtn').classList.add('text-gray-700');
    loadPlans();
});

document.getElementById('yearlyBtn')?.addEventListener('click', () => {
    currentBillingCycle = 'yearly';
    document.getElementById('yearlyBtn').classList.add('bg-indigo-600', 'text-white');
    document.getElementById('yearlyBtn').classList.remove('text-gray-700');
    document.getElementById('monthlyBtn').classList.remove('bg-indigo-600', 'text-white');
    document.getElementById('monthlyBtn').classList.add('text-gray-700');
    loadPlans();
});

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    loadDashboardData();
    loadPlans();
});

// User dropdown and theme toggle
document.getElementById('user-menu-button')?.addEventListener('click', () => 
    document.getElementById('user-dropdown')?.classList.toggle('hidden')
);

document.addEventListener('click', (e) => {
    if (!document.getElementById('user-menu-container')?.contains(e.target)) 
        document.getElementById('user-dropdown')?.classList.add('hidden');
});

document.getElementById('theme-toggle')?.addEventListener('click', () => {
    document.documentElement.classList.toggle('dark');
    localStorage.setItem('theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
});

if (localStorage.getItem('theme') === 'dark') 
    document.documentElement.classList.add('dark');

// Add animation styles
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateX(100%);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    .animate-slideIn {
        animation: slideIn 0.3s ease-out;
    }
`;
document.head.appendChild(style);
</script>

<?php include_once('../../includes/footer.php'); ?>