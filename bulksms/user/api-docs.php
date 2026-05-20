<?php
require_once '../config/config.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// Get user's API key
$stmt = $pdo->prepare("SELECT * FROM api_keys WHERE user_id = ? AND status = 'active' ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$user_id]);
$api_key = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Documentation - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/styles/github.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background-color: #ffffff;
            overflow-x: hidden;
        }

        .main-content {
            margin-left: 250px;
            margin-top: 60px;
            padding: 30px;
            background-color: #ffffff;
            min-height: calc(100vh - 60px);
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-header h2 {
            color: #1e3a8a;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .api-key-card {
            background: linear-gradient(135deg, #1e3a8a 0%, #152b63 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
        }

        .api-key-card .label {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 5px;
        }

        .api-key-display {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            padding: 15px;
            font-family: 'Courier New', monospace;
            font-size: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 15px 0;
        }

        .copy-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            padding: 5px 15px;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .copy-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .section-card {
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            margin-bottom: 30px;
            overflow: hidden;
        }

        .section-header {
            background-color: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #e0e0e0;
            font-weight: 600;
            color: #1e3a8a;
        }

        .section-header i {
            margin-right: 8px;
        }

        .section-body {
            padding: 20px;
        }

        .endpoint {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-family: 'Courier New', monospace;
        }

        .method {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-right: 10px;
        }

        .method-post {
            background-color: #10b981;
            color: white;
        }

        .method-get {
            background-color: #3b82f6;
            color: white;
        }

        .url {
            color: #1e3a8a;
            font-weight: 500;
        }

        .parameter-table {
            width: 100%;
            margin-bottom: 20px;
        }

        .parameter-table th {
            background-color: #f8f9fa;
            padding: 10px;
            font-size: 13px;
            font-weight: 600;
            color: #333333;
        }

        .parameter-table td {
            padding: 10px;
            border-bottom: 1px solid #e0e0e0;
            font-size: 13px;
        }

        .code-block {
            background-color: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .code-block pre {
            margin: 0;
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }

        .nav-tabs .nav-link {
            color: #666666;
            border: none;
            padding: 10px 20px;
        }

        .nav-tabs .nav-link:hover {
            color: #1e3a8a;
            border: none;
        }

        .nav-tabs .nav-link.active {
            color: #1e3a8a;
            border-bottom: 3px solid #1e3a8a;
            background: none;
        }

        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
        }

        .badge-required {
            background-color: #fee2e2;
            color: #dc2626;
        }

        .badge-optional {
            background-color: #f3f4f6;
            color: #6b7280;
        }

        .response-example {
            background-color: #1e1e1e;
            color: #d4d4d4;
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
        }
    </style>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/topbar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h2><i class="bi bi-code-square me-2"></i>API Documentation</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">API Documentation</li>
                </ol>
            </nav>
        </div>

        <!-- API Key Section -->
        <div class="api-key-card">
            <div class="label">Your API Credentials</div>
            <?php if ($api_key): ?>
                <div class="api-key-display">
                    <span>API Key: <?php echo substr($api_key['api_key'], 0, 20); ?>...</span>
                    <button class="copy-btn" onclick="copyToClipboard('<?php echo $api_key['api_key']; ?>')">
                        <i class="bi bi-clipboard"></i> Copy
                    </button>
                </div>
                <div class="api-key-display">
                    <span>API Secret: ••••••••••••••••••••••••••••••••</span>
                    <button class="copy-btn" onclick="alert('For security reasons, API secret cannot be viewed. Please regenerate if needed.')">
                        <i class="bi bi-eye"></i> Show
                    </button>
                </div>
            <?php else: ?>
                <p>You don't have an active API key. <a href="api-keys.php" style="color: white; text-decoration: underline;">Generate one now</a></p>
            <?php endif; ?>
            <div class="mt-3">
                <span class="badge bg-light text-dark">Base URL: <code><?php echo APP_URL; ?>/api/</code></span>
            </div>
        </div>

        <!-- Authentication Section -->
        <div class="section-card">
            <div class="section-header">
                <i class="bi bi-shield-lock"></i> Authentication
            </div>
            <div class="section-body">
                <p>All API requests require authentication using your API key. Include your API key in every request either as a query parameter or in the request body.</p>
                
                <div class="code-block">
                    <pre><code>// As query parameter
<?php echo APP_URL; ?>/api/send_sms.php?api_key=YOUR_API_KEY

// In request body (POST)
{
    "api_key": "YOUR_API_KEY",
    "phone": "254712345678",
    "message": "Hello World"
}</code></pre>
                </div>
            </div>
        </div>

        <!-- Endpoints -->
        <div class="section-card">
            <div class="section-header">
                <i class="bi bi-envelope-paper"></i> Send SMS
            </div>
            <div class="section-body">
                <div class="endpoint">
                    <span class="method method-post">POST</span>
                    <span class="url">/api/send_sms.php</span>
                </div>

                <h6>Parameters</h6>
                <table class="parameter-table">
                    <thead>
                        <tr>
                            <th>Parameter</th>
                            <th>Type</th>
                            <th>Required</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>api_key</code></td>
                            <td>string</td>
                            <td><span class="badge badge-required">Required</span></td>
                            <td>Your API key</td>
                        </tr>
                        <tr>
                            <td><code>phone</code></td>
                            <td>string</td>
                            <td><span class="badge badge-required">Required</span></td>
                            <td>Recipient phone number (international format, e.g., 254712345678)</td>
                        </tr>
                        <tr>
                            <td><code>message</code></td>
                            <td>string</td>
                            <td><span class="badge badge-required">Required</span></td>
                            <td>SMS message content (max 1600 characters)</td>
                        </tr>
                        <tr>
                            <td><code>sender_id</code></td>
                            <td>string</td>
                            <td><span class="badge badge-optional">Optional</span></td>
                            <td>Sender ID (default: EduScore, must be approved first)</td>
                        </tr>
                        <tr>
                            <td><code>schedule_time</code></td>
                            <td>datetime</td>
                            <td><span class="badge badge-optional">Optional</span></td>
                            <td>Schedule message for later delivery (YYYY-MM-DD HH:MM:SS)</td>
                        </tr>
                    </tbody>
                </table>

                <h6 class="mt-4">Example Request</h6>
                <ul class="nav nav-tabs" id="codeTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="curl-tab" data-bs-toggle="tab" data-bs-target="#curl" type="button">cURL</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="php-tab" data-bs-toggle="tab" data-bs-target="#php" type="button">PHP</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="python-tab" data-bs-toggle="tab" data-bs-target="#python" type="button">Python</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="javascript-tab" data-bs-toggle="tab" data-bs-target="#javascript" type="button">JavaScript</button>
                    </li>
                </ul>

                <div class="tab-content mt-3">
                    <div class="tab-pane fade show active" id="curl">
                        <div class="code-block">
                            <pre><code>curl -X POST <?php echo APP_URL; ?>/api/send_sms.php \
    -H "Content-Type: application/json" \
    -d '{
        "api_key": "YOUR_API_KEY",
        "phone": "254712345678",
        "message": "Hello from EduScore SMS!",
        "sender_id": "EduScore"
    }'</code></pre>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="php">
                        <div class="code-block">
                            <pre><code>&lt;?php
$api_key = 'YOUR_API_KEY';
$url = '<?php echo APP_URL; ?>/api/send_sms.php';

$data = [
    'api_key' => $api_key,
    'phone' => '254712345678',
    'message' => 'Hello from EduScore SMS!',
    'sender_id' => 'EduScore'
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
if ($result['status'] == 'success') {
    echo "SMS sent! Message ID: " . $result['data']['message_id'];
} else {
    echo "Error: " . $result['message'];
}
?&gt;</code></pre>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="python">
                        <div class="code-block">
                            <pre><code>import requests
import json

api_key = 'YOUR_API_KEY'
url = '<?php echo APP_URL; ?>/api/send_sms.php'

data = {
    'api_key': api_key,
    'phone': '254712345678',
    'message': 'Hello from EduScore SMS!',
    'sender_id': 'EduScore'
}

response = requests.post(url, json=data)
result = response.json()

if result['status'] == 'success':
    print(f"SMS sent! Message ID: {result['data']['message_id']}")
else:
    print(f"Error: {result['message']}")</code></pre>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="javascript">
                        <div class="code-block">
                            <pre><code>const apiKey = 'YOUR_API_KEY';
const url = '<?php echo APP_URL; ?>/api/send_sms.php';

const data = {
    api_key: apiKey,
    phone: '254712345678',
    message: 'Hello from EduScore SMS!',
    sender_id: 'EduScore'
};

fetch(url, {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json'
    },
    body: JSON.stringify(data)
})
.then(response => response.json())
.then(result => {
    if (result.status === 'success') {
        console.log(`SMS sent! Message ID: ${result.data.message_id}`);
    } else {
        console.log(`Error: ${result.message}`);
    }
});</code></pre>
                        </div>
                    </div>
                </div>

                <h6 class="mt-4">Success Response</h6>
                <div class="response-example">
                    <pre><code>{
    "status": "success",
    "message": "SMS sent successfully",
    "data": {
        "message_id": "MSG1234567890",
        "recipient": "254712345678",
        "sms_parts": 1,
        "cost": 1,
        "balance_remaining": 99
    }
}</code></pre>
                </div>

                <h6 class="mt-4">Error Response</h6>
                <div class="response-example">
                    <pre><code>{
    "status": "error",
    "message": "Insufficient SMS balance"
}</code></pre>
                </div>
            </div>
        </div>

        <!-- Check Balance Endpoint -->
        <div class="section-card">
            <div class="section-header">
                <i class="bi bi-piggy-bank"></i> Check Balance
            </div>
            <div class="section-body">
                <div class="endpoint">
                    <span class="method method-get">GET</span>
                    <span class="url">/api/check_balance.php?api_key=YOUR_API_KEY</span>
                </div>

                <h6>Response</h6>
                <div class="response-example">
                    <pre><code>{
    "status": "success",
    "data": {
        "balance": 100,
        "currency": "credits"
    }
}</code></pre>
                </div>
            </div>
        </div>

        <!-- Rate Limits -->
        <div class="section-card">
            <div class="section-header">
                <i class="bi bi-speedometer2"></i> Rate Limits
            </div>
            <div class="section-body">
                <p>To ensure fair usage and system stability, the following rate limits apply:</p>
                <ul>
                    <li><strong>100 requests per minute</strong> per API key</li>
                    <li><strong>Maximum message length:</strong> 1600 characters (10 SMS parts)</li>
                    <li><strong>Minimum time between requests:</strong> 0.5 seconds</li>
                </ul>
                <p>If you exceed these limits, you'll receive a <code>429 Too Many Requests</code> response.</p>
            </div>
        </div>

        <!-- Error Codes -->
        <div class="section-card">
            <div class="section-header">
                <i class="bi bi-exclamation-triangle"></i> Error Codes
            </div>
            <div class="section-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>HTTP Status</th>
                            <th>Error Code</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><span class="badge bg-danger">400</span></td>
                            <td>Bad Request</td>
                            <td>Missing or invalid parameters</td>
                        </tr>
                        <tr>
                            <td><span class="badge bg-danger">401</span></td>
                            <td>Unauthorized</td>
                            <td>Invalid or missing API key</td>
                        </tr>
                        <tr>
                            <td><span class="badge bg-danger">402</span></td>
                            <td>Payment Required</td>
                            <td>Insufficient SMS balance</td>
                        </tr>
                        <tr>
                            <td><span class="badge bg-danger">403</span></td>
                            <td>Forbidden</td>
                            <td>Account suspended or inactive</td>
                        </tr>
                        <tr>
                            <td><span class="badge bg-danger">429</span></td>
                            <td>Too Many Requests</td>
                            <td>Rate limit exceeded</td>
                        </tr>
                        <tr>
                            <td><span class="badge bg-danger">500</span></td>
                            <td>Server Error</td>
                            <td>Internal server error</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Best Practices -->
        <div class="section-card">
            <div class="section-header">
                <i class="bi bi-star"></i> Best Practices
            </div>
            <div class="section-body">
                <ul>
                    <li class="mb-2"><strong>Store your API key securely</strong> - Never expose it in client-side code or public repositories</li>
                    <li class="mb-2"><strong>Implement proper error handling</strong> - Always check the response status and handle errors gracefully</li>
                    <li class="mb-2"><strong>Respect rate limits</strong> - Implement exponential backoff for retries</li>
                    <li class="mb-2"><strong>Use webhooks</strong> - Set up webhooks to receive delivery reports in real-time</li>
                    <li class="mb-2"><strong>Rotate API keys regularly</strong> - Regenerate keys periodically for security</li>
                </ul>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/highlight.min.js"></script>
    <script>
        hljs.highlightAll();

        // Mobile menu toggle
        document.getElementById('menuToggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });

        // Copy to clipboard
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                alert('API key copied to clipboard!');
            });
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.querySelector('.sidebar');
            const menuToggle = document.getElementById('menuToggle');
            
            if (window.innerWidth <= 768 && 
                sidebar.classList.contains('active') && 
                !sidebar.contains(event.target) && 
                !menuToggle.contains(event.target)) {
                sidebar.classList.remove('active');
            }
        });
    </script>
</body>
</html>