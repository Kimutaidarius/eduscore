<?php
// config/smtp_config.php

// SMTP Configuration - Check if constants are already defined before defining
if (!defined('SMTP_HOST')) {
    define('SMTP_HOST', 'smtp.gmail.com');
}

if (!defined('SMTP_PORT')) {
    define('SMTP_PORT', 587);
}

if (!defined('SMTP_USERNAME')) {
    define('SMTP_USERNAME', 'kymtechnologiesltd@gmail.com');
}

if (!defined('SMTP_PASSWORD')) {
    define('SMTP_PASSWORD', 'cwev xgwb wksp clbt');
}

if (!defined('SMTP_FROM_EMAIL')) {
    define('SMTP_FROM_EMAIL', 'noreply@eduscore.com');
}

if (!defined('SMTP_FROM_NAME')) {
    define('SMTP_FROM_NAME', 'EduScore System');
}

if (!defined('SMTP_ENCRYPTION')) {
    define('SMTP_ENCRYPTION', 'tls');
}

if (!defined('AFRICASTALKING_USERNAME')) {
    define('AFRICASTALKING_USERNAME', 'EduscoreKenya');
}

if (!defined('AFRICASTALKING_API_KEY')) {
    define('AFRICASTALKING_API_KEY', 'atsk_8e2cf9562375cb79e5f82776354aa718fe49091eba945e71ca283fb692f5ae6227a43648');
}
?>