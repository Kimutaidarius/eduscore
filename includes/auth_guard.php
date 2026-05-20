<?php
session_start();

// 🔒 Activation lock
if (!empty($_SESSION['locked']) && !empty($_SESSION['activation_only'])) {
    header('Location: activation-module.php');
    exit;
}

// ✅ Normal authenticated access
if (empty($_SESSION['authenticated'])) {
    header('Location: login.php');
    exit;
}
