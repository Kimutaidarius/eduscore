<?php
session_start();
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: ../../login.php');
    exit;
}

require_once('../../includes/config.php');

$school_id = $_SESSION['school_id'];
$school_name = $_SESSION['school_name'] ?? '';

include_once('../../includes/header.php');
include_once('../../includes/sidebar.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Vote Heads Management - EduScore</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-blue: #1A73E8;
            --secondary-blue: #1976D2;
            --dark-blue: #0D47A1;
            --light-blue: #E8F0FE;
            --success-green: #10b981;
            --warning-orange: #f59e0b;
            --error-red: #ef4444;
            --text-dark: #1f2937;
            --text-light: #6b7280;
            --bg-light: #f9fafb;
            --bg-white: #ffffff;
            --border-color: #e5e7eb;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            --border-radius: 20px;
            --transition: all 0.3s ease;
        }

        body {
            background: #f3f4f6;
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.5;
            overflow-x: hidden;
        }

        /* Main Content Area - Full width like students.php */
        .main-content {
            flex: 1;
            padding: 1rem;
            max-width: 100%;
            overflow-x: hidden;
        }

        @media (min-width: 640px) {
            .main-content {
                padding: 1.25rem;
            }
        }

        @media (min-width: 768px) {
            .main-content {
                padding: 1.5rem;
            }
        }

        @media (min-width: 1024px) {
            .main-content {
                padding: 2rem;
            }
        }

        /* Page Header - Responsive */
        .page-header {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
            gap: 1rem;
        }

        @media (min-width: 640px) {
            .page-header {
                flex-direction: row;
                align-items: center;
                margin-bottom: 2rem;
            }
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        @media (min-width: 640px) {
            .page-title {
                font-size: 1.6rem;
            }
        }

        @media (min-width: 768px) {
            .page-title {
                font-size: 1.8rem;
            }
        }

        .page-title i {
            color: var(--primary-blue);
            font-size: 1.3rem;
        }

        @media (min-width: 768px) {
            .page-title i {
                font-size: 1.5rem;
            }
        }

        .header-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            width: 100%;
        }

        @media (min-width: 640px) {
            .header-actions {
                width: auto;
                gap: 1rem;
            }
        }

        /* Buttons */
        .btn {
            padding: 0.6rem 1rem;
            border: none;
            border-radius: 12px;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-decoration: none;
            min-height: 44px;
        }

        @media (min-width: 640px) {
            .btn {
                padding: 0.75rem 1.5rem;
                font-size: 0.95rem;
            }
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
            color: white;
        }

        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary-blue);
            color: var(--primary-blue);
        }

        .btn-outline:hover:not(:disabled) {
            background: var(--primary-blue);
            color: white;
        }

        .btn-secondary {
            background: var(--bg-light);
            border: 1px solid var(--border-color);
            color: var(--text-dark);
        }

        /* Form Card */
        .form-card {
            background: var(--bg-white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-xl);
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--border-color);
        }

        @media (min-width: 640px) {
            .form-card {
                padding: 1.5rem;
                margin-bottom: 2rem;
            }
        }

        @media (min-width: 768px) {
            .form-card {
                padding: 2rem;
            }
        }

        .form-header {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--light-blue);
            gap: 1rem;
        }

        @media (min-width: 640px) {
            .form-header {
                flex-direction: row;
                align-items: center;
                margin-bottom: 2rem;
                padding-bottom: 1.5rem;
            }
        }

        .form-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        @media (min-width: 640px) {
            .form-title {
                font-size: 1.35rem;
            }
        }

        @media (min-width: 768px) {
            .form-title {
                font-size: 1.5rem;
            }
        }

        /* Form Sections */
        .form-section {
            background: var(--light-blue);
            border-radius: 16px;
            padding: 1rem;
            border-left: 4px solid var(--primary-blue);
            margin-bottom: 1.5rem;
        }

        @media (min-width: 640px) {
            .form-section {
                padding: 1.25rem;
                margin-bottom: 2rem;
            }
        }

        @media (min-width: 768px) {
            .form-section {
                padding: 1.5rem;
            }
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        @media (min-width: 640px) {
            .section-header {
                margin-bottom: 1.5rem;
            }
        }

        .section-icon {
            width: 36px;
            height: 36px;
            background: var(--primary-blue);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1rem;
        }

        @media (min-width: 640px) {
            .section-icon {
                width: 40px;
                height: 40px;
                font-size: 1.2rem;
            }
        }

        .section-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-dark);
        }

        @media (min-width: 640px) {
            .section-title {
                font-size: 1.1rem;
            }
        }

        @media (min-width: 768px) {
            .section-title {
                font-size: 1.2rem;
            }
        }

        /* Form Grid - Fully Responsive */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        @media (min-width: 480px) {
            .form-grid {
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap: 1.25rem;
            }
        }

        @media (min-width: 768px) {
            .form-grid {
                gap: 1.5rem;
            }
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.85rem;
        }

        @media (min-width: 640px) {
            .form-label {
                font-size: 0.9rem;
            }
        }

        .required::after {
            content: '*';
            color: var(--error-red);
            margin-left: 0.25rem;
        }

        .form-control {
            padding: 0.6rem 0.8rem;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            background: var(--bg-white);
            color: var(--text-dark);
            width: 100%;
            min-height: 42px;
        }

        @media (min-width: 640px) {
            .form-control {
                padding: 0.75rem 1rem;
                font-size: 1rem;
            }
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(26, 115, 232, 0.1);
        }

        /* Form Actions */
        .form-actions {
            display: flex;
            flex-direction: column-reverse;
            justify-content: flex-end;
            gap: 0.75rem;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 2px solid var(--light-blue);
        }

        @media (min-width: 480px) {
            .form-actions {
                flex-direction: row;
                justify-content: flex-end;
            }
        }

        @media (min-width: 640px) {
            .form-actions {
                gap: 1rem;
                margin-top: 2rem;
                padding-top: 2rem;
            }
        }

        /* Table Card */
        .table-card {
            background: var(--bg-white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-xl);
            overflow: hidden;
            border: 1px solid var(--border-color);
        }

        .table-header {
            padding: 1.25rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        @media (min-width: 640px) {
            .table-header {
                padding: 1.5rem;
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
            }
        }

        .table-header h2 {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        @media (min-width: 640px) {
            .table-header h2 {
                font-size: 1.35rem;
            }
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            width: 100%;
        }

        @media (min-width: 480px) {
            .filter-group {
                flex-direction: row;
                flex-wrap: wrap;
            }
        }

        @media (min-width: 640px) {
            .filter-group {
                width: auto;
                gap: 1rem;
            }
        }

        .search-box {
            position: relative;
            flex: 1;
        }

        .search-box input {
            width: 100%;
            padding: 0.6rem 0.8rem 0.6rem 2.3rem;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-size: 0.875rem;
            min-height: 42px;
        }

        @media (min-width: 640px) {
            .search-box input {
                width: 220px;
                padding: 0.75rem 1rem 0.75rem 2.5rem;
                font-size: 0.9rem;
            }
        }

        .search-box i {
            position: absolute;
            left: 0.8rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
            font-size: 0.875rem;
        }

        @media (min-width: 640px) {
            .search-box i {
                left: 1rem;
                font-size: 1rem;
            }
        }

        .filter-select {
            padding: 0.6rem 0.8rem;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-size: 0.875rem;
            background: var(--bg-white);
            cursor: pointer;
            min-height: 42px;
        }

        @media (min-width: 640px) {
            .filter-select {
                padding: 0.75rem 1rem;
                font-size: 0.9rem;
            }
        }

        /* Table Styles */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        .data-table th {
            padding: 0.875rem 1rem;
            text-align: left;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-light);
            background: #f8fafc;
            border-bottom: 1px solid var(--border-color);
        }

        @media (min-width: 640px) {
            .data-table th {
                padding: 1rem 1.25rem;
            }
        }

        .data-table td {
            padding: 0.875rem 1rem;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.875rem;
            color: var(--text-dark);
        }

        @media (min-width: 640px) {
            .data-table td {
                padding: 1rem 1.25rem;
            }
        }

        .data-table tr:hover {
            background: var(--bg-light);
        }

        /* Priority Badge */
        .priority-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            font-weight: 600;
            font-size: 0.875rem;
        }

        .priority-high { background: #fee2e2; color: #dc2626; }
        .priority-medium { background: #fef3c7; color: #d97706; }
        .priority-low { background: #e5e7eb; color: #4b5563; }

        /* Type Badge */
        .type-badge {
            display: inline-block;
            padding: 4px 10px;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 20px;
        }

        .type-income { background: #d1fae5; color: #059669; }
        .type-expense { background: #fee2e2; color: #dc2626; }
        .type-both { background: #dbeafe; color: #2563eb; }

        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 20px;
        }

        .status-active { background: #d1fae5; color: #059669; }
        .status-inactive { background: #e5e7eb; color: #4b5563; }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .action-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 8px;
            transition: var(--transition);
        }

        .action-btn.edit {
            color: var(--secondary-blue);
        }

        .action-btn.edit:hover {
            background: #dbeafe;
        }

        .action-btn.delete {
            color: var(--error-red);
        }

        .action-btn.delete:hover {
            background: #fee2e2;
        }

        /* Pagination */
        .pagination-container {
            padding: 1.25rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        @media (min-width: 640px) {
            .pagination-container {
                padding: 1.5rem;
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
            }
        }

        .pagination-info {
            font-size: 0.875rem;
            color: var(--text-light);
            text-align: center;
        }

        @media (min-width: 640px) {
            .pagination-info {
                text-align: left;
            }
        }

        .pagination-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            justify-content: center;
        }

        .pagination-btn {
            padding: 0.5rem 0.875rem;
            border: 1px solid var(--border-color);
            background: var(--bg-white);
            color: var(--text-dark);
            border-radius: 8px;
            cursor: pointer;
            transition: var(--transition);
            min-width: 36px;
            text-align: center;
            font-size: 0.875rem;
        }

        .pagination-btn:hover:not(:disabled) {
            background: var(--bg-light);
            border-color: var(--secondary-blue);
        }

        .pagination-btn.active {
            background: var(--secondary-blue);
            color: white;
            border-color: var(--secondary-blue);
        }

        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(5px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            padding: 1rem;
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .modal-container {
            background: var(--bg-white);
            border-radius: var(--border-radius);
            width: 100%;
            max-width: 700px;
            max-height: 90vh;
            overflow-y: auto;
            transform: scale(0.95);
            transition: transform 0.3s ease;
        }

        .modal-overlay.active .modal-container {
            transform: scale(1);
        }

        .modal-header {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        @media (min-width: 640px) {
            .modal-header {
                padding: 1.25rem;
            }
        }

        @media (min-width: 768px) {
            .modal-header {
                padding: 1.5rem;
            }
        }

        .modal-header h3 {
            font-size: 1.1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        @media (min-width: 640px) {
            .modal-header h3 {
                font-size: 1.2rem;
            }
        }

        @media (min-width: 768px) {
            .modal-header h3 {
                font-size: 1.3rem;
            }
        }

        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 1.3rem;
            cursor: pointer;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.3s ease;
        }

        @media (min-width: 640px) {
            .modal-close {
                font-size: 1.5rem;
                width: 40px;
                height: 40px;
            }
        }

        .modal-body {
            padding: 1rem;
        }

        @media (min-width: 640px) {
            .modal-body {
                padding: 1.25rem;
            }
        }

        @media (min-width: 768px) {
            .modal-body {
                padding: 1.5rem;
            }
        }

        .modal-footer {
            padding: 1rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }

        @media (min-width: 640px) {
            .modal-footer {
                padding: 1.25rem;
                gap: 1rem;
            }
        }

        /* Loading & Empty States */
        .loading-state, .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-light);
        }

        @media (min-width: 640px) {
            .loading-state, .empty-state {
                padding: 4rem 2rem;
            }
        }

        .loading-state i, .empty-state i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        @media (min-width: 640px) {
            .loading-state i, .empty-state i {
                font-size: 3rem;
            }
        }

        /* Toast Container */
        .toast-container {
            position: fixed;
            top: 70px;
            right: 0.5rem;
            left: 0.5rem;
            z-index: 1100;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }

        @media (min-width: 640px) {
            .toast-container {
                top: 80px;
                right: 1rem;
                left: auto;
            }
        }

        /* Spinner */
        .spinner-small {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 2px solid var(--border-color);
            border-top-color: var(--primary-blue);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 0.5rem;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @keyframes popIn {
            0% { opacity: 0; transform: scale(0.8) translateY(20px); }
            100% { opacity: 1; transform: scale(1) translateY(0); }
        }

        /* Utility */
        .hidden {
            display: none !important;
        }

        .text-muted {
            color: var(--text-light);
            font-size: 0.7rem;
        }

        @media (min-width: 640px) {
            .text-muted {
                font-size: 0.75rem;
            }
        }

        /* Touch-friendly adjustments */
        @media (hover: none) and (pointer: coarse) {
            .btn, .action-btn, .pagination-btn, .modal-close {
                -webkit-tap-highlight-color: transparent;
            }
            
            .btn:active {
                transform: scale(0.97);
            }
        }

        /* Small devices */
        @media (max-width: 360px) {
            .page-title {
                font-size: 1.2rem;
            }
            
            .form-title {
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>
    <main class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                <h1 class="page-title">
                    <i class="fas fa-tags"></i>
                    Vote Heads Management
                </h1>
            </div>
            <div class="header-actions">
                <button class="btn btn-primary" id="addVoteHeadBtn">
                    <i class="fas fa-plus"></i>
                    <span>Add New Vote Head</span>
                </button>
            </div>
        </div>

        <!-- Add/Edit Form Card -->
        <div class="form-card" id="formCard" style="display: none;">
            <div class="form-header">
                <h2 class="form-title" id="formTitle">
                    <i class="fas fa-plus-circle"></i>
                    <span>Add New Vote Head</span>
                </h2>
                <button class="btn btn-outline" id="closeFormBtn" style="padding: 0.5rem 1rem;">
                    <i class="fas fa-times"></i>
                    <span>Close</span>
                </button>
            </div>
            <form id="voteHeadForm">
                <input type="hidden" id="voteHeadId" value="">
                
                <div class="form-section">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-info-circle"></i>
                        </div>
                        <h3 class="section-title">Vote Head Information</h3>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label required">Vote Head Name</label>
                            <input type="text" id="name" class="form-control" placeholder="e.g., Tuition Fee" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label required">Alias</label>
                            <input type="text" id="alias" class="form-control" placeholder="e.g., TUITION" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label required">Type</label>
                            <select id="type" class="form-control" required>
                                <option value="">Select Type</option>
                                <option value="income">Income Only</option>
                                <option value="expense">Expense Only</option>
                                <option value="both">Income & Expense</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label required">Priority</label>
                            <select id="priority" class="form-control" required>
                                <option value="">Select Priority</option>
                                <option value="1">1 (Highest)</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                                <option value="5">5</option>
                                <option value="6">6</option>
                                <option value="7">7</option>
                                <option value="8">8 (Lowest)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label required">Applies To</label>
                            <select id="applies_to" class="form-control" required>
                                <option value="">Select Category</option>
                                <option value="all_students">All Students</option>
                                <option value="all_boys">All Boys</option>
                                <option value="all_girls">All Girls</option>
                                <option value="all_day_scholars">All Day Scholars</option>
                                <option value="all_boarders">All Boarders</option>
                                <option value="boarders_boys">Boarders Boys</option>
                                <option value="boarders_girls">Boarders Girls</option>
                                <option value="day_scholars_boys">Day Scholars Boys</option>
                                <option value="day_scholars_girls">Day Scholars Girls</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select id="status" class="form-control">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="form-group" style="grid-column: 1/-1;">
                            <label class="form-label">Description</label>
                            <textarea id="description" class="form-control" placeholder="Additional description for this vote head" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" id="cancelFormBtn">
                        <i class="fas fa-times"></i>
                        <span>Cancel</span>
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        <span id="saveBtnText">Save Vote Head</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Vote Heads Table Card -->
        <div class="table-card">
            <div class="table-header">
                <h2>
                    <i class="fas fa-list"></i>
                    Vote Heads List
                </h2>
                <div class="filter-group">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" placeholder="Search by name or alias...">
                    </div>
                    <select id="typeFilter" class="filter-select">
                        <option value="">All Types</option>
                        <option value="income">Income Only</option>
                        <option value="expense">Expense Only</option>
                        <option value="both">Income & Expense</option>
                    </select>
                    <select id="statusFilter" class="filter-select">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Priority</th>
                            <th>Vote Head Name</th>
                            <th>Alias</th>
                            <th>Type</th>
                            <th>Applies To</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <tr>
                            <td colspan="7" class="loading-state">
                                <i class="fas fa-spinner fa-spin"></i>
                                <p>Loading vote heads...</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="pagination-container">
                <div class="pagination-info">
                    Showing <span id="startCount">0</span> to <span id="endCount">0</span> of <span id="totalCount">0</span> vote heads
                </div>
                <div class="pagination-buttons" id="paginationButtons"></div>
            </div>
        </div>
    </main>

    <!-- Edit Modal -->
    <div id="editModal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header">
                <h3>
                    <i class="fas fa-edit"></i>
                    Edit Vote Head
                </h3>
                <button class="modal-close" onclick="closeEditModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="editForm">
                    <input type="hidden" id="editId">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label required">Vote Head Name</label>
                            <input type="text" id="editName" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label required">Alias</label>
                            <input type="text" id="editAlias" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label required">Type</label>
                            <select id="editType" class="form-control" required>
                                <option value="income">Income Only</option>
                                <option value="expense">Expense Only</option>
                                <option value="both">Income & Expense</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label required">Priority</label>
                            <select id="editPriority" class="form-control" required>
                                <option value="1">1 (Highest)</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                                <option value="5">5</option>
                                <option value="6">6</option>
                                <option value="7">7</option>
                                <option value="8">8 (Lowest)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label required">Applies To</label>
                            <select id="editAppliesTo" class="form-control" required>
                                <option value="all_students">All Students</option>
                                <option value="all_boys">All Boys</option>
                                <option value="all_girls">All Girls</option>
                                <option value="all_day_scholars">All Day Scholars</option>
                                <option value="all_boarders">All Boarders</option>
                                <option value="boarders_boys">Boarders Boys</option>
                                <option value="boarders_girls">Boarders Girls</option>
                                <option value="day_scholars_boys">Day Scholars Boys</option>
                                <option value="day_scholars_girls">Day Scholars Girls</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select id="editStatus" class="form-control">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="form-group" style="grid-column: 1/-1;">
                            <label class="form-label">Description</label>
                            <textarea id="editDescription" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeEditModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button class="btn btn-primary" onclick="saveEdit()">
                    <i class="fas fa-save"></i> Update
                </button>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>

    <script>
        const schoolId = <?php echo json_encode($school_id); ?>;
        let currentPage = 1;
        let itemsPerPage = 10;
        let voteHeads = [];
        let totalItems = 0;

        // Helper Functions
        function getPriorityClass(priority) {
            if (priority <= 2) return 'priority-high';
            if (priority <= 4) return 'priority-medium';
            return 'priority-low';
        }

        function getTypeClass(type) {
            const classes = { income: 'type-income', expense: 'type-expense', both: 'type-both' };
            return classes[type] || 'type-both';
        }

        function getTypeText(type) {
            const texts = { income: 'Income Only', expense: 'Expense Only', both: 'Income & Expense' };
            return texts[type] || type;
        }

        function getAppliesToText(value) {
            const options = {
                'all_students': 'All Students', 'all_boys': 'All Boys', 'all_girls': 'All Girls',
                'all_day_scholars': 'All Day Scholars', 'all_boarders': 'All Boarders',
                'boarders_boys': 'Boarders Boys', 'boarders_girls': 'Boarders Girls',
                'day_scholars_boys': 'Day Scholars Boys', 'day_scholars_girls': 'Day Scholars Girls'
            };
            return options[value] || value;
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.style.cssText = `
                background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : type === 'warning' ? '#f59e0b' : '#1A73E8'};
                color: white;
                padding: 0.75rem 1rem;
                border-radius: 12px;
                margin-bottom: 0.75rem;
                display: flex;
                align-items: center;
                gap: 0.6rem;
                transform: translateX(400px);
                opacity: 0;
                transition: all 0.3s ease;
                max-width: calc(100vw - 1rem);
                font-size: 0.85rem;
                box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            `;
            toast.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                <span>${escapeHtml(message)}</span>
            `;
            
            document.getElementById('toastContainer').appendChild(toast);
            setTimeout(() => { toast.style.transform = 'translateX(0)'; toast.style.opacity = '1'; }, 10);
            setTimeout(() => {
                toast.style.transform = 'translateX(400px)';
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 300);
            }, 5000);
        }

        // Load Vote Heads
        async function loadVoteHeads() {
            const search = document.getElementById('searchInput')?.value || '';
            const typeFilter = document.getElementById('typeFilter')?.value || '';
            const statusFilter = document.getElementById('statusFilter')?.value || '';

            try {
                const response = await fetch('../../api/feesystem/get_vote_heads.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ school_id: schoolId, search: search, type: typeFilter, status: statusFilter })
                });
                const data = await response.json();
                
                if (data.success) {
                    voteHeads = data.vote_heads || [];
                    totalItems = voteHeads.length;
                    renderTable();
                    renderPagination();
                } else {
                    document.getElementById('tableBody').innerHTML = `<tr><td colspan="7" class="empty-state"><i class="fas fa-exclamation-triangle"></i><p>${escapeHtml(data.message)}</p></td></tr>`;
                }
            } catch (error) {
                console.error('Error:', error);
                document.getElementById('tableBody').innerHTML = `<tr><td colspan="7" class="empty-state"><i class="fas fa-exclamation-triangle"></i><p>Error loading vote heads</p></td></tr>`;
            }
        }

        function renderTable() {
            const start = (currentPage - 1) * itemsPerPage;
            const end = start + itemsPerPage;
            const pageData = voteHeads.slice(start, end);
            const tbody = document.getElementById('tableBody');
            
            if (pageData.length === 0) {
                tbody.innerHTML = `<tr><td colspan="7" class="empty-state"><i class="fas fa-inbox"></i><p>No vote heads found</p></td></tr>`;
                return;
            }
            
            tbody.innerHTML = pageData.map(vh => `
                <tr>
                    <td><span class="priority-badge ${getPriorityClass(vh.priority)}">${vh.priority}</span></td>
                    <td><strong>${escapeHtml(vh.name)}</strong></td>
                    <td>${escapeHtml(vh.alias)}</td>
                    <td><span class="type-badge ${getTypeClass(vh.type)}">${getTypeText(vh.type)}</span></td>
                    <td>${getAppliesToText(vh.applies_to)}</td>
                    <td><span class="status-badge ${vh.status === 'active' ? 'status-active' : 'status-inactive'}">${vh.status === 'active' ? 'Active' : 'Inactive'}</span></td>
                    <td>
                        <div class="action-buttons">
                            <button class="action-btn edit" onclick="openEditModal(${vh.id})" title="Edit"><i class="fas fa-edit"></i></button>
                            <button class="action-btn delete" onclick="deleteVoteHead(${vh.id})" title="Delete"><i class="fas fa-trash"></i></button>
                        </div>
                    </td>
                </tr>
            `).join('');
            
            document.getElementById('startCount').textContent = totalItems > 0 ? start + 1 : 0;
            document.getElementById('endCount').textContent = Math.min(end, totalItems);
            document.getElementById('totalCount').textContent = totalItems;
        }

        function renderPagination() {
            const totalPages = Math.ceil(totalItems / itemsPerPage);
            const container = document.getElementById('paginationButtons');
            
            if (totalPages <= 1) {
                container.innerHTML = '';
                return;
            }
            
            let html = '';
            if (currentPage > 1) {
                html += `<button class="pagination-btn" onclick="changePage(${currentPage - 1})"><i class="fas fa-chevron-left"></i> Prev</button>`;
            }
            
            for (let i = 1; i <= totalPages; i++) {
                if (i === currentPage) {
                    html += `<button class="pagination-btn active">${i}</button>`;
                } else if (Math.abs(i - currentPage) <= 2 || i === 1 || i === totalPages) {
                    html += `<button class="pagination-btn" onclick="changePage(${i})">${i}</button>`;
                } else if (Math.abs(i - currentPage) === 3) {
                    html += `<span class="pagination-btn" style="border: none;">...</span>`;
                }
            }
            
            if (currentPage < totalPages) {
                html += `<button class="pagination-btn" onclick="changePage(${currentPage + 1})">Next <i class="fas fa-chevron-right"></i></button>`;
            }
            
            container.innerHTML = html;
        }

        function changePage(page) {
            currentPage = page;
            renderTable();
            renderPagination();
        }

        // Form Functions
        function openAddForm() {
            document.getElementById('formCard').style.display = 'block';
            document.getElementById('formTitle').innerHTML = '<i class="fas fa-plus-circle"></i><span>Add New Vote Head</span>';
            document.getElementById('voteHeadId').value = '';
            document.getElementById('voteHeadForm').reset();
            document.getElementById('saveBtnText').textContent = 'Save Vote Head';
            document.getElementById('formCard').scrollIntoView({ behavior: 'smooth' });
        }

        function closeForm() {
            document.getElementById('formCard').style.display = 'none';
            document.getElementById('voteHeadForm').reset();
        }

        // Form Submit
        document.getElementById('voteHeadForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = {
                school_id: schoolId,
                name: document.getElementById('name').value.trim(),
                alias: document.getElementById('alias').value.trim().toUpperCase(),
                type: document.getElementById('type').value,
                priority: parseInt(document.getElementById('priority').value),
                applies_to: document.getElementById('applies_to').value,
                status: document.getElementById('status').value,
                description: document.getElementById('description').value.trim()
            };
            
            if (!formData.name || !formData.alias || !formData.type || !formData.priority || !formData.applies_to) {
                showToast('Please fill in all required fields', 'error');
                return;
            }
            
            const submitBtn = e.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<div class="spinner-small"></div> Saving...';
            submitBtn.disabled = true;
            
            try {
                const response = await fetch('../../api/feesystem/save_vote_head.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(formData)
                });
                const data = await response.json();
                
                if (data.success) {
                    showToast('Vote head saved successfully', 'success');
                    closeForm();
                    currentPage = 1;
                    loadVoteHeads();
                } else {
                    showToast(data.message || 'Failed to save', 'error');
                }
            } catch (error) {
                showToast('An error occurred', 'error');
            } finally {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        });

        // Open Edit Modal
        async function openEditModal(id) {
            try {
                const response = await fetch('../../api/feesystem/get_vote_head.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id, school_id: schoolId })
                });
                const data = await response.json();
                
                if (data.success && data.vote_head) {
                    const vh = data.vote_head;
                    document.getElementById('editId').value = vh.id;
                    document.getElementById('editName').value = vh.name;
                    document.getElementById('editAlias').value = vh.alias;
                    document.getElementById('editType').value = vh.type;
                    document.getElementById('editPriority').value = vh.priority;
                    document.getElementById('editAppliesTo').value = vh.applies_to;
                    document.getElementById('editStatus').value = vh.status;
                    document.getElementById('editDescription').value = vh.description || '';
                    document.getElementById('editModal').classList.add('active');
                    document.body.style.overflow = 'hidden';
                } else {
                    showToast('Failed to load vote head data', 'error');
                }
            } catch (error) {
                showToast('An error occurred', 'error');
            }
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        async function saveEdit() {
            const formData = {
                id: parseInt(document.getElementById('editId').value),
                school_id: schoolId,
                name: document.getElementById('editName').value.trim(),
                alias: document.getElementById('editAlias').value.trim().toUpperCase(),
                type: document.getElementById('editType').value,
                priority: parseInt(document.getElementById('editPriority').value),
                applies_to: document.getElementById('editAppliesTo').value,
                status: document.getElementById('editStatus').value,
                description: document.getElementById('editDescription').value.trim()
            };
            
            if (!formData.name || !formData.alias || !formData.type || !formData.priority || !formData.applies_to) {
                showToast('Please fill in all required fields', 'error');
                return;
            }
            
            const saveBtn = document.querySelector('#editModal .btn-primary');
            const originalText = saveBtn.innerHTML;
            saveBtn.innerHTML = '<div class="spinner-small"></div> Updating...';
            saveBtn.disabled = true;
            
            try {
                const response = await fetch('../../api/feesystem/update_vote_head.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(formData)
                });
                const data = await response.json();
                
                if (data.success) {
                    showToast('Vote head updated successfully', 'success');
                    closeEditModal();
                    currentPage = 1;
                    loadVoteHeads();
                } else {
                    showToast(data.message || 'Failed to update', 'error');
                }
            } catch (error) {
                showToast('An error occurred', 'error');
            } finally {
                saveBtn.innerHTML = originalText;
                saveBtn.disabled = false;
            }
        }

        async function deleteVoteHead(id) {
            const result = await Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            });
            
            if (result.isConfirmed) {
                try {
                    const response = await fetch('../../api/feesystem/delete_vote_head.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: id, school_id: schoolId })
                    });
                    const data = await response.json();
                    
                    if (data.success) {
                        showToast('Vote head deleted successfully', 'success');
                        currentPage = 1;
                        loadVoteHeads();
                    } else {
                        showToast(data.message || 'Failed to delete', 'error');
                    }
                } catch (error) {
                    showToast('An error occurred', 'error');
                }
            }
        }

        // Event Listeners
        document.getElementById('addVoteHeadBtn')?.addEventListener('click', openAddForm);
        document.getElementById('closeFormBtn')?.addEventListener('click', closeForm);
        document.getElementById('cancelFormBtn')?.addEventListener('click', closeForm);
        document.getElementById('searchInput')?.addEventListener('input', () => { currentPage = 1; loadVoteHeads(); });
        document.getElementById('typeFilter')?.addEventListener('change', () => { currentPage = 1; loadVoteHeads(); });
        document.getElementById('statusFilter')?.addEventListener('change', () => { currentPage = 1; loadVoteHeads(); });
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') { closeEditModal(); closeForm(); } });
        
        // Close modal on overlay click
        document.getElementById('editModal')?.addEventListener('click', (e) => {
            if (e.target === document.getElementById('editModal')) closeEditModal();
        });

        // Initialize
        document.addEventListener('DOMContentLoaded', loadVoteHeads);
    </script>
</body>
</html>

<?php include_once('../../includes/footer.php'); ?>