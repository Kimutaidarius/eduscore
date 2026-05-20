<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include('includes/config.php'); // Ensure this path is correct

if (!isset($_SESSION['id']) || empty($_SESSION['id']) || !isset($_SESSION['login']) || empty($_SESSION['login']) || !isset($_SESSION['school_id']) || empty($_SESSION['school_id'])) {
    header('location:index.php');
    exit();
}

$loggedInUserId = $_SESSION['id']; // Get the logged-in user's ID
$schoolId = $_SESSION['school_id']; // Get the logged-in school's ID
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>EDUSCORE</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/common-styles.css">
    <link rel="shortcut icon" href="images/icon.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
/* --- Reports Wrapper --- */
.reports-wrapper {
  background: #fff;
  border-radius: 16px;
  padding: 25px 30px 60px;
  box-shadow: 0 10px 25px rgba(0,0,0,0.08);
  margin-top: 20px;
  position: relative;
  overflow: hidden;
}

/* --- Shared Export / Print Buttons (top right) --- */
.reports-actions {
  position: absolute;
  top: 25px;
  right: 30px;
  display: flex;
  gap: 10px;
}

.export-btn,
.print-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  border: none;
  border-radius: 30px;
  padding: 10px 18px;
  font-size: 14px;
  font-weight: 600;
  color: #fff;
  cursor: pointer;
  transition: all 0.3s ease;
}

.export-btn {
  background: linear-gradient(135deg, #0066ff, #00ccff);
  box-shadow: 0 4px 10px rgba(0,102,255,0.25);
}

.export-btn:hover {
  background: linear-gradient(135deg, #00ccff, #0066ff);
  transform: scale(1.05);
  box-shadow: 0 0 12px rgba(0,153,255,0.45);
}

.print-btn {
  background: linear-gradient(135deg, #10b981, #34d399);
  box-shadow: 0 4px 10px rgba(16,185,129,0.25);
}

.print-btn:hover {
  background: linear-gradient(135deg, #34d399, #10b981);
  transform: scale(1.05);
  box-shadow: 0 0 12px rgba(16,185,129,0.45);
}

/* --- Futuristic Tabs --- */
.reports-tabs {
  display: flex;
  justify-content: flex-start;
  border-bottom: 2px solid #e5e9f0;
  gap: 8px;
  flex-wrap: wrap;
  margin-bottom: 25px;
  padding-bottom: 10px;
}

.tab-button {
  background: linear-gradient(135deg, #0066ff, #00ccff);
  border: none;
  color: white;
  padding: 10px 20px;
  border-radius: 30px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.25s ease;
}

.tab-button:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 10px rgba(0,102,255,0.3);
}

.tab-button.active {
  background: linear-gradient(135deg, #00ccff, #0066ff);
  box-shadow: 0 0 12px rgba(0,153,255,0.4);
}

/* --- Report Content Sections --- */
.report-section {
  display: none;
  padding-top: 25px;
  animation: fadeIn 0.5s ease;
}

.report-section.active {
  display: block;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

/* --- Action Buttons --- */
.add-new-btn {
  background: linear-gradient(135deg, #10b981, #34d399);
  box-shadow: 0 4px 10px rgba(16,185,129,0.25);
  padding: 8px 14px; /* Reduced padding */
  font-size: 13px;    /* Slightly smaller text */
  border-radius: 8px; 
  margin: 4px;
}

.add-new-btn:hover {
  background: linear-gradient(135deg, #34d399, #10b981);
  transform: scale(1.05);
  box-shadow: 0 0 12px rgba(16,185,129,0.4);
}

/* --- Responsive Design --- */
@media (max-width: 768px) {
  .reports-actions {
    position: static;
    justify-content: center;
    margin-top: 15px;
  }
  .reports-tabs {
    justify-content: center;
  }
}
/* --- Merit List Controls --- */
.merit-controls {
  display: flex;
  gap: 15px;
  align-items: center;
  margin-bottom: 20px;
  flex-wrap: wrap;
}

.modern-select {
  padding: 10px 15px;
  border: 1.5px solid #d1d5db;
  border-radius: 25px;
  background: #f9fafb;
  color: #111827;
  font-weight: 500;
  outline: none;
  transition: all 0.3s ease;
}

.modern-select:hover, .modern-select:focus {
  border-color: #3b82f6;
  background: #fff;
  box-shadow: 0 0 10px rgba(59,130,246,0.2);
}

.add-new-btn {
  background: linear-gradient(135deg, #10b981, #34d399);
  box-shadow: 0 4px 10px rgba(16,185,129,0.25);
}

.add-new-btn:hover {
  background: linear-gradient(135deg, #34d399, #10b981);
  transform: scale(1.05);
  box-shadow: 0 0 12px rgba(16,185,129,0.4);
}

/* --- Modern Table --- */
.table-container {
  overflow-x: auto;
}

.modern-table {
  width: 100%;
  border-collapse: collapse;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 4px 20px rgba(0,0,0,0.05);
}

.modern-table th {
  background: linear-gradient(135deg, #2563eb, #3b82f6);
  color: #fff;
  text-align: left;
  padding: 12px 16px;
  font-weight: 600;
}

.modern-table td {
  padding: 12px 16px;
  border-bottom: 1px solid #e5e7eb;
  background: #fff;
}

.modern-table tr:hover td {
  background: #f9fafb;
}

/* --- Table Actions --- */
.table-action {
  background: none;
  border: none;
  color: #2563eb;
  cursor: pointer;
  margin: 0 5px;
  transition: 0.3s ease;
}

.table-action:hover {
  transform: scale(1.15);
}

.table-action.view { color: #3b82f6; }
.table-action.edit { color: #10b981; }
.table-action.delete { color: #ef4444; }

/* --- Status Badges --- */
.status-badge {
  padding: 4px 10px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
}

.status-badge.active {
  background: #dcfce7;
  color: #15803d;
}

.status-badge.draft {
  background: #fef9c3;
  color: #92400e;
}
/* Filter/Search Bar */
.filter-bar {
  margin: 10px 0 20px 0;
  display: flex;
  justify-content: flex-end;
}

.search-input {
  width: 260px;
  padding: 8px 12px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-size: 14px;
  outline: none;
  transition: all 0.3s ease;
}

.search-input:focus {
  border-color: #3b82f6;
  box-shadow: 0 0 6px rgba(59,130,246,0.4);
}
/* Generate Button */
.generate-btn {
  background: linear-gradient(135deg, #2563eb, #1e40af);
  box-shadow: 0 4px 10px rgba(37, 99, 235, 0.25);
  padding: 10px 20px;
  border-radius: 8px;
  font-weight: 600;
  letter-spacing: 0.3px;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  position: relative;
  overflow: hidden;
}

.generate-btn i {
  margin-right: 6px;
  transition: transform 0.6s ease;
}

/* Hover animation */
.generate-btn:hover {
  background: linear-gradient(135deg, #1e40af, #2563eb);
  transform: translateY(-2px);
  box-shadow: 0 6px 15px rgba(37, 99, 235, 0.35);
}

/* Light sweep glow */
.generate-btn::after {
  content: "";
  position: absolute;
  top: 0;
  left: -75%;
  width: 50%;
  height: 100%;
  background: rgba(255, 255, 255, 0.15);
  transform: skewX(-25deg);
  transition: left 0.6s ease;
}

.generate-btn:hover::after {
  left: 130%;
}

/* Rotate once on hover */
.generate-btn:hover i {
  transform: rotate(360deg);
}

/* Active pressed effect */
.generate-btn:active {
  transform: scale(0.97);
  box-shadow: 0 3px 8px rgba(37, 99, 235, 0.25);
}

/* --- Continuous Spin Animation --- */
@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

/* When loading: continuous rotation */
.generate-btn.loading i {
  animation: spin 1.2s linear infinite;
}

/* Optional: subtle dimming effect while loading */
.generate-btn.loading {
  opacity: 0.9;
  cursor: not-allowed;
}
/* --- Analytics Section --- */
.analytics-filters {
  display: flex;
  gap: 10px;
  align-items: center;
  flex-wrap: wrap;
  margin-bottom: 25px;
}
/* --- Analytics Section --- */
.analytics-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
  gap: 20px;
}

.analytics-card {
  background: #ffffff;
  border-radius: 14px;
  padding: 20px;
  box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
}

.analytics-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 10px 24px rgba(0, 0, 0, 0.12);
}

.analytics-card h4 {
  display: flex;
  align-items: center;
  font-weight: 600;
  margin-bottom: 15px;
  color: #1e3a8a;
  font-size: 15px;
}

.analytics-card h4 i {
  margin-right: 8px;
  font-size: 16px;
}

/* --- Card Tools --- */
.card-tools {
  position: absolute;
  top: 10px;
  right: 10px;
  display: flex;
  gap: 8px;
}

.card-icon {
  background: rgba(240, 247, 255, 0.9);
  border: none;
  color: #1e3a8a;
  padding: 6px 8px;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.25s ease;
  font-size: 13px;
}

.card-icon:hover {
  background: linear-gradient(135deg, #2563eb, #3b82f6);
  color: white;
  transform: scale(1.1);
  box-shadow: 0 3px 10px rgba(37,99,235,0.25);
}

/* Chart placeholders */
.analytics-card canvas {
  width: 100%;
  height: 200px;
}

/* Icon colors */
.text-pink { color: #ec4899; }
.text-danger { color: #ef4444; }
.text-success { color: #10b981; }
.text-warning { color: #f59e0b; }

/* Adjust colors for icons */
.text-pink { color: #ec4899; }
.text-danger { color: #ef4444; }
.text-success { color: #10b981; }
.text-warning { color: #f59e0b; }
.message-controls {
  display: flex;
  gap: 10px;
  align-items: center;
  margin-bottom: 15px;
  flex-wrap: wrap;
}
.table-search-bar {
  position: relative;
  margin-bottom: 15px;
  display: flex;
  justify-content: flex-end;
}

.table-search-input {
  width: 100%;
  max-width: 400px;
  padding: 10px 42px 10px 14px; /* space for icon */
  border-radius: 10px;
  border: 1px solid #d1d5db;
  background: #f9fafb;
  font-size: 14px;
  transition: all 0.3s ease;
  color: #111827;
}

.table-search-input::placeholder {
  color: #9ca3af;
}

.table-search-input:focus {
  outline: none;
  background: #fff;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59,130,246,0.15);
}
/* Make table scrollable with both horizontal and vertical scrollbars */
.table-container {
  overflow-x: auto; /* existing horizontal scroll */
  overflow-y: auto; /* vertical scroll */
  max-height: 400px; /* adjust height as needed */
}

.search-icon {
  position: absolute;
  right: 16px;
  top: 50%;
  transform: translateY(-50%);
  color: #6b7280;
  font-size: 15px;
  pointer-events: none; /* allows click-through */
  transition: color 0.3s ease;
}

.table-search-input:focus + .search-icon {
  color: #2563eb;
}

.table-action.download {
  background-color: #10b981;
}

.table-action.download:hover {
  background-color: #059669;
}

.table-action.print {
  background-color: #f59e0b;
}

.table-action.print:hover {
  background-color: #d97706;
}
/* Normal generate button look */
.generate-btn {
  background: linear-gradient(135deg, #2563eb, #3b82f6);
  box-shadow: 0 4px 10px rgba(37, 99, 235, 0.25);
  transition: all 0.3s ease;
}

.generate-btn:hover {
  background: linear-gradient(135deg, #3b82f6, #2563eb);
  transform: scale(1.05);
  box-shadow: 0 0 12px rgba(37, 99, 235, 0.4);
}

/* Spinning gear animation */
@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

/* When button is processing */
.generate-btn.processing i {
  animation: spin 1.2s linear infinite;
}

/* Optional: dim the button slightly during processing */
.generate-btn.processing {
  opacity: 0.85;
  cursor: not-allowed;
  pointer-events: none;
}

/* === Unified Button Sizes and Styles === */
.action-btn,
.add-new-btn,
.export-btn,
.print-btn,
.tab-button {
  padding: 10px 20px !important;
  border-radius: 8px !important;
  font-size: 14px !important;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  transition: all 0.3s ease;
  letter-spacing: 0.3px;
}

/* Match spacing and height for consistency */
.action-btn i,
.add-new-btn i,
.export-btn i,
.print-btn i,
.tab-button i {
  font-size: 15px;
}

/* Make sure small buttons don't appear "shorter" */
.add-new-btn {
  min-height: 42px;
}

/* Keep existing gradients but match proportions */
.export-btn,
.print-btn {
  height: 42px;
  line-height: 1;
  padding: 10px 20px;
}

/* Optional: keep “Generate” hover glow on all action buttons */
.action-btn:hover,
.add-new-btn:hover,
.export-btn:hover,
.print-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
}
/* ===== MODAL OVERLAY ===== */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.55);
  backdrop-filter: blur(5px);
  display: none;
  align-items: center;
  justify-content: center;
  z-index: 2000;
  animation: fadeIn 0.3s ease;
}

.modal-overlay.active {
  display: flex;
}

/* ===== MODAL BOX ===== */
.modal-content {
  background: #fff;
  border-radius: 14px;
  padding: 25px 30px;
  width: 100%;
  max-width: 550px;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
  position: relative;
  animation: slideUp 0.4s ease;
}

@keyframes slideUp {
  from { opacity: 0; transform: translateY(20px); }
  to { opacity: 1; transform: translateY(0); }
}

.modal-content h3 {
  margin-bottom: 8px;
  color: #1e3a8a;
  display: flex;
  align-items: center;
  gap: 8px;
}

.modal-subtitle {
  color: #6b7280;
  font-size: 14px;
  margin-bottom: 18px;
}

.modal-form label {
  display: block;
  margin-bottom: 6px;
  font-weight: 600;
  color: #374151;
}

.modal-form select {
  width: 100%;
  margin-bottom: 14px;
}

.checkbox-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
  margin-bottom: 16px;
}

.checkbox-inline {
  display: flex;
  align-items: center;
  gap: 6px;
}

.small {
  width: auto;
  min-width: 160px;
}

.modal-buttons {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

.cancel-btn {
  background: #f3f4f6;
  color: #111827;
}

.cancel-btn:hover {
  background: #e5e7eb;
  transform: scale(1.03);
}

/* ####################################################### */
/* ################ FIXES FOR DROPDOWN AND SCROLL ################ */
/* ####################################################### */

/* --- Table Scroll Container (Added Padding for Dropdown Space) --- */
.table-scroll {
  /* Key scroll properties for always visible vertical scroll */
  max-height: 400px;
  overflow-y: scroll;
  overflow-x: hidden; 
  
  /* ADDED PADDING: Creates space at the bottom of the scrollable area 
     so the dropdown menu in the last row can fully extend without clipping. */
  padding-bottom: 30px; 
  
  /* Retaining your existing styles: */
  border: 1px solid #ddd;
  border-radius: 5px;
  position: relative;

  /* For Firefox compatibility: */
  scrollbar-color: #3b82f6 #f1f1f1;
  scrollbar-width: thin;
}

/* Ensure the table itself takes up its full content width inside the wrapper */
.table-scroll table {
  width: 100%;
  border-collapse: collapse;
}

/* Fix the table header (thead) to the top when scrolling vertically (recommended) */
.modern-table thead th {
  position: sticky;
  top: 0;
  z-index: 10;
  background: linear-gradient(135deg, #2563eb, #3b82f6); 
}

/* Webkit scrollbar styles */
.table-scroll::-webkit-scrollbar {
  width: 12px;
}

.table-scroll::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 6px;
}

.table-scroll::-webkit-scrollbar-thumb {
  background: #3b82f6;
  border-radius: 6px;
}

.table-scroll::-webkit-scrollbar-thumb:hover {
  background: #2563eb;
}

/* --- Dropdown Z-Index Fixes --- */
.dropdown {
  position: relative;
  display: inline-block;
  /* INCREASED Z-INDEX: Ensures the dropdown button/container layers above the table data */
  z-index: 1000; 
}

.dropdown-menu {
  display: none;
  position: absolute;
  right: 0;
  background: rgba(10,10,30,0.95);
  min-width: 180px;
  border-radius: 10px;
  box-shadow: 0 0 15px #0ff;
  /* INCREASED Z-INDEX: Ensures the menu layers above EVERYTHING else in the table */
  z-index: 1001; 
  padding: 5px 0;
}

.dropdown-menu.show { display: block; }

/* The rest of the dropdown styles (ellipsis-btn, etc.) are kept as is */

/* ======= FUTURISTIC DROPDOWN ======= */
.ellipsis-btn {
  background: #1a1a1a;
  border: none;
  color: #0ff;
  font-size: 20px;
  cursor: pointer;
  padding: 5px 10px;
  border-radius: 6px;
  transition: 0.2s;
}

.ellipsis-btn:hover {
  background: #0ff;
  color: #000;
}

.dropdown-menu li {
  padding: 8px 15px;
  color: #0ff;
  cursor: pointer;
  list-style: none;
  display: flex;
  align-items: center;
  gap: 10px;
  transition: 0.2s;
}

.dropdown-menu li:hover {
  background: #0ff;
  color: #000;
  border-radius: 6px;
}
/* ===== Spinner ===== */
.spinner {
  border: 3px solid rgba(0, 255, 255, 0.2);
  border-top: 3px solid #0ff;
  border-radius: 50%;
  width: 18px;
  height: 18px;
  animation: spin 1s linear infinite;
  display: inline-block;
}

/* ===== Row Glow ===== */
.processing-row {
  animation: glow 1s ease-in-out infinite alternate;
}

@keyframes glow {
  0% { box-shadow: 0 0 5px #0ff; }
  100% { box-shadow: 0 0 15px #0ff; }
}

.status-completed {
  color: #0f0;
  font-weight: bold;
  display: flex;
  align-items: center;
  gap: 5px;
}

.futuristic-dropdown {
  list-style: none;
  margin: 0;
  padding: 0;
  background: #111;
  color: #0ff;
  border: 1px solid #0ff;
  border-radius: 8px;
  display: none;
  position: absolute;
  min-width: 160px;
  z-index: 1000;
}

.futuristic-dropdown.show {
  display: block;
}

.futuristic-dropdown li {
  padding: 10px;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 8px;
}

.futuristic-dropdown li:hover {
  background: #0ff2;
}

.spinner {
  display: inline-block;
  width: 16px;
  height: 16px;
  border: 2px solid rgba(0, 0, 0, 0.2);
  border-top-color: #3498db; /* Blue color, can change to match theme */
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  vertical-align: middle;
  margin-right: 5px; /* spacing before text */
}

/* Optional larger spinner for modal */
.modal .spinner {
  width: 20px;
  height: 20px;
  border-width: 3px;
}

/* Spin animation */
@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

/* Optional: fade-in effect for status messages */
.regenerate-status {
  display: flex;
  align-items: center;
  gap: 5px;
  font-weight: 500;
}
/* =========================
   FUTURISTIC MODAL STYLING (LIGHT VERSION)
========================= */
.modal {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(255, 255, 255, 0.8);
  justify-content: center;
  align-items: center;
  z-index: 1000;
  backdrop-filter: blur(6px);
  transition: opacity 0.3s ease-in-out;
  opacity: 0;
}

.modal.active {
  display: flex;
  opacity: 1;
  animation: modalFadeIn 0.3s ease forwards;
}

@keyframes modalFadeIn {
  0% { transform: translateY(-30px); opacity: 0; }
  100% { transform: translateY(0); opacity: 1; }
}

.modal-content {
  background: linear-gradient(145deg, #ffffff, #f0f0f0);
  padding: 25px 30px;
  border-radius: 16px;
  width: 90%;
  max-width: 420px;
  text-align: center;
  color: #00bcd4;
  font-family: 'Orbitron', sans-serif;
  box-shadow: 0 0 20px rgba(0, 188, 212, 0.3), 0 0 40px rgba(0, 188, 212, 0.15);
  border: 1px solid rgba(0, 188, 212, 0.4);
  transform: scale(0.9);
  animation: contentPop 0.3s forwards;
}

@keyframes contentPop {
  0% { transform: scale(0.8); opacity: 0; }
  100% { transform: scale(1); opacity: 1; }
}

.modal h2, .modal p, .modal .modal-text {
  margin: 0;
  padding: 5px 0;
  color: #007c91;
}

.modal-actions {
  margin-top: 25px;
  display: flex;
  justify-content: center;
  gap: 15px;
}

.modal-actions button {
  padding: 10px 20px;
  background: linear-gradient(90deg, #00e5ff, #00bcd4);
  border: none;
  border-radius: 8px;
  color: #fff;
  font-weight: bold;
  cursor: pointer;
  text-transform: uppercase;
  letter-spacing: 1px;
  transition: all 0.3s ease;
  box-shadow: 0 0 10px rgba(0, 188, 212, 0.4), 0 0 20px rgba(0, 229, 255, 0.25);
}

.modal-actions button:hover {
  background: linear-gradient(90deg, #00bcd4, #00e5ff);
  transform: scale(1.05);
  box-shadow: 0 0 15px rgba(0, 188, 212, 0.6), 0 0 30px rgba(0, 229, 255, 0.4);
}

/* Spinner for processing */
.spinner {
  display: inline-block;
  width: 18px;
  height: 18px;
  border: 3px solid rgba(0, 188, 212, 0.3);
  border-top-color: #00e5ff;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  vertical-align: middle;
  margin-right: 6px;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

</style>
</head>
<body class="top-navbar-fixed">
<div class="main-wrapper">
  <?php include('includes/leftbar.php');?> 
    <div class="content-wrapper">
        <div class="content-container">
            <?php include('includes/topbar.php');?> 
            <div class="main-page">
                <div class="container-fluid">
                    <div class="row page-title-div">
                        <div class="col-md-6">
                            <h2 class="title">Reports</h2>
                        </div>
                    </div>
                </div>
<div class="container-fluid">
<div class="reports-wrapper">

  <!-- Tabs Navigation -->
  <div class="reports-tabs">
    <button class="tab-button active" data-tab="meritList">
      <i class="fa fa-trophy"></i> Merit List
    </button>
    <button class="tab-button" data-tab="reportCards">
      <i class="fa fa-file-alt"></i> Report Cards
    </button>
    <button class="tab-button" data-tab="analytics">
      <i class="fa fa-chart-line"></i> Analytics
    </button>
    <button class="tab-button" data-tab="messageCards">
      <i class="fa fa-envelope-open-text"></i> Message Cards
    </button>
  </div>

  <!-- Shared Export/Print Buttons -->
  <div class="reports-actions">
    <button class="export-btn"><i class="fa fa-file-export"></i> Export</button>
    <button class="print-btn"><i class="fa fa-print"></i> Print</button>
  </div>

  <!-- MERIT LIST SECTION -->
  <div id="meritList" class="report-section active">
    <h3><i class="fa fa-trophy text-warning"></i> Merit List</h3>
    <p>View ranked student performance across exams and classes.</p>

    <div class="merit-controls">
      <select id="meritClassSelect" class="modern-select">
        <option value="">Select Class</option>
      </select>
      <select id="meritStreamSelect" class="modern-select">
        <option value="">Select Stream</option>
      </select>
      <button class="action-btn add-new-btn"><i class="fa fa-plus-circle"></i> Add New</button>
    </div>

    <div class="filter-bar">
      <input type="text" class="search-input" placeholder="🔍 Search by name or status...">
    </div>

    <div class="table-responsive">
      <table id="meritListTable" class="display">
        <thead>
          <tr>
            <th>Merit List</th>
            <th>Rank</th>
            <th>Status</th>
            <th>Mean Score</th>
            <th>Top Student</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <!-- Data will be dynamically inserted here -->
        </tbody>
      </table>
    </div>
  </div>

  <!-- REPORT CARDS SECTION -->
<div id="reportCards" class="report-section">
    <h3><i class="fa fa-file-alt text-primary"></i> Report Cards</h3>
    <p>Generate, view, and manage student report cards by class, exam, and term.</p>

    <div class="report-controls">
      <!-- Class Dropdown -->
      <select class="modern-select" id="classSelect">
        <option value="">Select Class</option>
      </select>

      <!-- Stream Dropdown -->
      <select class="modern-select" id="streamSelect">
        <option value="">Select Stream</option>
      </select>

      <!-- Exam Dropdown -->
      <select class="modern-select" id="examSelect">
        <option value="">Select Exam</option>
      </select>

      <!-- Term Dropdown -->
      <select class="modern-select" id="termSelect">
        <option value="">Select Term</option>
      </select>

      <!-- Generate Button -->
      <button class="action-btn generate-btn"><i class="fa fa-cogs"></i> Generate</button>
    </div>

    <div class="filter-bar">
      <input type="text" class="search-input" placeholder="🔍 Search by report name or status...">
    </div>

    <div class="table-responsive">
      <table class="display">
        <thead>
          <tr>
            <th>Report Card</th>
            <th>Exam</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <!-- Data will be dynamically inserted here -->
        </tbody>
      </table>
    </div>
</div>

  <!-- ANALYTICS SECTION -->
  <div id="analytics" class="report-section">
    <h3><i class="fa fa-chart-line text-success"></i> Analytics</h3>
    <p>View academic performance trends and visual insights.</p>

    <div class="analytics-filters">
      <select class="modern-select">
        <option value="">Select Class</option>
      </select>
      <select class="modern-select">
        <option value="">Select Exam</option>
      </select>
      <button class="action-btn generate-btn"><i class="fa fa-sync-alt"></i> Refresh Analytics</button>
    </div>

    <div class="analytics-grid">
      <div class="analytics-card">
        <div class="card-tools">
          <button class="card-icon download"><i class="fa fa-download"></i></button>
          <button class="card-icon print"><i class="fa fa-print"></i></button>
        </div>
        <h4><i class="fa fa-medal text-warning"></i> Subject Champions</h4>
        <canvas id="subjectChampionsChart"></canvas>
      </div>

      <div class="analytics-card">
        <div class="card-tools">
          <button class="card-icon download"><i class="fa fa-download"></i></button>
          <button class="card-icon print"><i class="fa fa-print"></i></button>
        </div>
        <h4><i class="fa fa-venus-mars text-pink"></i> Gender Analysis</h4>
        <canvas id="genderAnalysisChart"></canvas>
      </div>

      <div class="analytics-card">
        <div class="card-tools">
          <button class="card-icon download"><i class="fa fa-download"></i></button>
          <button class="card-icon print"><i class="fa fa-print"></i></button>
        </div>
        <h4><i class="fa fa-arrow-up text-success"></i> Most Improved Students</h4>
        <canvas id="improvedChart"></canvas>
      </div>

      <div class="analytics-card">
        <div class="card-tools">
          <button class="card-icon download"><i class="fa fa-download"></i></button>
          <button class="card-icon print"><i class="fa fa-print"></i></button>
        </div>
        <h4><i class="fa fa-arrow-down text-danger"></i> Most Declined Students</h4>
        <canvas id="declinedChart"></canvas>
      </div>
    </div>
  </div>

  <!-- MESSAGE CARDS SECTION -->
  <div id="messageCards" class="report-section">
    <h3><i class="fa fa-envelope-open-text text-info"></i> Message Cards</h3>
    <p>Generate and print student message cards containing fee balances, important notices, and next term details.</p>

    <div class="message-controls">
      <select class="modern-select">
        <option value="">Select Class</option>
      </select>
      <select class="modern-select">
        <option value="">Select Stream</option>
      </select>
      <button class="action-btn generate-btn"><i class="fa fa-cogs"></i> Generate</button>
    </div>

    <div class="table-search-bar">
      <input type="text" placeholder="Search by student name or status..." class="table-search-input">
      <i class="fa fa-search search-icon"></i>
    </div>

    <div class="table-responsive">
      <table class="display">
        <thead>
          <tr>
            <th>Message Card</th>
            <th>Class</th>
            <th>Status</th>
            <th>Date Generated</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <!-- Data will be dynamically inserted here -->
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- ===================== MODAL OVERLAYS ===================== -->

<!-- MERIT LIST MODAL -->
<div id="meritModal" class="modal-overlay">
  <div class="modal-content">
    <h3><i class="fa fa-trophy text-warning"></i> Generate Merit List</h3>
    <p class="modal-subtitle">Select filters and generate ranked results.</p>

    <div class="modal-form">
      <label>Select Class</label>
      <select class="modern-select" data-role="class-select">
        <option value="">Select Class</option>
      </select>

      <label>Select Stream</label>
      <select class="modern-select" data-role="stream-select">
        <option value="">Select Stream</option>
      </select>

      <label>Select Term</label>
      <select class="modern-select" data-role="term-select">
        <option value="">Select Term</option>
      </select>

      <label>Select Exam</label>
      <select class="modern-select" data-role="exam-select">
        <option value="">Select Exam</option>
      </select>

      <label>Performance Type</label> <!-- renamed from “Compute By” -->
      <select class="modern-select">
        <option value="">Overall</option>
        <option value="">Subject Wise</option>
        <option value="">Stream Wise</option>
      </select>

      <div class="checkbox-row">
        <label>Rank Students By:</label>
        <select class="modern-select small">
          <option value="">Mean Score</option>
          <option value="">Total Marks</option>
        </select>

        <div class="checkbox-inline">
          <input type="checkbox" id="showSubjects">
          <label for="showSubjects">Show Subjects</label>
        </div>

        <select class="modern-select small">
          <option value="">Compulsory</option>
          <option value="">Optional</option>
          <option value="">Both</option>
        </select>
      </div>

      <div class="modal-buttons">
        <button class="action-btn generate-btn"><i class="fa fa-cogs"></i> Generate</button>
        <button class="action-btn cancel-btn"><i class="fa fa-times"></i> Cancel</button>
      </div>
    </div>
  </div>
</div>

<!-- ===================== MODAL OVERLAYS ===================== -->

<!-- REPORT CARDS MODAL -->
<div id="reportCardModal" class="modal-overlay">
  <div class="modal-content">
    <h3><i class="fa fa-file-alt text-primary"></i> Generate Report Cards</h3>
    <p class="modal-subtitle">Choose class, stream, and exam to generate student reports.</p>

    <div class="modal-form">
      <label>Select Class</label>
      <select class="modern-select"><option value="">Select Class</option></select>

      <label>Select Stream</label>
      <select class="modern-select"><option value="">Select Stream</option></select>

      <label>Select Exam</label>
      <select class="modern-select"><option value="">Select Exam</option></select>

      <label>Select Term</label>
      <select class="modern-select"><option value="">Select Term</option></select>

      <div class="checkbox-row">
        <div class="checkbox-inline">
          <input type="checkbox" id="includeRemarks">
          <label for="includeRemarks">Include Teacher Remarks</label>
        </div>
        <div class="checkbox-inline">
          <input type="checkbox" id="publishOnline">
          <label for="publishOnline">Publish Online</label>
        </div>
      </div>

      <div class="modal-buttons">
        <button class="action-btn generate-btn"><i class="fa fa-cogs"></i> Generate</button>
        <button class="action-btn cancel-btn"><i class="fa fa-times"></i> Cancel</button>
      </div>
    </div>
  </div>
</div>

<!-- ANALYTICS MODAL -->
<div id="analyticsModal" class="modal-overlay">
  <div class="modal-content">
    <h3><i class="fa fa-chart-line text-success"></i> Generate Analytics</h3>
    <p class="modal-subtitle">Select data parameters to refresh visual analytics.</p>

    <div class="modal-form">
      <label>Select Class</label>
      <select class="modern-select"><option value="">Select Class</option></select>

      <label>Select Exam</label>
      <select class="modern-select"><option value="">Select Exam</option></select>

      <label>Select Metric</label>
      <select class="modern-select">
        <option value="">Overall Performance</option>
        <option value="">Subject Trends</option>
        <option value="">Gender Analysis</option>
        <option value="">Improvement Index</option>
      </select>

      <div class="checkbox-inline">
        <input type="checkbox" id="includeComparisons">
        <label for="includeComparisons">Include Comparisons with Previous Term</label>
      </div>

      <div class="modal-buttons">
        <button class="action-btn generate-btn"><i class="fa fa-sync-alt"></i> Generate</button>
        <button class="action-btn cancel-btn"><i class="fa fa-times"></i> Cancel</button>
      </div>
    </div>
  </div>
</div>

<!-- MESSAGE CARDS MODAL -->
<div id="messageCardModal" class="modal-overlay">
  <div class="modal-content">
    <h3><i class="fa fa-envelope-open-text text-info"></i> Generate Message Cards</h3>
    <p class="modal-subtitle">Generate student message cards with key information.</p>

    <div class="modal-form">
      <label>Select Class</label>
      <select class="modern-select"><option value="">Select Class</option></select>

      <label>Select Stream</label>
      <select class="modern-select"><option value="">Select Stream</option></select>

      <label>Select Term</label>
      <select class="modern-select"><option value="">Select Term</option></select>

      <div class="checkbox-inline">
        <input type="checkbox" id="includeFees">
        <label for="includeFees">Include Fee Balance</label>
      </div>

      <div class="checkbox-inline">
        <input type="checkbox" id="includeNextTerm">
        <label for="includeNextTerm">Include Next Term Details</label>
      </div>

      <div class="modal-buttons">
        <button class="action-btn generate-btn"><i class="fa fa-cogs"></i> Generate</button>
        <button class="action-btn cancel-btn"><i class="fa fa-times"></i> Cancel</button>
      </div>
    </div>
  </div>
</div>
<!-- ======= REGENERATE CONFIRMATION MODAL ======= -->
<div id="regenerateModal" class="modal-overlay">
  <div class="modal-content">
    <h3><i class="fa fa-rotate-right text-warning"></i> Regenerate Merit List</h3>
    <p class="modal-subtitle">
      This will <strong>recalculate and overwrite</strong> the existing merit list for the selected class and stream.
    </p>
    <p>Are you sure you want to continue?</p>

    <!-- ADD THIS ELEMENT FOR SPINNER STATUS -->
    <p class="regenerate-status"></p>

    <div class="modal-buttons">
      <button id="cancelRegenerateBtn" class="action-btn cancel-btn">
        <i class="fa fa-times"></i> Cancel
      </button>
      <button id="confirmRegenerateBtn" class="action-btn generate-btn">
        <i class="fa fa-sync-alt"></i> Regenerate
      </button>
    </div>
  </div>
</div>
<div id="deleteMeritModal" class="modal-overlay">
  <div class="modal-content">
    <h3><i class="fa fa-trash text-danger"></i> Delete Merit List</h3>
    <p class="modal-subtitle">
      Are you sure you want to <strong>delete this merit list</strong>? This action cannot be undone.
    </p>
    <div class="modal-buttons">
      <button id="cancelDeleteBtn" class="action-btn cancel-btn">
        <i class="fa fa-times"></i> Cancel
      </button>
      <button id="confirmDeleteBtn" class="action-btn delete-btn">
        <i class="fa fa-trash"></i> Delete
      </button>
    </div>
  </div>
</div>

</div>
<!-- DELETE MERIT MODAL -->
<div id="deleteMeritModal" class="modal-overlay">
  <div class="modal-content">
    <h3><i class="fa fa-trash text-danger"></i> Delete Merit List</h3>
    <p class="modal-subtitle">
      Are you sure you want to <strong>delete this merit list</strong>? This action cannot be undone.
    </p>
    <div class="modal-buttons">
      <button id="cancelDeleteBtn" class="action-btn cancel-btn">
        <i class="fa fa-times"></i> Cancel
      </button>
      <button id="confirmDeleteBtn" class="action-btn delete-btn">
        <i class="fa fa-trash"></i> Delete
      </button>
    </div>
  </div>
</div>
<!-- DELETE REPORT CARD MODAL -->
<div id="deleteReportModal" class="modal">
  <div class="modal-content">
    <h4>Delete Report Card</h4>
    <p>Are you sure you want to delete this report card? This action cannot be undone.</p>
    <div class="modal-actions">
      <button id="cancelDeleteReportBtn" class="btn cancel-btn">Cancel</button>
      <button id="confirmDeleteReportBtn" class="btn confirm-btn">Delete</button>
    </div>
  </div>
</div>

<!-- REGENERATE REPORT CARD MODAL -->
<div id="regenerateReportModal" class="modal">
  <div class="modal-content">
    <h4>Regenerate Report Card</h4>
    <p class="regenerate-status"><span class="spinner"></span> Ready to regenerate</p>
    <div class="modal-actions">
      <button id="cancelRegenerateBtn" class="btn cancel-btn">Cancel</button>
      <button id="confirmRegenerateReportBtn" class="btn confirm-btn">Regenerate</button>
    </div>
  </div>
</div>
<!-- MESSAGE MODAL -->
<div id="reportMessageModal" class="modal">
  <div class="modal-content">
    <h2 class="modal-title">Notification</h2>
    <p class="modal-text">This is your message.</p>
    <div class="modal-actions">
      <button id="closeMessageModalBtn" class="confirm-btn">OK</button>
    </div>
  </div>
</div>

<!-- merit list -->
<script>
document.addEventListener("DOMContentLoaded", () => {
  const messageModal = document.getElementById("reportMessageModal");
  const messageText = messageModal?.querySelector(".modal-text");
  const closeMessageBtn = document.getElementById("closeMessageModalBtn");

  function showMessage(msg, title = "Notification") {
    if (!messageModal || !messageText) return;
    messageModal.querySelector(".modal-title").textContent = title;
    messageText.textContent = msg;
    messageModal.classList.add("active");
  }

  closeMessageBtn?.addEventListener("click", () => {
    messageModal.classList.remove("active");
  });

  // ======================
  // TAB SWITCHING
  // ======================
  const tabButtons = document.querySelectorAll(".reports-tabs .tab-button");
  const reportSections = document.querySelectorAll(".report-section");
  tabButtons.forEach(btn => {
    btn.addEventListener("click", () => {
      const target = btn.dataset.tab;
      tabButtons.forEach(b => b.classList.remove("active"));
      reportSections.forEach(sec => sec.classList.remove("active"));
      btn.classList.add("active");
      const section = document.getElementById(target);
      if (section) section.classList.add("active");
    });
  });

  // ======================
  // MERIT LIST LOGIC
  // ======================
  let selectedClassId = "", selectedStreamId = "", pollInterval = null;
  const cache = { streams: {}, terms: {} };
  let regenerateId = null, deleteMeritId = null;

  async function fetchJSON(url, options = {}) {
    try {
      const res = await fetch(url, options);
      return await res.json();
    } catch (err) {
      console.error("Fetch error:", err);
      showMessage("Failed to fetch data: " + err.message, "Error");
      return null;
    }
  }

  function populateSelect(select, items, valueKey, textKey, defaultText = "Select") {
    if (!select) return;
    select.innerHTML = `<option value="">${defaultText}</option>`;
    items.forEach(item => {
      const opt = document.createElement("option");
      opt.value = item[valueKey] ?? "";
      opt.textContent = item[textKey] ?? "";
      if (item.preferred) opt.selected = true; // ✅ auto-select preferred term
      select.appendChild(opt);
    });
    select.disabled = items.length === 0;
  }

  function syncSelects(selects, value) {
    selects.forEach(s => { if (s) s.value = value; });
  }

  async function initClassStreamDropdowns() {
    const topClassSelect = document.getElementById("meritClassSelect");
    const topStreamSelect = document.getElementById("meritStreamSelect");
    const modal = document.getElementById("meritModal");
    const modalClassSelect = modal?.querySelector("select[data-role='class-select']");
    const modalStreamSelect = modal?.querySelector("select[data-role='stream-select']");
    const modalTermSelect = modal?.querySelector("select[data-role='term-select']");
    const modalExamSelect = modal?.querySelector("select[data-role='exam-select']");

    const allClassSelects = [topClassSelect, modalClassSelect];
    const allStreamSelects = [topStreamSelect, modalStreamSelect];
    allStreamSelects.forEach(s => { if(s) s.disabled = true; });

    const classesData = await fetchJSON("api/get_classes.php");
    const classes = classesData?.success ? classesData.classes : [];
    allClassSelects.forEach(s => populateSelect(s, classes, "id", "class_level", "Select Class"));

    if (modalTermSelect && !cache.terms.fetched) {
      const termsData = await fetchJSON("api/get_terms.php");
      const terms = termsData?.success ? termsData.terms : [];
      populateSelect(modalTermSelect, terms, "id", "term_name", "Select Term");
      cache.terms.fetched = true;
    }

    allClassSelects.forEach(s => {
      s.addEventListener("change", async e => {
        selectedClassId = e.target.value;
        selectedStreamId = "";
        syncSelects(allClassSelects, selectedClassId);
        syncSelects(allStreamSelects, "");
        if (!selectedClassId) {
          clearMeritListTable();
          allStreamSelects.forEach(s => { if(s) s.disabled = true; });
          if (modalExamSelect) populateSelect(modalExamSelect, [], "id", "examname", "Select Exam");
          return;
        }
        if (!cache.streams[selectedClassId]) {
          const streamsData = await fetchJSON(`api/get_streams.php?class_id=${selectedClassId}`);
          cache.streams[selectedClassId] = streamsData?.success ? streamsData.streams : [];
        }
        allStreamSelects.forEach(s => populateSelect(s, cache.streams[selectedClassId], "id", "stream_name", "Select Stream"));
        if (modalExamSelect) {
          const examsData = await fetchJSON(`api/get_exams_by_class.php?class_id=${selectedClassId}`);
          const exams = examsData?.success ? examsData.exams : [];
          populateSelect(modalExamSelect, exams, "id", "examname", "Select Exam");
        }
        displayMeritList(selectedClassId, selectedStreamId);
      });
    });

    allStreamSelects.forEach(s => {
      s.addEventListener("change", e => {
        selectedStreamId = e.target.value;
        syncSelects(allStreamSelects, selectedStreamId);
        displayMeritList(selectedClassId, selectedStreamId);
      });
    });
  }

  function clearMeritListTable() {
    const tbody = document.querySelector("#meritListTable tbody");
    if (tbody) tbody.innerHTML = "";
  }

  async function displayMeritList(classId = "", streamId = "") {
    const tbody = document.querySelector("#meritListTable tbody");
    if (!tbody) return;
    tbody.innerHTML = "";

    if (!classId) return;

    const params = new URLSearchParams({ class_id: classId });
    if (streamId) params.append("stream_id", streamId);

    const data = await fetchJSON(`api/get_merit_list_status.php?${params.toString()}`);
    const lists = data?.merit_lists || [];

    lists.forEach((row, index) => {
      const tr = document.createElement("tr");
      tr.dataset.meritId = row.id;
      const statusHTML = row.status === "Completed"
        ? `<span class="status-completed">✔ Completed</span>`
        : `<span class="spinner"></span> Processing`;

      tr.innerHTML = `
        <td>${row.class_name}${row.stream_name ? " - " + row.stream_name : ""}</td>
        <td>${index + 1}</td>
        <td class="status-col">${statusHTML}</td>
        <td>${row.mean_score ?? "-"}</td>
        <td>${row.top_student ?? "-"}</td>
        <td>
          ${row.status === "Completed" 
            ? `<div class="dropdown">
                 <button class="ellipsis-btn">⋮</button>
                 <ul class="dropdown-menu futuristic-dropdown">
                   <li onclick="viewMeritList(${row.id})"><span>👁️</span> View</li>
                   <li onclick="regenerateMerit(${row.id})"><span>♻️</span> Regenerate</li>
                   <li onclick="deleteMerit(${row.id})"><span>🗑️</span> Delete</li>
                 </ul>
               </div>` 
            : `<span class="spinner"></span> Processing` }
        </td>
      `;
      tbody.appendChild(tr);
    });

    document.querySelectorAll(".ellipsis-btn").forEach(btn => {
      btn.addEventListener("click", e => {
        e.stopPropagation();
        closeAllDropdowns();
        btn.nextElementSibling.classList.toggle("show");
      });
    });

    document.addEventListener("click", closeAllDropdowns);
  }

  function closeAllDropdowns() {
    document.querySelectorAll(".dropdown-menu").forEach(menu => menu.classList.remove("show"));
  }

  // ======================
  // ACTIONS
  // ======================
  window.viewMeritList = id => {
    if (!id) return showMessage("Invalid merit list ID.");
    window.location.href = `view_merit_list.php?merit_id=${id}`;
  };

  window.editMerit = id => showMessage("Edit merit list: " + id);

  // DELETE MERIT
  const deleteModal = document.getElementById("deleteMeritModal");
  const deleteConfirmBtn = document.getElementById("confirmDeleteBtn");
  const deleteCancelBtn = document.getElementById("cancelDeleteBtn");

  window.deleteMerit = (id) => {
    deleteMeritId = id;
    if (!deleteModal) return showMessage("Delete modal not found!");
    deleteModal.classList.add("active");
  };

  deleteCancelBtn?.addEventListener("click", () => {
    deleteModal.classList.remove("active");
    deleteMeritId = null;
  });

  deleteModal?.addEventListener("click", e => {
    if (e.target === deleteModal) {
      deleteModal.classList.remove("active");
      deleteMeritId = null;
    }
  });

  deleteConfirmBtn?.addEventListener("click", async () => {
    if (!deleteMeritId) return;
    deleteConfirmBtn.disabled = true;
    try {
      const res = await fetchJSON("api/delete_merit_list.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id: deleteMeritId })
      });
      if (res?.success) {
        showMessage("Merit list deleted successfully!", "Success");
        displayMeritList(selectedClassId, selectedStreamId);
      } else {
        showMessage(res?.message || "Failed to delete merit list.", "Error");
      }
    } catch (err) {
      showMessage("Error: " + err.message, "Error");
    } finally {
      deleteModal.classList.remove("active");
      deleteConfirmBtn.disabled = false;
      deleteMeritId = null;
    }
  });

  // ======================
  // GENERATE MERIT LIST
  // ======================
  function setupModal() {
    const modal = document.getElementById("meritModal");
    if (!modal) return;

    document.querySelectorAll(".add-new-btn").forEach(btn => {
      btn.addEventListener("click", () => modal.classList.add("active"));
    });

    modal.querySelector(".cancel-btn")?.addEventListener("click", () => modal.classList.remove("active"));
    modal.addEventListener("click", e => { if (e.target === modal) modal.classList.remove("active"); });

    modal.querySelector(".generate-btn")?.addEventListener("click", async e => {
      const examSelect = modal.querySelector("select[data-role='exam-select']");
      const termSelect = modal.querySelector("select[data-role='term-select']");
      const classSelect = modal.querySelector("select[data-role='class-select']");
      const streamSelect = modal.querySelector("select[data-role='stream-select']");

      const payload = {
        class_id: classSelect.value || "",
        stream_id: streamSelect.value || "",
        exam_id: examSelect.value || "",
        term_id: termSelect.value || ""
      };

      if (!payload.class_id || !payload.exam_id || !payload.term_id) {
        return showMessage("Please select class, term, and exam before generating.", "Warning");
      }

      e.target.disabled = true;
      try {
        const res = await fetchJSON("api/generate_merit_list.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(payload)
        });
        if (res?.success) {
          showMessage("Merit list generated successfully!", "Success");
          modal.classList.remove("active");
          displayMeritList(selectedClassId, selectedStreamId);
        } else {
          showMessage(res?.message || "Failed to generate merit list.", "Error");
        }
      } catch (err) {
        showMessage("Error: " + err.message, "Error");
      } finally {
        e.target.disabled = false;
      }
    });
  }

  // ======================
  // REGENERATE MERIT LIST
  // ======================
  const regenerateModal = document.getElementById("regenerateMeritModal");
  const regenerateStatus = regenerateModal?.querySelector(".regenerate-status");
  const regenerateConfirmBtn = regenerateModal?.querySelector(".confirm-regenerate-btn");
  const regenerateCancelBtn = regenerateModal?.querySelector(".cancel-regenerate-btn");

  window.regenerateMerit = (id) => {
    if (!id) return showMessage("Invalid merit list ID.");
    regenerateId = id;
    if (!regenerateModal || !regenerateStatus) return showMessage("Regenerate modal not found!");
    regenerateStatus.innerHTML = `<span class="spinner"></span> Processing...`;
    regenerateModal.classList.add("active");
  };

  regenerateCancelBtn?.addEventListener("click", () => {
    regenerateModal.classList.remove("active");
    regenerateId = null;
  });

  regenerateModal?.addEventListener("click", e => {
    if (e.target === regenerateModal) {
      regenerateModal.classList.remove("active");
      regenerateId = null;
    }
  });

  regenerateConfirmBtn?.addEventListener("click", async () => {
    if (!regenerateId) return;
    regenerateConfirmBtn.disabled = true;
    regenerateStatus.innerHTML = `<span class="spinner"></span> Regenerating merit list...`;

    try {
      const res = await fetchJSON("api/regenerate_merit_list.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id: regenerateId })
      });

      if (res?.success) {
        showMessage("Merit list regenerated successfully!", "Success");
        displayMeritList(selectedClassId, selectedStreamId);
      } else {
        showMessage(res?.message || "Failed to regenerate merit list.", "Error");
      }
    } catch (err) {
      showMessage("Error: " + err.message, "Error");
    } finally {
      regenerateModal.classList.remove("active");
      regenerateConfirmBtn.disabled = false;
      regenerateId = null;
    }
  });

  // ======================
  // INIT
  // ======================
  (async function init() {
    await initClassStreamDropdowns();
    setupModal();
    displayMeritList();
  })();
});
</script>




<!-- report cards  -->
<script>
document.addEventListener("DOMContentLoaded", () => {

  // ======================
  // DOM ELEMENTS
  // ======================
  const reportSection = document.getElementById("reportCards");
  if (!reportSection) return;

  const classSelect = reportSection.querySelector("#classSelect");
  const streamSelect = reportSection.querySelector("#streamSelect");
  const examSelect = reportSection.querySelector("#examSelect");
  const termSelect = reportSection.querySelector("#termSelect");
  const generateBtn = reportSection.querySelector(".generate-btn");
  const searchInput = reportSection.querySelector(".search-input");
  const tableBody = reportSection.querySelector("table tbody");

  let selectedClassId = "";
  let selectedStreamId = "";
  let selectedExamId = "";
  let selectedTermId = "";
  const cache = { streams: {}, exams: {}, terms: [] };

  // ======================
  // FETCH UTILITY
  // ======================
  async function fetchJSON(url, options = {}) {
    try {
      const res = await fetch(url, options);
      return await res.json();
    } catch (err) {
      console.error("Fetch error:", err);
      alert("Network error: " + err.message);
      return null;
    }
  }

  // ======================
  // POPULATE SELECT
  // ======================
  function populateSelect(select, items, valueKey, textKey, defaultText = "Select") {
    if (!select) return;
    select.innerHTML = `<option value="">${defaultText}</option>`;
    items.forEach(item => {
      const opt = document.createElement("option");
      opt.value = item[valueKey] ?? "";
      opt.textContent = item[textKey] ?? "";
      select.appendChild(opt);
    });
    select.disabled = items.length === 0;
  }

  // ======================
  // INITIALIZE CLASS DROPDOWN
  // ======================
  async function initClassDropdown() {
    const classesData = await fetchJSON("api/get_classes.php");
    const classes = classesData?.success ? classesData.classes : [];
    populateSelect(classSelect, classes, "id", "class_level", "Select Class");

    classSelect.addEventListener("change", async e => {
      selectedClassId = e.target.value;
      selectedStreamId = "";
      selectedExamId = "";
      selectedTermId = "";

      streamSelect.value = "";
      examSelect.value = "";
      termSelect.value = "";
      tableBody.innerHTML = "";

      if (!selectedClassId) {
        streamSelect.disabled = true;
        examSelect.disabled = true;
        termSelect.disabled = true;
        return;
      }

      // FETCH STREAMS
      if (!cache.streams[selectedClassId]) {
        const streamsData = await fetchJSON(`api/get_streams.php?class_id=${selectedClassId}`);
        cache.streams[selectedClassId] = streamsData?.success ? streamsData.streams : [];
      }
      populateSelect(streamSelect, cache.streams[selectedClassId], "id", "stream_name", "Select Stream");

      // FETCH EXAMS
      if (!cache.exams[selectedClassId]) {
        const examsData = await fetchJSON(`api/get_exams.php?class_id=${selectedClassId}`);
        cache.exams[selectedClassId] = examsData?.success ? examsData.data : [];
      }
      populateSelect(examSelect, cache.exams[selectedClassId], "id", "examname", "Select Exam");

      // FETCH TERMS
      if (cache.terms.length === 0) {
        const termsData = await fetchJSON("api/get_terms.php");
        cache.terms = termsData?.success ? termsData.terms : [];
      }
      populateSelect(termSelect, cache.terms, "id", "term_name", "Select Term");

      // Auto-select current term
      const today = new Date();
      const currentTerm = cache.terms.find(term => {
        const start = new Date(term.start_date);
        const end = new Date(term.end_date);
        return today >= start && today <= end;
      });
      if (currentTerm) {
        termSelect.value = currentTerm.id;
        selectedTermId = currentTerm.id;
      }
    });
  }

  // ======================
  // HANDLE STREAM, EXAM, TERM CHANGES
  // ======================
  streamSelect.addEventListener("change", e => {
    selectedStreamId = e.target.value;
    tableBody.innerHTML = "";
  });

  examSelect.addEventListener("change", e => {
    selectedExamId = e.target.value;
    tableBody.innerHTML = "";
  });

  termSelect.addEventListener("change", e => {
    selectedTermId = e.target.value;
    tableBody.innerHTML = "";
  });

  // ======================
  // GENERATE REPORT CARDS
  // ======================
  generateBtn?.addEventListener("click", async () => {
    if (!selectedClassId) return alert("Please select a class.");
    if (!selectedExamId) return alert("Please select an exam.");
    if (!selectedTermId) return alert("Please select a term.");

    const payload = { 
      class_id: selectedClassId, 
      stream_id: selectedStreamId || "", 
      exam_id: selectedExamId, 
      term_id: selectedTermId
    };

    generateBtn.disabled = true;
    generateBtn.textContent = "Generating...";

    const res = await fetchJSON("api/generate_report_cards.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload)
    });

    generateBtn.disabled = false;
    generateBtn.textContent = "Generate";

    if (res?.success) {
      alert("Report cards generated successfully!");
      displayReportCards(selectedClassId, selectedStreamId, selectedExamId, selectedTermId);
    } else {
      alert(res?.message || "Failed to generate report cards.");
    }
  });

  // ======================
  // DISPLAY REPORT CARDS
  // ======================
  async function displayReportCards(classId = "", streamId = "", examId = "", termId = "") {
    tableBody.innerHTML = "";
    if (!classId || !examId || !termId) return;

    const params = new URLSearchParams({ class_id: classId, exam_id: examId, term_id: termId });
    if (streamId) params.append("stream_id", streamId);

    const data = await fetchJSON(`api/get_report_cards.php?${params.toString()}`);
    const reports = data?.report_cards || [];

    if (reports.length === 0) {
        tableBody.innerHTML = `<tr><td colspan="4" style="text-align:center;">No report cards found.</td></tr>`;
        return;
    }

    reports.forEach(row => {
        const tr = document.createElement("tr");
        tr.dataset.reportId = row.id;

        const statusHTML = row.status === "Completed"
            ? `<span class="status-completed">✔ Completed</span>`
            : `<span class="spinner"></span> Processing`;

        tr.innerHTML = `
            <td>${row.student_name}</td>
            <td>${row.exam_name}</td>
            <td class="status-col">${statusHTML}</td>
            <td>
                <button class="view-btn">View</button>
                <button class="regenerate-btn">♻️ Regenerate</button>
                <button class="delete-btn">🗑️ Delete</button>
            </td>
        `;

        tableBody.appendChild(tr);
    });

    // Add event listeners for view buttons
    document.querySelectorAll(".view-btn").forEach(button => {
        button.addEventListener("click", (e) => {
            const reportId = e.target.closest("tr").dataset.reportId;
            if (reportId) window.open(`view_report_cards.php?report_id=${reportId}`, "_blank");
        });
    });

    // You can add regenerate and delete handlers here
    attachTableActions(); // Make sure this function exists
  }

  // ======================
  // SEARCH FILTER
  // ======================
  searchInput?.addEventListener("input", e => {
    const query = e.target.value.toLowerCase();
    tableBody.querySelectorAll("tr").forEach(tr => {
      tr.style.display = tr.textContent.toLowerCase().includes(query) ? "" : "none";
    });
  });

  // ======================
  // INIT
  // ======================
  (async function init() {
    await initClassDropdown();
  })();

});
</script>

</body>
</html>