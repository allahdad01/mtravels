<?php
    // Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}    
// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Include language helper
require_once '../includes/language_helpers.php';

// Enforce authentication
enforce_auth();

// Check if user is logged in with proper role
require_permission('hotels.view');

// Database connection
require_once('../includes/db.php');

// Check if user is admin or finance
$canEdit = user_can('hotels.edit');

// Load hotel bookings using handler
include '../api/hotel/hotel_handler.php';

// Include utility functions
require_once('../includes/utils.php');

$paginationPattern = empty($search)
    ? '?page='
    : '?search=' . urlencode($search) . '&page=';

?>

<?php include '../includes/header.php'; ?>
<!-- CSRF Token Meta Tag for dynamic form updates -->
<meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

<link rel="stylesheet" href="../css/general/modal-styles.css">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Playfair+Display:wght@600&display=swap" rel="stylesheet">
<style>
/* ═══════════════════════════════════════════════════════
   Hotel Bookings — Row Card Design
   Prefixed with hb- to avoid conflicts with existing CSS
   ═══════════════════════════════════════════════════════ */

:root {
    --hb-bg:         #f0f2f7;
    --hb-surface:    #ffffff;
    --hb-border:     #e4e8f0;
    --hb-text-1:     #111827;
    --hb-text-2:     #4b5563;
    --hb-text-3:     #9ca3af;
    --hb-accent:     #1a56db;
    --hb-accent-lt:  #eff3ff;
    --hb-green:      #059669;
    --hb-green-lt:   #ecfdf5;
    --hb-amber:      #d97706;
    --hb-amber-lt:   #fffbeb;
    --hb-red:        #dc2626;
    --hb-red-lt:     #fef2f2;
    --hb-radius:     14px;
    --hb-shadow:     0 1px 3px rgba(0,0,0,.07), 0 4px 12px rgba(0,0,0,.06);
    --hb-shadow-hover: 0 4px 8px rgba(0,0,0,.08), 0 14px 28px rgba(0,0,0,.1);
}

/* ── Page Header ─────────────────────────────────────────── */
.hb-page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 14px;
}
.hb-page-header-left h1 {
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 1.65rem;
    font-weight: 600;
    color: var(--hb-text-1);
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0;
}
.hb-page-header-left h1 i {
    color: var(--hb-accent);
    font-size: 1.3rem;
}
.hb-page-header-left p {
    color: var(--hb-text-3);
    font-size: .85rem;
    margin: 3px 0 0;
}
.hb-page-header-right {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}
.hb-btn-back {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 9px 18px;
    border-radius: 10px;
    border: 1px solid var(--hb-border);
    background: var(--hb-surface);
    color: var(--hb-text-2);
    font-size: .85rem;
    font-weight: 500;
    text-decoration: none;
    transition: all .2s;
}
.hb-btn-back:hover {
    border-color: var(--hb-accent);
    color: var(--hb-accent);
    text-decoration: none;
}
.hb-btn-new {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 10px 20px;
    border-radius: 10px;
    border: none;
    background: var(--hb-accent);
    color: #fff;
    font-size: .875rem;
    font-weight: 600;
    cursor: pointer;
    transition: background .2s, transform .15s, box-shadow .2s;
    box-shadow: 0 2px 8px rgba(26,86,219,.28);
    font-family: inherit;
}
.hb-btn-new:hover {
    background: #1648c2;
    transform: translateY(-1px);
    box-shadow: 0 4px 16px rgba(26,86,219,.35);
    color: #fff;
    text-decoration: none;
}

/* ── Toolbar ─────────────────────────────────────────────── */
.hb-toolbar {
    display: flex;
    gap: 12px;
    margin-bottom: 18px;
    flex-wrap: wrap;
    align-items: center;
}
.hb-search-wrap {
    position: relative;
    flex: 1;
    min-width: 220px;
    max-width: 380px;
}
.hb-search-wrap > i.feather {
    position: absolute;
    left: 13px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--hb-text-3);
    font-size: .85rem;
    pointer-events: none;
}
.hb-search-wrap input {
    width: 100%;
    padding: 10px 38px 10px 38px;
    border: 1px solid var(--hb-border);
    border-radius: 10px;
    font-family: inherit;
    font-size: .875rem;
    background: var(--hb-surface);
    color: var(--hb-text-1);
    outline: none;
    box-shadow: var(--hb-shadow);
    transition: border-color .2s, box-shadow .2s;
}
.hb-search-wrap input:focus {
    border-color: var(--hb-accent);
    box-shadow: 0 0 0 3px rgba(26,86,219,.12);
}
.hb-clear-btn {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--hb-text-3);
    font-size: .85rem;
    display: flex;
    align-items: center;
    padding: 4px;
    border-radius: 4px;
    transition: color .2s;
}
.hb-clear-btn:hover { color: var(--hb-red); }
.hb-filter-tabs {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}
.hb-filter-tab {
    padding: 8px 16px;
    border-radius: 8px;
    border: 1px solid var(--hb-border);
    background: var(--hb-surface);
    font-family: inherit;
    font-size: .8rem;
    font-weight: 500;
    color: var(--hb-text-2);
    cursor: pointer;
    transition: all .2s;
    box-shadow: var(--hb-shadow);
}
.hb-filter-tab.active,
.hb-filter-tab:hover {
    background: var(--hb-accent-lt);
    border-color: #c7d7fb;
    color: var(--hb-accent);
}

/* ── Column Headers ──────────────────────────────────────── */
.hb-list-header {
    display: grid;
    grid-template-columns: 6px 52px 1fr 160px 180px 120px 140px 200px;
    align-items: center;
    padding: 0 0 8px;
}
.hb-list-header > div {
    font-size: .68rem;
    font-weight: 700;
    letter-spacing: .07em;
    text-transform: uppercase;
    color: var(--hb-text-3);
    padding: 0 12px;
}
.hb-list-header .lh-bar,
.hb-list-header .lh-icon { padding: 0; }
.hb-list-header .lh-guest { padding-left: 16px; }
.hb-list-header .lh-price { text-align: right; }
.hb-list-header .lh-actions { padding-right: 16px; }

/* ── Payment Status Colors ───────────────────────────────── */
.hb-row.status-paid    { --hb-payment-color: #7aaa62; }
.hb-row.status-partial { --hb-payment-color: #d4a574; }
.hb-row.status-unpaid  { --hb-payment-color: #e07a7a; }
.hb-row.status-neutral { --hb-payment-color: #6b8fb3; }

.hb-row.status-paid .hb-accent-bar,
.hb-row.status-partial .hb-accent-bar,
.hb-row.status-unpaid .hb-accent-bar,
.hb-row.status-neutral .hb-accent-bar {
    background: var(--hb-payment-color) !important;
}

/* ── List Wrapper ────────────────────────────────────────── */
.hb-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

/* ── Booking Row ─────────────────────────────────────────── */
.hb-row {
    display: grid;
    grid-template-columns: 6px 52px 1fr 160px 180px 120px 140px 200px;
    align-items: center;
    background: var(--hb-surface);
    border-radius: var(--hb-radius);
    border: 1px solid var(--hb-border);
    box-shadow: var(--hb-shadow);
    overflow: visible !important;
    transition: box-shadow .25s, transform .25s;
    animation: hbFadeUp .35s ease both;
    min-height: 88px;
    position: relative;
}
.hb-row:hover {
    box-shadow: var(--hb-shadow-hover);
    transform: translateY(-2px);
}
.hb-row-cancelled {
    opacity: .72;
}

@keyframes hbFadeUp {
    from { opacity: 0; transform: translateY(10px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* ── Accent Bar ──────────────────────────────────────────── */
.hb-accent-bar {
    height: 100%;
    min-height: 88px;
    border-radius: var(--hb-radius) 0 0 var(--hb-radius);
}

/* ── Icon Cell ───────────────────────────────────────────── */
.hb-icon-cell {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 0 0 10px;
}
.hb-hotel-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: var(--hb-accent-lt);
    color: var(--hb-accent);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .95rem;
    flex-shrink: 0;
}

/* ── Guest Cell ──────────────────────────────────────────── */
.hb-guest-cell {
    padding: 14px 12px 14px 16px;
    min-width: 0;
}
.hb-guest-name {
    font-weight: 600;
    font-size: .9375rem;
    color: var(--hb-text-1);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.hb-row-cancelled .hb-guest-name {
    text-decoration: line-through;
    color: var(--hb-text-3);
}
.hb-guest-meta {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 4px;
    flex-wrap: wrap;
}
.hb-guest-meta span {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: .78rem;
    color: var(--hb-text-3);
    white-space: nowrap;
}
.hb-guest-meta span .feather { font-size: .72rem; }
.hb-order-id {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    margin-top: 6px;
    font-size: .73rem;
    font-weight: 700;
    color: var(--hb-accent);
    background: var(--hb-accent-lt);
    padding: 2px 8px;
    border-radius: 20px;
}
.hb-order-id.hb-order-id-muted {
    color: var(--hb-text-3);
    background: #f3f4f6;
}
.hb-order-id .feather { font-size: .65rem; }

/* ── Shared Cell Styles ──────────────────────────────────── */
.hb-room-cell,
.hb-dates-cell,
.hb-price-cell,
.hb-status-cell {
    padding: 14px 12px;
    border-left: 1px solid var(--hb-border);
    min-width: 0;
}
.hb-cell-label {
    font-size: .68rem;
    font-weight: 700;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: var(--hb-text-3);
    margin-bottom: 5px;
}

/* ── Room Cell ───────────────────────────────────────────── */
.hb-room-name {
    font-size: .875rem;
    font-weight: 500;
    color: var(--hb-text-1);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* ── Dates Cell ──────────────────────────────────────────── */
.hb-dates-track {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: nowrap;
}
.hb-date-block { flex-shrink: 0; }
.hb-date-day {
    display: block;
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--hb-text-1);
    line-height: 1;
}
.hb-date-month {
    display: block;
    font-size: .68rem;
    color: var(--hb-text-3);
    text-transform: uppercase;
    letter-spacing: .04em;
    margin-top: 2px;
}
.hb-arrow {
    color: var(--hb-text-3);
    font-size: .75rem;
    flex-shrink: 0;
    display: flex;
    align-items: center;
}
.hb-nights-pill {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: #f3f4f6;
    border-radius: 20px;
    padding: 3px 8px;
    font-size: .72rem;
    font-weight: 700;
    color: var(--hb-text-2);
    white-space: nowrap;
    flex-shrink: 0;
}
.hb-nights-pill .feather { font-size: .65rem; }

/* ── Price Cell ──────────────────────────────────────────── */
.hb-price-cell { text-align: right; }
.hb-price-amount {
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--hb-text-1);
    letter-spacing: -.2px;
    white-space: nowrap;
}
.hb-price-amount.hb-muted { color: var(--hb-text-3); }
.hb-currency {
    font-size: .72rem;
    font-weight: 500;
    color: var(--hb-text-3);
    margin-right: 2px;
}
.hb-profit-row {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    margin-top: 5px;
    background: var(--hb-green-lt);
    padding: 2px 8px;
    border-radius: 20px;
}
.hb-profit-row .feather { font-size: .65rem; color: var(--hb-green); }
.hb-profit-row span { font-size: .73rem; font-weight: 600; color: var(--hb-green); }
.hb-profit-row.hb-refunded { background: #f3f4f6; }
.hb-profit-row.hb-refunded .feather { color: var(--hb-text-3); }
.hb-profit-row.hb-refunded span { color: var(--hb-text-3); }

/* ── Status Cell ─────────────────────────────────────────── */
.hb-status-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: .75rem;
    font-weight: 600;
    white-space: nowrap;
}
.hb-dot {
    width: 6px; height: 6px;
    border-radius: 50%;
    flex-shrink: 0;
}
.hb-status-confirmed { background: var(--hb-green-lt); color: var(--hb-green); }
.hb-status-confirmed .hb-dot {
    background: var(--hb-green);
    box-shadow: 0 0 0 2px rgba(5,150,105,.2);
    animation: hbPulse 2s infinite;
}
.hb-status-pending { background: var(--hb-amber-lt); color: var(--hb-amber); }
.hb-status-pending .hb-dot { background: var(--hb-amber); }
.hb-status-cancelled { background: #f3f4f6; color: var(--hb-text-3); }
.hb-status-cancelled .hb-dot { background: var(--hb-text-3); }

@keyframes hbPulse {
    0%, 100% { box-shadow: 0 0 0 2px rgba(5,150,105,.2); }
    50%       { box-shadow: 0 0 0 4px rgba(5,150,105,.08); }
}

.hb-created-by {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: .78rem;
    color: var(--hb-text-3);
    margin-top: 6px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.hb-created-by .feather { font-size: .7rem; flex-shrink: 0; }

/* ── Actions Cell ────────────────────────────────────────── */
.hb-actions-cell {
    padding: 14px 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    border-left: 1px solid var(--hb-border);
    flex-wrap: wrap;
    min-width: 0;
    overflow: visible;
}
.hb-btn-icon {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    border: 1px solid var(--hb-border);
    background: var(--hb-surface);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: .8rem;
    cursor: pointer;
    transition: all .18s;
    color: var(--hb-text-2);
    position: relative;
    flex-shrink: 0;
}
.hb-btn-icon:hover { border-color: transparent; }
.hb-btn-icon.hb-view  { color: var(--hb-accent); }
.hb-btn-icon.hb-view:hover  { background: var(--hb-accent-lt); }
.hb-btn-icon.hb-edit  { color: var(--hb-amber); }
.hb-btn-icon.hb-edit:hover  { background: var(--hb-amber-lt); }
.hb-btn-icon.hb-trans  { color: var(--hb-green); }
.hb-btn-icon.hb-trans:hover { background: var(--hb-green-lt); }
.hb-btn-icon.hb-refund  { color: var(--hb-green); }
.hb-btn-icon.hb-refund:hover  { background: var(--hb-green-lt); }
.hb-btn-icon.hb-delete  { color: var(--hb-red); }
.hb-btn-icon.hb-delete:hover  { background: var(--hb-red-lt); }

/* Tooltip */
.hb-btn-icon[data-tip]::after {
    content: attr(data-tip);
    position: absolute;
    bottom: calc(100% + 7px);
    left: 50%;
    transform: translateX(-50%);
    background: #1f2937;
    color: #fff;
    font-size: .68rem;
    padding: 3px 8px;
    border-radius: 5px;
    white-space: nowrap;
    opacity: 0;
    pointer-events: none;
    transition: opacity .15s;
    z-index: 200;
}
.hb-btn-icon[data-tip]:hover::after { opacity: 1; }

/* ── Empty State ─────────────────────────────────────────── */
.hb-empty {
    background: var(--hb-surface);
    border: 1px dashed var(--hb-border);
    border-radius: var(--hb-radius);
    padding: 64px 24px;
    text-align: center;
    color: var(--hb-text-3);
}
.hb-empty .feather { font-size: 2.5rem; display: block; margin: 0 auto 14px; }
.hb-empty h4 { font-size: 1rem; font-weight: 600; color: var(--hb-text-2); margin-bottom: 6px; }
.hb-empty p  { font-size: .875rem; }

/* ── Pagination ──────────────────────────────────────────── */
.hb-pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 20px;
    flex-wrap: wrap;
    gap: 12px;
}
.hb-pagination-info {
    font-size: .8rem;
    color: var(--hb-text-3);
}
.hb-pager {
    display: flex;
    gap: 5px;
    align-items: center;
}
.hb-page-btn {
    min-width: 34px;
    height: 34px;
    padding: 0 8px;
    border-radius: 8px;
    border: 1px solid var(--hb-border);
    background: var(--hb-surface);
    color: var(--hb-text-2);
    font-size: .82rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    transition: all .18s;
    cursor: pointer;
}
.hb-page-btn:hover:not(.disabled):not(.hb-page-active) {
    border-color: var(--hb-accent);
    color: var(--hb-accent);
    text-decoration: none;
}
.hb-page-btn.hb-page-active {
    background: var(--hb-accent);
    border-color: var(--hb-accent);
    color: #fff;
    box-shadow: 0 2px 8px rgba(26,86,219,.28);
}
.hb-page-btn.disabled {
    opacity: .4;
    pointer-events: none;
}
.hb-page-ellipsis {
    color: var(--hb-text-3);
    font-size: .8rem;
    padding: 0 4px;
    display: inline-flex;
    align-items: center;
}

/* ── Responsive ──────────────────────────────────────────── */
@media (max-width: 1200px) {
    .hb-row,
    .hb-list-header {
        grid-template-columns: 6px 48px 1fr 170px 190px 140px auto;
    }
    .hb-status-cell,
    .hb-list-header .lh-status { display: none; }
}

@media (max-width: 960px) {
    .hb-row,
    .hb-list-header {
        grid-template-columns: 6px 0 1fr 170px 140px auto;
    }
    .hb-icon-cell,
    .hb-list-header .lh-icon { display: none; }
    .hb-dates-cell,
    .hb-list-header .lh-dates { display: none; }
}

@media (max-width: 700px) {
    .hb-row,
    .hb-list-header {
        grid-template-columns: 6px 1fr auto;
    }
    .hb-room-cell,
    .hb-price-cell,
    .hb-list-header .lh-room,
    .hb-list-header .lh-price { display: none; }
    .hb-list-header { display: none; }
    .hb-actions-cell { border-left: none; }
}

@media (max-width: 480px) {
    .hb-page-header { flex-direction: column; align-items: flex-start; }
    .hb-page-header-right { width: 100%; }
    .hb-btn-new,
    .hb-btn-back { width: 100%; justify-content: center; }
    .hb-toolbar { flex-direction: column; }
    .hb-search-wrap { max-width: 100%; }
}

/* ── Toast (keep existing) ───────────────────────────────── */
.toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
    max-width: 350px;
}
/* ─── FAB ──────────────────────────────────────────────── */
.pg-fab {
    position: fixed;
    bottom: 80px;
    z-index: 1050;
}

.pg-fab button {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: #185FA5;
    border: none;
    color: #fff;
    font-size: 25px;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(24,95,165,0.35);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background .15s;
}

.pg-fab button:hover {
    background: #0C447C;
}
</style>
<!-- Main Content -->
<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <div class="main-body">
                    <div class="page-wrapper">

                        <!-- Page Header -->
                        <div class="hb-page-header">
                            <div class="hb-page-header-left">
                                <h1><i class="fa-solid fa-hotel"></i><?= __('hotel_bookings') ?></h1>
                                <p><?= __('manage_hotel_bookings_efficiently') ?></p>
                            </div>
                            <div class="hb-page-header-right">
                                <a href="dashboard.php" class="hb-btn-back">
                                    <i class="feather icon-arrow-left"></i><?= __('back_to_dashboard') ?>
                                </a>
                                <button class="hb-btn-new" data-toggle="modal" data-target="#addBookingModal">
                                    <i class="feather icon-plus"></i><?= __('new_booking') ?>
                                </button>
                            </div>
                        </div>

                        <!-- Toast Container -->
                        <div class="toast-container"></div>

                        <!-- Toolbar -->
                        <div class="hb-toolbar">
                            <form class="hb-search-wrap" method="get">
                                <i class="feather icon-search"></i>
                                <input type="text"
                                       id="searchBookings"
                                       name="search"
                                       value="<?= htmlspecialchars($search ?? '') ?>"
                                       placeholder="<?= __('search_bookings') ?>…">
                                <?php if (!empty($search)): ?>
                                    <a href="hotel.php" class="hb-clear-btn" title="<?= __('clear') ?>">
                                        <i class="feather icon-x"></i>
                                    </a>
                                <?php endif; ?>
                            </form>
                            <div class="hb-filter-tabs">
                                <button class="hb-filter-tab active" data-filter="all">All</button>
                                <button class="hb-filter-tab" data-filter="confirmed">Confirmed</button>
                                <button class="hb-filter-tab" data-filter="pending">Pending</button>
                                <button class="hb-filter-tab" data-filter="cancelled">Cancelled</button>
                            </div>
                        </div>

                        <!-- Column Headers -->
                        <div class="hb-list-header">
                            <div class="lh-bar"></div>
                            <div class="lh-icon"></div>
                            <div class="lh-guest">Guest</div>
                            <div class="lh-room">Room</div>
                            <div class="lh-dates">Dates</div>
                            <div class="lh-price">Amount</div>
                            <div class="lh-status">Status</div>
                            <div class="lh-actions"></div>
                        </div>

                        <!-- Bookings List -->
                        <div class="hb-list" id="bookingsContainer">
                            <?php if (!empty($bookings)): ?>
                                <?php foreach ($bookings as $i => $booking): ?>
                                    <?php
                                    // Check agency client
                                    $isAgencyClient = false;
                                    if (!empty($booking['sold_to'])) {
                                        try {
                                            $clientStmt = $pdo->prepare("SELECT client_type FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                                            $clientStmt->execute([$booking['sold_to'], $tenant_id, $branch_id]);
                                            $clientRow = $clientStmt->fetch(PDO::FETCH_ASSOC);
                                            if ($clientRow) {
                                                $isAgencyClient = ($clientRow['client_type'] === 'agency');
                                            }
                                        } catch (PDOException $e) {
                                            error_log("Error checking client type: " . $e->getMessage());
                                        }
                                    }

                                    // Compute nights
                                    $nights = '';
                                    if (!empty($booking['check_in_date']) && !empty($booking['check_out_date'])) {
                                        $cin  = new DateTime($booking['check_in_date']);
                                        $cout = new DateTime($booking['check_out_date']);
                                        $nights = $cin->diff($cout)->days;
                                    }

                                    // Status — default confirmed; extend as needed
                                    $status     = $booking['status'] ?? 'confirmed';
                                    $statusMap  = [
                                        'confirmed' => ['label' => 'Confirmed', 'class' => 'hb-status-confirmed', 'bar' => 'linear-gradient(180deg,#1a56db 0%,#7c3aed 100%)'],
                                        'pending'   => ['label' => 'Pending',   'class' => 'hb-status-pending',   'bar' => 'linear-gradient(180deg,#d97706 0%,#f59e0b 100%)'],
                                        'cancelled' => ['label' => 'Cancelled', 'class' => 'hb-status-cancelled', 'bar' => '#d1d5db'],
                                        'refunded'  => ['label' => 'Refunded',  'class' => 'hb-status-cancelled', 'bar' => '#d1d5db'],
                                    ];
                                    $statusInfo = $statusMap[$status] ?? $statusMap['confirmed'];
                                    $isCancelled = ($status === 'cancelled' || $status === 'refunded');

                                    // Icon colour per status
                                    $iconStyle = match($status) {
                                        'pending'   => 'background:#fffbeb; color:#d97706;',
                                        'cancelled' => 'background:#f9fafb; color:#9ca3af;',
                                        'refunded'  => 'background:#f9fafb; color:#9ca3af;',
                                        default     => 'background:#eff3ff; color:#1a56db;',
                                    };

                                    // Calculate payment status
                                    $isAgencyClient = false;
                                    $soldTo = $booking['client_name'] ?? '';
                                    $clientStmt = $pdo->prepare("SELECT client_type FROM clients WHERE name = ? AND tenant_id = ? AND branch_id = ?");
                                    $clientStmt->bindParam(1, $soldTo, PDO::PARAM_STR);
                                    $clientStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
                                    $clientStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
                                    $clientStmt->execute();
                                    $clientRow = $clientStmt->fetch(PDO::FETCH_ASSOC);
                                    if ($clientRow) {
                                        $isAgencyClient = ($clientRow['client_type'] === 'agency');
                                    }

                                    $paymentStatus = 'neutral';
                                    $totalPaidInBase = 0;
                                    $baseCurrency = $booking['currency'];
                                    $soldAmount = floatval($booking['sold_amount']);
                                    $bookingId = $booking['id'];

                                    if ($isAgencyClient) {
                                        $transactionStmt = $pdo->prepare("SELECT * FROM main_account_transactions WHERE
                                            transaction_of = 'hotel'
                                            AND reference_id = ? AND tenant_id = ? AND branch_id = ?");
                                        $transactionStmt->bindParam(1, $bookingId, PDO::PARAM_INT);
                                        $transactionStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
                                        $transactionStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
                                        $transactionStmt->execute();
                                        $transactions = $transactionStmt->fetchAll(PDO::FETCH_ASSOC);

                                        if ($transactions && count($transactions) > 0) {
                                            foreach ($transactions as $transaction) {
                                                $amount = floatval($transaction['amount']);
                                                $transCurrency = $transaction['currency'];
                                                $transExchangeRate = isset($transaction['exchange_rate']) && $transaction['exchange_rate'] > 0
                                                    ? floatval($transaction['exchange_rate']) : 1.0;

                                                if ($transCurrency === $baseCurrency) {
                                                    $convertedAmount = $amount;
                                                } else {
                                                    if ($baseCurrency === 'AFS') {
                                                        $convertedAmount = $amount * $transExchangeRate;
                                                    } else {
                                                        $convertedAmount = $amount / $transExchangeRate;
                                                    }
                                                }
                                                $totalPaidInBase += $convertedAmount;
                                            }
                                        }

                                        if ($totalPaidInBase <= 0)               $paymentStatus = 'unpaid';
                                        elseif ($totalPaidInBase < $soldAmount)  $paymentStatus = 'partial';
                                        else                                      $paymentStatus = 'paid';
                                    }
                                    ?>
                                    <div class="hb-row<?= $isCancelled ? ' hb-row-cancelled' : '' ?> status-<?= $paymentStatus ?>" 
                                         data-booking-id="<?= $booking['id'] ?>"
                                         data-status="<?= htmlspecialchars($status) ?>"
                                         data-payment-status="<?= htmlspecialchars($paymentStatus) ?>"
                                         style="animation-delay: <?= $i * 0.04 ?>s">

                                        <!-- Accent Bar -->
                                        <div class="hb-accent-bar" style="background: <?= $statusInfo['bar'] ?>;"></div>

                                        <!-- Hotel Icon -->
                                        <div class="hb-icon-cell">
                                            <div class="hb-hotel-icon" style="<?= $iconStyle ?>">
                                                <i class="feather icon-home"></i>
                                            </div>
                                        </div>

                                        <!-- Guest -->
                                        <div class="hb-guest-cell">
                                            <div class="hb-guest-name"><?= htmlspecialchars(getValue($booking, 'guest_name')) ?></div>
                                            <div class="hb-guest-meta">
                                                <?php if (!empty($booking['contact_no'])): ?>
                                                <span><i class="feather icon-phone"></i><?= htmlspecialchars($booking['contact_no']) ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($booking['client_name'])): ?>
                                                <span><i class="feather icon-briefcase"></i><?= htmlspecialchars($booking['client_name']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="hb-order-id <?= $isCancelled ? 'hb-order-id-muted' : '' ?>">
                                                <i class="feather icon-hash"></i><?= htmlspecialchars(getValue($booking, 'order_id')) ?>
                                            </div>
                                        </div>

                                        <!-- Room -->
                                        <div class="hb-room-cell">
                                            <div class="hb-cell-label">Room</div>
                                            <div class="hb-room-name"><?= htmlspecialchars(getValue($booking, 'accommodation_details')) ?></div>
                                        </div>

                                        <!-- Dates -->
                                        <div class="hb-dates-cell">
                                            <div class="hb-cell-label">Stay</div>
                                            <div class="hb-dates-track">
                                                <div class="hb-date-block">
                                                    <span class="hb-date-day">
                                                        <?= !empty($booking['check_in_date']) ? date('d', strtotime($booking['check_in_date'])) : '—' ?>
                                                    </span>
                                                    <span class="hb-date-month">
                                                        <?= !empty($booking['check_in_date']) ? date('M Y', strtotime($booking['check_in_date'])) : '' ?>
                                                    </span>
                                                </div>
                                                <div class="hb-arrow"><i class="feather icon-arrow-right"></i></div>
                                                <div class="hb-date-block">
                                                    <span class="hb-date-day">
                                                        <?= !empty($booking['check_out_date']) ? date('d', strtotime($booking['check_out_date'])) : '—' ?>
                                                    </span>
                                                    <span class="hb-date-month">
                                                        <?= !empty($booking['check_out_date']) ? date('M Y', strtotime($booking['check_out_date'])) : '' ?>
                                                    </span>
                                                </div>
                                                <?php if ($nights !== ''): ?>
                                                <span class="hb-nights-pill">
                                                    <i class="feather icon-moon"></i><?= $nights ?>n
                                                </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Price -->
                                        <div class="hb-price-cell">
                                            <div class="hb-price-amount <?= $isCancelled ? 'hb-muted' : '' ?>">
                                                <span class="hb-currency"><?= htmlspecialchars(getValue($booking, 'currency')) ?></span>
                                                <?= number_format(getValue($booking, 'sold_amount', 0), 2) ?>
                                            </div>
                                            <?php if (!$isCancelled): ?>
                                            <div class="hb-profit-row">
                                                <i class="feather icon-trending-up"></i>
                                                <span>+ <?= getValue($booking, 'currency') ?> <?= number_format(getValue($booking, 'profit', 0), 2) ?></span>
                                            </div>
                                            <?php else: ?>
                                            <div class="hb-profit-row hb-refunded">
                                                <i class="feather icon-minus"></i><span>Refunded</span>
                                            </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Status + Created By -->
                                        <div class="hb-status-cell">
                                            <span class="hb-status-pill <?= $statusInfo['class'] ?>">
                                                <span class="hb-dot"></span><?= $statusInfo['label'] ?>
                                            </span>
                                            <div class="hb-created-by">
                                                <i class="feather icon-user"></i>
                                                <?= htmlspecialchars($booking['created_by'] ?? '') ?>
                                            </div>
                                        </div>

                                        <!-- Actions -->
                                         <div class="hb-actions-cell">
                                              <button class="hb-btn-icon hb-view"
                                                      data-id="<?= $booking['id'] ?>"
                                                      data-action="view"
                                                      data-tip="<?= __('view_details') ?>"
                                                      onclick="viewBooking(<?= $booking['id'] ?>)">
                                                  <i class="feather icon-eye"></i>
                                              </button>

                                              <?php if ($canEdit && !$isCancelled): ?>
                                              <button class="hb-btn-icon hb-edit"
                                                      data-id="<?= $booking['id'] ?>"
                                                      data-action="edit"
                                                      data-tip="<?= __('edit_booking') ?>"
                                                      onclick="editBooking(<?= $booking['id'] ?>)">
                                                  <i class="feather icon-edit-2"></i>
                                              </button>
                                              <?php endif; ?>

                                              <?php if ($isAgencyClient && user_can('hotels.transactions')): ?>
                                              <button class="hb-btn-icon hb-trans"
                                                      data-id="<?= $booking['id'] ?>"
                                                      data-action="transactions"
                                                      data-tip="<?= __('manage_transactions') ?>"
                                                      onclick="manageTransactions(<?= $booking['id'] ?>)">
                                                  <i class="fas fa-dollar-sign"></i>
                                              </button>
                                              <?php endif; ?>

                                              <?php if ($status !== 'refunded'): ?>
                                              <button class="hb-btn-icon hb-refund"
                                                       data-id="<?= $booking['id'] ?>"
                                                       data-action="refund"
                                                       data-tip="<?= __('process_refund') ?>"
                                                       onclick="openRefundModal(<?= $booking['id'] ?>, <?= $booking['sold_amount'] ?>, <?= $booking['base_amount'] ?>, '<?= $booking['currency'] ?>')">
                                                  <i class="feather icon-refresh-ccw"></i>
                                              </button>
                                              <?php endif; ?>

                                              <?php if ($canEdit): ?>
                                              <button class="hb-btn-icon hb-delete"
                                                      data-id="<?= $booking['id'] ?>"
                                                      data-action="delete"
                                                      data-tip="<?= __('delete_booking') ?>"
                                                      onclick="deleteBooking(<?= $booking['id'] ?>)">
                                                  <i class="feather icon-trash-2"></i>
                                              </button>
                                             <?php endif; ?>
                                         </div>

                                    </div><!-- /.hb-row -->
                                <?php endforeach; ?>

                            <?php else: ?>
                                <div class="hb-empty">
                                    <i class="feather icon-inbox"></i>
                                    <h4><?= __('no_bookings_found') ?></h4>
                                    <p><?= __('start_by_adding_your_first_hotel_booking') ?></p>
                                    <button class="hb-btn-new mt-3" data-toggle="modal" data-target="#addBookingModal">
                                        <i class="feather icon-plus"></i><?= __('add_new_booking') ?>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Pagination -->
                        <?php if (!empty($bookings) && isset($totalPages) && $totalPages > 1): ?>
                        <div class="hb-pagination">
                            <span class="hb-pagination-info">
                                <?php
                                if (isset($currentPage, $itemsPerPage, $totalRecords)) {
                                    $startRecord = (($currentPage - 1) * $itemsPerPage) + 1;
                                    $endRecord   = min($currentPage * $itemsPerPage, $totalRecords);
                                    echo sprintf('Showing %d–%d of %d entries', $startRecord, $endRecord, $totalRecords);
                                }
                                ?>
                            </span>
                            <nav class="hb-pager">
                                <?php
                                $prevDisabled = ($currentPage <= 1) ? 'disabled' : '';
                                echo '<a class="hb-page-btn ' . $prevDisabled . '" href="' . ($prevDisabled ? '#' : $paginationPattern . ($currentPage - 1)) . '"><i class="feather icon-chevron-left"></i></a>';

                                $maxPages  = 5;
                                $startPage = max(1, min($currentPage - floor($maxPages / 2), $totalPages - $maxPages + 1));
                                $endPage   = min($startPage + $maxPages - 1, $totalPages);

                                if ($startPage > 1) {
                                    echo '<a class="hb-page-btn" href="' . $paginationPattern . '1">1</a>';
                                    if ($startPage > 2) echo '<span class="hb-page-ellipsis">…</span>';
                                }

                                for ($p = $startPage; $p <= $endPage; $p++) {
                                    $activeClass = ($p == $currentPage) ? 'hb-page-active' : '';
                                    echo '<a class="hb-page-btn ' . $activeClass . '" href="' . $paginationPattern . $p . '">' . $p . '</a>';
                                }

                                if ($endPage < $totalPages) {
                                    if ($endPage < $totalPages - 1) echo '<span class="hb-page-ellipsis">…</span>';
                                    echo '<a class="hb-page-btn" href="' . $paginationPattern . $totalPages . '">' . $totalPages . '</a>';
                                }

                                $nextDisabled = ($currentPage >= $totalPages) ? 'disabled' : '';
                                echo '<a class="hb-page-btn ' . $nextDisabled . '" href="' . ($nextDisabled ? '#' : $paginationPattern . ($currentPage + 1)) . '"><i class="feather icon-chevron-right"></i></a>';
                                ?>
                            </nav>
                        </div>
                        <?php endif; ?>

                    </div><!-- /.page-wrapper -->
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../modals/hotel/refund_modal.php'; ?>
<?php include '../modals/hotel/transaction_modal.php'; ?>
<?php include '../modals/hotel/edit_transaction_modal.php'; ?>
<?php include '../modals/hotel/add_hotel_modal.php'; ?>
<?php include '../modals/hotel/edit_hotel_modal.php'; ?>
<?php include '../modals/hotel/view_details_modal.php'; ?>
<?php include '../modals/hotel/multi_ticket.php'; ?>



<script>
// Filter tabs — client-side show/hide by data-status
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.hb-filter-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.hb-filter-tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');

            const filter = this.dataset.filter;
            document.querySelectorAll('.hb-row').forEach(row => {
                if (filter === 'all' || row.dataset.status === filter) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    });
});
</script>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script src="../js/hotel/transactions.js<?= '?v=' . time() ?>"></script>
<script src="../js/hotel/bookings.js"></script>
<script src="../js/hotel/invoices.js"></script>
<script src="../js/hotel/refunds.js"></script>
<script src="../js/hotel/init.js"></script>
<script src="../js/hotel/toast.js"></script>
<script src="../js/hotel/extra.js"></script>

<?php include '../includes/admin_footer.php'; ?>
</body>
</html>