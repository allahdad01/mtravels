<?php
// Include security module
require_once 'security.php';

// Include language helper
require_once '../includes/language_helpers.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Check if user is logged in
if (!isset($_SESSION['user_id'])  || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Database connection
require_once('../includes/db.php');
?>

<?php include '../includes/header.php'; ?>
<script src="../assets/plugins/jquery/js/jquery.min.js"></script>
<link rel="stylesheet" href="../css/general/modal-styles.css">
<link rel="stylesheet" href="../css/umrah/umrah-enhanced.css">
<link rel="stylesheet" href="../css/document-upload.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    /* ============================================
   Umrah Management Enhanced Styles
   Modern, Clean, Professional Design
   ============================================ */

:root {
    /* Color Palette */
    --primary-color:rgb(37, 199, 235);
    --primary-dark:rgb(30, 151, 175);
    --primary-light: #60a5fa;
    --secondary-color: #10b981;
    --accent-color: #f59e0b;
    --danger-color: #ef4444;
    --warning-color: #f59e0b;
    --success-color: #10b981;
    --info-color: #3b82f6;
    
    /* Neutral Colors */
    --gray-50: #f9fafb;
    --gray-100: #f3f4f6;
    --gray-200: #e5e7eb;
    --gray-300: #d1d5db;
    --gray-400: #9ca3af;
    --gray-500: #6b7280;
    --gray-600: #4b5563;
    --gray-700: #374151;
    --gray-800: #1f2937;
    --gray-900: #111827;
    
    /* Shadows */
    --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
    --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    
    /* Spacing */
    --spacing-xs: 0.25rem;
    --spacing-sm: 0.5rem;
    --spacing-md: 1rem;
    --spacing-lg: 1.5rem;
    --spacing-xl: 2rem;
    --spacing-2xl: 3rem;
    
    /* Border Radius */
    --radius-sm: 0.375rem;
    --radius-md: 0.5rem;
    --radius-lg: 0.75rem;
    --radius-xl: 1rem;
    --radius-full: 9999px;
    
    /* Transitions */
    --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
    --transition-base: 200ms cubic-bezier(0.4, 0, 0.2, 1);
    --transition-slow: 300ms cubic-bezier(0.4, 0, 0.2, 1);
}

/* ============================================
   Global Resets & Base Styles
   ============================================ */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    background: var(--gray-50);
    color: var(--gray-900);
    line-height: 1.6;
}

/* ============================================
   Enhanced Page Header
   ============================================ */

.enhanced-page-header {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
    padding: var(--spacing-xl) 0;
    margin-bottom: var(--spacing-xl);
    box-shadow: var(--shadow-lg);
    border-bottom: 4px solid var(--primary-dark);
}

.page-title-wrapper {
    display: flex;
    align-items: center;
    gap: var(--spacing-md);
}

.page-icon {
    font-size: 2.5rem;
    color: white;
    background: rgba(255, 255, 255, 0.2);
    width: 70px;
    height: 70px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: var(--radius-lg);
    backdrop-filter: blur(10px);
}

.page-title {
    font-size: 2rem;
    font-weight: 700;
    color: white;
    margin: 0;
    letter-spacing: -0.025em;
}

.page-subtitle {
    font-size: 1rem;
    color: rgba(255, 255, 255, 0.9);
    margin: 0;
    font-weight: 400;
}

/* ============================================
   Financial Dashboard
   ============================================ */

.financial-dashboard {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: var(--spacing-lg);
    margin-bottom: var(--spacing-xl);
}

.dashboard-card {
    background: white;
    border-radius: var(--radius-lg);
    padding: var(--spacing-lg);
    display: flex;
    align-items: center;
    gap: var(--spacing-md);
    box-shadow: var(--shadow-md);
    transition: all var(--transition-base);
    border: 1px solid var(--gray-200);
    position: relative;
    overflow: hidden;
}

.dashboard-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary-color), var(--primary-light));
    transform: scaleX(0);
    transform-origin: left;
    transition: transform var(--transition-base);
}

.dashboard-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-xl);
}

.dashboard-card:hover::before {
    transform: scaleX(1);
}

.card-icon {
    width: 60px;
    height: 60px;
    border-radius: var(--radius-lg);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
    flex-shrink: 0;
}

.card-icon.revenue {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
}

.card-icon.collected {
    background: linear-gradient(135deg, #10b981, #059669);
}

.card-icon.outstanding {
    background: linear-gradient(135deg, #f59e0b, #d97706);
}

.card-icon.families {
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
}

.card-content h3 {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--gray-900);
    margin: 0;
    line-height: 1;
}

.card-content p {
    font-size: 0.875rem;
    color: var(--gray-600);
    margin: var(--spacing-xs) 0 0;
    font-weight: 500;
}

/* ============================================
   Filters Wrapper
   ============================================ */

.filters-wrapper {
    background: white;
    border-radius: var(--radius-lg);
    padding: var(--spacing-lg);
    box-shadow: var(--shadow-md);
    border: 1px solid var(--gray-200);
    margin-bottom: var(--spacing-xl);
}

/* Filter Pills */
.filter-pills {
    display: flex;
    flex-wrap: wrap;
    gap: var(--spacing-sm);
    margin-bottom: var(--spacing-lg);
    padding-bottom: var(--spacing-lg);
    border-bottom: 1px solid var(--gray-200);
}

.filter-pill {
    display: inline-flex;
    align-items: center;
    gap: var(--spacing-sm);
    padding: var(--spacing-sm) var(--spacing-md);
    background: var(--gray-100);
    color: var(--gray-700);
    border-radius: var(--radius-full);
    font-size: 0.875rem;
    font-weight: 500;
    text-decoration: none;
    transition: all var(--transition-fast);
    border: 2px solid transparent;
}

.filter-pill i {
    font-size: 1rem;
}

.filter-pill:hover {
    background: var(--gray-200);
    transform: translateY(-1px);
    text-decoration: none;
    color: var(--gray-900);
}

.filter-pill.active {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-dark);
    box-shadow: var(--shadow-md);
}

.pill-badge {
    background: rgba(255, 255, 255, 0.3);
    padding: 0.125rem 0.5rem;
    border-radius: var(--radius-full);
    font-size: 0.75rem;
    font-weight: 600;
    min-width: 24px;
    text-align: center;
}

.filter-pill.active .pill-badge {
    background: rgba(255, 255, 255, 0.25);
}

/* Search Wrapper */
.search-wrapper {
    position: relative;
}

.search-form {
    width: 100%;
}

.search-input-group {
    position: relative;
    display: flex;
    align-items: center;
    background: var(--gray-50);
    border: 2px solid var(--gray-300);
    border-radius: var(--radius-lg);
    padding: 0.25rem;
    transition: all var(--transition-fast);
}

.search-input-group:focus-within {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.search-icon {
    position: absolute;
    left: 1rem;
    color: var(--gray-400);
    font-size: 1.125rem;
    pointer-events: none;
}

.search-input {
    flex: 1;
    border: none;
    background: transparent;
    padding: 0.75rem 1rem 0.75rem 3rem;
    font-size: 1rem;
    color: var(--gray-900);
    outline: none;
}

.search-input::placeholder {
    color: var(--gray-400);
}

.clear-search {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    color: var(--gray-400);
    transition: all var(--transition-fast);
    border-radius: var(--radius-md);
}

.clear-search:hover {
    color: var(--gray-600);
    background: var(--gray-200);
    text-decoration: none;
}

.search-button {
    background: var(--primary-color);
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: var(--radius-md);
    font-weight: 600;
    font-size: 0.875rem;
    cursor: pointer;
    transition: all var(--transition-fast);
    white-space: nowrap;
}

.search-button:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
    box-shadow: var(--shadow-md);
}

/* ============================================
   Family Cards Grid
   ============================================ */

.family-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
    gap: var(--spacing-lg);
    margin-bottom: var(--spacing-xl);
    grid-auto-flow: dense;
}

.family-card.members-visible {
    grid-column: 1 / -1;
}

.family-card {
    background: white;
    border-radius: var(--radius-xl);
    overflow: visible;
    box-shadow: var(--shadow);
    border: 1px solid var(--gray-200);
    transition: all var(--transition-base);
    position: relative;
    display: grid;
    grid-template-columns: 1fr;
    grid-template-rows: auto auto auto;
}

.family-card.members-visible {
    grid-template-columns: 1fr 1fr;
    grid-template-rows: auto auto;
}

.family-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
}

.family-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-xl);
}

.family-card.refunded-family {
    opacity: 0.8;
    border-color: var(--danger-color);
}

.family-card.refunded-family::before {
    background: var(--danger-color);
}

/* Card Header Section */
.card-header-section {
    padding: var(--spacing-lg);
    background: linear-gradient(135deg, var(--gray-50) 0%, white 100%);
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    align-items: flex-start;
    gap: var(--spacing-md);
}

.family-avatar {
    width: 60px;
    height: 60px;
    border-radius: var(--radius-lg);
    background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.75rem;
    flex-shrink: 0;
    box-shadow: var(--shadow-md);
}

.family-main-info {
    flex: 1;
    min-width: 0;
}

.family-name {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--gray-900);
    margin: 0 0 var(--spacing-xs);
    line-height: 1.3;
}

.family-meta {
    display: flex;
    flex-wrap: wrap;
    gap: var(--spacing-md);
}

.meta-item {
    display: inline-flex;
    align-items: center;
    gap: var(--spacing-xs);
    font-size: 0.813rem;
    color: var(--gray-600);
}

.meta-item i {
    color: var(--primary-color);
}

.card-actions {
    display: flex;
    gap: var(--spacing-xs);
    flex-shrink: 0;
}

.btn-icon {
    width: 36px;
    height: 36px;
    border-radius: var(--radius-md);
    border: 1px solid var(--gray-300);
    background: white;
    color: var(--gray-600);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all var(--transition-fast);
}

.btn-icon:hover {
    background: var(--gray-100);
    color: var(--gray-900);
    border-color: var(--gray-400);
}

/* Card Body Section */
.card-body-section {
    padding: var(--spacing-lg);
}

.info-row {
    display: flex;
    align-items: center;
    gap: var(--spacing-md);
    padding: var(--spacing-sm) 0;
    font-size: 0.875rem;
    color: var(--gray-700);
}

.info-row i {
    color: var(--primary-color);
    width: 20px;
    flex-shrink: 0;
}

.package-info {
    display: flex;
    gap: var(--spacing-sm);
    margin: var(--spacing-md) 0;
    flex-wrap: wrap;
}

.package-badge,
.visa-badge {
    display: inline-flex;
    align-items: center;
    gap: var(--spacing-xs);
    padding: var(--spacing-xs) var(--spacing-md);
    border-radius: var(--radius-md);
    font-size: 0.813rem;
    font-weight: 600;
}

.package-badge {
    background: var(--gray-100);
    color: var(--gray-700);
    border: 1px solid var(--gray-300);
}

.visa-badge {
    border: 1px solid currentColor;
}

.visa-default {
    background: var(--gray-100);
    color: var(--gray-700);
}

.visa-warning {
    background: #fef3c7;
    color: #92400e;
}

.visa-info {
    background: #dbeafe;
    color: #1e40af;
}

.visa-success {
    background: #d1fae5;
    color: #065f46;
}

/* Financial Summary */
.financial-summary {
    background: var(--gray-50);
    border-radius: var(--radius-lg);
    padding: var(--spacing-md);
    margin-top: var(--spacing-md);
    border: 1px solid var(--gray-200);
}

.financial-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--spacing-sm);
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--gray-700);
}

.percentage {
    color: var(--primary-color);
    font-size: 1rem;
}

.progress-bar-container {
    height: 8px;
    background: var(--gray-200);
    border-radius: var(--radius-full);
    overflow: hidden;
    margin-bottom: var(--spacing-md);
}

.progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
    border-radius: var(--radius-full);
    transition: width var(--transition-slow);
}

.financial-details {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: var(--spacing-sm);
}

.financial-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--spacing-sm);
    background: white;
    border-radius: var(--radius-md);
    border: 1px solid var(--gray-200);
}

.financial-item .label {
    font-size: 0.75rem;
    color: var(--gray-600);
    font-weight: 500;
}

.financial-item .value {
    font-size: 0.875rem;
    font-weight: 700;
    color: var(--gray-900);
}

.financial-item.success .value {
    color: var(--success-color);
}

.financial-item.warning .value {
    color: var(--warning-color);
}

.financial-item.danger .value {
    color: var(--danger-color);
}

/* ============================================
   Members Section
   ============================================ */

.card-header-section {
    grid-column: 1;
    grid-row: 1;
}

.card-body-section {
    grid-column: 1;
    grid-row: 2;
}

.members-section {
    grid-column: 1;
    grid-row: 3;
    border-top: 1px solid var(--gray-200);
    background: var(--gray-50);
    padding: var(--spacing-lg);
    border-radius: 0 0 var(--radius-xl) var(--radius-xl);
}

.family-card.members-visible .card-header-section {
    grid-column: 1;
    grid-row: 1;
}

.family-card.members-visible .card-body-section {
    grid-column: 1;
    grid-row: 2;
}

.family-card.members-visible .members-section {
    grid-column: 2;
    grid-row: 1 / 3;
    border-top: none;
    border-left: 1px solid var(--gray-200);
    border-radius: 0 var(--radius-xl) var(--radius-xl) 0;
    background: var(--gray-50);
    padding: var(--spacing-lg);
    margin: 0;
}

.members-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--spacing-md);
}

.members-header h4 {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--gray-900);
    margin: 0;
}

.members-grid {
    display: grid;
    gap: var(--spacing-md);
    max-height: 600px;
    overflow-y: auto;
    padding-right: 0.5rem;
}

.members-grid::-webkit-scrollbar {
    width: 6px;
}

.members-grid::-webkit-scrollbar-track {
    background: #f3f4f6;
    border-radius: 0.375rem;
}

.members-grid::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 0.375rem;
}

.members-grid::-webkit-scrollbar-thumb:hover {
    background: #9ca3af;
}

.loading-spinner {
    text-align: center;
    padding: var(--spacing-xl);
    color: var(--gray-500);
}

.loading-spinner i {
    font-size: 2rem;
    margin-bottom: var(--spacing-sm);
}

/* ============================================
   Pagination
   ============================================ */

.pagination-wrapper {
    background: white;
    border-radius: var(--radius-lg);
    padding: var(--spacing-lg);
    box-shadow: var(--shadow-md);
    border: 1px solid var(--gray-200);
    margin-top: var(--spacing-xl);
}

.pagination-list {
    display: flex;
    justify-content: center;
    gap: var(--spacing-xs);
    list-style: none;
    margin: 0 0 var(--spacing-md);
    padding: 0;
}

.pagination-link {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: var(--radius-md);
    border: 1px solid var(--gray-300);
    background: white;
    color: var(--gray-700);
    font-weight: 500;
    text-decoration: none;
    transition: all var(--transition-fast);
}

.pagination-link:hover {
    background: var(--gray-100);
    border-color: var(--gray-400);
    text-decoration: none;
}

.pagination-link.active {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}

.pagination-info {
    text-align: center;
    font-size: 0.875rem;
    color: var(--gray-600);
}

/* ============================================
   Empty State
   ============================================ */

.empty-state {
    text-align: center;
    padding: var(--spacing-2xl) var(--spacing-xl);
    background: white;
    border-radius: var(--radius-xl);
    box-shadow: var(--shadow-md);
    border: 1px solid var(--gray-200);
}

.empty-state-icon {
    width: 120px;
    height: 120px;
    margin: 0 auto var(--spacing-lg);
    background: var(--gray-100);
    border-radius: var(--radius-full);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--gray-400);
    font-size: 3rem;
}

.empty-state h3 {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--gray-900);
    margin-bottom: var(--spacing-md);
}

.empty-state p {
    color: var(--gray-600);
    margin-bottom: var(--spacing-lg);
}

/* ============================================
   Buttons
   ============================================ */

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: var(--spacing-sm);
    padding: var(--spacing-sm) var(--spacing-lg);
    border-radius: var(--radius-md);
    font-weight: 600;
    font-size: 0.875rem;
    border: none;
    cursor: pointer;
    transition: all var(--transition-fast);
    text-decoration: none;
}

.btn-primary {
    background: var(--primary-color);
    color: white;
}

.btn-primary:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
    box-shadow: var(--shadow-md);
}

.btn-gradient-primary {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
    color: white;
    box-shadow: var(--shadow-md);
}

.btn-gradient-primary:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.btn-sm {
    padding: var(--spacing-xs) var(--spacing-md);
    font-size: 0.813rem;
}

/* ============================================
   Floating Action Buttons
   ============================================ */

.floating-action-btn {
    position: fixed;
    right: 30px;
    z-index: 1050;
}

.fab-button {
    width: 56px;
    height: 56px;
    border-radius: var(--radius-full);
    background: var(--primary-color);
    color: white;
    border: none;
    box-shadow: var(--shadow-xl);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    transition: all var(--transition-base);
    position: relative;
}

.fab-button:hover {
    transform: scale(1.1);
    box-shadow: 0 20px 30px -10px rgba(0, 0, 0, 0.3);
}

.fab-dark {
    background: var(--gray-800);
}

.fab-badge {
    position: absolute;
    top: -4px;
    right: -4px;
    background: var(--danger-color);
    color: white;
    width: 24px;
    height: 24px;
    border-radius: var(--radius-full);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 700;
    border: 2px solid white;
}

/* ============================================
   Dropdown Menus
   ============================================ */

.dropdown-menu {
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-xl);
    border: 1px solid var(--gray-200);
    padding: var(--spacing-sm);
    min-width: 200px;
}

.dropdown-item {
    display: flex;
    align-items: center;
    gap: var(--spacing-md);
    padding: var(--spacing-sm) var(--spacing-md);
    border-radius: var(--radius-md);
    color: var(--gray-700);
    font-size: 0.875rem;
    transition: all var(--transition-fast);
}

.dropdown-item:hover {
    background: var(--gray-100);
    color: var(--gray-900);
}

.dropdown-item i {
    width: 20px;
    flex-shrink: 0;
}

.dropdown-divider {
    margin: var(--spacing-sm) 0;
    border-top-color: var(--gray-200);
}

/* ============================================
   Responsive Design
   ============================================ */

@media (max-width: 768px) {
    .enhanced-page-header {
        padding: var(--spacing-lg) 0;
    }
    
    .page-title {
        font-size: 1.5rem;
    }
    
    .page-icon {
        width: 50px;
        height: 50px;
        font-size: 1.75rem;
    }
    
    .financial-dashboard {
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: var(--spacing-md);
    }
    
    .family-cards-grid {
        grid-template-columns: 1fr;
        gap: var(--spacing-md);
    }
    
    .filter-pills {
        overflow-x: auto;
        flex-wrap: nowrap;
        scrollbar-width: thin;
        padding-bottom: var(--spacing-md);
    }
    
    .search-input-group {
        flex-direction: column;
        align-items: stretch;
    }
    
    .search-button {
        width: 100%;
        margin-top: var(--spacing-sm);
    }
}

@media (max-width: 480px) {
    .financial-dashboard {
        grid-template-columns: 1fr;
    }
    
    .card-header-section {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .card-actions {
        width: 100%;
        justify-content: flex-end;
    }
    
    .financial-details {
        grid-template-columns: 1fr;
    }
}

/* ============================================
   Animation Keyframes
   ============================================ */

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.family-card {
    animation: fadeIn var(--transition-base) ease-out;
}

/* ============================================
   Print Styles
   ============================================ */

@media print {
    .enhanced-page-header,
    .filters-wrapper,
    .pagination-wrapper,
    .floating-action-btn,
    .card-actions,
    .btn {
        display: none !important;
    }
    
    .family-card {
        break-inside: avoid;
        box-shadow: none !important;
        border: 1px solid var(--gray-300);
    }
}
    </style>

<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <!-- Enhanced Page Header -->
                <div class="enhanced-page-header">
                    <div class="container-fluid">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <div class="page-title-wrapper">
                                    <i class="fas fa-kaaba page-icon"></i>
                                    <div>
                                        <h2 class="page-title"><?= __('umrah_management') ?></h2>
                                        <p class="page-subtitle"><?= __('manage_families_and_bookings') ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 text-right">
                                <button class="btn btn-gradient-primary" data-toggle="modal" data-target="#createFamilyModal">
                                    <i class="fas fa-plus-circle mr-2"></i><?= __('add_family') ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <?php
                    // Search and Pagination setup
                    $resultsPerPage = 12;
                    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
                    $visaStatus = isset($_GET['visa_status']) ? trim($_GET['visa_status']) : '';
                    $filter = isset($_GET['filter']) ? trim($_GET['filter']) : '';
                    $offset = ($page - 1) * $resultsPerPage;

                    // COUNT QUERY
                    if ($filter === 'refunded' || $filter === 'cancelled') {
                        $statusFilter = $filter === 'refunded' ? 'refunded' : 'cancelled';
                        $countSql = "SELECT COUNT(DISTINCT f.family_id) as total
                                    FROM families f
                                    LEFT JOIN users u ON f.created_by = u.id
                                    LEFT JOIN umrah_bookings ub ON f.family_id = ub.family_id
                                    WHERE 1=1 AND f.tenant_id = ? AND f.branch_id = ?";
                        $countParams = [$tenant_id, $branch_id];
                        $countTypes = "ii";

                        if (!empty($search)) {
                            $countSql .= " AND (
                                f.head_of_family LIKE ? OR
                                f.contact LIKE ? OR
                                f.address LIKE ? OR
                                f.package_type LIKE ? OR
                                f.location LIKE ? OR
                                u.name LIKE ? OR
                                EXISTS (SELECT 1 FROM umrah_bookings ub2 WHERE ub2.family_id = f.family_id AND ub2.tenant_id = ? AND ub2.branch_id = ? AND (
                                    ub2.name LIKE ? OR
                                    ub2.passport_number LIKE ?
                                ))
                            )";
                            $searchTerm = "%$search%";
                            $countParams = array_merge($countParams, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $tenant_id, $branch_id, $searchTerm, $searchTerm]);
                            $countTypes .= "ssssssiiiss";
                        }

                        $countSql .= " GROUP BY f.family_id
                                    HAVING COUNT(ub.booking_id) > 0 AND COUNT(ub.booking_id) = SUM(CASE WHEN ub.status = '$statusFilter' THEN 1 ELSE 0 END)";
                    } else {
                        $countSql = "SELECT COUNT(DISTINCT f.family_id) as total
                                    FROM families f
                                    LEFT JOIN users u ON f.created_by = u.id
                                    WHERE 1=1 AND f.tenant_id = ? AND f.branch_id = ?";

                        $countParams = [$tenant_id, $branch_id];
                        $countTypes = "ii";

                        if (!empty($visaStatus)) {
                            $countSql .= " AND f.visa_status = ?";
                            $countParams[] = $visaStatus;
                            $countTypes .= "s";
                        }

                        if (!empty($search)) {
                            $countSql .= " AND (
                                f.head_of_family LIKE ? OR
                                f.contact LIKE ? OR
                                f.address LIKE ? OR
                                f.package_type LIKE ? OR
                                f.location LIKE ? OR
                                u.name LIKE ? OR
                                EXISTS (SELECT 1 FROM umrah_bookings ub WHERE ub.family_id = f.family_id AND ub.tenant_id = ? AND ub.branch_id = ? AND (
                                    ub.name LIKE ? OR
                                    ub.passport_number LIKE ?
                                ))
                            )";
                            $searchTerm = "%$search%";
                            $countParams = array_merge($countParams, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $tenant_id, $branch_id, $searchTerm, $searchTerm]);
                            $countTypes .= "ssssssiiiss";
                        }
                    }

                    $countStmt = $pdo->prepare($countSql);
                    $countStmt->execute($countParams);
                    $totalFamilies = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
                    $totalPages = ceil($totalFamilies / $resultsPerPage);

                    // MAIN QUERY
                    $sqlFamilies = "SELECT
                                        f.*,
                                        u.name as created_by,
                                        COUNT(ub.booking_id) AS total_members,
                                        SUM(CASE WHEN ub.status = 'refunded' THEN 1 ELSE 0 END) AS refunded_members
                                    FROM families f
                                    LEFT JOIN users u ON f.created_by = u.id
                                    LEFT JOIN umrah_bookings ub ON f.family_id = ub.family_id
                                    WHERE 1=1 AND f.tenant_id = ? AND f.branch_id = ?";

                    $familiesParams = [$tenant_id, $branch_id];
                    $familiesTypes = "ii";

                    if (($filter !== 'refunded' && $filter !== 'cancelled') && !empty($visaStatus)) {
                        $sqlFamilies .= " AND f.visa_status = ?";
                        $familiesParams[] = $visaStatus;
                        $familiesTypes .= "s";
                    }

                    if (!empty($search)) {
                        $sqlFamilies .= " AND (
                            f.head_of_family LIKE ? OR
                            f.contact LIKE ? OR
                            f.address LIKE ? OR
                            f.package_type LIKE ? OR
                            f.location LIKE ? OR
                            u.name LIKE ? OR
                            EXISTS (SELECT 1 FROM umrah_bookings ub WHERE ub.family_id = f.family_id AND ub.tenant_id = ? AND ub.branch_id = ? AND (
                                ub.name LIKE ? OR
                                ub.passport_number LIKE ?
                            ))
                        )";
                        $searchTerm = "%$search%";
                        $familiesParams = array_merge($familiesParams, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $tenant_id, $branch_id, $searchTerm, $searchTerm]);
                        $familiesTypes .= "ssssssiiiss";
                    }

                    $sqlFamilies .= " GROUP BY f.family_id";
                    if ($filter === 'refunded' || $filter === 'cancelled') {
                        $statusFilter = $filter === 'refunded' ? 'refunded' : 'cancelled';
                        $sqlFamilies .= " HAVING COUNT(ub.booking_id) > 0 AND COUNT(ub.booking_id) = SUM(CASE WHEN ub.status = '$statusFilter' THEN 1 ELSE 0 END)";
                    }
                    $sqlFamilies .= " ORDER BY f.created_at DESC LIMIT ? OFFSET ?";
                    $familiesParams[] = $resultsPerPage;
                    $familiesParams[] = $offset;
                    $familiesTypes .= "ii";

                    $familiesStmt = $pdo->prepare($sqlFamilies);
                    $familiesStmt->execute($familiesParams);
                    $resultFamilies = $familiesStmt->fetchAll(PDO::FETCH_ASSOC);

                    // Calculate statistics
                    $totalRevenue = 0;
                    $totalCollected = 0;
                    $totalOutstanding = 0;
                    foreach ($resultFamilies as $family) {
                        $totalRevenue += floatval($family['total_price'] ?? 0);
                        $totalCollected += floatval($family['total_paid'] ?? 0);
                        $totalOutstanding += floatval($family['total_due'] ?? 0);
                    }
                ?>

                <!-- Filters and Search -->
                <div class="container-fluid px-4 mb-4">
                    <div class="filters-wrapper">
                        <!-- Filter Pills -->
                        <div class="filter-pills">
                            <a href="?visa_status=" class="filter-pill <?= empty($filter) && empty($visaStatus) ? 'active' : '' ?>">
                                <i class="fas fa-layer-group"></i>
                                <span><?= __('all') ?></span>
                                <span class="pill-badge"><?= $totalFamilies ?></span>
                            </a>
                            <a href="?visa_status=Not Applied" class="filter-pill <?= empty($filter) && $visaStatus === 'Not Applied' ? 'active' : '' ?>">
                                <i class="fas fa-clock"></i>
                                <span><?= __('not_applied') ?></span>
                            </a>
                            <a href="?visa_status=Applied" class="filter-pill <?= empty($filter) && $visaStatus === 'Applied' ? 'active' : '' ?>">
                                <i class="fas fa-hourglass-half"></i>
                                <span><?= __('applied') ?></span>
                            </a>
                            <a href="?visa_status=Issued" class="filter-pill <?= empty($filter) && $visaStatus === 'Issued' ? 'active' : '' ?>">
                                <i class="fas fa-check-circle"></i>
                                <span><?= __('issued') ?></span>
                            </a>
                            <a href="?filter=refunded" class="filter-pill <?= $filter === 'refunded' ? 'active' : '' ?>">
                                <i class="fas fa-undo"></i>
                                <span><?= __('refunded') ?></span>
                            </a>
                            <a href="?filter=cancelled" class="filter-pill <?= $filter === 'cancelled' ? 'active' : '' ?>">
                                <i class="fas fa-times-circle"></i>
                                <span><?= __('cancelled') ?></span>
                            </a>
                        </div>

                        <!-- Enhanced Search -->
                        <div class="search-wrapper">
                            <form method="GET" class="search-form">
                                <div class="search-input-group">
                                    <i class="fas fa-search search-icon"></i>
                                    <input type="search" 
                                           name="search" 
                                           value="<?= htmlspecialchars($search) ?>"
                                           placeholder="<?= __('search_families_members_passports') ?>"
                                           class="search-input">
                                    <input type="hidden" name="visa_status" value="<?= htmlspecialchars($visaStatus) ?>">
                                    <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                                    <?php if (!empty($search)): ?>
                                        <a href="?" class="clear-search">
                                            <i class="fas fa-times"></i>
                                        </a>
                                    <?php endif; ?>
                                    <button type="submit" class="search-button">
                                        <?= __('search') ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Family Cards Grid -->
                <div class="container-fluid px-4">
                    <?php if (!empty($resultFamilies)): ?>
                        <div class="family-cards-grid">
                            <?php foreach ($resultFamilies as $row): 
                                $familyId = $row['family_id'];
                                $isFullyRefunded = ($row['total_members'] > 0 && $row['total_members'] == $row['refunded_members']);
                                
                                // Calculate payment percentage
                                $totalPrice = floatval($row['total_price'] ?? 0);
                                $totalPaid = floatval($row['total_paid'] ?? 0);
                                $paymentPercentage = $totalPrice > 0 ? ($totalPaid / $totalPrice) * 100 : 0;
                                
                                // Get visa status color
                                $visaStatusClass = 'default';
                                switch ($row['visa_status']) {
                                    case 'Not Applied':
                                        $visaStatusClass = 'warning';
                                        break;
                                    case 'Applied':
                                        $visaStatusClass = 'info';
                                        break;
                                    case 'Issued':
                                        $visaStatusClass = 'success';
                                        break;
                                }
                            ?>
                                <div class="family-card <?= $isFullyRefunded ? 'refunded-family' : '' ?>" data-family-id="<?= $familyId ?>">
                                    <!-- Card Header -->
                                    <div class="card-header-section">
                                        <div class="family-avatar">
                                            <i class="fas fa-users"></i>
                                        </div>
                                        <div class="family-main-info">
                                            <h3 class="family-name"><?= htmlspecialchars($row['head_of_family']) ?></h3>
                                            <div class="family-meta">
                                                <span class="meta-item">
                                                    <i class="fas fa-map-marker-alt"></i>
                                                    <?= htmlspecialchars($row['location']) ?>
                                                </span>
                                                <span class="meta-item">
                                                    <i class="fas fa-users"></i>
                                                    <?= $row['total_members'] ?> <?= __('members') ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="card-actions">
                                             <button class="btn-icon view-members-btn" data-family-id="<?= $familyId ?>" type="button" title="<?= __('view_members') ?>">
                                                 <i class="fas fa-eye"></i>
                                             </button>
                                            <div class="dropdown">
                                                <button class="btn-icon" type="button" data-toggle="dropdown">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <div class="dropdown-menu dropdown-menu-right">
                                                    <a class="dropdown-item" href="javascript:void(0)" onclick="openBookingModal(<?= $familyId ?>, '<?= addslashes($row['package_type']) ?>')">
                                                        <i class="fas fa-user-plus"></i><?= __('add_member') ?>
                                                    </a>
                                                    <a class="dropdown-item" href="javascript:void(0)" onclick="openEditFamilyModal(<?= $familyId ?>, '<?= htmlspecialchars($row['head_of_family']) ?>',
                                                    '<?= htmlspecialchars($row['contact']) ?>', '<?= htmlspecialchars($row['address']) ?>',
                                                    '<?= htmlspecialchars($row['package_type']) ?>', '<?= htmlspecialchars($row['location']) ?>',
                                                    '<?= htmlspecialchars($row['tazmin']) ?>', '<?= htmlspecialchars($row['visa_status']) ?>',
                                                    '<?= htmlspecialchars($row['province']) ?>', '<?= htmlspecialchars($row['district']) ?>')">
                                                        <i class="fas fa-edit"></i><?= __('edit') ?>
                                                    </a>
                                                    <a class="dropdown-item" href="javascript:void(0)" onclick="openFamilyTransactionModal(<?= $familyId ?>, '<?= htmlspecialchars($row['head_of_family']) ?>', '<?= htmlspecialchars($row['package_type']) ?>', <?= $row['total_members'] ?>)">
                                                        <i class="fas fa-credit-card"></i><?= __('family_transaction') ?>
                                                    </a>
                                                    <div class="dropdown-divider"></div>
                                                    <a class="dropdown-item" href="javascript:void(0)" onclick="generateFamilyTazmin(<?= $familyId ?>)">
                                                        <i class="fas fa-shield-alt"></i><?= __('generate_family_tazmin') ?>
                                                    </a>
                                                    <a class="dropdown-item" href="javascript:void(0)" onclick="generateFamilyAgreement(<?= $familyId ?>)">
                                                        <i class="fas fa-file-contract"></i><?= __('generate_family_agreement') ?>
                                                    </a>
                                                    <a class="dropdown-item" href="javascript:void(0)" onclick="generateFamilyCompletion(<?= $familyId ?>)">
                                                        <i class="fas fa-check-circle"></i><?= __('generate_family_completion') ?>
                                                    </a>
                                                    <a class="dropdown-item" href="javascript:void(0)" onclick="generateFamilyCancellation(<?= $familyId ?>)">
                                                        <i class="fas fa-times-circle"></i><?= __('generate_family_cancellation') ?>
                                                    </a>
                                                    <a class="dropdown-item" href="#" onclick="showBankLetterModal(<?= $familyId ?>)">
                                                        <i class="fas fa-file-invoice"></i><?= __("bank_receipt") ?>
                                                    </a>
                                                    <a class="dropdown-item" href="#" onclick="showUmrahPresidencyModal(<?= $familyId ?>)">
                                                        <i class="fas fa-landmark"></i><?= __("umrah_presidency") ?>
                                                    </a>
                                                    <div class="dropdown-divider"></div>
                                                    <a class="dropdown-item text-danger" href="javascript:void(0)" onclick="deleteFamily(<?= $familyId ?>)">
                                                        <i class="fas fa-trash"></i><?= __('delete') ?>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Card Body -->
                                    <div class="card-body-section">
                                        <!-- Contact Information -->
                                        <div class="info-row">
                                            <i class="fas fa-phone"></i>
                                            <span><?= htmlspecialchars($row['contact']) ?></span>
                                        </div>
                                        <div class="info-row">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <span><?= htmlspecialchars($row['address']) ?></span>
                                        </div>
                                        <div class="info-row">
                                            <i class="fas fa-globe"></i>
                                            <span><?= htmlspecialchars($row['province']) ?> - <?= htmlspecialchars($row['district']) ?></span>
                                        </div>

                                        <!-- Package Info -->
                                        <div class="package-info">
                                            <div class="package-badge">
                                                <i class="fas fa-box"></i>
                                                <?= htmlspecialchars($row['package_type']) ?>
                                            </div>
                                            <div class="visa-badge visa-<?= $visaStatusClass ?>">
                                                <i class="fas fa-passport"></i>
                                                <?= htmlspecialchars($row['visa_status']) ?>
                                            </div>
                                        </div>

                                        <!-- Financial Summary -->
                                        <div class="financial-summary">
                                            <div class="financial-header">
                                                <span><?= __('payment_status') ?></span>
                                                <span class="percentage"><?= number_format($paymentPercentage, 1) ?>%</span>
                                            </div>
                                            <div class="progress-bar-container">
                                                <div class="progress-bar-fill" style="width: <?= $paymentPercentage ?>%"></div>
                                            </div>
                                            <div class="financial-details">
                                                <div class="financial-item">
                                                    <span class="label"><?= __('total_price') ?></span>
                                                    <span class="value"><?= htmlspecialchars($row['total_price'] ?? '0') ?></span>
                                                </div>
                                                <div class="financial-item success">
                                                    <span class="label"><?= __('paid') ?></span>
                                                    <span class="value"><?= htmlspecialchars($row['total_paid'] ?? '0') ?></span>
                                                </div>
                                                <div class="financial-item warning">
                                                    <span class="label"><?= __('bank') ?></span>
                                                    <span class="value"><?= htmlspecialchars($row['total_paid_to_bank'] ?? '0') ?></span>
                                                </div>
                                                <div class="financial-item danger">
                                                    <span class="label"><?= __('due') ?></span>
                                                    <span class="value"><?= htmlspecialchars($row['total_due'] ?? '0') ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Members Section (Initially Hidden) -->
                                    <div class="members-section" id="members-<?= $familyId ?>" style="display: none;">
                                        <div class="members-header">
                                            <h4><?= __('family_members') ?></h4>
                                            <button class="btn-sm btn-primary" onclick="openBookingModal(<?= $familyId ?>, '<?= addslashes($row['package_type']) ?>')">
                                                <i class="fas fa-plus"></i> <?= __('add_member') ?>
                                            </button>
                                        </div>
                                        <div class="members-grid" id="members-grid-<?= $familyId ?>">
                                            <!-- Members will be loaded via AJAX -->
                                            <div class="loading-spinner">
                                                <i class="fas fa-spinner fa-spin"></i>
                                                <?= __('loading_members') ?>...
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Enhanced Pagination -->
                        <nav class="pagination-wrapper" aria-label="Family list pagination">
                            <ul class="pagination-list">
                                <?php
                                $queryString = "";
                                if (!empty($search)) {
                                    $queryString .= "&search=" . urlencode($search);
                                }
                                if (!empty($visaStatus)) {
                                    $queryString .= "&visa_status=" . urlencode($visaStatus);
                                }
                                if (!empty($filter)) {
                                    $queryString .= "&filter=" . urlencode($filter);
                                }

                                if ($page > 1): ?>
                                    <li>
                                        <a href="?page=<?= $page - 1 . $queryString ?>" class="pagination-link">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php
                                $startPage = max(1, $page - 2);
                                $endPage = min($totalPages, $page + 2);

                                for ($i = $startPage; $i <= $endPage; $i++): ?>
                                    <li>
                                        <a href="?page=<?= $i . $queryString ?>" 
                                           class="pagination-link <?= $i == $page ? 'active' : '' ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                    <li>
                                        <a href="?page=<?= $page + 1 . $queryString ?>" class="pagination-link">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                            <div class="pagination-info">
                                <?= sprintf(__('showing_page_x_of_y'), $page, $totalPages) ?> 
                                (<?= $totalFamilies ?> <?= __('total_families') ?>)
                            </div>
                        </nav>
                    <?php else: ?>
                        <!-- Empty State -->
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="fas fa-search"></i>
                            </div>
                            <h3><?= !empty($search) ? sprintf(__('no_families_found_for_search'), htmlspecialchars($search)) : __('no_families_available') ?></h3>
                            <?php if (!empty($search)): ?>
                                <a href="?" class="btn btn-primary">
                                    <i class="fas fa-times mr-2"></i><?= __('clear_search') ?>
                                </a>
                            <?php else: ?>
                                <p><?= __('start_by_adding_a_new_family') ?></p>
                                <button class="btn btn-gradient-primary" data-toggle="modal" data-target="#createFamilyModal">
                                    <i class="fas fa-plus mr-2"></i><?= __('add_new_family') ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../modals/umrah/edit_transaction_modal.php'; ?>
<?php include '../modals/umrah/language_modal.php'; ?>
<?php include '../modals/umrah/edit_member_modal.php'; ?>
<?php include '../modals/umrah/umrah_modal.php'; ?>
<?php include '../modals/umrah/create_family_modal.php'; ?>
<?php include '../modals/umrah/transaction_modal.php'; ?>
<?php include '../modals/umrah/edit_family_modal.php'; ?>
<?php include '../modals/umrah/refund_modal.php'; ?>
<?php include '../modals/umrah/cancellation_reapply_modal.php'; ?>
<?php include '../modals/umrah/multi_ticket_invoice_modal.php'; ?>
<?php include '../modals/umrah/completion_details_modal.php'; ?>
<?php include '../modals/umrah/cancellation_details_modal.php'; ?>
<?php include '../modals/umrah/family_language_modal.php'; ?>
<?php include '../modals/umrah/family_completion_details_modal.php'; ?>
<?php include '../modals/umrah/family_cancellation_details_modal.php'; ?>
<?php include '../modals/umrah/member_document_template.php'; ?>
<?php include '../modals/umrah/member_details_modal.php'; ?>
<?php include '../modals/umrah/member_documents_modal.php'; ?>
<?php include '../modals/umrah/date_change_modal.php'; ?>
<?php include '../modals/umrah/bank_receipt_modal.php'; ?>
<?php include '../modals/umrah/umrah_presidency_modal.php'; ?>
<?php include '../modals/umrah/group_ticket_modal.php'; ?>
<?php include '../modals/umrah/id_card_modal.php'; ?>
<?php include '../modals/umrah/family_transaction_modal.php'; ?>

<!-- Floating action buttons -->
<div id="groupTicketFloatingButton" class="floating-action-btn" style="display: none; bottom: 220px; right: 23px;">
    <button type="button" class="fab-button" id="showGroupTicketModal">
        <i class="fas fa-plane"></i>
        <span class="fab-badge" id="groupTicketSelectionCount">0</span>
    </button>
</div>

<div id="idCardFloatingButton" class="floating-action-btn" style="display: none; bottom: 85px; right: 23px;">
    <button type="button" class="fab-button fab-dark" id="showIdCardModal">
        <i class="fas fa-id-card"></i>
        <span class="fab-badge" id="idCardSelectionCount">0</span>
    </button>
</div>

<!-- Required Scripts -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>

<!-- Document Upload Script -->
<script src="../js/member-document-upload.js"></script>



<script src="../js/umrah/transaction_manager.js"></script>
<script src="../js/umrah/bookings.js"></script>
<script src="../js/umrah/approve_booking.js"></script>
<script src="../js/umrah/refund.js?v=1"></script>
<script src="../js/umrah/cancellation_reapply.js"></script>
<script src="../js/umrah/idcard.js"></script>
<script src="../js/umrah/groupTickets.js"></script>
<script src="../js/umrah/family.js"></script>
<script src="../js/umrah/generations.js"></script>
<script src="../js/umrah/generations_received_form.js"></script>
<script src="../js/umrah/generate_completion.js"></script>
<script src="../js/umrah/generate_cancelation.js"></script>
<script src="../js/umrah/family_documents.js"></script>
<script src="../js/umrah/generate_bankandumrah.js"></script>
<script src="../js/umrah/date_change_request.js"></script>
<script src="../js/umrah/multi_ticket.js"></script>
<script src="../js/umrah/add_member.js"></script>
<script src="../js/umrah/edit_member.js"></script>
<script src="../js/umrah/family_cancellation.js"></script>
<script src="../js/umrah/view_member_details.js"></script>
<script src="../js/umrah/family_transaction_manager.js"></script>
<script src="../js/umrah/umrah-forms.js"></script>

<!-- Tesseract.js -->
<script src="https://cdn.jsdelivr.net/npm/tesseract.js@5.1.0/dist/tesseract.min.js"></script>
<script src="../js/umrah/document-upload-handler.js"></script>
<script src="../js/umrah/open_documents_modal.js"></script>
<!-- Custom Scripts -->
<script>
    // Set CSRF token
    window.csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';

    // Toast notification
    function showToast(type, message) {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
        
        Toast.fire({
            icon: type,
            title: message
        });
    }

    // View family members with AJAX loading
    window.viewFamilyMembers = function(familyId) {
        try {
            const sectionId = 'members-' + familyId;
            const gridId = 'members-grid-' + familyId;
            const section = document.getElementById(sectionId);
            const grid = document.getElementById(gridId);
            const card = document.querySelector('[data-family-id="' + familyId + '"]');
            
            console.log('VIEW: familyId=' + familyId + ', section=' + (section ? 'FOUND' : 'NOT FOUND') + ', grid=' + (grid ? 'FOUND' : 'NOT FOUND'));
            
            if (!section || !grid) {
                console.error('ERROR: Could not find section or grid');
                return false;
            }
            
            const isHidden = section.style.display === 'none';
            section.style.display = isHidden ? 'block' : 'none';
            
            // Add/remove members-visible class to the card
            if (card) {
                if (isHidden) {
                    card.classList.add('members-visible');
                } else {
                    card.classList.remove('members-visible');
                }
            }
            
            console.log('VIEW: Display changed to ' + section.style.display);
            
            if (isHidden && grid.innerHTML.includes('loading-spinner')) {
                console.log('VIEW: Loading members...');
                window.loadFamilyMembers(familyId);
            }
            return false;
        } catch(err) {
            console.error('VIEW ERROR:', err);
            console.error('Stack:', err.stack);
        }
    };

    // Load family members via AJAX
    window.loadFamilyMembers = function(familyId) {
        const gridId = 'members-grid-' + familyId;
        const grid = document.getElementById(gridId);
        
        console.log('LOAD: familyId=' + familyId + ', grid=' + (grid ? 'FOUND' : 'NOT FOUND'));
        
        if (!grid) {
            alert('ERROR: Grid element not found: ' + gridId);
            return;
        }
        
        grid.innerHTML = '<div style="padding: 20px; text-align: center;"><i class="fas fa-spinner fa-spin"></i> Loading members...</div>';
        
        const url = '../api/umrah/load_family_members.php?family_id=' + familyId;
        console.log('LOAD: Fetching from ' + url);
        
        fetch(url)
            .then(function(response) {
                console.log('LOAD: Response status ' + response.status);
                if (!response.ok) throw new Error('HTTP ' + response.status);
                return response.json();
            })
            .then(function(data) {
                console.log('LOAD: Data received', data);
                if (data.success) {
                    grid.innerHTML = data.html;
                    console.log('LOAD: Success - members displayed');
                } else {
                    grid.innerHTML = '<div style="color: red; padding: 20px;">ERROR: ' + (data.message || 'Unknown error') + '</div>';
                    console.error('LOAD: API error - ' + data.message);
                }
            })
            .catch(function(error) {
                console.error('LOAD: Fetch error', error);
                grid.innerHTML = '<div style="color: red; padding: 20px;">ERROR: ' + error.message + '</div>';
                alert('Error loading members: ' + error.message);
            });
    };

    // Auto-expand members when searching
    if (window.location.search.includes('search=')) {
        document.querySelectorAll('.members-section').forEach(section => {
            section.style.display = 'block';
            const familyId = section.id.replace('members-', '');
            loadFamilyMembers(familyId);
        });
    }

    // Add event listener to view members buttons (run immediately, don't wait for DOMContentLoaded)
    function attachMembersButtonListeners() {
        console.log('Attaching members button listeners...');
        const buttons = document.querySelectorAll('.view-members-btn');
        console.log('Found ' + buttons.length + ' buttons');
        buttons.forEach(function(button) {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const familyId = this.getAttribute('data-family-id');
                console.log('Button clicked for family ' + familyId);
                try {
                    var result = window.viewFamilyMembers(familyId);
                    console.log('viewFamilyMembers returned:', result);
                } catch(ex) {
                    console.error('Exception:', ex);
                }
            });
        });
    }
    
    // Attach listeners immediately
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', attachMembersButtonListeners);
    } else {
        attachMembersButtonListeners();
    }
</script>
<?php include '../includes/admin_footer.php'; ?>
</body>
</html>