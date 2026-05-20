<?php
// preloader.php - Dotted yellow circle loading spinner
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loading EduScore</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: #ffffff;
            font-family: 'Inter', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            overflow: hidden;
        }

        .preloader-container {
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 30px;
        }

        /* Dotted Yellow Circle Spinner */
        .dotted-spinner {
            width: 80px;
            height: 80px;
            position: relative;
        }

        .dotted-spinner::before {
            content: '';
            box-sizing: border-box;
            position: absolute;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 4px dotted #facc15; /* Yellow color */
            border-top-color: transparent;
            animation: spin 1.5s linear infinite;
        }

        .dotted-spinner::after {
            content: '';
            box-sizing: border-box;
            position: absolute;
            width: 60px;
            height: 60px;
            top: 10px;
            left: 10px;
            border-radius: 50%;
            border: 3px dotted rgba(250, 204, 21, 0.5); /* Lighter yellow */
            border-bottom-color: transparent;
            animation: spinReverse 1s linear infinite;
        }

        /* Loading Text */
        .loading-text {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1e40af; /* EduScore blue */
            letter-spacing: 2px;
            position: relative;
        }

        .loading-text::after {
            content: '...';
            display: inline-block;
            width: 30px;
            text-align: left;
            animation: dots 1.5s steps(4, end) infinite;
        }

        /* EduScore Logo */
        .eduscore-brand {
            position: absolute;
            bottom: 40px;
            left: 0;
            right: 0;
            text-align: center;
            color: #6b7280;
            font-size: 0.9rem;
        }

        .eduscore-brand strong {
            color: #1e40af;
            font-weight: 700;
        }

        /* Animations */
        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }
            100% {
                transform: rotate(360deg);
            }
        }

        @keyframes spinReverse {
            0% {
                transform: rotate(0deg);
            }
            100% {
                transform: rotate(-360deg);
            }
        }

        @keyframes dots {
            0%, 20% {
                content: '.';
            }
            40% {
                content: '..';
            }
            60%, 100% {
                content: '...';
            }
        }

        /* Pulse animation for the circle */
        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.7;
            }
        }

        .dotted-spinner {
            animation: pulse 2s ease-in-out infinite;
        }

        /* Progress bar */
        .progress-container {
            width: 200px;
            height: 4px;
            background: #e5e7eb;
            border-radius: 2px;
            overflow: hidden;
            margin-top: 10px;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #facc15, #fbbf24);
            width: 0%;
            border-radius: 2px;
            animation: progress 2s ease-in-out infinite;
        }

        @keyframes progress {
            0% {
                width: 0%;
                transform: translateX(-100%);
            }
            50% {
                width: 100%;
                transform: translateX(0%);
            }
            100% {
                width: 0%;
                transform: translateX(100%);
            }
        }
    </style>
</head>
<body>
    <div class="preloader-container">
        <div class="dotted-spinner"></div>
        <div class="loading-text">Loading EduScore</div>
        <div class="progress-container">
            <div class="progress-bar"></div>
        </div>
    </div>
    
    <div class="eduscore-brand">
        <strong>EduScore</strong> • Modern School Management System
    </div>

    <script>
        // After 2 seconds, redirect to the main page
        setTimeout(function() {
            // Check if we should redirect to index.php or dashboard
            const urlParams = new URLSearchParams(window.location.search);
            const redirectTo = urlParams.get('redirect') || 'index.php';
            
            // Add fade out effect
            document.body.style.opacity = '0';
            document.body.style.transition = 'opacity 0.5s ease';
            
            setTimeout(function() {
                window.location.href = redirectTo;
            }, 500);
        }, 2000);

        // Add keyboard shortcut to skip loading (for testing)
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                window.location.href = 'index.php';
            }
        });
    </script>
</body>
</html>