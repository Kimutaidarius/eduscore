<?php
// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Include config if needed
// require_once '../includes/config.php';

// Check if user is logged in (if session exists)
session_start();
$is_logged_in = isset($_SESSION['user_id']);

$page_title = "EduScore Fee Management | Smart School Fee Tracking System";
$page_description = "Complete fee management solution for Kenyan schools. Track payments, manage balances, generate receipts, and automate fee collection with M-Pesa integration.";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($page_description); ?>">
    <meta name="keywords" content="fee management, school fees, payment tracking, M-Pesa integration, school finance Kenya">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="/images/logo.png">
    
    <!-- Stylesheets -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;500;600;700;800&family=Poppins:wght@400;500&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --kappel: hsl(170, 75%, 41%);
            --kappel_15: hsla(170, 75%, 41%, 0.15);
            --selective-yellow: hsl(42, 94%, 55%);
            --eerie-black-1: hsl(0, 0%, 9%);
            --eerie-black-2: hsl(180, 3%, 7%);
            --quick-silver: hsl(0, 0%, 65%);
            --radical-red: hsl(351, 83%, 61%);
            --light-gray: hsl(0, 0%, 80%);
            --isabelline: hsl(36, 33%, 94%);
            --gray-x-11: hsl(0, 0%, 73%);
            --platinum: hsl(0, 0%, 90%);
            --gray-web: hsl(0, 0%, 50%);
            --black_80: hsla(0, 0%, 0%, 0.8);
            --white_50: hsla(0, 0%, 100%, 0.5);
            --black_50: hsla(0, 0%, 0%, 0.5);
            --black_30: hsla(0, 0%, 0%, 0.3);
            --white: hsl(0, 0%, 100%);
            
            --gradient: linear-gradient(-90deg, hsl(151, 58%, 46%) 0%, hsl(170, 75%, 41%) 100%);
            
            --ff-league_spartan: 'League Spartan', sans-serif;
            --ff-poppins: 'Poppins', sans-serif;
            
            --fs-1: 4.2rem;
            --fs-2: 3.2rem;
            --fs-3: 2.3rem;
            --fs-4: 1.8rem;
            --fs-5: 1.5rem;
            --fs-6: 1.4rem;
            --fs-7: 1.3rem;
            
            --fw-500: 500;
            --fw-600: 600;
            
            --section-padding: 75px;
            
            --shadow-1: 0 6px 15px 0 hsla(0, 0%, 0%, 0.05);
            --shadow-2: 0 10px 30px hsla(0, 0%, 0%, 0.06);
            --shadow-3: 0 10px 50px 0 hsla(220, 53%, 22%, 0.1);
            
            --radius-pill: 500px;
            --radius-circle: 50%;
            --radius-3: 3px;
            --radius-5: 5px;
            --radius-10: 10px;
            
            --transition-1: 0.25s ease;
            --transition-2: 0.5s ease;
            --cubic-in: cubic-bezier(0.51, 0.03, 0.64, 0.28);
            --cubic-out: cubic-bezier(0.33, 0.85, 0.4, 0.96);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body { font-family: var(--ff-poppins); line-height: 1.6; color: var(--gray-web); background: var(--white); overflow-x: hidden; transition: background-color 0.3s ease, color 0.3s ease; }
        
        /* Dark Mode */
        body.dark-mode {
            --eerie-black-1: hsl(0, 0%, 90%);
            --eerie-black-2: hsl(0, 0%, 95%);
            --gray-web: hsl(0, 0%, 70%);
            --light-gray: hsl(0, 0%, 30%);
            --isabelline: hsl(36, 20%, 15%);
            --platinum: hsl(0, 0%, 25%);
            --white: hsl(0, 0%, 15%);
            --black_80: hsla(0, 0%, 0%, 0.9);
        }
        
        .container { max-width: 1280px; margin: 0 auto; padding: 0 1.5rem; }
        .section { padding-block: var(--section-padding); }
        .btn {
            background-color: var(--kappel);
            color: var(--white);
            font-family: var(--ff-league_spartan);
            font-size: var(--fs-4);
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 10px 20px;
            border-radius: var(--radius-5);
            text-decoration: none;
            transition: var(--transition-1);
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,191,255,0.2); }
        .btn-outline { background: transparent; border: 2px solid var(--kappel); color: var(--kappel); }
        .btn-outline:hover { background: var(--kappel); color: white; }
        
        /* Header */
        .header {
            position: sticky;
            top: 0;
            background-color: var(--white);
            padding-block: 12px;
            box-shadow: var(--shadow-1);
            z-index: 100;
        }
        .header .container { display: flex; justify-content: space-between; align-items: center; gap: 15px; }
        .logo { flex-shrink: 0; }
        .logo img { height: 40px; width: auto; }
        .header-actions { display: flex; align-items: center; gap: 15px; flex-shrink: 0; }
        .theme-toggle { background: none; border: none; font-size: 1.8rem; cursor: pointer; color: var(--eerie-black-1); display: flex; align-items: center; justify-content: center; }
        .theme-toggle .fa-sun { display: none; }
        body.dark-mode .theme-toggle .fa-moon { display: none; }
        body.dark-mode .theme-toggle .fa-sun { display: block; }
        .menu-btn { display: none; background: none; border: none; font-size: 2.4rem; cursor: pointer; color: var(--eerie-black-1); }
        
        /* Navbar */
        .navbar {
            position: fixed;
            top: 0;
            left: -100%;
            background-color: var(--white);
            width: 85%;
            max-width: 320px;
            height: 100vh;
            z-index: 1001;
            transition: left 0.3s ease-out;
            overflow-y: auto;
            box-shadow: 2px 0 20px rgba(0,0,0,0.15);
        }
        .navbar.active { left: 0; }
        body.navbar-open { overflow: hidden; position: fixed; width: 100%; }
        .navbar .wrapper { display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; border-bottom: 1px solid var(--platinum); }
        .nav-close-btn { background: none; border: none; font-size: 2rem; cursor: pointer; }
        .navbar-list { padding: 15px 20px; display: flex; flex-direction: column; gap: 5px; }
        .navbar-item { border-bottom: 1px solid var(--platinum); }
        .navbar-link { padding-block: 12px; font-weight: 500; display: block; color: var(--eerie-black-1); text-decoration: none; }
        .navbar-link:hover { color: var(--kappel); }
        .mobile-portal-buttons { padding: 15px 20px; border-top: 1px solid var(--platinum); display: flex; flex-direction: column; gap: 10px; }
        .mobile-portal-btn { padding: 12px 20px; border-radius: 8px; font-weight: 600; text-align: center; text-decoration: none; }
        .mobile-portal-btn-analytics { background: #00BFFF; color: #fff; }
        .mobile-portal-btn-finance { background: transparent; color: #00BFFF; border: 1.5px solid #00BFFF; }
        .overlay {
            position: fixed;
            inset: 0;
            background-color: var(--black_80);
            pointer-events: none;
            opacity: 0;
            z-index: 1000;
            transition: opacity 0.3s ease;
        }
        .overlay.active { opacity: 1; pointer-events: all; }
        
        @media (min-width: 992px) {
            .menu-btn { display: none; }
            .navbar { position: static; left: auto !important; width: auto; height: auto; background: none; box-shadow: none; overflow: visible; }
            body.navbar-open { overflow: auto; position: relative; }
            .navbar .wrapper { display: none; }
            .navbar-list { flex-direction: row; padding: 0; gap: 30px; }
            .navbar-item { border-bottom: none; }
            .navbar-link { padding-block: 0; }
            .mobile-portal-buttons { display: none; }
            .overlay { display: none; }
        }
        @media (max-width: 991px) {
            .menu-btn { display: block; }
        }
        
        /* Hero Section */
        .hero { padding-block-start: 80px; background: linear-gradient(135deg, var(--white) 0%, var(--isabelline) 100%); }
        .hero .container { display: grid; gap: 40px; align-items: center; }
        @media (min-width: 992px) { .hero .container { grid-template-columns: 1fr 1fr; gap: 60px; } }
        .hero-title { font-size: var(--fs-1); font-weight: 800; color: var(--eerie-black-1); margin-bottom: 1.5rem; }
        .hero-title .highlight { color: var(--kappel); border-bottom: 4px solid var(--selective-yellow); display: inline-block; }
        .hero-text { font-size: 1.1rem; margin-bottom: 2rem; }
        .hero-buttons { display: flex; gap: 1rem; flex-wrap: wrap; margin-top: 2rem; }
        .hero-image { background: var(--kappel_15); border-radius: 28px; padding: 1.5rem; text-align: center; border: 1px solid rgba(0,191,255,0.1); }
        .hero-image img { max-width: 100%; border-radius: 20px; box-shadow: var(--shadow-3); }
        
        /* Features Section */
        .features-section { background: var(--white); }
        .section-header { text-align: center; margin-bottom: 3rem; }
        .section-header h2 { font-size: var(--fs-2); font-weight: 700; color: var(--eerie-black-1); }
        .yellow-line { width: 80px; height: 4px; background: var(--selective-yellow); margin: 1rem auto; border-radius: 4px; }
        .features-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 2rem; }
        .feature-card { background: var(--white); border-radius: 24px; padding: 2rem; transition: all 0.3s ease; border: 1px solid var(--platinum); box-shadow: var(--shadow-1); }
        .feature-card:hover { transform: translateY(-6px); box-shadow: var(--shadow-3); border-color: var(--kappel); }
        .feature-icon { width: 64px; height: 64px; background: var(--kappel_15); border-radius: 18px; display: flex; align-items: center; justify-content: center; margin-bottom: 1.5rem; color: var(--kappel); font-size: 1.6rem; }
        .feature-card h3 { font-size: 1.3rem; font-weight: 700; margin-bottom: 0.75rem; color: var(--eerie-black-1); }
        .feature-badge { display: inline-block; background: var(--kappel_15); color: var(--kappel); padding: 0.2rem 0.8rem; border-radius: 20px; font-size: 0.7rem; font-weight: 600; margin-right: 0.5rem; margin-bottom: 0.5rem; }
        
        /* Steps Section */
        .steps-section { background: var(--isabelline); }
        .steps-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem; text-align: center; }
        .step-number { width: 60px; height: 60px; background: var(--kappel); color: white; border-radius: 30px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 800; margin: 0 auto 1.5rem; }
        
        /* Pricing Section */
        .pricing-section { background: var(--white); }
        .pricing-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem; }
        .pricing-card { background: var(--white); border-radius: 24px; padding: 2rem; text-align: center; border: 1px solid var(--platinum); transition: all 0.3s ease; position: relative; }
        .pricing-card:hover { transform: translateY(-6px); box-shadow: var(--shadow-3); }
        .pricing-card.popular { border: 2px solid var(--selective-yellow); }
        .popular-badge { position: absolute; top: -12px; left: 50%; transform: translateX(-50%); background: var(--selective-yellow); color: var(--eerie-black-1); padding: 0.3rem 1rem; border-radius: 30px; font-size: 0.75rem; font-weight: 700; }
        .pricing-price { font-size: 2.5rem; font-weight: 800; color: var(--kappel); margin-bottom: 1rem; }
        .pricing-features { list-style: none; margin: 1.5rem 0; }
        .pricing-features li { padding: 0.5rem 0; }
        .pricing-features i { color: var(--selective-yellow); margin-right: 0.5rem; }
        
        /* CTA Banner */
        .cta-banner { background: var(--kappel); padding: 70px 0; text-align: center; }
        .cta-banner h2 { color: white; font-size: 2rem; margin-bottom: 1rem; }
        .cta-banner .btn-yellow { background: var(--selective-yellow); color: var(--eerie-black-1); padding: 0.9rem 2rem; border-radius: 40px; font-weight: 700; text-decoration: none; display: inline-block; margin: 0.5rem; }
        
        /* Footer */
        .footer { background: var(--eerie-black-2); color: var(--gray-x-11); padding: 60px 0 30px; }
        .footer-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 2rem; }
        .footer-column h4 { color: white; margin-bottom: 1.5rem; }
        .footer-column a { display: block; color: var(--gray-x-11); text-decoration: none; margin-bottom: 0.75rem; transition: color 0.2s; }
        .footer-column a:hover { color: var(--selective-yellow); }
        .footer-bottom { text-align: center; padding-top: 2rem; margin-top: 2rem; border-top: 1px solid var(--eerie-black-1); }
        
        /* Back to Top */
        .back-top-btn { position: fixed; bottom: 40px; right: 30px; background: var(--kappel); color: white; width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; text-decoration: none; z-index: 3; opacity: 0; pointer-events: none; transition: var(--transition-1); }
        .back-top-btn.active { opacity: 1; pointer-events: all; transform: translateY(10px); }
        
        /* Reveal Animation */
        .reveal { opacity: 0; transform: translateY(40px); transition: all 0.8s ease; }
        .reveal.active { opacity: 1; transform: translateY(0); }
        .delay-1 { transition-delay: 0.2s; }
        .delay-2 { transition-delay: 0.4s; }
        
        @media (max-width: 768px) {
            .hero-title { font-size: 2.5rem; }
            .section-header h2 { font-size: 1.8rem; }
            .features-grid { grid-template-columns: 1fr; }
            .hero-image { display: none; }
        }
    </style>
</head>
<body>

<!-- Header -->
<header class="header">
    <div class="container">
        <a href="/" class="logo">
            <img src="/images/logo.png" alt="EduScore logo">
        </a>

        <nav class="navbar" data-navbar>
            <div class="wrapper">
                <a href="/" class="logo"><img src="/images/logo.png" alt="EduScore logo"></a>
                <button class="nav-close-btn" aria-label="close menu" data-nav-toggler>
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <ul class="navbar-list">
                <li class="navbar-item"><a href="#home" class="navbar-link" data-nav-link>Home</a></li>
                <li class="navbar-item"><a href="#features" class="navbar-link" data-nav-link>Features</a></li>
                <li class="navbar-item"><a href="#pricing" class="navbar-link" data-nav-link>Pricing</a></li>
                <li class="navbar-item"><a href="#faq" class="navbar-link" data-nav-link>FAQ</a></li>
                <li class="navbar-item"><a href="#contact" class="navbar-link" data-nav-link>Contact</a></li>
            </ul>
            <div class="mobile-portal-buttons">
                <a href="/analytics.php" class="mobile-portal-btn mobile-portal-btn-analytics">Analytics Portal</a>
                <a href="/feesystem.php" class="mobile-portal-btn mobile-portal-btn-finance">Finance Portal</a>
            </div>
        </nav>

        <div class="header-actions">
            <button class="theme-toggle" id="themeToggle">
                <i class="fas fa-moon"></i><i class="fas fa-sun"></i>
            </button>
            <?php if ($is_logged_in): ?>
                <a href="dashboard.php" class="btn">Dashboard</a>
            <?php else: ?>
                <a href="/feesystem/login.php" class="btn btn-outline">Login</a>
                <a href="/feesystem/register.php" class="btn">Register</a>
            <?php endif; ?>
            <button class="menu-btn" aria-label="open menu" data-nav-toggler>
                <i class="fas fa-bars"></i>
            </button>
        </div>
        <div class="overlay" data-nav-toggler data-overlay></div>
    </div>
</header>

<main>
    <!-- Hero Section -->
    <section class="section hero" id="home">
        <div class="container">
            <div class="hero-content reveal">
                <h1 class="hero-title">Smart <span class="highlight">Fee Management</span> for Kenyan Schools</h1>
                <p class="hero-text">Automate fee collection, track payments in real-time, send instant reminders, and generate financial reports—all in one powerful platform.</p>
                <div class="hero-buttons">
                    <a href="/feesystem/register.php" class="btn">Start Free Trial <i class="fas fa-arrow-right"></i></a>
                    <a href="/feesystem/login.php" class="btn btn-outline">Sign In</a>
                </div>
            </div>
            <div class="hero-image reveal delay-1">
                <img src="/images/feesystem.PNG" alt="Fee Management Dashboard" onerror="this.src='/images/school-bg.png'">
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="section features-section" id="features">
        <div class="container">
            <div class="section-header">
                <h2>Complete Fee Management Suite</h2>
                <div class="yellow-line"></div>
                <p>Everything you need to manage school finances efficiently</p>
            </div>
            <div class="features-grid">
                <div class="feature-card reveal">
                    <div class="feature-icon"><i class="fas fa-chart-line"></i></div>
                    <h3>Real-time Payment Tracking</h3>
                    <p>Monitor fee collections as they happen. Get live updates on payments, pending balances, and overdue accounts.</p>
                    <div><span class="feature-badge">Live Updates</span><span class="feature-badge">Dashboard View</span></div>
                </div>
                <div class="feature-card reveal delay-1">
                    <div class="feature-icon"><i class="fas fa-mobile-alt"></i></div>
                    <h3>M-Pesa Integration</h3>
                    <p>Seamless M-Pesa payment integration. Parents can pay fees directly via Paybill or Till Number.</p>
                    <div><span class="feature-badge">Auto Reconciliation</span><span class="feature-badge">Instant SMS</span></div>
                </div>
                <div class="feature-card reveal delay-2">
                    <div class="feature-icon"><i class="fas fa-receipt"></i></div>
                    <h3>Automated Receipts</h3>
                    <p>Generate professional receipts instantly after payment. Email or SMS receipts to parents automatically.</p>
                    <div><span class="feature-badge">PDF Download</span><span class="feature-badge">Email/SMS</span></div>
                </div>
                <div class="feature-card reveal">
                    <div class="feature-icon"><i class="fas fa-bell"></i></div>
                    <h3>Payment Reminders</h3>
                    <p>Automated SMS and email reminders for upcoming fee deadlines and overdue payments.</p>
                    <div><span class="feature-badge">Scheduled</span><span class="feature-badge">Customizable</span></div>
                </div>
                <div class="feature-card reveal delay-1">
                    <div class="feature-icon"><i class="fas fa-chart-pie"></i></div>
                    <h3>Financial Reports</h3>
                    <p>Generate comprehensive financial reports: collection summaries, balance sheets, and audit trails.</p>
                    <div><span class="feature-badge">Export Excel/PDF</span><span class="feature-badge">Analytics</span></div>
                </div>
                <div class="feature-card reveal delay-2">
                    <div class="feature-icon"><i class="fas fa-users"></i></div>
                    <h3>Parent Portal</h3>
                    <p>Parents can view fee balances, payment history, and make online payments through a dedicated portal.</p>
                    <div><span class="feature-badge">Self-Service</span><span class="feature-badge">24/7 Access</span></div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section class="section steps-section" id="how-it-works">
        <div class="container">
            <div class="section-header">
                <h2>How Fee Management Works</h2>
                <div class="yellow-line"></div>
                <p>Simple setup, powerful automation</p>
            </div>
            <div class="steps-grid">
                <div class="step reveal">
                    <div class="step-number">1</div>
                    <h3>Set Up Fee Structures</h3>
                    <p>Define fee types (tuition, activity, transport) and amounts per grade/class.</p>
                </div>
                <div class="step reveal delay-1">
                    <div class="step-number">2</div>
                    <h3>Add Students & Parents</h3>
                    <p>Import student data and assign parent contacts for communication.</p>
                </div>
                <div class="step reveal delay-2">
                    <div class="step-number">3</div>
                    <h3>Collect Payments</h3>
                    <p>Parents pay via M-Pesa, bank transfer, or cash. System auto-updates balances.</p>
                </div>
                <div class="step reveal">
                    <div class="step-number">4</div>
                    <h3>Track & Report</h3>
                    <p>Monitor collections, generate receipts, and export financial reports.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section class="section pricing-section" id="pricing">
        <div class="container">
            <div class="section-header">
                <h2>Simple, Transparent Pricing</h2>
                <div class="yellow-line"></div>
                <p>Affordable plans for schools of all sizes</p>
            </div>
            <div class="pricing-grid">
                <div class="pricing-card reveal">
                    <h3>Starter</h3>
                    <div class="pricing-price">KES 15<small>/student/term</small></div>
                    <ul class="pricing-features">
                        <li><i class="fas fa-check"></i> Basic fee tracking</li>
                        <li><i class="fas fa-check"></i> Manual payment entry</li>
                        <li><i class="fas fa-check"></i> Basic reports</li>
                        <li><i class="fas fa-check"></i> Email support</li>
                    </ul>
                    <a href="/feesystem/register.php" class="btn">Get Started</a>
                </div>
                <div class="pricing-card popular reveal delay-1">
                    <div class="popular-badge">Most Popular</div>
                    <h3>Professional</h3>
                    <div class="pricing-price">KES 25<small>/student/term</small></div>
                    <ul class="pricing-features">
                        <li><i class="fas fa-check"></i> M-Pesa integration</li>
                        <li><i class="fas fa-check"></i> Automated receipts</li>
                        <li><i class="fas fa-check"></i> SMS reminders</li>
                        <li><i class="fas fa-check"></i> Parent portal access</li>
                        <li><i class="fas fa-check"></i> Advanced analytics</li>
                    </ul>
                    <a href="/feesystem/register.php" class="btn">Get Started</a>
                </div>
                <div class="pricing-card reveal delay-2">
                    <h3>Enterprise</h3>
                    <div class="pricing-price">Custom</div>
                    <ul class="pricing-features">
                        <li><i class="fas fa-check"></i> Everything in Professional</li>
                        <li><i class="fas fa-check"></i> API access</li>
                        <li><i class="fas fa-check"></i> Dedicated support</li>
                        <li><i class="fas fa-check"></i> Custom integrations</li>
                    </ul>
                    <a href="#contact" class="btn">Contact Sales</a>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="section faq-section" id="faq" style="background: var(--isabelline);">
        <div class="container">
            <div class="section-header">
                <h2>Frequently Asked Questions</h2>
                <div class="yellow-line"></div>
            </div>
            <div style="max-width: 800px; margin: 0 auto;">
                <div class="faq-item" style="background: var(--white); margin-bottom: 1rem; border-radius: 12px; padding: 1.5rem;">
                    <h3 style="color: var(--eerie-black-1); margin-bottom: 0.5rem;">Is there a free trial available?</h3>
                    <p>Yes, we offer a 14-day free trial for all our plans. No credit card required.</p>
                </div>
                <div class="faq-item" style="background: var(--white); margin-bottom: 1rem; border-radius: 12px; padding: 1.5rem;">
                    <h3 style="color: var(--eerie-black-1); margin-bottom: 0.5rem;">Can parents access fee information?</h3>
                    <p>Yes! The Parents Portal gives parents real-time access to their children's fee balances and payment history.</p>
                </div>
                <div class="faq-item" style="background: var(--white); margin-bottom: 1rem; border-radius: 12px; padding: 1.5rem;">
                    <h3 style="color: var(--eerie-black-1); margin-bottom: 0.5rem;">What payment methods are supported?</h3>
                    <p>We support M-Pesa, bank transfers, cash, and cheque payments with automatic reconciliation.</p>
                </div>
                <div class="faq-item" style="background: var(--white); border-radius: 12px; padding: 1.5rem;">
                    <h3 style="color: var(--eerie-black-1); margin-bottom: 0.5rem;">Is my data secure?</h3>
                    <p>Yes, we use industry-standard encryption and security measures to protect your school's financial data.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="section contact-section" id="contact">
        <div class="container">
            <div class="section-header">
                <h2>Get in Touch</h2>
                <div class="yellow-line"></div>
                <p>Have questions? We'd love to hear from you.</p>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem; text-align: center;">
                <div class="contact-card reveal" style="padding: 2rem; background: var(--white); border-radius: 20px; box-shadow: var(--shadow-1);">
                    <i class="fas fa-map-marker-alt" style="font-size: 2rem; color: var(--kappel); margin-bottom: 1rem;"></i>
                    <h3 style="color: var(--eerie-black-1);">Location</h3>
                    <p>Ngara - Nairobi, Kenya</p>
                </div>
                <div class="contact-card reveal delay-1" style="padding: 2rem; background: var(--white); border-radius: 20px; box-shadow: var(--shadow-1);">
                    <i class="fas fa-phone-alt" style="font-size: 2rem; color: var(--kappel); margin-bottom: 1rem;"></i>
                    <h3 style="color: var(--eerie-black-1);">Phone</h3>
                    <p><a href="tel:+254799115282" style="color: var(--gray-web); text-decoration: none;">+254 799 115 282</a></p>
                </div>
                <div class="contact-card reveal delay-2" style="padding: 2rem; background: var(--white); border-radius: 20px; box-shadow: var(--shadow-1);">
                    <i class="fas fa-envelope" style="font-size: 2rem; color: var(--kappel); margin-bottom: 1rem;"></i>
                    <h3 style="color: var(--eerie-black-1);">Email</h3>
                    <p><a href="mailto:eduscoreke@gmail.com" style="color: var(--gray-web); text-decoration: none;">eduscoreke@gmail.com</a></p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Banner -->
    <section class="cta-banner">
        <div class="container">
            <h2>Ready to Simplify Fee Management?</h2>
            <p>Join 500+ Kenyan schools already using EduScore Fee Management System</p>
            <div>
                <a href="/feesystem/register.php" class="btn-yellow" style="background: var(--selective-yellow); color: var(--eerie-black-1); padding: 0.9rem 2rem; border-radius: 40px; font-weight: 700; text-decoration: none; display: inline-block; margin: 0.5rem;">Start Free Trial</a>
                <a href="login.php" class="btn" style="background: transparent; border: 2px solid white; color: white; padding: 0.9rem 2rem; border-radius: 40px; text-decoration: none; display: inline-block; margin: 0.5rem;">Login</a>
            </div>
        </div>
    </section>
</main>

<!-- Footer -->
<footer class="footer">
    <div class="container">
        <div class="footer-container">
            <div class="footer-column">
                <h4>FeeManager</h4>
                <p>Smart fee management for Kenyan schools.</p>
            </div>
            <div class="footer-column">
                <h4>Product</h4>
                <a href="#features">Features</a>
                <a href="#pricing">Pricing</a>
                <a href="#how-it-works">How It Works</a>
            </div>
            <div class="footer-column">
                <h4>Support</h4>
                <a href="mailto:eduscoreke@gmail.com">eduscoreke@gmail.com</a>
                <a href="tel:+254799115282">+254 799 115 282</a>
            </div>
            <div class="footer-column">
                <h4>Legal</h4>
                <a href="#">Privacy Policy</a>
                <a href="#">Terms of Service</a>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> EduScore Kenya. All rights reserved.</p>
        </div>
    </div>
</footer>

<!-- Back to Top -->
<a href="#top" class="back-top-btn" aria-label="back to top" data-back-top-btn>
    <i class="fas fa-chevron-up"></i>
</a>

<!-- Scripts -->
<script>
    // Theme Toggle
    const themeToggle = document.getElementById('themeToggle');
    const body = document.body;
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') body.classList.add('dark-mode');
    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            body.classList.toggle('dark-mode');
            localStorage.setItem('theme', body.classList.contains('dark-mode') ? 'dark' : 'light');
        });
    }

    // Navbar Toggle
    const navbar = document.querySelector("[data-navbar]");
    const navTogglers = document.querySelectorAll("[data-nav-toggler]");
    const navLinks = document.querySelectorAll("[data-nav-link]");
    const overlay = document.querySelector("[data-overlay]");
    
    const toggleNavbar = function() { 
        navbar.classList.toggle("active"); 
        overlay.classList.toggle("active");
        if (navbar.classList.contains("active")) {
            body.classList.add("navbar-open");
            body.style.top = `-${window.scrollY}px`;
        } else {
            const scrollY = body.style.top;
            body.classList.remove("navbar-open");
            body.style.top = '';
            window.scrollTo(0, parseInt(scrollY || '0') * -1);
        }
    }
    
    const closeNavbar = function() { 
        navbar.classList.remove("active"); 
        overlay.classList.remove("active");
        body.classList.remove("navbar-open");
        body.style.top = '';
    }
    
    navTogglers.forEach(toggler => toggler.addEventListener("click", toggleNavbar));
    navLinks.forEach(link => link.addEventListener("click", closeNavbar));
    if (overlay) overlay.addEventListener("click", closeNavbar);

    // Header active on scroll
    const header = document.querySelector(".header");
    const backTopBtn = document.querySelector("[data-back-top-btn]");
    const activeElem = function() {
        if (window.scrollY > 100) {
            backTopBtn.classList.add("active");
        } else {
            backTopBtn.classList.remove("active");
        }
    }
    window.addEventListener("scroll", activeElem);

    // Scroll reveal
    const reveals = document.querySelectorAll('.reveal');
    const revealObserver = new IntersectionObserver(entries => {
        entries.forEach(entry => { if (entry.isIntersecting) entry.target.classList.add('active'); });
    }, { threshold: 0.15 });
    reveals.forEach(el => revealObserver.observe(el));

    // Smooth scroll
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const targetId = this.getAttribute('href');
            if (targetId === '#') return;
            const target = document.querySelector(targetId);
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth' });
                if (window.innerWidth < 992 && navbar.classList.contains('active')) closeNavbar();
            }
        });
    });
</script>
</body>
</html>