<?php
// global-header.php - Shared header for all frontend pages (index, about, pricing, etc.)
// This file is for the public-facing pages only, NOT for admin dashboard

// session_start() should already be called in the parent file
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="author" content="EduScore Kenya">
    <meta name="robots" content="index, follow">
    
    <link rel="shortcut icon" href="/images/logo.png" type="image/svg+xml">
    <link rel="icon" type="image/png" sizes="32x32" href="/images/logo.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/images/logo.png">
    <link rel="apple-touch-icon" href="/images/logo.png">
    <link rel="manifest" href="/site.webmanifest">
    <meta name="theme-color" content="#00BFFF">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;500;600;700;800&family=Poppins:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet">
    
    <style>
        /*-----------------------------------*\
          #CUSTOM PROPERTY
        \*-----------------------------------*/
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
            --white: hsl(0, 0%, 100%);
            
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
            --radius-15: 15px;
            
            --transition-1: 0.25s ease;
            --transition-2: 0.5s ease;
            --cubic-in: cubic-bezier(0.51, 0.03, 0.64, 0.28);
            --cubic-out: cubic-bezier(0.33, 0.85, 0.4, 0.96);
        }
        
        /* Dark Mode Overrides */
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
        
        /*-----------------------------------*\
          #RESET
        \*-----------------------------------*/
        *,
        *::before,
        *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        li { list-style: none; }
        
        a {
            color: inherit;
            text-decoration: none;
        }
        
        img { height: auto; }
        
        button { cursor: pointer; }
        
        html {
            font-family: var(--ff-poppins);
            font-size: 10px;
            scroll-behavior: smooth;
        }
        
        body {
            background-color: var(--white);
            color: var(--gray-web);
            font-size: 1.6rem;
            line-height: 1.75;
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        
        /*-----------------------------------*\
          #REUSED STYLE
        \*-----------------------------------*/
        .container { 
            max-width: 1400px;
            margin: 0 auto;
            padding-inline: 15px;
        }
        
        .section { 
            padding-block: var(--section-padding); 
        }
        
        .section-subtitle {
            font-size: var(--fs-5);
            text-transform: uppercase;
            font-weight: var(--fw-500);
            letter-spacing: 1px;
            text-align: center;
            margin-block-end: 12px;
        }
        
        .section-title {
            font-size: var(--fs-2);
            color: var(--eerie-black-1);
            font-family: var(--ff-league_spartan);
            font-weight: var(--fw-600);
            text-align: center;
            margin-bottom: 12px;
        }
        
        .btn {
            background-color: var(--kappel);
            color: var(--white);
            font-family: var(--ff-league_spartan);
            font-size: var(--fs-4);
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 8px 18px;
            border-radius: var(--radius-5);
            text-decoration: none;
            transition: var(--transition-1);
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-2);
        }
        
        .reveal {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.7s ease;
        }
        
        .reveal.active {
            opacity: 1;
            transform: translateY(0);
        }
        
        main {
            min-height: 400px;
        }
        
        /*============================================
          #HEADER - FULLY STYLED MODERN NAVBAR
        ============================================*/
        
        /* Base Header Styles */
        .header {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            z-index: 1000;
            transition: all 0.3s cubic-bezier(0.2, 0, 0, 1);
            border-bottom: 1px solid rgba(0, 0, 0, 0.06);
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.03);
        }
        
        .header.active {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            box-shadow: 0 4px 25px rgba(0, 0, 0, 0.08);
            border-bottom-color: rgba(0, 0, 0, 0.08);
        }
        
        .header.active .logo img {
            height: 36px;
        }
        
        .header.active .container {
            padding: 8px 32px;
        }
        
        body.dark-mode .header {
            background: rgba(15, 23, 42, 0.95);
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.2);
        }
        
        body.dark-mode .header.active {
            background: rgba(15, 23, 42, 0.98);
            box-shadow: 0 4px 25px rgba(0, 0, 0, 0.3);
            border-bottom-color: rgba(255, 255, 255, 0.08);
        }
        
        .header .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            max-width: 1400px;
            width: 100%;
            margin: 0 auto;
            padding: 12px 32px;
            transition: padding 0.25s ease;
        }
        
        .logo {
            flex-shrink: 0;
            line-height: 0;
        }
        
        .logo img {
            height: 40px;
            width: auto;
            transition: height 0.25s ease;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.05));
        }
        
        .navbar {
            display: flex;
            align-items: center;
            flex: 1;
            justify-content: center;
        }
        
        .navbar-list {
            display: flex;
            align-items: center;
            gap: 28px;
            list-style: none;
            margin: 0;
            padding: 0;
        }
        
        .navbar-link {
            font-weight: 500;
            color: var(--eerie-black-1);
            transition: all 0.25s ease;
            position: relative;
            padding: 6px 0;
            font-size: 1.4rem;
            text-decoration: none;
            letter-spacing: -0.2px;
        }
        
        body.dark-mode .navbar-link {
            color: #e2e8f0;
        }
        
        .navbar-link:hover {
            color: #00BFFF;
        }
        
        .navbar-link::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background: linear-gradient(90deg, #00BFFF, #0099cc);
            background-size: 200% auto;
            transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 2px;
        }
        
        .navbar-link:hover::after,
        .navbar-link.active::after {
            width: 100%;
        }
        
        .navbar-link.active {
            color: #00BFFF;
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }
        
        .theme-toggle {
            background: rgba(0, 0, 0, 0.04);
            border: none;
            font-size: 1.6rem;
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            width: 38px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--eerie-black-1);
            transition: all 0.25s ease;
        }
        
        body.dark-mode .theme-toggle {
            background: rgba(255, 255, 255, 0.08);
            color: #e2e8f0;
        }
        
        .theme-toggle:hover {
            background: rgba(0, 191, 255, 0.12);
            transform: scale(1.05);
            color: #00BFFF;
        }
        
        .theme-toggle .fa-sun { display: none; }
        body.dark-mode .theme-toggle .fa-moon { display: none; }
        body.dark-mode .theme-toggle .fa-sun { display: block; }
        
        .portal-buttons-header {
            display: flex;
            gap: 10px;
        }
        
        .portal-btn {
            padding: 7px 18px;
            border-radius: 40px;
            font-weight: 600;
            text-decoration: none;
            font-size: 1.25rem;
            transition: all 0.25s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            letter-spacing: -0.2px;
        }
        
        .portal-btn-analytics {
            background: linear-gradient(135deg, #00BFFF, #0099cc);
            color: #ffffff;
            box-shadow: 0 2px 8px rgba(0, 191, 255, 0.25);
            border: none;
        }
        
        .portal-btn-analytics:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 14px rgba(0, 191, 255, 0.35);
            background: linear-gradient(135deg, #0099cc, #0077aa);
        }
        
        .portal-btn-finance {
            background: rgba(0, 191, 255, 0.08);
            color: #00BFFF;
            border: 1px solid rgba(0, 191, 255, 0.4);
        }
        
        .portal-btn-finance:hover {
            background: #00BFFF;
            color: #ffffff;
            transform: translateY(-2px);
            border-color: transparent;
            box-shadow: 0 4px 12px rgba(0, 191, 255, 0.3);
        }
        
        .menu-btn {
            display: none;
            background: rgba(0, 0, 0, 0.04);
            border: none;
            font-size: 2rem;
            cursor: pointer;
            padding: 8px;
            border-radius: 12px;
            width: 40px;
            height: 40px;
            align-items: center;
            justify-content: center;
            color: var(--eerie-black-1);
            transition: all 0.25s ease;
        }
        
        body.dark-mode .menu-btn {
            background: rgba(255, 255, 255, 0.08);
            color: #e2e8f0;
        }
        
        .menu-btn:hover {
            background: rgba(0, 191, 255, 0.12);
            color: #00BFFF;
            transform: scale(1.02);
        }
        
        .nav-close-btn {
            background: rgba(0, 0, 0, 0.05);
            border: none;
            font-size: 1.8rem;
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            width: 38px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--eerie-black-1);
            transition: all 0.25s ease;
        }
        
        body.dark-mode .nav-close-btn {
            background: rgba(255, 255, 255, 0.08);
            color: #e2e8f0;
        }
        
        .nav-close-btn:hover {
            background: rgba(0, 191, 255, 0.12);
            color: #00BFFF;
            transform: rotate(90deg);
        }
        
        .navbar .wrapper {
            display: none;
        }
        
        .overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.25s ease;
            z-index: 1000;
        }
        
        .overlay.active {
            opacity: 1;
            pointer-events: all;
        }
        
        body.navbar-open {
            overflow: hidden;
            position: fixed;
            width: 100%;
            height: 100%;
        }
        
        /* Mobile Drawer */
        @media (max-width: 991px) {
            .navbar {
                position: fixed;
                top: 0;
                right: -100%;
                width: min(85%, 320px);
                height: 100vh;
                background: rgba(255, 255, 255, 0.98);
                backdrop-filter: blur(24px);
                -webkit-backdrop-filter: blur(24px);
                z-index: 1001;
                transition: right 0.35s cubic-bezier(0.2, 0.9, 0.4, 1.1);
                overflow-y: auto;
                padding: 20px;
                flex-direction: column;
                justify-content: flex-start;
                box-shadow: -5px 0 30px rgba(0, 0, 0, 0.1);
            }
            
            body.dark-mode .navbar {
                background: rgba(15, 23, 42, 0.98);
                box-shadow: -5px 0 30px rgba(0, 0, 0, 0.3);
            }
            
            .navbar.active {
                right: 0;
            }
            
            .navbar .wrapper {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding-bottom: 16px;
                margin-bottom: 20px;
                border-bottom: 1px solid rgba(0, 0, 0, 0.08);
            }
            
            body.dark-mode .navbar .wrapper {
                border-bottom-color: rgba(255, 255, 255, 0.1);
            }
            
            .navbar-list {
                flex-direction: column;
                gap: 4px;
                width: 100%;
            }
            
            .navbar-item {
                width: 100%;
            }
            
            .navbar-link {
                display: block;
                padding: 12px 16px;
                font-size: 1.4rem;
                font-weight: 500;
                border-radius: 12px;
                transition: all 0.25s ease;
                white-space: normal;
            }
            
            .navbar-link:hover,
            .navbar-link.active {
                background: rgba(0, 191, 255, 0.08);
                color: #00BFFF;
                transform: translateX(4px);
            }
            
            .navbar-link::after {
                display: none;
            }
            
            .mobile-portal-buttons {
                display: flex;
                flex-direction: column;
                gap: 12px;
                margin-top: 24px;
                padding-top: 20px;
                border-top: 1px solid rgba(0, 0, 0, 0.08);
            }
            
            body.dark-mode .mobile-portal-buttons {
                border-top-color: rgba(255, 255, 255, 0.1);
            }
            
            .mobile-portal-btn {
                padding: 12px 20px;
                border-radius: 40px;
                font-weight: 600;
                font-size: 1.35rem;
                text-decoration: none;
                text-align: center;
                transition: all 0.25s ease;
            }
            
            .mobile-portal-btn-analytics {
                background: linear-gradient(135deg, #00BFFF, #0099cc);
                color: #ffffff;
                box-shadow: 0 2px 8px rgba(0, 191, 255, 0.2);
            }
            
            .mobile-portal-btn-analytics:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 14px rgba(0, 191, 255, 0.3);
            }
            
            .mobile-portal-btn-finance {
                background: transparent;
                color: #00BFFF;
                border: 1.5px solid #00BFFF;
            }
            
            .mobile-portal-btn-finance:hover {
                background: #00BFFF;
                color: #ffffff;
                transform: translateY(-2px);
            }
            
            .portal-buttons-header {
                display: none;
            }
            
            .menu-btn {
                display: flex;
            }
            
            .header .container {
                padding: 12px 20px;
            }
            
            .header.active .container {
                padding: 8px 20px;
            }
        }
        
        @media (max-width: 480px) {
            .header .container {
                padding: 10px 16px;
            }
            
            .logo img {
                height: 34px;
            }
            
            .header.active .logo img {
                height: 30px;
            }
            
            .menu-btn {
                width: 38px;
                height: 38px;
                font-size: 1.8rem;
            }
            
            .theme-toggle {
                width: 36px;
                height: 36px;
                font-size: 1.4rem;
            }
            
            .navbar {
                width: 85%;
                padding: 16px;
            }
            
            .navbar-link {
                font-size: 1.35rem;
                padding: 10px 14px;
            }
            
            .mobile-portal-btn {
                font-size: 1.25rem;
                padding: 10px 16px;
            }
        }
        
        @media (min-width: 1400px) {
            .header .container {
                padding: 14px 40px;
            }
            
            .header.active .container {
                padding: 10px 40px;
            }
            
            .navbar-list {
                gap: 36px;
            }
            
            .navbar-link {
                font-size: 1.45rem;
            }
            
            .logo img {
                height: 44px;
            }
            
            .header.active .logo img {
                height: 38px;
            }
        }
        
        /* Footer Styles */
        .footer {
            background-color: var(--eerie-black-2);
            color: var(--gray-x-11);
            padding-block-start: 50px;
        }
        
        .footer-top {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 30px;
            padding-block-end: 35px;
        }
        
        .footer-brand-text {
            font-size: 1.3rem;
            margin-block: 15px;
        }
        
        .footer-list-title {
            color: var(--white);
            font-family: var(--ff-league_spartan);
            font-size: 1.6rem;
            font-weight: 600;
            margin-block-end: 12px;
        }
        
        .footer-link {
            text-decoration: none;
            color: inherit;
            display: block;
            padding-block: 4px;
            font-size: 1.3rem;
            transition: var(--transition-1);
        }
        
        .footer-link:hover {
            color: var(--kappel);
        }
        
        .copyright {
            text-align: center;
            padding-block: 25px;
            border-block-start: 1px solid var(--eerie-black-1);
            font-size: 1.25rem;
        }
        
        .back-top-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background-color: var(--kappel);
            color: var(--white);
            font-size: 1.6rem;
            padding: 12px;
            border-radius: var(--radius-circle);
            z-index: 3;
            opacity: 0;
            pointer-events: none;
            transition: var(--transition-1);
        }
        
        .back-top-btn.active {
            opacity: 1;
            pointer-events: all;
        }
        
        @media (max-width: 991px) {
            .footer-top {
                grid-template-columns: 1fr;
                gap: 25px;
            }
        }
    </style>
</head>
<body>

<!-- Header -->
<header class="header" data-header>
    <div class="container">
        <a href="index.php" class="logo">
            <img src="/images/logo.png" alt="EduScore logo">
        </a>

        <nav class="navbar" data-navbar>
            <div class="wrapper">
                <a href="index.php" class="logo">
                    <img src="/images/logo.png" alt="EduScore logo">
                </a>
                <button class="nav-close-btn" aria-label="close menu" data-nav-toggler>
                    <ion-icon name="close-outline" aria-hidden="true"></ion-icon>
                </button>
            </div>
            <ul class="navbar-list">
                <li class="navbar-item"><a href="index.php" class="navbar-link" data-nav-link>Home</a></li>
                <li class="navbar-item"><a href="about.php" class="navbar-link" data-nav-link>About</a></li>
                <li class="navbar-item"><a href="pricing.php" class="navbar-link" data-nav-link>Pricing</a></li>
                <li class="navbar-item"><a href="career-pathways.php" class="navbar-link" data-nav-link>Career Pathways</a></li>
                <li class="navbar-item"><a href="blog.php" class="navbar-link" data-nav-link>Blog</a></li>
            </ul>
            
            <div class="mobile-portal-buttons">
                <a href="analytics.php" class="mobile-portal-btn mobile-portal-btn-analytics">
                    <i class="fas fa-chart-line"></i> Analytics Portal
                </a>
                <a href="feesystem.php" class="mobile-portal-btn mobile-portal-btn-finance">
                    <i class="fas fa-wallet"></i> Finance Portal
                </a>
            </div>
        </nav>
        
        <div class="header-actions">
            <button class="theme-toggle" id="themeToggle" aria-label="Toggle dark mode">
                <i class="fas fa-moon"></i>
                <i class="fas fa-sun"></i>
            </button>
            <div class="portal-buttons-header">
                <a href="analytics.php" class="portal-btn portal-btn-analytics">
                    <i class="fas fa-chart-line"></i> Analytics Portal
                </a>
                <a href="feesystem.php" class="portal-btn portal-btn-finance">
                    <i class="fas fa-wallet"></i> Finance Portal
                </a>
            </div>
            <button class="menu-btn" aria-label="open menu" data-nav-toggler>
                <ion-icon name="menu-outline" aria-hidden="true"></ion-icon>
            </button>
        </div>

        <div class="overlay" data-nav-toggler data-overlay></div>
    </div>
</header>

<!-- Include Ion Icons -->
<script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>

<script>
// Theme Toggle
const themeToggle = document.getElementById('themeToggle');
const body = document.body;
const savedTheme = localStorage.getItem('theme');
if (savedTheme === 'dark') { body.classList.add('dark-mode'); }
if (themeToggle) {
    themeToggle.addEventListener('click', () => {
        body.classList.toggle('dark-mode');
        localStorage.setItem('theme', body.classList.contains('dark-mode') ? 'dark' : 'light');
    });
}

// Navbar Toggle
const navbar = document.querySelector("[data-navbar]");
const navTogglers = document.querySelectorAll("[data-nav-toggler]");
const overlay = document.querySelector("[data-overlay]");

const toggleNavbar = function () { 
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

const closeNavbar = function () { 
    navbar.classList.remove("active"); 
    overlay.classList.remove("active");
    body.classList.remove("navbar-open");
    body.style.top = '';
}

navTogglers.forEach(toggler => toggler.addEventListener("click", toggleNavbar));

if (overlay) {
    overlay.addEventListener("click", closeNavbar);
}

// Header active on scroll
const header = document.querySelector("[data-header]");
window.addEventListener("scroll", function() {
    if (window.scrollY > 50) {
        header.classList.add("active");
    } else {
        header.classList.remove("active");
    }
});

// Set active nav link based on current page
const currentPage = window.location.pathname.split('/').pop();
document.querySelectorAll('.navbar-link').forEach(link => {
    const href = link.getAttribute('href');
    if (href === currentPage || (currentPage === '' && href === 'index.php')) {
        link.classList.add('active');
    }
});
</script>