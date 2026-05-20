<?php
session_start();

// Define base URL
$base_url = "https://eduscore.co.ke";

$page_title = "Privacy Policy | EduScore Kenya";
$page_description = "Read EduScore's Privacy Policy. Learn how we collect, use, and protect your personal information when using our school management system.";
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
        
        <h1 class="page-title">Privacy Policy</h1>
        <div class="last-updated">Last Updated: April 20, 2026</div>
        
        <div class="section">
            <h2 class="section-title">1. Introduction</h2>
            <div class="section-content">
                <p>EduScore Kenya ("we", "our", "us") is committed to protecting your privacy. This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you use our school management system ("the Service").</p>
                <p>Please read this Privacy Policy carefully. By accessing or using the Service, you acknowledge that you have read, understood, and agree to be bound by this Privacy Policy.</p>
            </div>
        </div>
        
        <div class="section">
            <h2 class="section-title">2. Information We Collect</h2>
            <div class="section-content">
                <p>We collect several types of information from and about users of our Service:</p>
                <ul>
                    <li><strong>School Information:</strong> School name, address, email, phone number, county, institution type, student population, and school motto.</li>
                    <li><strong>Personal Information:</strong> Names, email addresses, phone numbers of school administrators, teachers, and parents.</li>
                    <li><strong>Student Information:</strong> Student names, admission numbers, class, stream, academic performance data, attendance records, and fee payment history.</li>
                    <li><strong>Technical Data:</strong> IP addresses, browser type, device information, and usage statistics.</li>
                    <li><strong>Payment Information:</strong> Transaction details, M-Pesa codes, and payment history (we do not store full payment credentials).</li>
                </ul>
            </div>
        </div>
        
        <div class="section">
            <h2 class="section-title">3. How We Use Your Information</h2>
            <div class="section-content">
                <p>We use the information we collect for various purposes:</p>
                <ul>
                    <li>To provide, maintain, and improve the Service</li>
                    <li>To process transactions and manage fee payments</li>
                    <li>To generate reports, merit lists, and report cards</li>
                    <li>To communicate with schools, administrators, teachers, and parents</li>
                    <li>To send SMS notifications and email updates</li>
                    <li>To analyze usage patterns and improve user experience</li>
                    <li>To comply with legal obligations</li>
                    <li>To detect, prevent, and address technical or security issues</li>
                </ul>
            </div>
        </div>
        
        <div class="section">
            <h2 class="section-title">4. Legal Basis for Processing</h2>
            <div class="section-content">
                <p>Under Kenyan data protection laws, we process your information based on:</p>
                <ul>
                    <li><strong>Contractual Necessity:</strong> To fulfill our agreement with your school</li>
                    <li><strong>Legitimate Interests:</strong> To improve and secure our Service</li>
                    <li><strong>Legal Obligations:</strong> To comply with applicable laws and regulations</li>
                    <li><strong>Consent:</strong> Where you have given explicit consent for specific processing</li>
                </ul>
            </div>
        </div>
        
        <div class="section">
            <h2 class="section-title">5. Data Sharing and Disclosure</h2>
            <div class="section-content">
                <p>We do not sell, trade, or rent your personal information to third parties. We may share information in the following circumstances:</p>
                <ul>
                    <li><strong>Service Providers:</strong> With third-party vendors who assist in operating our Service (e.g., SMS gateway providers, payment processors)</li>
                    <li><strong>Legal Requirements:</strong> When required by law, court order, or government regulation</li>
                    <li><strong>Business Transfers:</strong> In connection with a merger, acquisition, or sale of assets</li>
                    <li><strong>With Your Consent:</strong> When you have given explicit permission</li>
                </ul>
                <p>All third-party service providers are contractually obligated to protect your information and use it only for specified purposes.</p>
            </div>
        </div>
        
        <div class="section">
            <h2 class="section-title">6. Data Security</h2>
            <div class="section-content">
                <p>We implement appropriate technical and organizational security measures to protect your information:</p>
                <ul>
                    <li>Data encryption in transit (SSL/TLS) and at rest</li>
                    <li>Secure data centers with access controls</li>
                    <li>Regular security assessments and audits</li>
                    <li>Employee training on data protection</li>
                    <li>Two-factor authentication for administrative access</li>
                </ul>
                <p>However, no method of transmission over the Internet is 100% secure. We cannot guarantee absolute security.</p>
            </div>
        </div>
        
        <div class="section">
            <h2 class="section-title">7. Data Retention</h2>
            <div class="section-content">
                <p>We retain your information for as long as your account is active or as needed to provide the Service. We will retain and use your information as necessary to:</p>
                <ul>
                    <li>Comply with legal obligations</li>
                    <li>Resolve disputes</li>
                    <li>Enforce our agreements</li>
                    <li>Maintain historical academic records</li>
                </ul>
                <p>Upon termination of your account, we will delete or anonymize your personal information within 90 days, unless legal retention requirements apply.</p>
            </div>
        </div>
        
        <div class="section">
            <h2 class="section-title">8. Your Rights</h2>
            <div class="section-content">
                <p>Under Kenyan data protection law, you have the following rights:</p>
                <ul>
                    <li><strong>Access:</strong> Request a copy of your personal information</li>
                    <li><strong>Correction:</strong> Request correction of inaccurate or incomplete information</li>
                    <li><strong>Deletion:</strong> Request deletion of your personal information (subject to legal requirements)</li>
                    <li><strong>Restriction:</strong> Request restriction of processing in certain circumstances</li>
                    <li><strong>Portability:</strong> Request transfer of your data to another service</li>
                    <li><strong>Objection:</strong> Object to processing based on legitimate interests</li>
                    <li><strong>Withdraw Consent:</strong> Withdraw previously given consent</li>
                </ul>
                <p>To exercise these rights, please contact us at <a href="mailto:eduscoreke@gmail.com" style="color: var(--kappel);">eduscoreke@gmail.com</a>.</p>
            </div>
        </div>
        
        <div class="section">
            <h2 class="section-title">9. Children's Privacy</h2>
            <div class="section-content">
                <p>Our Service collects information about students as provided by schools. Schools are responsible for obtaining appropriate parental consent for collecting and processing student information. Parents should direct privacy inquiries to their child's school.</p>
                <p>We do not knowingly collect personal information from children without school authorization. If you believe we have inadvertently collected such information, please contact us.</p>
            </div>
        </div>
        
        <div class="section">
            <h2 class="section-title">10. Cookies and Tracking</h2>
            <div class="section-content">
                <p>We use cookies and similar tracking technologies to:</p>
                <ul>
                    <li>Maintain user sessions and preferences</li>
                    <li>Analyze usage patterns and improve the Service</li>
                    <li>Provide personalized content</li>
                </ul>
                <p>You can control cookie settings through your browser preferences. However, disabling cookies may affect the functionality of the Service.</p>
            </div>
        </div>
        
        <div class="section">
            <h2 class="section-title">11. International Data Transfers</h2>
            <div class="section-content">
                <p>Your information may be transferred to and maintained on servers located outside of Kenya. We ensure appropriate safeguards are in place for such transfers, including standard contractual clauses approved by relevant authorities.</p>
            </div>
        </div>
        
        <div class="section">
            <h2 class="section-title">12. Changes to This Privacy Policy</h2>
            <div class="section-content">
                <p>We may update this Privacy Policy from time to time. We will notify you of any material changes by:</p>
                <ul>
                    <li>Posting the new Privacy Policy on this page</li>
                    <li>Sending an email notification to registered school administrators</li>
                    <li>Displaying a notice within the Service</li>
                </ul>
                <p>The "Last Updated" date at the top of this page indicates when changes were made. Your continued use of the Service after changes constitutes acceptance of the updated Privacy Policy.</p>
            </div>
        </div>
        
        <div class="section">
            <h2 class="section-title">13. Contact Information</h2>
            <div class="section-content">
                <p>If you have any questions or concerns about this Privacy Policy or our data practices, please contact us:</p>
                <ul>
                    <li><strong>Email:</strong> <a href="mailto:eduscoreke@gmail.com" style="color: var(--kappel);">eduscoreke@gmail.com</a></li>
                    <li><strong>Phone:</strong> <a href="tel:+254799115282" style="color: var(--kappel);">+254 799 115 282</a></li>
                    <li><strong>Address:</strong> Ngara - Nairobi, Kenya</li>
                    <li><strong>Data Protection Officer:</strong> dpo@eduscore.co.ke</li>
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