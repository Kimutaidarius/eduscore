<?php
session_start();

// Define base URL
$base_url = "https://eduscore.co.ke";

$page_title = "Terms of Service | EduScore Kenya";
$page_description = "Read EduScore's Terms of Service. Understand the terms and conditions for using our school management system.";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    
    <!-- Primary SEO Meta Tags -->
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="title" content="<?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="description" content="<?php echo htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="author" content="EduScore Kenya">
    <meta name="robots" content="index, follow">
    
    <!-- Favicon -->
    <link rel="shortcut icon" href="/images/logo.png" type="image/svg+xml">
    <link rel="icon" type="image/png" sizes="32x32" href="/images/logo.png">
    <link rel="apple-touch-icon" href="/images/logo.png">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;500;600;700;800&family=Poppins:wght@400;500&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet">
    
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
            --white: hsl(0, 0%, 100%);
            --text-dark: #1f2937;
            --text-muted: #6b7280;
            
            --ff-league_spartan: 'League Spartan', sans-serif;
            --ff-poppins: 'Poppins', sans-serif;
            
            --shadow-1: 0 6px 15px 0 hsla(0, 0%, 0%, 0.05);
            --shadow-2: 0 10px 30px hsla(0, 0%, 0%, 0.06);
            --shadow-3: 0 10px 50px 0 hsla(220, 53%, 22%, 0.1);
            --radius-5: 5px;
            --radius-10: 10px;
            --transition-1: 0.25s ease;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: var(--ff-poppins);
            background: linear-gradient(135deg, var(--isabelline) 0%, var(--white) 100%);
            color: var(--gray-web);
            font-size: 1.6rem;
            line-height: 1.75;
            min-height: 100vh;
        }
        
        .container { 
            max-width: 1200px; 
            width: 100%;
            margin: 0 auto; 
            padding: 40px 20px;
        }
        
        /* Content Card */
        .content-card {
            background: var(--white);
            border-radius: 28px;
            padding: 50px;
            box-shadow: var(--shadow-3);
            border: 1px solid var(--platinum);
            transition: all 0.3s ease;
        }
        
        .content-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        
        .logo-center {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo-center img {
            height: 55px;
            width: auto;
        }
        
        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--eerie-black-1);
            text-align: center;
            margin-bottom: 15px;
            font-family: var(--ff-league_spartan);
        }
        
        .last-updated {
            text-align: center;
            color: var(--gray-web);
            margin-bottom: 40px;
            font-size: 0.85rem;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--platinum);
        }
        
        .section {
            margin-bottom: 35px;
        }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--kappel);
            margin-bottom: 15px;
            font-family: var(--ff-league_spartan);
        }
        
        .section-content {
            color: var(--text-muted);
            line-height: 1.8;
        }
        
        .section-content p {
            margin-bottom: 15px;
        }
        
        .section-content ul, 
        .section-content ol {
            margin-left: 25px;
            margin-bottom: 15px;
        }
        
        .section-content li {
            margin-bottom: 8px;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--kappel);
            text-decoration: none;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--platinum);
            transition: all 0.3s ease;
        }
        
        .back-link:hover {
            gap: 12px;
            color: hsl(170, 75%, 35%);
        }
        
        /* Footer */
        .footer {
            background-color: var(--eerie-black-2);
            color: var(--gray-x-11);
            padding-block-start: 60px;
            margin-top: 60px;
        }
        
        .footer-top {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 30px;
            padding-block-end: 40px;
        }
        
        .footer-brand-text { margin-block: 20px; }
        
        .footer-link { 
            transition: var(--transition-1); 
            color: var(--gray-x-11); 
            text-decoration: none; 
            display: inline-block; 
            margin-bottom: 8px;
        }
        
        .footer-link:hover { color: var(--kappel); }
        
        .footer-list-title {
            color: var(--white);
            font-family: var(--ff-league_spartan);
            font-size: 1.8rem;
            font-weight: 600;
            margin-block-end: 15px;
        }
        
        .social-list {
            display: flex;
            gap: 20px;
            margin-top: 20px;
        }
        
        .social-link { 
            font-size: 2rem; 
            color: var(--gray-x-11); 
            transition: var(--transition-1);
        }
        
        .social-link:hover { 
            color: var(--kappel); 
            transform: translateY(-3px);
        }
        
        .footer-bottom {
            text-align: center;
            padding-block: 30px;
            border-top: 1px solid var(--eerie-black-1);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .container { padding: 20px 15px; }
            .content-card { padding: 30px 25px; }
            .page-title { font-size: 1.8rem; }
            .section-title { font-size: 1.3rem; }
            .footer-top { grid-template-columns: 1fr; text-align: center; }
        }
        
        @media (max-width: 480px) {
            .content-card { padding: 25px 20px; }
            .page-title { font-size: 1.5rem; }
        }
        
        /* Animation */
        .reveal {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.6s ease;
        }
        
        .reveal.active {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</head>
<body>

<section class="container">
    <div class="content-card reveal">
        <div class="logo-center">
            <img src="/images/logo.png" alt="EduScore logo">
        </div>
        
        <h1 class="page-title">Terms of Service</h1>
        <div class="last-updated">Last Updated: April 20, 2026</div>
        
        <div class="section">
            <h2 class="section-title">1. Acceptance of Terms</h2>
            <div class="section-content">
                <p>By accessing or using EduScore's school management system ("the Service"), you agree to be bound by these Terms of Service ("Terms"). If you disagree with any part of the terms, you may not access the Service.</p>
                <p>These Terms apply to all visitors, users, schools, administrators, teachers, and parents who access or use the Service.</p>
            </div>
        </div>
        
        <div class="section">
            <h2 class="section-title">2. Description of Service</h2>
            <div class="section-content">
                <p>EduScore provides a comprehensive school management platform including but not limited to:</p>
                <ul>
                    <li>Exam analysis and student performance tracking</li>
                    <li>Fee management and payment processing</li>
                    <li>Parent portal for real-time access to student information</li>
                    <li>Bulk SMS notifications and communications</li>
                    <li>Report card generation and analytics</li>
                    <li>Mwalimu AI teaching assistant</li>
                </ul>
                <p>We reserve the right to modify, suspend, or discontinue any part of the Service at any time without prior notice.</p>
            </div>
        </div>
        
        <div class="section">
            <h2 class="section-title">3. User Accounts and Registration</h2>
            <div class="section-content">
                <p>To use the Service, you must register for an account. You agree to:</p>
                <ul>
                    <li>Provide accurate, current, and complete information during registration</li>
                    <li>Maintain the security of your password and account</li>
                    <li>Accept responsibility for all activities that occur under your account</li>
                    <li>Notify us immediately of any unauthorized use of your account</li>
                </ul>
                <p>Each school is responsible for all activities conducted under its account and for maintaining the confidentiality of login credentials.</p>
            </div>
        </div>
        
        <div class="section">
            <h2 class="section-title">4. Data Privacy and Security</h2>
            <div class="section-content">
                <p>Your privacy is important to us. Our <a href="privacy.php" style="color: var(--kappel);">Privacy Policy</a> explains how we collect, use, and protect your personal information.</p>
                <p>We implement industry-standard security measures to protect your data, including encryption, secure servers, and regular security audits. However, no method of transmission over the Internet is 100% secure.</p>
            </div>
        </div>
        
        <div class="section">
            <h2 class="section-title">5. Payment Terms</h2>
            <div class="section-content">
                <p>EduScore offers various pricing plans for schools:</p>
                <ul>
                    <li><strong>Free Trial:</strong> 14-day free trial with full features</li>
                    <li><strong>Paid Plans:</strong> Subscription fees are charged per student per term</li>
                    <li><strong>Payment Methods:</strong> M-Pesa, bank transfers, and credit cards</li>
                    <li><strong>Onboarding Fee:</strong> One-time setup fee applies per school</li>
                </ul>
                <p>All fees are in Kenyan Shillings (KES) and are exclusive of taxes. Late payments may result in suspension of service.</p>
            </div>
        </div>
        
        <div class="section">
            <h2 class="section-title">6. User Responsibilities</h2>
            <div class="section-content">
                <p>As a user of EduScore, you agree to:</p>
                <ul>
                    <li>Use the Service in compliance with all applicable Kenyan laws and regulations</li>
                    <li>Not upload or transmit any malicious code, viruses, or harmful content</li>
                    <li>Not attempt to gain unauthorized access to other accounts or systems</li>
                    <li>Not use the Service for any illegal or unauthorized purpose</li>
                    <li>Respect the intellectual property rights of EduScore and third parties</li>
                </ul>
            </div>
        </div>
        
        <div class="section">
            <h2 class="section-title">7. Intellectual Property</h2>
            <div class="section-content">
                <p>The Service and its original content, features, and functionality are owned by EduScore Kenya and are protected by Kenyan and international copyright, trademark, patent, trade secret, and other intellectual property laws.</p>
                <p>You may not copy, modify, distribute, sell, or lease any part of our Service without explicit written permission.</p>
            </div>
        </div>
        
        <div class="section">
            <h2 class="section-title">8. Termination</h2>
            <div class="section-content">
                <p>We may terminate or suspend your account immediately, without prior notice, for conduct that we believe violates these Terms or is harmful to other users or the Service.</p>
                <p>Upon termination, your right to use the Service will cease immediately. You may export your data before termination.</p>
            </div>
        </div>
        
        <div class="section">
            <h2 class="section-title">9. Limitation of Liability</h2>
            <div class="section-content">
                <p>To the maximum extent permitted by law, EduScore Kenya shall not be liable for any indirect, incidental, special, consequential, or punitive damages arising out of or related to your use of the Service.</p>
                <p>Our total liability for any claim arising from these Terms shall not exceed the amount you paid us during the 12 months preceding the claim.</p>
            </div>
        </div>
        
        <div class="section">
            <h2 class="section-title">10. Disclaimer of Warranties</h2>
            <div class="section-content">
                <p>The Service is provided "as is" and "as available" without warranties of any kind, either express or implied. We do not warrant that the Service will be uninterrupted, error-free, or completely secure.</p>
            </div>
        </div>
        
        <div class="section">
            <h2 class="section-title">11. Changes to Terms</h2>
            <div class="section-content">
                <p>We reserve the right to modify these Terms at any time. We will notify users of material changes via email or through the Service. Your continued use of the Service after changes constitutes acceptance of the new Terms.</p>
            </div>
        </div>
        
        <div class="section">
            <h2 class="section-title">12. Governing Law</h2>
            <div class="section-content">
                <p>These Terms shall be governed by and construed in accordance with the laws of the Republic of Kenya. Any disputes arising from these Terms shall be resolved in the courts of Nairobi, Kenya.</p>
            </div>
        </div>
        
        <div class="section">
            <h2 class="section-title">13. Contact Us</h2>
            <div class="section-content">
                <p>If you have any questions about these Terms, please contact us:</p>
                <ul>
                    <li>Email: <a href="mailto:eduscoreke@gmail.com" style="color: var(--kappel);">eduscoreke@gmail.com</a></li>
                    <li>Phone: <a href="tel:+254799115282" style="color: var(--kappel);">+254 799 115 282</a></li>
                    <li>Address: Ngara - Nairobi, Kenya</li>
                </ul>
            </div>
        </div>
        
        <a href="register.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Registration
        </a>
    </div>
</section>

<!-- Footer -->
<footer class="footer">
    <div class="container">
        <div class="footer-top">
            <div class="footer-brand">
                <a href="index.php"><img src="/images/logo.png" alt="EduScore logo" style="height: 40px;"></a>
                <p class="footer-brand-text">Modern school management system for Kenyan educational institutions.</p>
                <div><span>📍 </span><address style="display: inline;">Ngara - Nairobi, Kenya</address></div>
                <div><span>📞 </span><a href="tel:+254799115282" class="footer-link">+254 799 115 282</a></div>
                <div><span>✉️ </span><a href="mailto:eduscoreke@gmail.com" class="footer-link">eduscoreke@gmail.com</a></div>
            </div>
            <ul class="footer-list">
                <li><p class="footer-list-title">Quick Links</p></li>
                <li><a href="index.php#features" class="footer-link">Features</a></li>
                <li><a href="index.php#pricing" class="footer-link">Pricing</a></li>
                <li><a href="blog.php" class="footer-link">Blog</a></li>
            </ul>
            <ul class="footer-list">
                <li><p class="footer-list-title">Legal</p></li>
                <li><a href="terms.php" class="footer-link">Terms of Service</a></li>
                <li><a href="privacy.php" class="footer-link">Privacy Policy</a></li>
            </ul>
            <div class="footer-list">
                <p class="footer-list-title">Connect With Us</p>
                <div class="social-list">
                    <a href="#" class="social-link"><i class="fab fa-facebook"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-linkedin"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>Copyright <?php echo date('Y'); ?> All Rights Reserved by <a href="#" class="footer-link">EduScore Kenya</a></p>
        </div>
    </div>
</footer>

<script>
    // Scroll reveal
    const reveals = document.querySelectorAll('.reveal');
    const revealObserver = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) entry.target.classList.add('active');
        });
    }, { threshold: 0.1 });
    reveals.forEach(el => revealObserver.observe(el));
</script>
</body>
</html>