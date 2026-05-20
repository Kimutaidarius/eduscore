<?php
// config/sms_config.php
define('SMS_API_VERSION', 'v1');
define('SMS_API_BASE_URL', 'https://api.eduscore.com/sms/');
define('MPESA_ENVIRONMENT', 'sandbox'); // sandbox or production
define('MPESA_BUSINESS_SHORTCODE', '174379');
define('MPESA_PASSKEY', 'your-passkey-here');
define('MPESA_CONSUMER_KEY', 'your-consumer-key');
define('MPESA_CONSUMER_SECRET', 'your-consumer-secret');

// SMS credit calculation (per 160 characters = 1 credit)
define('SMS_CHARS_PER_CREDIT', 160);
define('SMS_MAX_MESSAGE_LENGTH', 918); // 160 * 6 credits max