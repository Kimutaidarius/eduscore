<?php
session_start();
require_once 'includes/config.php';

/* ===============================
   ACCESS CONTROL
================================ */
if (
    !isset($_SESSION['activation_only']) ||
    !isset($_SESSION['locked']) ||
    !isset($_SESSION['school_id'])
) {
    header('Location: login.php');
    exit;
}

$school_id = (int)$_SESSION['school_id'];

/* ===============================
   ACTIVATION CODE VERIFICATION
================================ */
$verification_success = false;
$verification_error = false;
$verification_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_activation_code'])) {
    $code = trim($_POST['activation_code'] ?? '');
    
    if (strlen($code) != 6 || !preg_match('/^[A-Z0-9]+$/i', $code)) {
        $verification_error = true;
        $verification_message = 'Invalid code format. Enter a 6-character code (letters and numbers only).';
    } else {
        $code_upper = strtoupper($code);
        
        $stmt = $db->prepare("
            SELECT id, expires_at, is_used, activation_code
            FROM tbl_activation_codes
            WHERE school_id = :sid AND UPPER(activation_code) = :code LIMIT 1
        ");
        $stmt->execute([':sid' => $school_id, ':code' => $code_upper]);
        $activation = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$activation) {
            $verification_error = true;
            $verification_message = 'Activation code not found. Please check your code or request a new one.';
        } elseif ((int)$activation['is_used']) {
            $verification_error = true;
            $verification_message = 'This code has already been used.';
        } elseif (strtotime($activation['expires_at']) < time()) {
            $verification_error = true;
            $verification_message = 'This code has expired. Please request a new one.';
        } else {
            $db->prepare("UPDATE tbl_activation_codes SET is_used = 1, used_at = NOW() WHERE id = :id")->execute([':id' => $activation['id']]);
            $db->prepare("UPDATE tblschoolinfo SET is_activated = 1, status = 'approved', activation_status = 'activated', activated_at = NOW() WHERE id = :sid")->execute([':sid' => $school_id]);

            $verification_success = true;
            $verification_message = 'Activation successful! Your school account is now active.';
            
            session_regenerate_id(true);
            unset($_SESSION['activation_only'], $_SESSION['locked']);
            $_SESSION['authenticated'] = true;
            $_SESSION['is_logged_in']  = true;
            $_SESSION['school_id']     = $school_id;
            $_SESSION['login_time']    = time();
            
            $is_activated = true;
        }
    }
}

/* ===============================
   FETCH SCHOOL INFO
================================ */
$stmt = $db->prepare("SELECT * FROM tblschoolinfo WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $school_id]);
$school = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$school) {
    die('Invalid school record');
}

$is_activated = (bool)($school['is_activated'] ?? false);

/* ===============================
   ONBOARDING FEE
================================ */
$onboarding_fee = 2000;
$totalCost = $onboarding_fee;

/* ===============================
   ACTIVATION REQUEST HANDLER
================================ */
$cooldownSeconds = 0;
$request_error = $request_success = false;
$error_message = $success_message = '';

$stmt = $db->prepare("SELECT requested_at FROM tbl_activation_requests WHERE school_id = :sid ORDER BY requested_at DESC LIMIT 1");
$stmt->execute([':sid' => $school_id]);
$lastReq = $stmt->fetch(PDO::FETCH_ASSOC);

if ($lastReq) {
    $cooldownSeconds = max(0, strtotime($lastReq['requested_at']) + 86400 - time());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_activation_code'])) {
    if ($is_activated) {
        $request_error = true;
        $error_message = 'Account already activated.';
    } elseif ($cooldownSeconds > 0) {
        $request_error = true;
        $error_message = 'Please wait before requesting another code.';
    } else {
        $db->prepare("UPDATE tbl_activation_codes SET is_used = 1 WHERE school_id = :sid AND is_used = 0")->execute([':sid' => $school_id]);

        $code = generateAlphanumericCode(6);
        $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $db->prepare("INSERT INTO tbl_activation_codes (school_id, activation_code, expires_at) VALUES (:sid, :code, :exp)")->execute([
            ':sid' => $school_id, ':code' => $code, ':exp' => $expires
        ]);

        $db->prepare("INSERT INTO tbl_activation_requests (school_id, ip_address) VALUES (:sid, :ip)")->execute([
            ':sid' => $school_id, ':ip' => $_SERVER['REMOTE_ADDR'] ?? null
        ]);

        $request_success = true;
        $success_message = 'Activation request submitted successfully. A 6-character code will be sent to your school email.';
    }
}

function generateAlphanumericCode($length = 6) {
    $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[random_int(0, strlen($characters) - 1)];
    }
    return $randomString;
}

$whatsappNumber = '254799115282';
$whatsappMessage = urlencode("Hello Eduscore Support 👋\n\nI have requested an activation code and would like assistance with activation.\n\n🏫 School: {$school['school_name']}\n📧 Email: {$school['school_email']}\n📞 Phone: {$school['school_phone']}\n\nPlease assist with activating my account.\n\nThank you.");
$whatsappLink = "https://wa.me/{$whatsappNumber}?text={$whatsappMessage}";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Activate School | EduScore</title>
    <link rel="shortcut icon" href="/images/logo.png" type="image/svg+xml">
    <link rel="icon" type="image/png" sizes="32x32" href="/images/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;500;600;700;800&family=Poppins:wght@400;500&display=swap" rel="stylesheet">
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
            --platinum: hsl(0, 0%, 90%);
            --gray-web: hsl(0, 0%, 50%);
            --black_80: hsla(0, 0%, 0%, 0.8);
            --white: hsl(0, 0%, 100%);
            --white_50: hsla(0, 0%, 100%, 0.5);
            --shadow-1: 0 6px 15px 0 hsla(0, 0%, 0%, 0.05);
            --shadow-2: 0 10px 30px hsla(0, 0%, 0%, 0.06);
            --shadow-3: 0 10px 50px 0 hsla(220, 53%, 22%, 0.1);
            --radius-5: 5px;
            --radius-10: 10px;
            --radius-circle: 50%;
            --transition-1: 0.25s ease;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--white);
            color: var(--gray-web);
            font-size: 1.6rem;
            line-height: 1.75;
        }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 15px; }
        
        /* Header */
        .header {
            position: sticky;
            top: 0;
            background-color: var(--white);
            padding-block: 12px;
            box-shadow: var(--shadow-1);
            z-index: 100;
        }
        .header .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logo img { height: 40px; width: auto; }
        .back-btn {
            background: var(--kappel);
            color: white;
            padding: 10px 20px;
            border-radius: var(--radius-5);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition-1);
        }
        .back-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,191,255,0.2); }
        
        /* Main Content */
        .activation-container {
            max-width: 1100px;
            margin: 40px auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        .card {
            background: var(--white);
            border: 1px solid var(--platinum);
            border-radius: 16px;
            padding: 30px;
            box-shadow: var(--shadow-1);
        }
        .card h2 { color: var(--eerie-black-1); margin-bottom: 10px; font-size: 2rem; }
        .card p { color: var(--gray-web); margin-bottom: 20px; }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        .info-box {
            background: var(--isabelline);
            border-left: 4px solid var(--selective-yellow);
            padding: 12px;
            border-radius: 8px;
        }
        .info-box span { font-size: 12px; color: var(--gray-web); display: block; }
        .info-box strong { font-size: 14px; color: var(--eerie-black-1); display: block; margin-top: 4px; }
        
        /* Activation Status */
        .activation-status {
            background: linear-gradient(135deg, var(--isabelline) 0%, #fff 100%);
            border: 2px solid var(--platinum);
            border-radius: 16px;
            padding: 25px;
            margin-top: 20px;
            position: relative;
        }
        .activation-status::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--kappel) 0%, var(--selective-yellow) 100%);
            border-radius: 16px 16px 0 0;
        }
        
        /* Code Input */
        .code-input-container {
            display: flex;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
            margin: 20px 0;
        }
        .code-digit {
            width: 55px;
            height: 65px;
            text-align: center;
            font-size: 28px;
            font-weight: 700;
            color: var(--kappel);
            background: var(--white);
            border: 2px solid var(--platinum);
            border-radius: 12px;
            outline: none;
            transition: all 0.3s ease;
            text-transform: uppercase;
        }
        .code-digit:focus {
            border-color: var(--kappel);
            box-shadow: 0 0 0 3px var(--kappel_15);
            transform: translateY(-3px);
        }
        .code-digit.filled { border-color: #10b981; background: #f0fdf4; }
        .code-digit.error { border-color: #ef4444; background: #fef2f2; }
        
        .btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
        }
        .btn-primary { background: var(--kappel); color: white; }
        .btn-primary:hover { background: hsl(170, 75%, 35%); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,191,255,0.2); }
        .btn-success { background: #10b981; color: white; }
        .btn-success:hover { background: #0da271; transform: translateY(-2px); }
        .btn-secondary { background: var(--isabelline); color: var(--kappel); border: 2px solid var(--kappel); margin-top: 10px; }
        .btn-secondary:hover { background: var(--kappel_15); transform: translateY(-2px); }
        .btn-warning { background: var(--selective-yellow); color: var(--eerie-black-1); }
        .btn-warning:hover { background: #e6b800; transform: translateY(-2px); }
        .btn-disabled { background: #e5e7eb; color: #94a3b8; cursor: not-allowed; transform: none !important; }
        
        .fee-notice {
            background: linear-gradient(135deg, var(--kappel_15) 0%, #e0f2fe 100%);
            border: 2px solid var(--kappel);
            border-radius: 12px;
            padding: 15px 20px;
            margin: 15px 0 25px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .fee-notice i { font-size: 24px; color: var(--kappel); background: rgba(0,191,255,0.1); padding: 10px; border-radius: 50%; }
        .fee-notice h4 { color: var(--kappel); margin: 0 0 5px; font-size: 16px; }
        .fee-notice p { color: var(--gray-web); margin: 0; font-size: 14px; }
        
        .total { font-size: 30px; color: var(--kappel); margin-bottom: 15px; font-weight: 700; }
        
        .success-message, .error-message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .success-message { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        .error-message { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }
        
        .activated-badge {
            background: linear-gradient(135deg, #10b981 0%, #0da271 100%);
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
        }
        .activation-icon { font-size: 50px; color: #10b981; margin-bottom: 15px; text-align: center; }
        
        /* Modal */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        .modal {
            background: var(--white);
            width: 100%;
            max-width: 500px;
            border-radius: 16px;
            padding: 25px;
            margin: 20px;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .modal-header h3 { color: var(--kappel); }
        .modal-header button { background: none; border: none; font-size: 24px; cursor: pointer; color: var(--gray-web); }
        .modal-note {
            background: var(--isabelline);
            border-left: 4px solid var(--selective-yellow);
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-size: 14px;
        }
        
        /* Chat Widget */
        .chat-widget {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1000;
        }
        .chat-toggle {
            width: 60px;
            height: 60px;
            background: #00BFFF;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(0,191,255,0.3);
            transition: all 0.3s ease;
            border: none;
            color: white;
            font-size: 1.8rem;
        }
        .chat-toggle:hover { transform: scale(1.05); }
        .chat-container {
            position: absolute;
            bottom: 80px;
            right: 0;
            background: var(--white);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            width: 320px;
            overflow: hidden;
            display: none;
            border: 1px solid var(--platinum);
        }
        .chat-container.active { display: block; }
        .chat-header { background: #00BFFF; padding: 15px; color: white; display: flex; justify-content: space-between; align-items: center; }
        .chat-body { padding: 20px; max-height: 400px; overflow-y: auto; }
        .chat-message { background: var(--isabelline); border-radius: 12px; padding: 15px; margin-bottom: 15px; border-left: 4px solid var(--selective-yellow); }
        .contact-options { display: flex; flex-direction: column; gap: 12px; margin-top: 20px; }
        .contact-option {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            background: var(--isabelline);
            border-radius: 10px;
            text-decoration: none;
            color: var(--kappel);
            transition: all 0.3s;
            border: 1px solid var(--platinum);
        }
        .contact-option:hover { background: var(--kappel_15); transform: translateX(5px); }
        
        @media (max-width: 900px) { .activation-container { grid-template-columns: 1fr; } }
        @media (max-width: 600px) { .code-digit { width: 45px; height: 55px; font-size: 24px; } .card { padding: 20px; } }
        
        .footer {
            background-color: var(--eerie-black-2);
            color: var(--gray-x-11);
            padding: 40px 0 20px;
            margin-top: 60px;
            text-align: center;
        }
    </style>
</head>
<body>

<!-- Header -->
<header class="header">
    <div class="container">
        <a href="/" class="logo"><img src="/images/logo.png" alt="EduScore logo"></a>
        <a href="index.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Home</a>
    </div>
</header>

<div class="activation-container">
    <!-- School Info Card -->
    <div class="card">
        <h2><?php echo htmlspecialchars($school['school_name']); ?></h2>
        <p>Onboarding Activation</p>
        <div class="info-grid">
            <div class="info-box"><span>Email</span><strong><?php echo htmlspecialchars($school['school_email']); ?></strong></div>
            <div class="info-box"><span>Phone</span><strong><?php echo htmlspecialchars($school['school_phone']); ?></strong></div>
            <div class="info-box"><span>Institution Level</span><strong><?php echo ucfirst($school['institution_level'] ?? 'N/A'); ?></strong></div>
            <div class="info-box"><span>Product Type</span><strong><?php echo htmlspecialchars($school['product_type'] ?? 'N/A'); ?></strong></div>
        </div>

        <div class="activation-status">
            <?php if ($is_activated): ?>
                <div class="activation-icon"><i class="fas fa-check-circle"></i></div>
                <div class="activated-badge"><i class="fas fa-check-circle"></i> Account Activated</div>
                <p>Your school account is fully activated and ready to use.</p>
                <a href="dashboard.php" class="btn btn-primary" style="margin-top: 20px;"><i class="fas fa-arrow-right"></i> Go to Dashboard</a>
            <?php else: ?>
                <h3 style="color: var(--kappel); margin-bottom: 20px;"><i class="fas fa-key"></i> Enter Activation Code</h3>
                
                <?php if (isset($verification_error) && $verification_error): ?>
                    <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($verification_message); ?></div>
                <?php endif; ?>
                
                <div class="code-input-container" id="codeInputsContainer"></div>
                <p class="code-hint" style="font-size: 12px; text-align: center; margin-bottom: 20px;">
                    <i class="fas fa-info-circle"></i> Enter the 6-character activation code sent to your email
                </p>
                
                <form method="POST" id="activationForm">
                    <input type="hidden" name="activation_code" id="activationCodeHidden">
                    <input type="hidden" name="verify_activation_code" value="1">
                    <button type="submit" class="btn btn-success"><i class="fas fa-check-circle"></i> Verify & Activate</button>
                </form>
            <?php endif; ?>
        </div>

        <?php if (!$is_activated): ?>
            <button type="button" class="btn btn-secondary <?php echo $cooldownSeconds > 0 ? 'btn-disabled' : ''; ?>" id="requestCodeBtn" <?php echo ($cooldownSeconds > 0) ? 'disabled' : ''; ?>>
                <i class="fas fa-key"></i> Request Activation Code
            </button>
            <?php if ($cooldownSeconds > 0): ?>
                <div class="modal-note" style="margin-top: 15px; text-align: center;">
                    <i class="fas fa-clock"></i> You can request another code in <strong id="cooldownTimer"></strong>
                </div>
                <a href="<?php echo $whatsappLink; ?>" target="_blank" class="btn btn-warning" style="margin-top: 12px;">
                    <i class="fab fa-whatsapp"></i> Contact Support on WhatsApp
                </a>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Payment Card -->
    <div class="card">
        <h2 style="color: var(--kappel);">One-Time Onboarding Fee</h2>
        <div class="fee-notice">
            <i class="fas fa-credit-card"></i>
            <div>
                <h4>One-Time Setup Fee</h4>
                <p>This is a one-time payment required to activate your school account.</p>
            </div>
        </div>
        <div class="total">KSh <?php echo number_format($totalCost); ?></div>
        <?php if ($totalCost > 0): ?>
            <button class="btn btn-primary" id="payBtn" <?php echo $is_activated ? 'disabled' : ''; ?>><i class="fas fa-mobile-alt"></i> Pay via M-Pesa</button>
        <?php else: ?>
            <p style="color: #10b981;">No onboarding fee required.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Footer -->
<footer class="footer">
    <div class="container">
        <p>© <?php echo date('Y'); ?> EduScore Kenya. All rights reserved.</p>
    </div>
</footer>

<!-- Request Activation Code Modal -->
<div id="activationCodeModal" class="modal-overlay">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-key"></i> Request Activation Code</h3>
            <button id="closeActivationModal">&times;</button>
        </div>
        <div class="modal-body">
            <?php if (isset($request_success) && $request_success): ?>
                <div class="success-message"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?></div>
                <div class="modal-note"><i class="fab fa-whatsapp"></i> Need faster assistance? Contact us on WhatsApp.</div>
                <a href="<?php echo $whatsappLink; ?>" target="_blank" class="btn btn-warning"><i class="fab fa-whatsapp"></i> Message Support</a>
                <button class="btn btn-primary" style="margin-top: 10px;" id="closeSuccessModal">Close</button>
            <?php else: ?>
                <div class="modal-note"><i class="fas fa-info-circle"></i> This request will be sent to our support team. A 6-character activation code will be sent to your school email.</div>
                <?php if (isset($request_error) && $request_error): ?>
                    <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>
                <form method="POST">
                    <input type="hidden" name="request_activation_code" value="1">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit Request</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- M-Pesa Payment Modal -->
<div id="mpesaModal" class="modal-overlay">
    <div class="modal">
        <div class="modal-header">
            <h3>M-Pesa Payment - Onboarding Fee</h3>
            <button id="closeModal">&times;</button>
        </div>
        <div class="modal-body">
            <p>Amount to Pay: <strong>KSh <?php echo number_format($totalCost); ?></strong></p>
            <div class="modal-note"><i class="fas fa-info-circle"></i> This is a one-time onboarding fee.</div>
            <form id="mpesaForm">
                <label>Phone Number</label>
                <input type="tel" id="mpesaPhone" placeholder="07XXXXXXXX" required style="width:100%; padding:12px; border:1px solid var(--platinum); border-radius:8px; margin-bottom:15px;">
                <input type="hidden" id="amount" value="<?php echo $totalCost; ?>">
                <p id="statusMsg" style="margin-top:10px; font-size:14px;"></p>
                <button type="submit" class="btn btn-primary" style="margin-top:15px;">Pay Now</button>
            </form>
        </div>
    </div>
</div>

<!-- Chat Widget -->
<div class="chat-widget">
    <div class="chat-container" id="chatContainer">
        <div class="chat-header">
            <h4><i class="fas fa-headset"></i> Contact Support</h4>
            <button class="close-chat" id="closeChat">&times;</button>
        </div>
        <div class="chat-body">
            <div class="chat-message">
                <p><strong>Need help with activation?</strong></p>
                <p>Our support team is here to assist you.</p>
            </div>
            <div class="contact-options">
                <a href="tel:+254746614238" class="contact-option"><i class="fas fa-phone"></i> <span>Call: +254 746 614 238</span></a>
                <a href="<?php echo $whatsappLink; ?>" target="_blank" class="contact-option"><i class="fab fa-whatsapp"></i> <span>WhatsApp Support</span></a>
                <a href="mailto:support@eduscore.co.ke" class="contact-option"><i class="fas fa-envelope"></i> <span>Email Support</span></a>
            </div>
        </div>
    </div>
    <button class="chat-toggle" id="chatToggle"><i class="fas fa-comment-dots"></i></button>
</div>

<script>
// Code Input Generator
let codeLength = 6;
function createCodeInputs() {
    const container = document.getElementById('codeInputsContainer');
    if (!container) return;
    container.innerHTML = '';
    
    for (let i = 0; i < codeLength; i++) {
        const input = document.createElement('input');
        input.type = 'text';
        input.maxLength = 1;
        input.className = 'code-digit';
        input.addEventListener('input', (e) => {
            let val = e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
            e.target.value = val;
            if (val) {
                e.target.classList.add('filled');
                if (i < codeLength - 1) container.children[i+1].focus();
            } else {
                e.target.classList.remove('filled');
            }
            updateHiddenInput();
        });
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && !e.target.value && i > 0) {
                container.children[i-1].focus();
                container.children[i-1].value = '';
                container.children[i-1].classList.remove('filled');
                updateHiddenInput();
            }
        });
        container.appendChild(input);
    }
    setTimeout(() => container.children[0]?.focus(), 100);
    updateHiddenInput();
}

function updateHiddenInput() {
    const hidden = document.getElementById('activationCodeHidden');
    if (hidden) {
        hidden.value = Array.from(document.querySelectorAll('.code-digit')).map(i => i.value).join('');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    createCodeInputs();
    
    const form = document.getElementById('activationForm');
    form?.addEventListener('submit', (e) => {
        const code = document.getElementById('activationCodeHidden').value;
        if (!code || code.length !== codeLength) {
            e.preventDefault();
            document.querySelectorAll('.code-digit').forEach(i => i.classList.add('error'));
            alert('Please enter a complete 6-character code');
        } else if (!/^[A-Z0-9]+$/i.test(code)) {
            e.preventDefault();
            alert('Code can only contain letters and numbers (A-Z, 0-9)');
        }
    });
});

// Chat Widget
const chatToggle = document.getElementById('chatToggle');
const chatContainer = document.getElementById('chatContainer');
const closeChat = document.getElementById('closeChat');
if (chatToggle && chatContainer) {
    chatToggle.addEventListener('click', () => chatContainer.classList.toggle('active'));
    closeChat.addEventListener('click', () => chatContainer.classList.remove('active'));
    document.addEventListener('click', (e) => {
        if (!chatContainer.contains(e.target) && !chatToggle.contains(e.target)) {
            chatContainer.classList.remove('active');
        }
    });
}

// M-Pesa Payment
const mpesaModal = document.getElementById('mpesaModal');
const payBtn = document.getElementById('payBtn');
const closeModal = document.getElementById('closeModal');
const mpesaForm = document.getElementById('mpesaForm');
const statusMsg = document.getElementById('statusMsg');

if (payBtn) payBtn.addEventListener('click', () => mpesaModal.style.display = 'flex');
if (closeModal) closeModal.addEventListener('click', () => mpesaModal.style.display = 'none');
if (mpesaForm) {
    mpesaForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        statusMsg.textContent = 'Sending STK push...';
        try {
            const res = await fetch('includes/stk_push.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    phone: document.getElementById('mpesaPhone').value,
                    amount: document.getElementById('amount').value
                })
            });
            const data = await res.json();
            statusMsg.textContent = data.success ? 'STK Push sent. Check your phone.' : 'Error: ' + (data.message || 'Payment failed.');
        } catch (error) {
            statusMsg.textContent = 'Network error. Please try again.';
        }
    });
}

// Request Code Modal
const activationModal = document.getElementById('activationCodeModal');
const requestCodeBtn = document.getElementById('requestCodeBtn');
const closeActivationModal = document.getElementById('closeActivationModal');
const closeSuccessModal = document.getElementById('closeSuccessModal');

if (requestCodeBtn) requestCodeBtn.addEventListener('click', () => activationModal.style.display = 'flex');
if (closeActivationModal) closeActivationModal.addEventListener('click', () => activationModal.style.display = 'none');
if (closeSuccessModal) closeSuccessModal.addEventListener('click', () => { activationModal.style.display = 'none'; window.location.reload(); });

window.addEventListener('click', (e) => {
    if (e.target === activationModal) activationModal.style.display = 'none';
    if (e.target === mpesaModal) mpesaModal.style.display = 'none';
});

// Cooldown Timer
let cooldown = <?php echo (int)$cooldownSeconds; ?>;
if (cooldown > 0) {
    const timerEl = document.getElementById('cooldownTimer');
    const formatTime = (s) => {
        const h = Math.floor(s / 3600);
        const m = Math.floor((s % 3600) / 60);
        const sec = s % 60;
        return `${h.toString().padStart(2,'0')}:${m.toString().padStart(2,'0')}:${sec.toString().padStart(2,'0')}`;
    };
    if (timerEl) timerEl.textContent = formatTime(cooldown);
    const interval = setInterval(() => {
        cooldown--;
        if (timerEl) timerEl.textContent = formatTime(cooldown);
        if (cooldown <= 0) {
            clearInterval(interval);
            if (requestCodeBtn) {
                requestCodeBtn.disabled = false;
                requestCodeBtn.classList.remove('btn-disabled');
            }
        }
    }, 1000);
}

<?php if ($request_success || $request_error): ?>
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('activationCodeModal').style.display = 'flex';
});
<?php endif; ?>
</script>
</body>
</html>