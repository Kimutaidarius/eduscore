<?php
session_start();
require_once '../includes/config.php';

$page_title = "Register - EduScore Finance System";

// Redirect if already logged in
if (!empty($_SESSION['is_logged_in'])) {
    header('Location: dashboard.php');
    exit;
}

// Helper function to get selected values for multi-select fields
function getSelectedValues($fieldName) {
    if (isset($_POST[$fieldName]) && !empty($_POST[$fieldName])) {
        return explode(',', $_POST[$fieldName]);
    }
    return [];
}

// Define options for multi-select fields
$institution_type_options = [
    'public_boarding' => 'Public Boarding School',
    'public_day' => 'Public Day School',
    'private_boarding' => 'Private Boarding School',
    'private_day' => 'Private Day School',
    'international' => 'International School'
];

$institution_level_options = [
    'primary' => 'Primary School',
    'secondary' => 'Secondary School',
    'college' => 'College',
    'university' => 'University',
    'mixed' => 'Mixed Levels'
];

$product_type_options = [
    'Exam Analysis System' => 'Exam Analysis System',
    'Fee Management System' => 'Fee Management System'
];

// County options array
$county_options = [
    'baringo' => 'Baringo',
    'bomet' => 'Bomet',
    'bungoma' => 'Bungoma',
    'busia' => 'Busia',
    'elgeyo-marakwet' => 'Elgeyo-Marakwet',
    'embu' => 'Embu',
    'garissa' => 'Garissa',
    'homa bay' => 'Homa Bay',
    'isiolo' => 'Isiolo',
    'kajiado' => 'Kajiado',
    'kakamega' => 'Kakamega',
    'kericho' => 'Kericho',
    'kiambu' => 'Kiambu',
    'kilifi' => 'Kilifi',
    'kirinyaga' => 'Kirinyaga',
    'kisii' => 'Kisii',
    'kisumu' => 'Kisumu',
    'kitui' => 'Kitui',
    'kwale' => 'Kwale',
    'laikipia' => 'Laikipia',
    'lamu' => 'Lamu',
    'machakos' => 'Machakos',
    'makueni' => 'Makueni',
    'mandera' => 'Mandera',
    'marsabit' => 'Marsabit',
    'meru' => 'Meru',
    'migori' => 'Migori',
    'mombasa' => 'Mombasa',
    "murang'a" => "Murang'a",
    'nairobi' => 'Nairobi',
    'nakuru' => 'Nakuru',
    'nandi' => 'Nandi',
    'narok' => 'Narok',
    'nyamira' => 'Nyamira',
    'nyandarua' => 'Nyandarua',
    'nyeri' => 'Nyeri',
    'samburu' => 'Samburu',
    'siaya' => 'Siaya',
    'taita taveta' => 'Taita Taveta',
    'tana river' => 'Tana River',
    'tharaka-nithi' => 'Tharaka-Nithi',
    'trans nzoia' => 'Trans Nzoia',
    'turkana' => 'Turkana',
    'uasin gishu' => 'Uasin Gishu',
    'vihiga' => 'Vihiga',
    'wajir' => 'Wajir',
    'west pokot' => 'West Pokot'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EduScore | Register School</title>

<link rel="icon" href="../images/logo.png">
<link rel="apple-touch-icon" href="../images/logo.png">

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<style>
:root{
    --primary-blue:#0b2c4d;
    --secondary-blue:#143a63;
    --accent-yellow:#f4c430;
    --bg-light:#f9fafb;
    --border:#e5e7eb;
    --text-dark:#1f2937;
    --text-muted:#6b7280;
    --success-green:#10b981;
    --error-red:#dc2626;
    --warning-yellow:#f59e0b;
    --step-inactive:#d1d5db;
    --card-bg:#ffffff;
    --input-bg:#ffffff;
    --shadow:0 25px 60px rgba(0,0,0,.08);
    --dropdown-bg:#ffffff;
    --hover-bg:#f8f9fa;
}

body.dark-mode {
    --primary-blue:#3b82f6;
    --secondary-blue:#2563eb;
    --accent-yellow:#facc15;
    --bg-light:#0f172a;
    --card-bg:#1e293b;
    --border:#334155;
    --text-dark:#f1f5f9;
    --text-muted:#94a3b8;
    --error-red:#f87171;
    --success-green:#4ade80;
    --warning-yellow:#fbbf24;
    --step-inactive:#475569;
    --input-bg:#1e293b;
    --shadow:0 25px 60px rgba(0,0,0,0.3);
    --dropdown-bg:#1e293b;
    --hover-bg:#334155;
}

*{margin:0;padding:0;box-sizing:border-box}

body{
    font-family:'Inter',sans-serif;
    background:var(--bg-light);
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:20px;
    transition:background-color 0.3s ease, color 0.3s ease;
}

.container{
    width:100%;
    max-width:1200px;
    background:var(--card-bg);
    display:grid;
    grid-template-columns:1fr 1fr;
    border-radius:18px;
    overflow:hidden;
    box-shadow:var(--shadow);
    transition:background-color 0.3s ease, box-shadow 0.3s ease;
}

.img{
    background:linear-gradient(135deg,var(--primary-blue),var(--secondary-blue));
    display:flex;
    align-items:center;
    justify-content:center;
    position:relative;
    padding:40px;
    transition:background 0.3s ease;
}
.img::after{
    content:"";
    position:absolute;
    bottom:0;
    width:100%;
    height:6px;
    background:var(--accent-yellow);
}
.img img{
    max-width:85%;
    animation: float 6s ease-in-out infinite;
}

@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-15px); }
}

.register-content{
    padding:40px 30px;
    display:flex;
    flex-direction:column;
    overflow-y:auto;
    position:relative;
    background:var(--card-bg);
    transition:background 0.3s ease;
}

form{
    width:100%;
    max-width:500px;
    margin:0 auto;
    position:relative;
    display:flex;
    flex-direction:column;
    height:100%;
}

.back-btn{
    position:absolute;
    top:10px;
    left:0;
    color:var(--primary-blue);
    font-size:.9rem;
    text-decoration:none;
    display:flex;
    align-items:center;
    gap:6px;
    background:var(--card-bg);
    padding:8px 12px;
    border-radius:20px;
    border:1px solid var(--border);
    transition:all .3s ease;
    z-index:10;
}
.back-btn:hover{
    background:var(--hover-bg);
    border-color:var(--primary-blue);
}

.avatar{
    height:80px;
    display:block;
    margin:10px auto 15px;
    max-width:100%;
}

h2{
    text-align:center;
    color:var(--primary-blue);
    font-size:1.9rem;
    margin-bottom:6px;
    line-height:1.3;
    transition:color 0.3s ease;
}
.subtitle{
    text-align:center;
    color:var(--text-muted);
    font-size:.95rem;
    margin-bottom:25px;
    line-height:1.5;
    transition:color 0.3s ease;
}

/* Progress Steps */
.form-steps{
    display:flex;
    justify-content:space-between;
    margin-bottom:30px;
    position:relative;
}
.form-steps::before{
    content:'';
    position:absolute;
    top:20px;
    left:0;
    right:0;
    height:2px;
    background:var(--step-inactive);
    z-index:1;
}
.form-step{
    position:relative;
    z-index:2;
    text-align:center;
    flex:1;
}
.step-number{
    width:40px;
    height:40px;
    border-radius:50%;
    background:var(--step-inactive);
    color:white;
    display:flex;
    align-items:center;
    justify-content:center;
    margin:0 auto 10px;
    font-weight:600;
    font-size:1.1rem;
    border:3px solid var(--card-bg);
    transition:all 0.3s ease;
}
.step-label{
    font-size:0.85rem;
    color:var(--text-muted);
    font-weight:500;
    transition:color 0.3s ease;
}
.form-step.active .step-number{
    background:var(--primary-blue);
    transform:scale(1.1);
}
.form-step.completed .step-number{
    background:var(--success-green);
}
.form-step.active .step-label{
    color:var(--primary-blue);
    font-weight:600;
}

/* Form Sections */
.form-container{
    flex:1;
    display:flex;
    flex-direction:column;
}
.form-sections{
    position:relative;
    min-height:400px;
}
.form-section{
    display:none;
    animation:fadeIn 0.5s ease;
}
.form-section.active{
    display:block;
}
@keyframes fadeIn{
    from{opacity:0;transform:translateY(-10px);}
    to{opacity:1;transform:translateY(0);}
}

/* Form Rows */
.form-row{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:15px;
    margin-bottom:15px;
}
.form-group{
    margin-bottom:15px;
}
.form-group label{
    display:block;
    color:var(--text-dark);
    font-size:.85rem;
    font-weight:500;
    margin-bottom:6px;
    transition:color 0.3s ease;
}

/* Inputs */
.input-group{
    position:relative;
    margin-bottom:15px;
    width:100%;
}
.input-group input,
.input-group select,
.input-group textarea{
    width:100%;
    padding:14px 15px;
    border-radius:10px;
    border:1px solid var(--border);
    font-size:.95rem;
    font-family:'Inter',sans-serif;
    transition:all 0.3s ease;
    background:var(--input-bg);
    color:var(--text-dark);
}
.input-group input:focus,
.input-group select:focus,
.input-group textarea:focus{
    outline:none;
    border-color:var(--primary-blue);
    box-shadow:0 0 0 3px rgba(59,130,246,.1);
}
.input-group i.fas{
    position:absolute;
    right:15px;
    top:50%;
    transform:translateY(-50%);
    color:var(--text-muted);
    pointer-events:none;
    transition:color 0.3s ease;
}
.input-group textarea{
    min-height:80px;
    resize:vertical;
}
.input-group select{
    appearance:none;
    cursor:pointer;
    background:var(--input-bg);
}

/* Multi-select Styles */
.multi-select-container{
    position:relative;
    width:100%;
}
.multi-select-trigger{
    width:100%;
    padding:14px 15px;
    border-radius:10px;
    border:1px solid var(--border);
    background:var(--input-bg);
    text-align:left;
    color:var(--text-dark);
    font-size:.95rem;
    cursor:pointer;
    display:flex;
    justify-content:space-between;
    align-items:center;
    font-family:'Inter',sans-serif;
    transition:all 0.3s ease;
}
.multi-select-trigger:hover{
    border-color:var(--primary-blue);
}
.multi-select-trigger .selected-text{
    flex:1;
    overflow:hidden;
    white-space:nowrap;
    text-overflow:ellipsis;
    min-width:0;
}
.multi-select-trigger .placeholder{
    color:var(--text-muted);
}
.multi-select-trigger .selected-count{
    background:var(--primary-blue);
    color:#fff;
    font-size:.75rem;
    padding:2px 8px;
    border-radius:10px;
    margin-left:8px;
    flex-shrink:0;
    min-width:24px;
    text-align:center;
}
.multi-select-dropdown{
    position:absolute;
    top:100%;
    left:0;
    right:0;
    background:var(--dropdown-bg);
    border:1px solid var(--border);
    border-radius:10px;
    padding:10px;
    z-index:1000;
    display:none;
    box-shadow:0 15px 40px rgba(0,0,0,.2);
    max-height:200px;
    overflow-y:auto;
    margin-top:5px;
}
.multi-select-dropdown.show{
    display:block;
    animation:slideDown .3s ease;
}
@keyframes slideDown{
    from{opacity:0;transform:translateY(-10px);}
    to{opacity:1;transform:translateY(0);}
}
.multi-select-option{
    padding:10px;
    border-radius:6px;
    cursor:pointer;
    transition:background .2s ease;
    display:flex;
    align-items:center;
    gap:10px;
    color:var(--text-dark);
}
.multi-select-option:hover{
    background:var(--hover-bg);
}
.multi-select-option.selected{
    background:rgba(59,130,246,.1);
}
.checkbox-custom{
    width:18px;
    height:18px;
    border:2px solid var(--border);
    border-radius:4px;
    display:flex;
    align-items:center;
    justify-content:center;
    flex-shrink:0;
    transition:all 0.2s ease;
}
.multi-select-option.selected .checkbox-custom{
    background:var(--primary-blue);
    border-color:var(--primary-blue);
}
.multi-select-option.selected .checkbox-custom::after{
    content:"✓";
    color:#fff;
    font-size:12px;
    font-weight:bold;
}

/* Password Styles */
.password-field{
    position:relative;
}
.password-field input{
    padding-right:45px;
}
.password-field i.fas.fa-lock{
    right:40px;
}
.password-toggle{
    position:absolute;
    right:15px;
    top:50%;
    transform:translateY(-50%);
    background:none;
    border:none;
    color:var(--text-muted);
    cursor:pointer;
    z-index:2;
    padding:0;
    width:24px;
    height:24px;
    display:flex;
    align-items:center;
    justify-content:center;
    transition:color 0.3s ease;
}
.password-toggle:hover{
    color:var(--primary-blue);
}
.password-strength{
    margin-top:10px;
}
.strength-meter{
    height:5px;
    background:var(--border);
    border-radius:3px;
    overflow:hidden;
    margin-bottom:5px;
}
.strength-bar{
    height:100%;
    width:0%;
    background:var(--error-red);
    border-radius:3px;
    transition:all 0.3s ease;
}
.strength-text{
    font-size:0.8rem;
    color:var(--text-muted);
    min-height:1em;
    font-weight:500;
    transition:color 0.3s ease;
}
.strength-weak{background:var(--error-red);}
.strength-fair{background:var(--warning-yellow);}
.strength-good{background:#3b82f6;}
.strength-strong{background:var(--success-green);}

.password-requirements{
    margin-top:15px;
    padding:12px;
    background:var(--bg-light);
    border-radius:8px;
    font-size:0.85rem;
    border:1px solid var(--border);
}
.requirement{
    display:flex;
    align-items:center;
    margin-bottom:6px;
    font-size:0.85rem;
    transition:color 0.3s ease;
    color:var(--text-muted);
}
.requirement:last-child{
    margin-bottom:0;
}
.requirement i{
    margin-right:8px;
    font-size:0.7rem;
    transition:transform 0.3s ease;
}
.requirement.met{
    color:var(--success-green);
}
.requirement.met i{
    color:var(--success-green);
    transform:scale(1.2);
}
.requirement.unmet{
    color:var(--error-red);
}
.requirement.unmet i{
    color:var(--error-red);
}

.checkbox-label{
    display:flex;
    align-items:center;
    gap:8px;
    margin-top:15px;
    cursor:pointer;
}
.checkbox-label input{
    width:18px;
    height:18px;
    cursor:pointer;
}
.checkbox-label span{
    color:var(--text-dark);
    font-size:0.85rem;
}
.checkbox-label a{
    color:var(--primary-blue);
    text-decoration:none;
}
.checkbox-label a:hover{
    text-decoration:underline;
}

/* Error Messages */
.error-message{
    color:var(--error-red);
    font-size:0.8rem;
    margin-top:5px;
    display:none;
}
.input-group.error input,
.input-group.error select,
.input-group.error textarea,
.multi-select-container.error .multi-select-trigger{
    border-color:var(--error-red);
}
.input-group.error i{
    color:var(--error-red);
}

/* Navigation Buttons */
.form-navigation{
    display:flex;
    justify-content:space-between;
    margin-top:30px;
    gap:15px;
}
.btn-nav{
    padding:12px 24px;
    border-radius:25px;
    font-weight:600;
    cursor:pointer;
    transition:all 0.3s ease;
    border:2px solid transparent;
    display:flex;
    align-items:center;
    gap:8px;
    font-size:0.9rem;
}
.btn-prev{
    background:transparent;
    color:var(--primary-blue);
    border-color:var(--border);
}
.btn-prev:hover{
    background:var(--hover-bg);
    border-color:var(--primary-blue);
}
.btn-next{
    background:var(--primary-blue);
    color:white;
}
.btn-next:hover{
    background:var(--secondary-blue);
    transform:translateY(-1px);
}
.btn-submit{
    background:var(--success-green);
    color:white;
}
.btn-submit:hover{
    background:#0da271;
    transform:translateY(-1px);
}

/* Alerts */
.alert{
    display:none;
    padding:12px;
    border-radius:8px;
    font-size:.85rem;
    margin-bottom:15px;
    animation:fadeIn 0.3s ease;
}
.alert-error{
    background:rgba(220,38,38,.1);
    color:var(--error-red);
    border-left:4px solid var(--error-red);
}
.alert-success{
    background:rgba(16,185,129,.1);
    color:var(--success-green);
    border-left:4px solid var(--success-green);
}
.alert.show{
    display:block;
}

/* Login Section */
.login-section{
    margin-top:auto;
    padding-top:20px;
    border-top:1px solid var(--border);
    text-align:center;
}
.login-text{
    color:var(--text-muted);
    font-size:.9rem;
    margin-bottom:12px;
    transition:color 0.3s ease;
}
.login-btn{
    display:flex;
    justify-content:center;
    align-items:center;
    gap:8px;
    padding:12px;
    border-radius:25px;
    border:1px solid var(--border);
    color:var(--primary-blue);
    font-size:.9rem;
    text-decoration:none;
    transition:all .3s ease;
    width:100%;
    background:transparent;
}
.login-btn:hover{
    border-color:var(--accent-yellow);
    background:rgba(244,196,48,.08);
}
.footer-links{
    margin-top:15px;
    text-align:center;
    color:var(--text-muted);
    font-size:.8rem;
    transition:color 0.3s ease;
}
.footer-links a{
    color:var(--primary-blue);
    text-decoration:none;
    transition:color 0.3s ease;
}
.footer-links a:hover{
    text-decoration:underline;
}

/* Theme Toggle */
.theme-toggle-register{
    position:fixed;
    top:20px;
    right:20px;
    background:var(--card-bg);
    border:1px solid var(--border);
    border-radius:50px;
    padding:10px 16px;
    cursor:pointer;
    display:flex;
    align-items:center;
    gap:8px;
    font-size:0.9rem;
    font-weight:500;
    color:var(--text-dark);
    transition:all 0.3s ease;
    z-index:1000;
    box-shadow:var(--shadow);
}
.theme-toggle-register:hover{
    transform:translateY(-2px);
    border-color:var(--accent-yellow);
}
.theme-toggle-register .fa-sun{
    display:none;
}
body.dark-mode .theme-toggle-register .fa-moon{
    display:none;
}
body.dark-mode .theme-toggle-register .fa-sun{
    display:inline-block;
}

/* Loading Modal */
.loading-modal{
    display:none;
    position:fixed;
    top:0;
    left:0;
    width:100%;
    height:100%;
    background:rgba(11,44,77,.98);
    z-index:9999;
    align-items:center;
    justify-content:center;
    padding:20px;
    overflow-y:auto;
    backdrop-filter:blur(5px);
}
body.dark-mode .loading-modal{
    background:rgba(15,23,42,.98);
}
.loading-modal.show{
    display:flex;
    animation:fadeIn 0.3s ease;
}
.loading-content{
    background:var(--card-bg);
    border-radius:20px;
    padding:40px 30px;
    text-align:center;
    max-width:600px;
    width:100%;
    box-shadow:0 25px 80px rgba(0,0,0,.3);
    max-height:90vh;
    overflow-y:auto;
    margin:auto;
    position:relative;
    border:1px solid var(--border);
}
.loading-spinner{
    width:80px;
    height:80px;
    border:5px solid rgba(229,231,235,.3);
    border-top:5px solid var(--accent-yellow);
    border-radius:50%;
    animation:spin 1s linear infinite;
    margin:0 auto 30px;
}
@keyframes spin{
    0%{transform:rotate(0deg);}
    100%{transform:rotate(360deg);}
}
.loading-text{
    font-size:1.8rem;
    color:var(--primary-blue);
    font-weight:700;
    margin-bottom:30px;
    min-height:2.5rem;
    line-height:1.3;
    transition:color 0.3s ease;
}
.loading-steps{
    margin:25px 0;
    text-align:left;
    background:var(--bg-light);
    border-radius:12px;
    padding:20px;
    border:1px solid var(--border);
}
.loading-step{
    display:flex;
    align-items:center;
    margin-bottom:20px;
    opacity:0.5;
    transition:all 0.3s ease;
    padding:12px 15px;
    border-radius:10px;
    background:var(--card-bg);
    border:1px solid transparent;
}
.loading-step:last-child{
    margin-bottom:0;
}
.loading-step.active{
    opacity:1;
    background:rgba(244,196,48,.1);
    border-color:var(--accent-yellow);
    transform:translateX(5px);
}
.loading-step.completed{
    opacity:1;
    background:rgba(16,185,129,.1);
    border-color:var(--success-green);
}
.loading-step-icon{
    width:40px;
    height:40px;
    border-radius:50%;
    background:var(--border);
    display:flex;
    align-items:center;
    justify-content:center;
    margin-right:20px;
    font-size:1rem;
    font-weight:600;
    flex-shrink:0;
    border:3px solid white;
    box-shadow:0 4px 8px rgba(0,0,0,.1);
    color:var(--text-dark);
}
.loading-step.active .loading-step-icon{
    background:var(--accent-yellow);
    color:var(--primary-blue);
    border-color:white;
}
.loading-step.completed .loading-step-icon{
    background:var(--success-green);
    color:white;
    border-color:white;
}
.loading-step-text{
    font-size:1rem;
    font-weight:500;
    color:var(--text-dark);
    flex:1;
}
.loading-step.completed .loading-step-text{
    color:var(--success-green);
}
.loading-progress{
    margin-top:35px;
    background:var(--bg-light);
    border-radius:12px;
    padding:20px;
    border:1px solid var(--border);
}
.progress-text{
    font-size:1rem;
    color:var(--text-dark);
    margin-bottom:15px;
    display:flex;
    justify-content:space-between;
    font-weight:500;
}
.progress-bar{
    height:10px;
    background:var(--border);
    border-radius:5px;
    overflow:hidden;
    margin-bottom:10px;
}
.progress-fill{
    height:100%;
    background:linear-gradient(90deg,var(--accent-yellow),#ffd700);
    width:0%;
    transition:width 0.5s ease;
    border-radius:5px;
}
.registration-success{
    display:none;
    text-align:center;
    animation:fadeInUp 0.5s ease;
}
@keyframes fadeInUp{
    from{opacity:0;transform:translateY(30px);}
    to{opacity:1;transform:translateY(0);}
}
.registration-success.show{
    display:block;
}
.success-icon{
    width:100px;
    height:100px;
    background:linear-gradient(135deg,var(--success-green),#0da271);
    border-radius:50%;
    display:flex;
    align-items:center;
    justify-content:center;
    margin:0 auto 30px;
    color:white;
    font-size:3rem;
    box-shadow:0 10px 30px rgba(16,185,129,.3);
    animation:pulse 2s infinite;
}
@keyframes pulse{
    0%{transform:scale(1);}
    50%{transform:scale(1.05);}
    100%{transform:scale(1);}
}
.success-title{
    font-size:2.2rem;
    color:var(--success-green);
    font-weight:700;
    margin-bottom:20px;
    line-height:1.2;
}
.success-message{
    color:var(--text-dark);
    font-size:1.1rem;
    line-height:1.7;
    margin-bottom:30px;
}
.success-details{
    background:var(--bg-light);
    border-radius:15px;
    padding:25px;
    margin:30px 0;
    text-align:left;
    border:1px solid var(--border);
}
.success-details h4{
    color:var(--primary-blue);
    margin-bottom:20px;
    font-size:1.3rem;
    font-weight:600;
    display:flex;
    align-items:center;
    gap:10px;
}
.success-details p{
    margin-bottom:12px;
    font-size:1rem;
    display:flex;
    align-items:center;
    gap:10px;
    color:var(--text-dark);
}
.success-details p strong{
    min-width:140px;
    color:var(--primary-blue);
}
.success-actions{
    display:flex;
    gap:20px;
    justify-content:center;
    margin-top:40px;
    flex-wrap:wrap;
}
.btn-success{
    padding:16px 35px;
    border-radius:30px;
    font-weight:600;
    cursor:pointer;
    border:none;
    transition:all 0.3s ease;
    display:flex;
    align-items:center;
    gap:12px;
    font-size:1rem;
    min-width:200px;
    justify-content:center;
    box-shadow:0 6px 20px rgba(0,0,0,.1);
}
.btn-success:hover{
    transform:translateY(-3px);
    box-shadow:0 10px 25px rgba(0,0,0,.15);
}
.btn-login{
    background:linear-gradient(135deg,var(--primary-blue),var(--secondary-blue));
    color:white;
}
.btn-dashboard{
    background:linear-gradient(135deg,var(--success-green),#0da271);
    color:white;
}

/* Mobile Overlay */
.mobile-overlay{
    display:none;
    position:fixed;
    top:0;
    left:0;
    right:0;
    bottom:0;
    background:rgba(0,0,0,.5);
    z-index:1999;
}
.mobile-overlay.show{
    display:block;
    animation:fadeIn .3s ease;
}

/* Responsive */
@media(max-width:1024px){
    .container{max-width:95%;}
    .register-content{padding:40px 25px;}
    h2{font-size:1.7rem;}
}
@media(max-width:900px){
    body{padding:10px;display:block;min-height:100vh;}
    .container{grid-template-columns:1fr;min-height:auto;margin:0 auto;max-width:600px;}
    .img{display:none;}
    .register-content{padding:60px 20px 30px;overflow:visible;}
    .back-btn{top:15px;left:15px;}
    form{max-width:100%;height:auto;}
    .form-row{grid-template-columns:1fr;gap:10px;}
    .avatar{height:70px;margin:0 auto 15px;}
    h2{font-size:1.7rem;margin-bottom:5px;}
    .subtitle{font-size:.9rem;margin-bottom:20px;}
    .multi-select-dropdown{
        position:fixed !important;
        top:50% !important;
        left:50% !important;
        transform:translate(-50%,-50%) !important;
        width:90vw !important;
        max-width:400px !important;
        max-height:60vh !important;
        z-index:2000 !important;
    }
    .theme-toggle-register{top:15px;right:15px;padding:8px 14px;}
}
@media(max-width:480px){
    body{padding:5px;}
    .register-content{padding:50px 15px 20px;}
    .back-btn{top:10px;left:10px;padding:6px 10px;font-size:.85rem;}
    h2{font-size:1.5rem;}
    .subtitle{font-size:.85rem;}
    .input-group input,.input-group select,.input-group textarea,.multi-select-trigger{padding:12px 40px 12px 12px;font-size:.9rem;}
    .input-group i.fas{right:12px;}
    .password-toggle{right:35px;}
    .btn-nav{padding:10px 18px;font-size:.85rem;}
    .login-btn{padding:10px;font-size:.85rem;}
    .footer-links{font-size:.75rem;}
    .avatar{height:60px;}
    .form-steps{margin-bottom:20px;}
    .step-number{width:32px;height:32px;font-size:.9rem;}
    .step-label{font-size:.75rem;}
}
</style>
</head>

<body>

<button class="theme-toggle-register" id="themeToggleRegister">
    <i class="fas fa-moon"></i>
    <i class="fas fa-sun"></i>
    <span>Dark Mode</span>
</button>

<div class="container">
    <div class="img">
        <img src="../images/bg.svg" alt="School Registration">
    </div>

    <div class="register-content">
        <form id="registerForm">
            <a href="../index.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Home
            </a>

            <img src="../images/avatar.svg" class="avatar" alt="Avatar">

            <h2>School Registration</h2>
            <p class="subtitle">Complete all three steps to register your school</p>

            <!-- Progress Steps -->
            <div class="form-steps">
                <div class="form-step active" data-step="1">
                    <div class="step-number">1</div>
                    <div class="step-label">School Details</div>
                </div>
                <div class="form-step" data-step="2">
                    <div class="step-number">2</div>
                    <div class="step-label">Admin Details</div>
                </div>
                <div class="form-step" data-step="3">
                    <div class="step-number">3</div>
                    <div class="step-label">Password Setup</div>
                </div>
            </div>

            <div class="alert alert-error" id="errorAlert">
                <span id="errorMessage"></span>
            </div>
            <div class="alert alert-success" id="successAlert">
                <span id="successMessage"></span>
            </div>

            <div class="form-container">
                <div class="form-sections">
                    <!-- SECTION 1: School Details -->
                    <div class="form-section active" id="section-1">
                        <div class="input-group">
                            <input type="text" id="school_name" name="school_name" required 
                                   placeholder="School Name *">
                            <i class="fas fa-school"></i>
                            <div class="error-message" id="school_name_error"></div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>School Email *</label>
                                <div class="input-group">
                                    <input type="email" id="school_email" name="school_email" required 
                                           placeholder="Email Address">
                                    <i class="fas fa-envelope"></i>
                                    <div class="error-message" id="school_email_error"></div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Phone Number *</label>
                                <div class="input-group">
                                    <input type="tel" id="school_phone" name="school_phone" required 
                                           placeholder="Phone Number">
                                    <i class="fas fa-phone"></i>
                                    <div class="error-message" id="school_phone_error"></div>
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Institution Type *</label>
                                <div class="input-group">
                                    <select id="institution_type" name="institution_type" required>
                                        <option value="">Select Institution Type *</option>
                                        <?php foreach ($institution_type_options as $value => $label): ?>
                                            <option value="<?php echo htmlspecialchars($value); ?>">
                                                <?php echo htmlspecialchars($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <i class="fas fa-school"></i>
                                    <div class="error-message" id="institution_type_error"></div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Institution Level *</label>
                                <div class="multi-select-container">
                                    <input type="hidden" name="institution_level" id="institution_level_input" value="">
                                    <button type="button" class="multi-select-trigger" id="institution_level_trigger">
                                        <span class="selected-text">
                                            <span class="placeholder">Select institution level(s)</span>
                                        </span>
                                        <span class="selected-count" id="institution_level_count">0</span>
                                    </button>
                                    <div class="multi-select-dropdown" id="institution_level_dropdown">
                                        <?php foreach ($institution_level_options as $value => $label): ?>
                                            <div class="multi-select-option" data-value="<?php echo htmlspecialchars($value); ?>">
                                                <div class="checkbox-custom"></div>
                                                <span class="option-text"><?php echo htmlspecialchars($label); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="error-message" id="institution_level_error"></div>
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>County *</label>
                                <div class="input-group">
                                    <select id="county" name="county" required>
                                        <option value="">Select County *</option>
                                        <?php foreach ($county_options as $value => $label): ?>
                                            <option value="<?php echo htmlspecialchars($value); ?>">
                                                <?php echo htmlspecialchars($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <i class="fas fa-map-marker-alt"></i>
                                    <div class="error-message" id="county_error"></div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Students Population *</label>
                                <div class="input-group">
                                    <input type="number" id="total_students" name="total_students" required 
                                           placeholder="Number of students" min="1" max="10000" step="1">
                                    <i class="fas fa-users"></i>
                                    <div class="error-message" id="total_students_error"></div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Product Type *</label>
                            <div class="multi-select-container">
                                <input type="hidden" name="product_type" id="product_type_input" value="">
                                <button type="button" class="multi-select-trigger" id="product_type_trigger">
                                    <span class="selected-text">
                                        <span class="placeholder">Select product type(s)</span>
                                    </span>
                                    <span class="selected-count" id="product_type_count">0</span>
                                </button>
                                <div class="multi-select-dropdown" id="product_type_dropdown">
                                    <?php foreach ($product_type_options as $value => $label): ?>
                                        <div class="multi-select-option" data-value="<?php echo htmlspecialchars($value); ?>">
                                            <div class="checkbox-custom"></div>
                                            <span class="option-text"><?php echo htmlspecialchars($label); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="error-message" id="product_type_error"></div>
                            </div>
                        </div>

                        <div class="input-group">
                            <input type="text" id="school_motto" name="school_motto" required 
                                   placeholder="School Motto *">
                            <i class="fas fa-quote-left"></i>
                            <div class="error-message" id="school_motto_error"></div>
                        </div>

                        <div class="input-group">
                            <textarea id="school_address" name="school_address" placeholder="School Address (Optional)" rows="2"></textarea>
                            <i class="fas fa-map"></i>
                        </div>
                    </div>

                    <!-- SECTION 2: Admin Details -->
                    <div class="form-section" id="section-2">
                        <div class="input-group">
                            <input type="text" id="admin_name" name="admin_name" required 
                                   placeholder="Full Name *">
                            <i class="fas fa-user"></i>
                            <div class="error-message" id="admin_name_error"></div>
                        </div>

                        <div class="input-group">
                            <input type="email" id="admin_email" name="admin_email" required 
                                   placeholder="Email Address *">
                            <i class="fas fa-envelope"></i>
                            <div class="error-message" id="admin_email_error"></div>
                        </div>

                        <div class="input-group">
                            <input type="tel" id="admin_phone" name="admin_phone" 
                                   placeholder="Phone Number (Optional)">
                            <i class="fas fa-phone"></i>
                            <div class="error-message" id="admin_phone_error"></div>
                        </div>
                    </div>

                    <!-- SECTION 3: Password Setup -->
                    <div class="form-section" id="section-3">
                        <div class="input-group password-field">
                            <input type="password" id="password" name="password" required 
                                   placeholder="New Password *">
                            <i class="fas fa-lock"></i>
                            <button type="button" class="password-toggle" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </button>
                            <div class="error-message" id="password_error"></div>
                        </div>

                        <div class="password-strength">
                            <div class="strength-meter">
                                <div class="strength-bar" id="strengthBar"></div>
                            </div>
                            <div class="strength-text" id="strengthText">Enter a password</div>
                        </div>

                        <div class="password-requirements">
                            <div class="requirement" id="reqLength">
                                <i class="fas fa-circle"></i>
                                <span>8-15 characters</span>
                            </div>
                            <div class="requirement" id="reqUppercase">
                                <i class="fas fa-circle"></i>
                                <span>At least one uppercase letter</span>
                            </div>
                            <div class="requirement" id="reqLowercase">
                                <i class="fas fa-circle"></i>
                                <span>At least one lowercase letter</span>
                            </div>
                            <div class="requirement" id="reqNumber">
                                <i class="fas fa-circle"></i>
                                <span>At least one number</span>
                            </div>
                            <div class="requirement" id="reqSpecial">
                                <i class="fas fa-circle"></i>
                                <span>At least one special character</span>
                            </div>
                        </div>

                        <div class="input-group password-field">
                            <input type="password" id="confirm_password" name="confirm_password" required 
                                   placeholder="Confirm Password *">
                            <i class="fas fa-lock"></i>
                            <button type="button" class="password-toggle" id="toggleConfirmPassword">
                                <i class="fas fa-eye"></i>
                            </button>
                            <div class="error-message" id="confirm_password_error"></div>
                        </div>

                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" id="terms" name="terms" required>
                                <span>I agree to the <a href="../terms.php" target="_blank">Terms of Service</a> and <a href="../privacy.php" target="_blank">Privacy Policy</a> *</span>
                            </label>
                            <div class="error-message" id="terms_error"></div>
                        </div>
                    </div>
                </div>

                <!-- Navigation Buttons -->
                <div class="form-navigation">
                    <button type="button" class="btn-nav btn-prev" id="prevBtn" style="display: none;">
                        <i class="fas fa-arrow-left"></i> Previous
                    </button>
                    <button type="button" class="btn-nav btn-next" id="nextBtn">
                        Next <i class="fas fa-arrow-right"></i>
                    </button>
                    <button type="submit" class="btn-nav btn-submit" id="submitBtn" style="display: none;">
                        <i class="fas fa-check-circle"></i> Complete Registration
                    </button>
                </div>
            </div>

            <!-- Login Section -->
            <div class="login-section">
                <div class="login-text">
                    <p>Already have an account? Sign in to EduScore!</p>
                </div>
                <a href="login.php" class="login-btn">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Sign In Now</span>
                </a>
            </div>

            <!-- Footer Links -->
            <div class="footer-links">
                <p>
                    By registering, you agree to our 
                    <a href="../terms.php">Terms</a> and 
                    <a href="../privacy.php">Privacy Policy</a>
                </p>
                <p style="margin-top:5px">
                    Need help? <a href="../contact.php">Contact Support</a>
                </p>
            </div>
        </form>
    </div>
</div>

<!-- Loading Modal -->
<div class="loading-modal" id="loadingModal">
    <div class="loading-content">
        <div class="loading-spinner" id="loadingSpinner"></div>
        <div class="loading-text" id="loadingText">Setting up your school...</div>
        <div class="loading-steps">
            <div class="loading-step" id="step1">
                <div class="loading-step-icon">1</div>
                <div class="loading-step-text">Creating school profile</div>
            </div>
            <div class="loading-step" id="step2">
                <div class="loading-step-icon">2</div>
                <div class="loading-step-text">Setting up admin account</div>
            </div>
            <div class="loading-step" id="step3">
                <div class="loading-step-icon">3</div>
                <div class="loading-step-text">Creating database structure</div>
            </div>
            <div class="loading-step" id="step4">
                <div class="loading-step-icon">4</div>
                <div class="loading-step-text">Preparing dashboard</div>
            </div>
            <div class="loading-step" id="step5">
                <div class="loading-step-icon">5</div>
                <div class="loading-step-text">Finalizing registration</div>
            </div>
        </div>
        <div class="loading-progress">
            <div class="progress-text">
                <span>Progress</span>
                <span id="progressPercent">0%</span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" id="progressFill"></div>
            </div>
        </div>
        <div class="registration-success" id="registrationSuccess">
            <div class="success-icon">
                <i class="fas fa-check"></i>
            </div>
            <h3 class="success-title">Registration Successful!</h3>
            <div class="success-message" id="successMessageContent">
                Your school has been successfully registered. You can now access your dashboard.
            </div>
            <div class="success-details" id="successDetails"></div>
            <div class="success-actions">
                <button class="btn-success btn-login" id="btnLogin">
                    <i class="fas fa-sign-in-alt"></i> Go to Login
                </button>
                <button class="btn-success btn-dashboard" id="btnDashboard">
                    <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Mobile Overlay -->
<div class="mobile-overlay" id="mobileOverlay"></div>

<script>
// Theme Toggle functionality
const themeToggleBtn = document.getElementById('themeToggleRegister');
const body = document.body;
const themeText = themeToggleBtn.querySelector('span');

const savedTheme = localStorage.getItem('registerTheme');
if (savedTheme === 'dark') {
    body.classList.add('dark-mode');
    updateThemeButton(true);
} else if (savedTheme === 'light') {
    body.classList.remove('dark-mode');
    updateThemeButton(false);
} else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
    body.classList.add('dark-mode');
    updateThemeButton(true);
}

function updateThemeButton(isDark) {
    themeText.textContent = isDark ? 'Light Mode' : 'Dark Mode';
}

themeToggleBtn.addEventListener('click', () => {
    body.classList.toggle('dark-mode');
    const isDark = body.classList.contains('dark-mode');
    localStorage.setItem('registerTheme', isDark ? 'dark' : 'light');
    updateThemeButton(isDark);
});

document.addEventListener('DOMContentLoaded', function() {
    // Multi-step form logic
    const form = document.getElementById('registerForm');
    const sections = document.querySelectorAll('.form-section');
    const steps = document.querySelectorAll('.form-step');
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const submitBtn = document.getElementById('submitBtn');
    const errorAlert = document.getElementById('errorAlert');
    const successAlert = document.getElementById('successAlert');
    const errorMessage = document.getElementById('errorMessage');
    const successMessage = document.getElementById('successMessage');
    
    let currentSection = 1;
    const totalSections = 3;

    // Multi-select class
    class MultiSelect {
        constructor(triggerId, dropdownId, inputId, countId) {
            this.trigger = document.getElementById(triggerId);
            this.dropdown = document.getElementById(dropdownId);
            this.input = document.getElementById(inputId);
            this.count = document.getElementById(countId);
            
            if (!this.trigger || !this.dropdown || !this.input || !this.count) return;
            
            this.options = this.dropdown.querySelectorAll('.multi-select-option');
            this.selectedValues = this.input.value ? this.input.value.split(',').filter(v => v) : [];
            
            this.init();
        }
        
        init() {
            this.updateUI();
            
            this.trigger.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.toggleDropdown();
            });
            
            this.options.forEach(option => {
                option.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this.toggleOption(option);
                });
            });
        }
        
        toggleDropdown() {
            const isOpen = this.dropdown.classList.contains('show');
            document.querySelectorAll('.multi-select-dropdown.show').forEach(dropdown => {
                if (dropdown !== this.dropdown) dropdown.classList.remove('show');
            });
            
            if (isOpen) {
                this.closeDropdown();
            } else {
                this.openDropdown();
            }
        }
        
        openDropdown() {
            this.dropdown.classList.add('show');
            if (window.innerWidth <= 900) {
                const overlay = document.getElementById('mobileOverlay');
                overlay.classList.add('show');
                overlay.onclick = () => {
                    this.closeDropdown();
                    overlay.classList.remove('show');
                };
            }
        }
        
        closeDropdown() {
            this.dropdown.classList.remove('show');
            const overlay = document.getElementById('mobileOverlay');
            if (overlay) overlay.classList.remove('show');
        }
        
        toggleOption(option) {
            const value = option.dataset.value;
            const index = this.selectedValues.indexOf(value);
            
            if (index > -1) {
                this.selectedValues.splice(index, 1);
                option.classList.remove('selected');
            } else {
                this.selectedValues.push(value);
                option.classList.add('selected');
            }
            
            this.updateUI();
        }
        
        updateUI() {
            this.input.value = this.selectedValues.join(',');
            this.count.textContent = this.selectedValues.length;
            
            const selectedText = this.selectedValues.length > 0 
                ? this.getDisplayText() 
                : '<span class="placeholder">Select options</span>';
            
            this.trigger.querySelector('.selected-text').innerHTML = selectedText;
            
            this.options.forEach(option => {
                const value = option.dataset.value;
                option.classList.toggle('selected', this.selectedValues.includes(value));
            });
        }
        
        getDisplayText() {
            const labels = [];
            this.options.forEach(option => {
                if (this.selectedValues.includes(option.dataset.value)) {
                    labels.push(option.querySelector('.option-text').textContent);
                }
            });
            
            if (labels.length <= 2) {
                return labels.join(', ');
            } else {
                return labels.slice(0, 2).join(', ') + ' +' + (labels.length - 2);
            }
        }
    }

    // Initialize multi-selects
    new MultiSelect('institution_level_trigger', 'institution_level_dropdown', 'institution_level_input', 'institution_level_count');
    new MultiSelect('product_type_trigger', 'product_type_dropdown', 'product_type_input', 'product_type_count');

    // Navigation functions
    function showSection(sectionNumber) {
        sections.forEach(section => section.classList.remove('active'));
        document.getElementById(`section-${sectionNumber}`).classList.add('active');
        
        steps.forEach(step => {
            const stepNum = parseInt(step.dataset.step);
            step.classList.remove('active', 'completed');
            if (stepNum === sectionNumber) {
                step.classList.add('active');
            } else if (stepNum < sectionNumber) {
                step.classList.add('completed');
            }
        });
        
        prevBtn.style.display = sectionNumber === 1 ? 'none' : 'flex';
        nextBtn.style.display = sectionNumber === totalSections ? 'none' : 'flex';
        submitBtn.style.display = sectionNumber === totalSections ? 'flex' : 'none';
        currentSection = sectionNumber;
    }

    // Password strength functions
    function calculatePasswordStrength(password) {
        let score = 0;
        if (password.length >= 8 && password.length <= 15) score += 20;
        if (/[A-Z]/.test(password)) score += 20;
        if (/[a-z]/.test(password)) score += 20;
        if (/[0-9]/.test(password)) score += 20;
        if (/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)) score += 20;
        return score;
    }

    function updatePasswordStrength(password) {
        const strengthBar = document.getElementById('strengthBar');
        const strengthText = document.getElementById('strengthText');
        
        if (!password || password.length === 0) {
            strengthBar.style.width = '0%';
            strengthBar.className = 'strength-bar';
            strengthText.textContent = 'Enter a password';
            strengthText.style.color = 'var(--text-muted)';
            return 0;
        }
        
        const score = calculatePasswordStrength(password);
        const percentage = Math.min(100, score);
        strengthBar.style.width = percentage + '%';
        
        if (score <= 40) {
            strengthBar.className = 'strength-bar strength-weak';
            strengthText.textContent = 'Weak password';
            strengthText.style.color = 'var(--error-red)';
        } else if (score <= 60) {
            strengthBar.className = 'strength-bar strength-fair';
            strengthText.textContent = 'Fair password';
            strengthText.style.color = 'var(--warning-yellow)';
        } else if (score <= 80) {
            strengthBar.className = 'strength-bar strength-good';
            strengthText.textContent = 'Good password';
            strengthText.style.color = '#3b82f6';
        } else {
            strengthBar.className = 'strength-bar strength-strong';
            strengthText.textContent = 'Strong password';
            strengthText.style.color = 'var(--success-green)';
        }
        return score;
    }

    function updatePasswordRequirements(password) {
        const requirements = {
            length: document.getElementById('reqLength'),
            uppercase: document.getElementById('reqUppercase'),
            lowercase: document.getElementById('reqLowercase'),
            number: document.getElementById('reqNumber'),
            special: document.getElementById('reqSpecial')
        };
        
        Object.values(requirements).forEach(req => req.classList.remove('met', 'unmet'));
        
        if (!password || password.length === 0) return;
        
        if (password.length >= 8 && password.length <= 15) requirements.length.classList.add('met');
        else requirements.length.classList.add('unmet');
        
        if (/[A-Z]/.test(password)) requirements.uppercase.classList.add('met');
        else requirements.uppercase.classList.add('unmet');
        
        if (/[a-z]/.test(password)) requirements.lowercase.classList.add('met');
        else requirements.lowercase.classList.add('unmet');
        
        if (/[0-9]/.test(password)) requirements.number.classList.add('met');
        else requirements.number.classList.add('unmet');
        
        if (/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)) requirements.special.classList.add('met');
        else requirements.special.classList.add('unmet');
    }

    // Password visibility toggles
    document.getElementById('togglePassword')?.addEventListener('click', function() {
        const input = document.getElementById('password');
        const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
        input.setAttribute('type', type);
        const icon = this.querySelector('i');
        icon.classList.toggle('fa-eye');
        icon.classList.toggle('fa-eye-slash');
    });

    document.getElementById('toggleConfirmPassword')?.addEventListener('click', function() {
        const input = document.getElementById('confirm_password');
        const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
        input.setAttribute('type', type);
        const icon = this.querySelector('i');
        icon.classList.toggle('fa-eye');
        icon.classList.toggle('fa-eye-slash');
    });

    // Real-time password validation
    const passwordInput = document.getElementById('password');
    const confirmInput = document.getElementById('confirm_password');
    
    passwordInput?.addEventListener('input', function() {
        updatePasswordStrength(this.value);
        updatePasswordRequirements(this.value);
        if (confirmInput.value && this.value !== confirmInput.value) {
            document.getElementById('confirm_password_error').textContent = 'Passwords do not match';
            document.getElementById('confirm_password_error').style.display = 'block';
        } else {
            document.getElementById('confirm_password_error').style.display = 'none';
        }
    });
    
    confirmInput?.addEventListener('input', function() {
        if (passwordInput.value !== this.value) {
            document.getElementById('confirm_password_error').textContent = 'Passwords do not match';
            document.getElementById('confirm_password_error').style.display = 'block';
        } else {
            document.getElementById('confirm_password_error').style.display = 'none';
        }
    });

    // Error handling functions
    function showError(fieldId, message) {
        const errorElement = document.getElementById(`${fieldId}_error`);
        if (errorElement) {
            errorElement.textContent = message;
            errorElement.style.display = 'block';
        }
    }

    function hideError(fieldId) {
        const errorElement = document.getElementById(`${fieldId}_error`);
        if (errorElement) errorElement.style.display = 'none';
    }

    function hideAllErrors() {
        document.querySelectorAll('.error-message').forEach(error => error.style.display = 'none');
    }

    function showAlert(type, message) {
        if (type === 'error') {
            errorMessage.textContent = message;
            errorAlert.style.display = 'block';
            errorAlert.classList.add('show');
            setTimeout(() => errorAlert.style.display = 'none', 5000);
        } else {
            successMessage.textContent = message;
            successAlert.style.display = 'block';
            successAlert.classList.add('show');
            setTimeout(() => successAlert.style.display = 'none', 5000);
        }
    }

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    function isValidPhone(phone) {
        return /^[\d\s\-\+\(\)]+$/.test(phone) && phone.replace(/\D/g, '').length >= 8;
    }

    // Validate current section
    function validateCurrentSection() {
        let isValid = true;
        hideAllErrors();

        if (currentSection === 1) {
            const schoolName = document.getElementById('school_name').value.trim();
            const schoolEmail = document.getElementById('school_email').value.trim();
            const schoolPhone = document.getElementById('school_phone').value.trim();
            const institutionType = document.getElementById('institution_type').value;
            const county = document.getElementById('county').value;
            const totalStudents = document.getElementById('total_students').value.trim();
            const schoolMotto = document.getElementById('school_motto').value.trim();
            const institutionLevelValue = document.getElementById('institution_level_input').value;
            const productTypeValue = document.getElementById('product_type_input').value;

            if (!schoolName) { showError('school_name', 'School name is required'); isValid = false; }
            if (!schoolEmail) { showError('school_email', 'School email is required'); isValid = false; }
            else if (!isValidEmail(schoolEmail)) { showError('school_email', 'Please enter a valid email address'); isValid = false; }
            if (!schoolPhone) { showError('school_phone', 'Phone number is required'); isValid = false; }
            else if (!isValidPhone(schoolPhone)) { showError('school_phone', 'Please enter a valid phone number'); isValid = false; }
            if (!institutionType) { showError('institution_type', 'Please select an institution type'); isValid = false; }
            if (!institutionLevelValue) { showError('institution_level', 'Please select at least one institution level'); isValid = false; }
            if (!county) { showError('county', 'Please select a county'); isValid = false; }
            if (!totalStudents) { showError('total_students', 'Students population is required'); isValid = false; }
            else { const num = parseInt(totalStudents); if (isNaN(num) || num < 1 || num > 10000) { showError('total_students', 'Students population must be between 1 and 10000'); isValid = false; } }
            if (!productTypeValue) { showError('product_type', 'Please select at least one product type'); isValid = false; }
            if (!schoolMotto) { showError('school_motto', 'School motto is required'); isValid = false; }

        } else if (currentSection === 2) {
            const adminName = document.getElementById('admin_name').value.trim();
            const adminEmail = document.getElementById('admin_email').value.trim();
            
            if (!adminName) { showError('admin_name', 'Full name is required'); isValid = false; }
            if (!adminEmail) { showError('admin_email', 'Email is required'); isValid = false; }
            else if (!isValidEmail(adminEmail)) { showError('admin_email', 'Please enter a valid email address'); isValid = false; }

        } else if (currentSection === 3) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const terms = document.getElementById('terms').checked;
            
            const hasLength = password.length >= 8 && password.length <= 15;
            const hasUppercase = /[A-Z]/.test(password);
            const hasLowercase = /[a-z]/.test(password);
            const hasNumber = /[0-9]/.test(password);
            const hasSpecial = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password);
            const allRequirementsMet = hasLength && hasUppercase && hasLowercase && hasNumber && hasSpecial;

            if (!password) { showError('password', 'Password is required'); isValid = false; }
            else if (!allRequirementsMet) { showError('password', 'Password does not meet all requirements'); isValid = false; }
            if (!confirmPassword) { showError('confirm_password', 'Please confirm your password'); isValid = false; }
            else if (password !== confirmPassword) { showError('confirm_password', 'Passwords do not match'); isValid = false; }
            if (!terms) { showError('terms', 'You must agree to the terms and conditions'); isValid = false; }
        }
        return isValid;
    }

    // Loading modal functions
    function showLoadingModal() {
        document.getElementById('loadingModal').classList.add('show');
        document.body.style.overflow = 'hidden';
        document.getElementById('loadingSpinner').style.display = 'block';
        document.getElementById('registrationSuccess').classList.remove('show');
        document.getElementById('progressFill').style.width = '0%';
        document.getElementById('progressPercent').textContent = '0%';
        for (let i = 1; i <= 5; i++) {
            const step = document.getElementById('step' + i);
            step.classList.remove('completed', 'active');
        }
    }

    function hideLoadingModal() {
        document.getElementById('loadingModal').classList.remove('show');
        document.body.style.overflow = 'auto';
    }

    function updateLoadingText(text) {
        document.getElementById('loadingText').textContent = text;
    }

    function updateProgress(percent) {
        document.getElementById('progressFill').style.width = percent + '%';
        document.getElementById('progressPercent').textContent = Math.round(percent) + '%';
    }

    function completeLoadingStep(stepNumber) {
        const step = document.getElementById('step' + stepNumber);
        step.classList.add('completed');
        step.classList.remove('active');
        updateProgress((stepNumber / 5) * 100);
    }

    function simulateLoadingSteps() {
        const steps = [1, 2, 3, 4, 5];
        const messages = [
            "Setting up your school...",
            "Creating admin account...",
            "Preparing database...",
            "Configuring dashboard...",
            "Finalizing registration..."
        ];
        
        steps.forEach((step, index) => {
            setTimeout(() => {
                document.getElementById('step' + step).classList.add('active');
                updateLoadingText(messages[index]);
                setTimeout(() => completeLoadingStep(step), 500);
            }, index * 600);
        });
    }

    function showRegistrationSuccess(data) {
        document.getElementById('loadingSpinner').style.display = 'none';
        document.getElementById('registrationSuccess').classList.add('show');
        
        const detailsSection = document.getElementById('successDetails');
        if (data.school_code || data.license_tier) {
            let html = '<h4>Registration Details:</h4>';
            if (data.school_code) html += `<p><strong>School Code:</strong> ${data.school_code}</p>`;
            if (data.license_tier) html += `<p><strong>License Tier:</strong> ${data.license_tier}</p>`;
            if (data.trial_expires) html += `<p><strong>Trial Expires:</strong> ${data.trial_expires}</p>`;
            html += '<p>Please check your email for activation instructions.</p>';
            detailsSection.innerHTML = html;
        } else {
            detailsSection.innerHTML = '<p>Your registration was successful. You will receive an email with further instructions shortly.</p>';
        }
        
        document.getElementById('btnLogin').onclick = () => window.location.href = 'login.php';
        document.getElementById('btnDashboard').onclick = () => window.location.href = 'dashboard.php';
    }

    // Form submission
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        let allValid = true;
        for (let i = 1; i <= totalSections; i++) {
            currentSection = i;
            showSection(i);
            await new Promise(resolve => setTimeout(resolve, 50));
            if (!validateCurrentSection()) {
                allValid = false;
                break;
            }
        }
        
        if (!allValid) {
            showAlert('error', 'Please fix all errors before submitting');
            return;
        }
        
        showLoadingModal();
        
        const formData = {
            school_name: document.getElementById('school_name').value.trim(),
            school_email: document.getElementById('school_email').value.trim(),
            school_phone: document.getElementById('school_phone').value.trim(),
            institution_type: document.getElementById('institution_type').value,
            institution_level: document.getElementById('institution_level_input').value || '',
            county: document.getElementById('county').value,
            total_students: parseInt(document.getElementById('total_students').value),
            product_type: document.getElementById('product_type_input').value || '',
            school_motto: document.getElementById('school_motto').value.trim(),
            school_address: document.getElementById('school_address').value.trim(),
            admin_name: document.getElementById('admin_name').value.trim(),
            admin_email: document.getElementById('admin_email').value.trim(),
            admin_phone: document.getElementById('admin_phone').value.trim() || '',
            password: document.getElementById('password').value,
            confirm_password: document.getElementById('confirm_password').value,
            terms: document.getElementById('terms').checked ? 'on' : ''
        };
        
        simulateLoadingSteps();
        
        try {
            const response = await fetch('../api/feesystem_register.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(formData)
            });
            
            const data = await response.json();
            completeLoadingStep(5);
            
            setTimeout(() => {
                if (!response.ok) {
                    hideLoadingModal();
                    if (data.errors) {
                        Object.keys(data.errors).forEach(fieldId => showError(fieldId, data.errors[fieldId]));
                        showAlert('error', 'Please fix the errors above');
                    } else {
                        showAlert('error', data.message || 'Registration failed');
                    }
                } else if (data.success) {
                    showRegistrationSuccess(data);
                } else {
                    hideLoadingModal();
                    showAlert('error', data.message || 'Registration failed. Please try again.');
                }
            }, 1000);
        } catch (error) {
            console.error('Registration error:', error);
            hideLoadingModal();
            showAlert('error', 'Network error. Please check your connection and try again.');
        }
    });

    // Navigation event listeners
    nextBtn.addEventListener('click', function() {
        if (validateCurrentSection()) {
            showSection(currentSection + 1);
            hideAllErrors();
        }
    });

    prevBtn.addEventListener('click', function() {
        showSection(currentSection - 1);
        hideAllErrors();
    });

    // Auto-fill admin email from school email
    document.getElementById('school_email').addEventListener('blur', function() {
        const schoolEmail = this.value.trim();
        const adminEmailField = document.getElementById('admin_email');
        if (schoolEmail && !adminEmailField.value.trim()) {
            adminEmailField.value = schoolEmail;
        }
    });

    // Terms checkbox validation
    document.getElementById('terms').addEventListener('change', function() {
        hideError('terms');
    });

    // Close dropdowns on outside click
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.multi-select-container')) {
            document.querySelectorAll('.multi-select-dropdown.show').forEach(dropdown => {
                dropdown.classList.remove('show');
            });
            document.getElementById('mobileOverlay').classList.remove('show');
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            document.querySelectorAll('.multi-select-dropdown.show').forEach(dropdown => {
                dropdown.classList.remove('show');
            });
            document.getElementById('mobileOverlay').classList.remove('show');
        }
    });

    // Initialize
    showSection(1);
});
</script>
</body>
</html>