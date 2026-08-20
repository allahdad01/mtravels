<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in with proper role - admin only
require_permission('reports.tax');

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Database connection
require_once('../includes/db.php');

// Fetch suppliers
$suppliers_query = "SELECT id, name FROM suppliers WHERE tenant_id = ? AND status = 'active' ORDER BY name ASC";
$stmt = $pdo->prepare($suppliers_query);
$stmt->execute([$tenant_id]);
$suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch expense categories
$categories_query = "SELECT id, name FROM expense_categories WHERE tenant_id = ? ORDER BY name ASC";
$stmt = $pdo->prepare($categories_query);
$stmt->execute([$tenant_id]);
$expense_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include '../includes/header.php'; ?>

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">

<style>
    :root {
        --ink: #0f1117;
        --ink-soft: #3a3f4b;
        --ink-muted: #6b7280;
        --surface: #ffffff;
        --surface-2: #f7f8fa;
        --surface-3: #eef0f4;
        --border: #e2e5eb;
        --border-strong: #c8cdd6;
        --accent: #2563eb;
        --accent-light: #eff4ff;
        --accent-mid: #93b4fd;
        --success: #059669;
        --success-light: #ecfdf5;
        --warning: #d97706;
        --warning-light: #fffbeb;
        --danger: #dc2626;
        --danger-light: #fef2f2;
        --info: #0891b2;
        --info-light: #ecfeff;
        --radius: 10px;
        --radius-sm: 6px;
        --radius-lg: 16px;
        --shadow-sm: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
        --shadow: 0 4px 16px rgba(0,0,0,0.07), 0 1px 4px rgba(0,0,0,0.04);
        --shadow-lg: 0 12px 40px rgba(0,0,0,0.10), 0 4px 12px rgba(0,0,0,0.06);
    }

    * { box-sizing: border-box; }

    body, .pcoded-main-container {
        font-family: 'DM Sans', sans-serif;
        background: #f0f2f7 !important;
        color: var(--ink);
    }

    /* PAGE WRAPPER */
    .tqr-page {
        padding: 28px 24px;
        max-width: 1200px;
        margin: 0 auto;
    }

    /* PAGE HEADER */
    .tqr-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        margin-bottom: 28px;
    }

    .tqr-header-title {
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .tqr-header-icon {
        width: 48px;
        height: 48px;
        background: var(--accent);
        border-radius: var(--radius);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .tqr-header-icon i {
        color: #fff;
        font-size: 20px;
    }

    .tqr-header h1 {
        font-size: 22px;
        font-weight: 700;
        color: var(--ink);
        margin: 0 0 3px;
        letter-spacing: -0.3px;
    }

    .tqr-header p {
        font-size: 13.5px;
        color: var(--ink-muted);
        margin: 0;
    }

    /* CARDS */
    .tqr-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow);
        overflow: hidden;
    }

    /* REPORT TYPE SELECTOR */
    .report-type-bar {
        background: var(--surface-2);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 16px 20px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 20px;
        flex-wrap: wrap;
    }

    .report-type-label {
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        color: var(--ink-muted);
        white-space: nowrap;
    }

    .report-type-options {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .rtype-btn {
        position: relative;
    }

    .rtype-btn input[type="radio"] {
        position: absolute;
        opacity: 0;
        width: 0;
        height: 0;
    }

    .rtype-btn label {
        display: block;
        padding: 7px 16px;
        border: 1.5px solid var(--border-strong);
        border-radius: 100px;
        font-size: 13px;
        font-weight: 500;
        color: var(--ink-soft);
        cursor: pointer;
        transition: all 0.15s ease;
        user-select: none;
        background: var(--surface);
    }

    .rtype-btn input:checked + label {
        background: var(--accent);
        border-color: var(--accent);
        color: #fff;
        box-shadow: 0 2px 8px rgba(37,99,235,0.25);
    }

    .rtype-btn label:hover {
        border-color: var(--accent-mid);
        color: var(--accent);
    }

    .rtype-btn input:checked + label:hover {
        color: #fff;
    }

    /* TABS */
    .tqr-tabs {
        display: flex;
        border-bottom: 1px solid var(--border);
        background: var(--surface);
        padding: 0 24px;
    }

    .tqr-tab {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 16px 4px;
        margin-right: 28px;
        font-size: 13.5px;
        font-weight: 500;
        color: var(--ink-muted);
        border-bottom: 2px solid transparent;
        cursor: pointer;
        transition: all 0.15s ease;
        white-space: nowrap;
        text-decoration: none;
    }

    .tqr-tab i {
        font-size: 15px;
    }

    .tqr-tab:hover {
        color: var(--ink-soft);
    }

    .tqr-tab.active {
        color: var(--accent);
        border-bottom-color: var(--accent);
        font-weight: 600;
    }

    /* TAB CONTENT */
    .tqr-tab-content {
        padding: 28px 24px;
    }

    .tab-pane { display: none; }
    .tab-pane.show.active { display: block; }

    /* SECTION HEADER */
    .sec-header {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 1px solid var(--border);
    }

    .sec-header-icon {
        width: 30px;
        height: 30px;
        background: var(--accent-light);
        border-radius: var(--radius-sm);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .sec-header-icon i {
        font-size: 14px;
        color: var(--accent);
    }

    .sec-header h6 {
        font-size: 14px;
        font-weight: 650;
        color: var(--ink);
        margin: 0;
    }

    /* FORM CONTROLS */
    .form-label {
        font-size: 12.5px;
        font-weight: 600;
        color: var(--ink-soft);
        margin-bottom: 6px;
        display: block;
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }

    .form-control, .form-select {
        font-family: 'DM Sans', sans-serif;
        font-size: 14px;
        border: 1.5px solid var(--border);
        border-radius: var(--radius-sm);
        padding: 9px 13px;
        color: var(--ink);
        background: var(--surface);
        transition: border-color 0.15s, box-shadow 0.15s;
        width: 100%;
    }

    .form-control:focus, .form-select:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(37,99,235,0.10);
    }

    /* QUARTER BUTTONS */
    .quarters-selector {
        display: flex;
        gap: 8px;
    }

    .quarter-btn {
        flex: 1;
        padding: 9px 4px;
        border: 1.5px solid var(--border);
        background: var(--surface);
        border-radius: var(--radius-sm);
        cursor: pointer;
        font-family: 'DM Mono', monospace;
        font-size: 13px;
        font-weight: 500;
        color: var(--ink-muted);
        transition: all 0.15s ease;
        text-align: center;
    }

    .quarter-btn:hover {
        border-color: var(--accent-mid);
        color: var(--accent);
        background: var(--accent-light);
    }

    .quarter-btn.active {
        background: var(--accent);
        border-color: var(--accent);
        color: #fff;
        box-shadow: 0 2px 8px rgba(37,99,235,0.25);
    }

    /* INFO BOX */
    .tqr-info {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        background: var(--info-light);
        border: 1px solid rgba(8,145,178,0.2);
        border-radius: var(--radius-sm);
        padding: 11px 14px;
        font-size: 13px;
        color: var(--info);
    }

    .tqr-info i {
        font-size: 15px;
        margin-top: 1px;
        flex-shrink: 0;
    }

    /* SUPPLIER CARDS */
    .supplier-card {
        border: 1.5px solid var(--border);
        border-radius: var(--radius);
        overflow: hidden;
        margin-bottom: 10px;
        transition: border-color 0.15s, box-shadow 0.15s;
    }

    .supplier-card:hover {
        border-color: var(--border-strong);
        box-shadow: var(--shadow-sm);
    }

    .supplier-card.is-checked {
        border-color: var(--accent-mid);
        box-shadow: 0 0 0 3px rgba(37,99,235,0.07);
    }

    .supplier-card-head {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 13px 16px;
        cursor: pointer;
        background: var(--surface);
    }

    .supplier-card-head label {
        font-size: 14px;
        font-weight: 500;
        color: var(--ink);
        cursor: pointer;
        margin: 0;
        flex: 1;
    }

    .supplier-avatar {
        width: 34px;
        height: 34px;
        background: var(--surface-3);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 13px;
        font-weight: 700;
        color: var(--ink-soft);
        flex-shrink: 0;
    }

    .supplier-card.is-checked .supplier-avatar {
        background: var(--accent-light);
        color: var(--accent);
    }

    /* Custom checkbox */
    .tqr-check {
        width: 18px;
        height: 18px;
        border: 2px solid var(--border-strong);
        border-radius: 5px;
        appearance: none;
        -webkit-appearance: none;
        cursor: pointer;
        position: relative;
        flex-shrink: 0;
        transition: all 0.15s;
        background: var(--surface);
    }

    .tqr-check:checked {
        background: var(--accent);
        border-color: var(--accent);
    }

    .tqr-check:checked::after {
        content: '';
        position: absolute;
        left: 4px;
        top: 1px;
        width: 6px;
        height: 10px;
        border: 2px solid #fff;
        border-left: none;
        border-top: none;
        transform: rotate(45deg);
    }

    /* Supplier Options */
    .supplier-options {
        background: var(--surface-2);
        border-top: 1px solid var(--border);
        padding: 16px;
    }

    .data-type-selector {
        display: flex;
        gap: 8px;
        margin-bottom: 14px;
    }

    .dtype-opt {
        position: relative;
    }

    .dtype-opt input { position: absolute; opacity: 0; width: 0; }

    .dtype-opt label {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 7px 14px;
        border: 1.5px solid var(--border);
        border-radius: 100px;
        font-size: 13px;
        font-weight: 500;
        color: var(--ink-soft);
        cursor: pointer;
        transition: all 0.15s;
        background: var(--surface);
    }

    .dtype-opt input:checked + label {
        border-color: var(--accent);
        color: var(--accent);
        background: var(--accent-light);
    }

    .random-options {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        padding: 14px;
        margin-top: 10px;
    }

    .random-options .form-label {
        font-size: 11px;
    }

    /* EXCHANGE RATE BLOCK */
    .exchange-block {
        background: linear-gradient(135deg, #eff4ff 0%, #f0fdff 100%);
        border: 1px solid rgba(37,99,235,0.15);
        border-radius: var(--radius);
        padding: 16px;
        margin-bottom: 24px;
    }

    .exchange-block .form-label {
        color: var(--accent);
    }

    .exchange-input-wrap {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .exchange-input-wrap .form-control {
        max-width: 200px;
    }

    .exchange-hint {
        font-size: 12px;
        color: var(--ink-muted);
        margin-top: 6px;
    }

    /* ACTION BUTTONS */
    .btn-primary-tqr {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 22px;
        background: var(--accent);
        color: #fff;
        border: none;
        border-radius: var(--radius-sm);
        font-family: 'DM Sans', sans-serif;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.15s ease;
        box-shadow: 0 2px 8px rgba(37,99,235,0.2);
    }

    .btn-primary-tqr:hover {
        background: #1d4ed8;
        box-shadow: 0 4px 14px rgba(37,99,235,0.3);
        transform: translateY(-1px);
    }

    .btn-secondary-tqr {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 18px;
        background: var(--surface);
        color: var(--ink-soft);
        border: 1.5px solid var(--border);
        border-radius: var(--radius-sm);
        font-family: 'DM Sans', sans-serif;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.15s ease;
    }

    .btn-secondary-tqr:hover {
        border-color: var(--border-strong);
        color: var(--ink);
        background: var(--surface-2);
    }

    .btn-success-tqr {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        background: var(--success);
        color: #fff;
        border: none;
        border-radius: var(--radius-sm);
        font-family: 'DM Sans', sans-serif;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.15s ease;
    }

    .btn-success-tqr:hover {
        background: #047857;
    }

    .btn-danger-tqr {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 18px;
        background: var(--surface);
        color: var(--danger);
        border: 1.5px solid rgba(220,38,38,0.25);
        border-radius: var(--radius-sm);
        font-family: 'DM Sans', sans-serif;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.15s ease;
    }

    .btn-danger-tqr:hover {
        background: var(--danger-light);
        border-color: var(--danger);
    }

    .btn-action-row {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin: 24px 0 0;
        padding-top: 20px;
        border-top: 1px solid var(--border);
    }

    /* PREVIEW PANEL */
    .tqr-preview {
        margin-top: 28px;
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        overflow: hidden;
    }

    .tqr-preview-header {
        background: var(--surface-2);
        border-bottom: 1px solid var(--border);
        padding: 14px 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 13px;
        font-weight: 600;
        color: var(--ink-soft);
    }

    .tqr-preview-header i {
        color: var(--accent);
    }

    .tqr-preview-body {
        padding: 20px;
    }

    /* PERIOD BADGE */
    .period-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: var(--accent-light);
        border: 1px solid var(--accent-mid);
        border-radius: 100px;
        padding: 5px 14px;
        font-size: 13px;
        font-weight: 600;
        color: var(--accent);
        font-family: 'DM Mono', monospace;
        margin-bottom: 20px;
    }

    /* SUPPLIER SECTION IN PREVIEW */
    .supplier-section {
        background: var(--surface-2);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 18px;
        margin-bottom: 16px;
    }

    .supplier-section-head {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 14px;
        padding-bottom: 12px;
        border-bottom: 1px solid var(--border);
    }

    .supplier-section-head h6 {
        font-size: 14px;
        font-weight: 650;
        color: var(--ink);
        margin: 0;
    }

    .tag {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 100px;
        font-size: 11.5px;
        font-weight: 500;
    }

    .tag-blue { background: var(--accent-light); color: var(--accent); }
    .tag-gray { background: var(--surface-3); color: var(--ink-muted); }
    .tag-green { background: var(--success-light); color: var(--success); }
    .tag-yellow { background: var(--warning-light); color: var(--warning); }
    .tag-red { background: var(--danger-light); color: var(--danger); }
    .tag-cyan { background: var(--info-light); color: var(--info); }

    /* DATA TABLE */
    .tqr-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }

    .tqr-table th {
        background: var(--surface-3);
        padding: 10px 14px;
        text-align: left;
        font-size: 11.5px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--ink-muted);
        border-bottom: 1px solid var(--border);
        white-space: nowrap;
    }

    .tqr-table th:last-child { text-align: right; }

    .tqr-table td {
        padding: 10px 14px;
        border-bottom: 1px solid var(--border);
        color: var(--ink-soft);
        vertical-align: middle;
    }

    .tqr-table td:last-child { text-align: right; }

    .tqr-table tr:last-child td { border-bottom: none; }

    .tqr-table tr:hover td { background: var(--surface-2); }

    .tqr-table .row-total td {
        background: var(--surface-3);
        font-weight: 700;
        color: var(--ink);
        border-top: 2px solid var(--border-strong);
    }

    .tqr-table .row-exchange td {
        background: var(--info-light);
        font-weight: 700;
        color: var(--info);
        font-family: 'DM Mono', monospace;
    }

    .tqr-table .row-tax td {
        background: #fff5f5;
        font-weight: 700;
        color: var(--danger);
        font-family: 'DM Mono', monospace;
    }

    .mono { font-family: 'DM Mono', monospace; font-size: 12.5px; }

    /* Loading state */
    .tqr-loading {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 20px;
        color: var(--ink-muted);
        font-size: 13.5px;
    }

    @keyframes spin { to { transform: rotate(360deg); } }
    .tqr-loading i { animation: spin 1s linear infinite; }

    /* EXPENSE ITEMS */
    .expense-cat-block {
        border: 1px solid var(--border);
        border-radius: var(--radius);
        overflow: hidden;
        margin-bottom: 12px;
    }

    .expense-cat-head {
        background: var(--surface-2);
        border-bottom: 1px solid var(--border);
        padding: 11px 16px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .expense-cat-head strong {
        font-size: 13.5px;
        font-weight: 650;
        color: var(--ink);
        flex: 1;
    }

    /* ADD EXPENSE */
    .add-expense-block {
        background: var(--surface);
        border: 1.5px dashed var(--border-strong);
        border-radius: var(--radius);
        padding: 18px;
        margin-top: 20px;
    }

    /* SAVED REPORTS TABLE */
    .saved-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13.5px;
    }

    .saved-table th {
        background: var(--surface-2);
        border-bottom: 2px solid var(--border);
        padding: 10px 16px;
        text-align: left;
        font-size: 11.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--ink-muted);
    }

    .saved-table td {
        padding: 12px 16px;
        border-bottom: 1px solid var(--border);
        color: var(--ink-soft);
        vertical-align: middle;
    }

    .saved-table tr:hover td { background: var(--surface-2); }
    .saved-table tr:last-child td { border-bottom: none; }

    .saved-table-wrap {
        border: 1px solid var(--border);
        border-radius: var(--radius);
        overflow: hidden;
    }

    /* Inline action buttons in tables */
    .tbl-btn {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 5px 11px;
        border-radius: var(--radius-sm);
        font-size: 12px;
        font-weight: 500;
        cursor: pointer;
        border: 1.5px solid transparent;
        font-family: 'DM Sans', sans-serif;
        transition: all 0.12s;
    }

    .tbl-btn-view { background: var(--info-light); color: var(--info); border-color: rgba(8,145,178,0.2); }
    .tbl-btn-view:hover { background: #cffafe; border-color: var(--info); }

    .tbl-btn-excel { background: var(--success-light); color: var(--success); border-color: rgba(5,150,105,0.2); }
    .tbl-btn-excel:hover { background: #a7f3d0; border-color: var(--success); }

    .tbl-btn-delete { background: var(--danger-light); color: var(--danger); border-color: rgba(220,38,38,0.2); }
    .tbl-btn-delete:hover { background: #fecaca; border-color: var(--danger); }

    /* DIVIDER */
    .tqr-divider {
        height: 1px;
        background: var(--border);
        margin: 24px 0;
    }

    /* TOTALS SUMMARY BAR */
    .totals-bar {
        background: var(--surface-2);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 14px 20px;
        display: flex;
        gap: 32px;
        align-items: center;
        flex-wrap: wrap;
        margin-top: 16px;
    }

    .totals-bar-item { text-align: center; }

    .totals-bar-item .label {
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        color: var(--ink-muted);
        margin-bottom: 3px;
    }

    .totals-bar-item .value {
        font-size: 17px;
        font-weight: 700;
        font-family: 'DM Mono', monospace;
        color: var(--ink);
    }

    .totals-bar-item .value.accent { color: var(--accent); }
    .totals-bar-item .value.danger { color: var(--danger); }
    .totals-bar-item .value.success { color: var(--success); }

    /* FORM ROW */
    .form-row {
        display: grid;
        gap: 16px;
        margin-bottom: 20px;
    }

    .form-row-2 { grid-template-columns: 1fr 1fr; }
    .form-row-3 { grid-template-columns: 1fr 1fr 1fr; }

    @media (max-width: 640px) {
        .form-row-2, .form-row-3 { grid-template-columns: 1fr; }
        .quarters-selector { flex-wrap: wrap; }
    }

    /* Suppliers select-all bar */
    .suppliers-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 14px;
        background: var(--surface-2);
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        margin-bottom: 12px;
    }

    .suppliers-toolbar label {
        font-size: 13px;
        font-weight: 500;
        color: var(--ink-soft);
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .supplier-count {
        font-size: 12px;
        color: var(--ink-muted);
        font-family: 'DM Mono', monospace;
    }

    /* Category checkboxes */
    .category-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 8px;
        margin-bottom: 20px;
    }

    .cat-item {
        position: relative;
    }

    .cat-item input { position: absolute; opacity: 0; width: 0; }

    .cat-item label {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 9px 12px;
        border: 1.5px solid var(--border);
        border-radius: var(--radius-sm);
        font-size: 13.5px;
        color: var(--ink-soft);
        cursor: pointer;
        transition: all 0.15s;
        background: var(--surface);
    }

    .cat-item label .cat-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: var(--border-strong);
        flex-shrink: 0;
        transition: background 0.15s;
    }

    .cat-item input:checked + label {
        border-color: var(--accent-mid);
        color: var(--accent);
        background: var(--accent-light);
    }

    .cat-item input:checked + label .cat-dot {
        background: var(--accent);
    }

    /* Empty states */
    .tqr-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 32px 20px;
        text-align: center;
        color: var(--ink-muted);
    }

    .tqr-empty i {
        font-size: 32px;
        margin-bottom: 10px;
        opacity: 0.4;
    }

    .tqr-empty p {
        font-size: 13.5px;
        margin: 0;
    }
</style>

<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <div class="main-body">
                    <div class="page-wrapper">
                        <div class="tqr-page">

                            <!-- PAGE HEADER -->
                            <div class="tqr-header">
                                <div class="tqr-header-title">
                                    <div class="tqr-header-icon">
                                        <i class="feather icon-file-text"></i>
                                    </div>
                                    <div>
                                        <h1>Quarterly Tax Report Generator</h1>
                                        <p>Generate individual supplier or general quarterly tax reports</p>
                                    </div>
                                </div>
                            </div>

                            <!-- REPORT TYPE SELECTOR -->
                            <div class="report-type-bar">
                                <span class="report-type-label">Report Type</span>
                                <div class="report-type-options">
                                    <div class="rtype-btn">
                                        <input class="form-check-input" type="radio" name="reportType" id="type_ticket" value="ticket" checked>
                                        <label for="type_ticket">✈ Ticket</label>
                                    </div>
                                    <div class="rtype-btn">
                                        <input class="form-check-input" type="radio" name="reportType" id="type_visa" value="visa">
                                        <label for="type_visa">📋 Visa</label>
                                    </div>
                                    <div class="rtype-btn">
                                        <input class="form-check-input" type="radio" name="reportType" id="type_umrah" value="umrah">
                                        <label for="type_umrah">🕌 Umrah</label>
                                    </div>
                                    <div class="rtype-btn">
                                        <input class="form-check-input" type="radio" name="reportType" id="type_hotel" value="hotel">
                                        <label for="type_hotel">🏨 Hotel</label>
                                    </div>
                                    <div class="rtype-btn">
                                        <input class="form-check-input" type="radio" name="reportType" id="type_all" value="all">
                                        <label for="type_all">⚡ All Types</label>
                                    </div>
                                </div>
                            </div>

                            <!-- MAIN CARD -->
                            <div class="tqr-card">

                                <!-- TABS -->
                                <div class="tqr-tabs" role="tablist">
                                    <a class="tqr-tab active" id="supplier-tab" data-bs-toggle="tab" data-bs-target="#supplier-report" role="tab" aria-controls="supplier-report" aria-selected="true">
                                        <i class="feather icon-building"></i> Individual Supplier
                                    </a>
                                    <a class="tqr-tab" id="general-tab" data-bs-toggle="tab" data-bs-target="#general-report" role="tab" aria-controls="general-report" aria-selected="false">
                                        <i class="feather icon-bar-chart-2"></i> General Tax Report
                                    </a>
                                    <a class="tqr-tab" id="saved-reports-tab" data-bs-toggle="tab" data-bs-target="#saved-reports" role="tab" aria-controls="saved-reports" aria-selected="false">
                                        <i class="feather icon-archive"></i> Saved Reports
                                    </a>
                                </div>

                                <!-- TAB CONTENT -->
                                <div class="tqr-tab-content">

                                    <!-- ======================== SUPPLIER REPORT TAB ======================== -->
                                    <div class="tab-pane fade show active" id="supplier-report" role="tabpanel">
                                        <form id="supplierReportForm">

                                            <!-- PERIOD -->
                                            <div class="sec-header">
                                                <div class="sec-header-icon"><i class="feather icon-calendar"></i></div>
                                                <h6>Select Period</h6>
                                            </div>

                                            <div class="form-row form-row-2">
                                                <div>
                                                    <label class="form-label">Year</label>
                                                    <select id="supplierYear" class="form-select" required>
                                                        <option value="">Select Year</option>
                                                        <?php
                                                        $currentYear = date('Y');
                                                        for ($y = $currentYear; $y >= $currentYear - 5; $y--) {
                                                            echo "<option value=\"$y\">$y</option>";
                                                        }
                                                        ?>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="form-label">Quarter</label>
                                                    <div class="quarters-selector" id="supplierQuarters">
                                                        <button type="button" class="quarter-btn" data-quarter="Q1">Q1</button>
                                                        <button type="button" class="quarter-btn" data-quarter="Q2">Q2</button>
                                                        <button type="button" class="quarter-btn" data-quarter="Q3">Q3</button>
                                                        <button type="button" class="quarter-btn" data-quarter="Q4">Q4</button>
                                                    </div>
                                                    <input type="hidden" id="supplierQuarter" required>
                                                </div>
                                            </div>

                                            <div class="tqr-info" style="margin-bottom: 16px;">
                                                <i class="feather icon-info"></i>
                                                <span>Or specify a custom date range to override the quarter period</span>
                                            </div>

                                            <div class="form-row form-row-2" style="margin-bottom: 24px;">
                                                <div>
                                                    <label class="form-label">Start Date (Optional)</label>
                                                    <input type="date" id="supplierQuarterStart" class="form-control">
                                                </div>
                                                <div>
                                                    <label class="form-label">End Date (Optional)</label>
                                                    <input type="date" id="supplierQuarterEnd" class="form-control">
                                                </div>
                                            </div>

                                            <!-- EXCHANGE RATE -->
                                            <div class="exchange-block">
                                                <div class="sec-header" style="border-color: rgba(37,99,235,0.15); margin-bottom: 12px;">
                                                    <div class="sec-header-icon"><i class="feather icon-dollar-sign"></i></div>
                                                    <h6>Tax Configuration</h6>
                                                </div>
                                                <div class="exchange-input-wrap">
                                                    <div style="flex: 1; max-width: 240px;">
                                                        <label class="form-label">Exchange Rate</label>
                                                        <input type="number" id="exchangeRate" class="form-control" placeholder="e.g. 1.25" step="0.01" min="0" value="1">
                                                    </div>
                                                </div>
                                                <p class="exchange-hint">Profit × Exchange Rate → 4% tax extracted</p>
                                            </div>

                                            <!-- SUPPLIERS -->
                                            <div class="sec-header">
                                                <div class="sec-header-icon"><i class="feather icon-users"></i></div>
                                                <h6>Select Suppliers</h6>
                                            </div>

                                            <div class="suppliers-toolbar">
                                                <label>
                                                    <input type="checkbox" class="tqr-check" id="selectAllSuppliers">
                                                    Select all suppliers
                                                </label>
                                                <span class="supplier-count"><?= count($suppliers) ?> available</span>
                                            </div>

                                            <div id="suppliersContainer">
                                                <?php foreach ($suppliers as $supplier):
                                                    $initials = implode('', array_map(fn($w) => strtoupper($w[0]), explode(' ', trim($supplier['name']))));
                                                    $initials = substr($initials, 0, 2);
                                                ?>
                                                    <div class="supplier-card" id="supplierCard<?= $supplier['id'] ?>">
                                                        <div class="supplier-card-head">
                                                            <input class="tqr-check supplier-checkbox" type="checkbox"
                                                                   id="supplier<?= $supplier['id'] ?>"
                                                                   value="<?= $supplier['id'] ?>"
                                                                   data-supplier-name="<?= htmlspecialchars($supplier['name']) ?>">
                                                            <div class="supplier-avatar"><?= $initials ?: 'S' ?></div>
                                                            <label for="supplier<?= $supplier['id'] ?>"><?= htmlspecialchars($supplier['name']) ?></label>
                                                            <i class="feather icon-chevron-down" style="color: var(--ink-muted); font-size: 16px;"></i>
                                                        </div>

                                                        <div class="supplier-options" style="display: none;">
                                                            <label class="form-label" style="margin-bottom: 8px;">Data Source</label>
                                                            <div class="data-type-selector">
                                                                <div class="dtype-opt">
                                                                    <input class="form-check-input data-type-radio" type="radio"
                                                                           name="dataType<?= $supplier['id'] ?>"
                                                                           id="actual<?= $supplier['id'] ?>" value="actual" checked>
                                                                    <label for="actual<?= $supplier['id'] ?>">
                                                                        <i class="feather icon-database" style="font-size: 13px;"></i> Actual Data
                                                                    </label>
                                                                </div>
                                                                <div class="dtype-opt">
                                                                    <input class="form-check-input data-type-radio" type="radio"
                                                                           name="dataType<?= $supplier['id'] ?>"
                                                                           id="random<?= $supplier['id'] ?>" value="random">
                                                                    <label for="random<?= $supplier['id'] ?>">
                                                                        <i class="feather icon-shuffle" style="font-size: 13px;"></i> Random Data
                                                                    </label>
                                                                </div>
                                                            </div>

                                                            <div class="random-options" style="display: none;">
                                                                <div class="form-row form-row-3">
                                                                    <div>
                                                                        <label class="form-label">Min Profit</label>
                                                                        <input type="number" class="form-control profit-min" placeholder="1000" min="0">
                                                                    </div>
                                                                    <div>
                                                                        <label class="form-label">Max Profit</label>
                                                                        <input type="number" class="form-control profit-max" placeholder="10000" min="0">
                                                                    </div>
                                                                    <div>
                                                                        <label class="form-label">Item Count</label>
                                                                        <input type="number" class="form-control items-count" placeholder="5" min="1" value="5">
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>

                                            <!-- ACTIONS -->
                                            <div class="btn-action-row">
                                                <button type="button" class="btn-primary-tqr" id="generateSupplierReport">
                                                    <i class="feather icon-zap"></i> Generate Report
                                                </button>
                                                <button type="button" class="btn-secondary-tqr" id="exportSupplierExcel">
                                                    <i class="feather icon-file-text"></i> Export Excel
                                                </button>
                                                <button type="button" class="btn-secondary-tqr" id="exportSupplierPDF">
                                                    <i class="feather icon-download"></i> Export PDF
                                                </button>
                                            </div>

                                            <!-- PREVIEW -->
                                            <div id="supplierReportPreview" style="display: none;">
                                                <div class="tqr-preview">
                                                    <div class="tqr-preview-header">
                                                        <i class="feather icon-eye"></i> Report Preview
                                                    </div>
                                                    <div class="tqr-preview-body">
                                                        <div id="supplierReportContent"></div>
                                                    </div>
                                                </div>
                                            </div>

                                        </form>
                                    </div>

                                    <!-- ======================== GENERAL REPORT TAB ======================== -->
                                    <div class="tab-pane fade" id="general-report" role="tabpanel">
                                        <form id="generalReportForm">

                                            <div class="sec-header">
                                                <div class="sec-header-icon"><i class="feather icon-calendar"></i></div>
                                                <h6>Select Period</h6>
                                            </div>

                                            <div class="form-row form-row-2">
                                                <div>
                                                    <label class="form-label">Year</label>
                                                    <select id="generalYear" class="form-select" required>
                                                        <option value="">Select Year</option>
                                                        <?php
                                                        $currentYear = date('Y');
                                                        for ($y = $currentYear; $y >= $currentYear - 5; $y--) {
                                                            echo "<option value=\"$y\">$y</option>";
                                                        }
                                                        ?>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="form-label">Quarter</label>
                                                    <div class="quarters-selector" id="generalQuarters">
                                                        <button type="button" class="quarter-btn" data-quarter="Q1">Q1</button>
                                                        <button type="button" class="quarter-btn" data-quarter="Q2">Q2</button>
                                                        <button type="button" class="quarter-btn" data-quarter="Q3">Q3</button>
                                                        <button type="button" class="quarter-btn" data-quarter="Q4">Q4</button>
                                                    </div>
                                                    <input type="hidden" id="generalQuarter" required>
                                                </div>
                                            </div>

                                            <div class="tqr-info" style="margin-bottom: 16px;">
                                                <i class="feather icon-info"></i>
                                                <span>Or specify a custom date range to override the quarter period</span>
                                            </div>

                                            <div class="form-row form-row-2" style="margin-bottom: 24px;">
                                                <div>
                                                    <label class="form-label">Start Date (Optional)</label>
                                                    <input type="date" id="generalQuarterStart" class="form-control">
                                                </div>
                                                <div>
                                                    <label class="form-label">End Date (Optional)</label>
                                                    <input type="date" id="generalQuarterEnd" class="form-control">
                                                </div>
                                            </div>

                                            <!-- EXPENSE CATEGORIES -->
                                            <div class="sec-header">
                                                <div class="sec-header-icon"><i class="feather icon-list"></i></div>
                                                <h6>Expense Categories</h6>
                                            </div>

                                            <div class="category-grid" id="expenseCategoriesCheckboxes">
                                                <?php foreach ($expense_categories as $cat): ?>
                                                    <div class="cat-item">
                                                        <input class="expense-category-checkbox" type="checkbox"
                                                               value="<?= htmlspecialchars($cat['name']) ?>"
                                                               id="cat<?= $cat['id'] ?>">
                                                        <label for="cat<?= $cat['id'] ?>">
                                                            <span class="cat-dot"></span>
                                                            <?= htmlspecialchars($cat['name']) ?>
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>

                                            <!-- EXPENSE ITEMS (dynamic) -->
                                            <div id="expenseItemsContainer"></div>

                                            <!-- ACTIONS -->
                                            <div class="btn-action-row">
                                                <button type="button" class="btn-primary-tqr" id="generateGeneralReport">
                                                    <i class="feather icon-zap"></i> Generate Report
                                                </button>
                                                <button type="button" class="btn-secondary-tqr" id="exportGeneralExcel">
                                                    <i class="feather icon-file-text"></i> Export Excel
                                                </button>
                                                <button type="button" class="btn-secondary-tqr" id="exportGeneralPDF">
                                                    <i class="feather icon-download"></i> Export PDF
                                                </button>
                                            </div>

                                            <!-- PREVIEW -->
                                            <div id="generalReportPreview" style="display: none;">
                                                <div class="tqr-preview">
                                                    <div class="tqr-preview-header">
                                                        <i class="feather icon-eye"></i> Report Preview
                                                    </div>
                                                    <div class="tqr-preview-body">
                                                        <div id="generalReportContent"></div>
                                                    </div>
                                                </div>
                                            </div>

                                        </form>
                                    </div>

                                    <!-- ======================== SAVED REPORTS TAB ======================== -->
                                    <div class="tab-pane fade" id="saved-reports" role="tabpanel">

                                        <div class="sec-header">
                                            <div class="sec-header-icon"><i class="feather icon-filter"></i></div>
                                            <h6>Filter Saved Reports</h6>
                                        </div>

                                        <div class="form-row" style="grid-template-columns: 1fr 2fr auto; align-items: end; margin-bottom: 28px;">
                                            <div>
                                                <label class="form-label">Year</label>
                                                <select id="savedReportsYear" class="form-select">
                                                    <option value="">Select Year</option>
                                                    <?php
                                                    $currentYear = date('Y');
                                                    for ($y = $currentYear; $y >= $currentYear - 5; $y--) {
                                                        echo "<option value=\"$y\">$y</option>";
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="form-label">Quarter</label>
                                                <div class="quarters-selector" id="savedReportsQuarters" style="flex-wrap: nowrap;">
                                                    <button type="button" class="quarter-btn" data-quarter="Q1">Q1</button>
                                                    <button type="button" class="quarter-btn" data-quarter="Q2">Q2</button>
                                                    <button type="button" class="quarter-btn" data-quarter="Q3">Q3</button>
                                                    <button type="button" class="quarter-btn" data-quarter="Q4">Q4</button>
                                                    <button type="button" class="quarter-btn" data-quarter="">All</button>
                                                </div>
                                                <input type="hidden" id="savedReportsQuarter">
                                            </div>
                                            <div>
                                                <button type="button" class="btn-primary-tqr" id="loadSavedReportsBtn">
                                                    <i class="feather icon-search"></i> Load
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Supplier Reports -->
                                        <div class="sec-header">
                                            <div class="sec-header-icon"><i class="feather icon-building"></i></div>
                                            <h6>Supplier Reports</h6>
                                        </div>
                                        <div id="savedSupplierReportsContainer">
                                            <div class="tqr-empty">
                                                <i class="feather icon-inbox"></i>
                                                <p>Select a year and click Load to view saved supplier reports</p>
                                            </div>
                                        </div>

                                        <div class="tqr-divider"></div>

                                        <!-- General Reports -->
                                        <div class="sec-header">
                                            <div class="sec-header-icon"><i class="feather icon-bar-chart-2"></i></div>
                                            <h6>General Reports</h6>
                                        </div>
                                        <div id="savedGeneralReportsContainer">
                                            <div class="tqr-empty">
                                                <i class="feather icon-inbox"></i>
                                                <p>Select a year and click Load to view saved general reports</p>
                                            </div>
                                        </div>

                                    </div>
                                </div><!-- /tab-content -->
                            </div><!-- /tqr-card -->

                        </div><!-- /tqr-page -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/admin_footer.php'; ?>

<!-- Required Scripts -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<script>
    console.log('Quarterly Tax Report page loaded');

    const PHP_TENANT_ID = <?php echo json_encode($tenant_id); ?>;
    const PHP_BRANCH_ID = <?php echo json_encode($branch_id); ?>;

    let supplierReportData = {};
    let generalReportData = {};
    let tempExpenses = [];
    let supplierReportLoadPromise = Promise.resolve();

    // ---- Quarter Buttons ----
    function setupQuarterButtons() {
        document.querySelectorAll('.quarters-selector').forEach(container => {
            container.querySelectorAll('.quarter-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const quarter = btn.getAttribute('data-quarter');
                    const input = container.nextElementSibling;
                    container.querySelectorAll('.quarter-btn').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    input.value = quarter;
                });
            });
        });
    }

    // ---- Supplier Checkboxes ----
    function setupSupplierCheckboxes() {
        document.querySelectorAll('.supplier-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', (e) => {
                const card = document.getElementById('supplierCard' + checkbox.value);
                const options = card.querySelector('.supplier-options');
                options.style.display = checkbox.checked ? 'block' : 'none';
                card.classList.toggle('is-checked', checkbox.checked);
                if (checkbox.checked) setupDataTypeRadios(checkbox.value);
            });
        });

        // Select all
        const selectAll = document.getElementById('selectAllSuppliers');
        selectAll?.addEventListener('change', () => {
            document.querySelectorAll('.supplier-checkbox').forEach(cb => {
                cb.checked = selectAll.checked;
                cb.dispatchEvent(new Event('change'));
            });
        });
    }

    function setupDataTypeRadios(supplierId) {
        const radios = document.querySelectorAll(`input[name="dataType${supplierId}"]`);
        radios.forEach(radio => {
            radio.addEventListener('change', (e) => {
                const card = document.getElementById(`supplier${supplierId}`).closest('.supplier-card');
                const randomOptions = card.querySelector('.random-options');
                randomOptions.style.display = e.target.value === 'random' ? 'block' : 'none';
            });
        });
    }

    // ---- Category Checkboxes ----
    function setupCategoryCheckboxes() {
        document.querySelectorAll('.expense-category-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', () => {
                const selected = Array.from(document.querySelectorAll('.expense-category-checkbox:checked'))
                    .map(cb => cb.value);
                updateExpenseItems(selected);
            });
        });
    }

    function updateExpenseItems(categories) {
        const container = document.getElementById('expenseItemsContainer');

        if (categories.length === 0) {
            container.innerHTML = '';
            return;
        }

        const year = document.getElementById('generalYear')?.value || new Date().getFullYear();
        const quarter = document.getElementById('generalQuarter')?.value || null;
        const quarterStart = document.getElementById('generalQuarterStart')?.value || null;
        const quarterEnd = document.getElementById('generalQuarterEnd')?.value || null;

        container.innerHTML = `
            <div class="sec-header" style="margin-top: 8px;">
                <div class="sec-header-icon"><i class="feather icon-dollar-sign"></i></div>
                <h6>Expense Items by Category</h6>
            </div>
            <div class="tqr-loading"><i class="feather icon-loader"></i> Loading expenses...</div>
        `;

        const currencySymbols = { USD:'$', AFS:'؋', AFN:'؋', EUR:'€', GBP:'£', JPY:'¥', INR:'₹', AED:'د.إ', SAR:'ر.س', QAR:'ق.ر', KWD:'د.ك', BHD:'د.ب', OMR:'ر.ع.', PKR:'₨', TRY:'₺' };

        Promise.all(categories.map(category => {
            return fetch('handlers/quarterly_tax_handler.php?action=get_expenses', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ quarter, year, date_from: quarterStart, date_to: quarterEnd, categories: [category] })
            })
            .then(res => res.json())
            .then(data => ({
                category,
                expenses: (data.data || []).concat(tempExpenses.filter(exp => exp.category === category))
            }));
        }))
        .then(results => {
            let html = `
                <div class="sec-header" style="margin-top: 8px;">
                    <div class="sec-header-icon"><i class="feather icon-dollar-sign"></i></div>
                    <h6>Expense Items by Category</h6>
                </div>
            `;

            let totalAmount = 0;
            let totalCurrency = 'USD';

            results.forEach(result => {
                const { category, expenses } = result;
                html += `<div class="expense-cat-block">
                    <div class="expense-cat-head">
                        <i class="feather icon-folder" style="color: var(--accent); font-size: 14px;"></i>
                        <strong>${category}</strong>
                        <span class="tag tag-blue">${expenses.length} item${expenses.length !== 1 ? 's' : ''}</span>
                    </div>`;

                if (expenses.length === 0) {
                    html += `<div class="tqr-empty" style="padding: 16px 20px;">
                        <p style="font-size: 13px;">No expenses found for this category in the selected period</p>
                    </div>`;
                } else {
                    html += `<table class="tqr-table">
                        <thead><tr>
                            <th style="width: 40px;"><input type="checkbox" class="tqr-check category-select-all" data-category="${category}"></th>
                            <th>Category / Type</th>
                            <th style="text-align:right;">Amount</th>
                            <th style="width:80px; text-align:center;">Include</th>
                        </tr></thead>
                        <tbody>`;

                    let catAmount = 0;
                    let expCurrency = 'USD';
                    expenses.forEach(expense => {
                        const amount = parseFloat(expense.total_amount || 0);
                        expCurrency = expense.currency || 'USD';
                        totalCurrency = expCurrency;
                        catAmount += amount;
                        const sym = currencySymbols[expCurrency] || expCurrency;
                        html += `<tr>
                            <td></td>
                            <td><strong>${expense.category}</strong></td>
                            <td style="text-align:right;" class="mono">${sym}${amount.toFixed(2)}</td>
                            <td style="text-align:center;">
                                <input type="checkbox" class="tqr-check expense-item-checkbox" data-category="${category}" data-amount="${amount}" checked>
                            </td>
                        </tr>`;
                    });

                    totalAmount += catAmount;
                    const sym = currencySymbols[expCurrency] || expCurrency;
                    html += `</tbody>
                        <tfoot><tr style="background:var(--surface-2);">
                            <td colspan="2" style="font-weight:600; color:var(--ink-soft); font-size:12px; text-transform:uppercase; padding: 10px 14px;">Category Total</td>
                            <td style="text-align:right; font-weight:700; font-family:'DM Mono',monospace;">${sym}${catAmount.toFixed(2)}</td>
                            <td></td>
                        </tr></tfoot>
                    </table>`;
                }
                html += `</div>`;
            });

            const totalSym = currencySymbols[totalCurrency] || totalCurrency;
            html += `
                <div class="totals-bar" style="margin-top: 0; border-top: none; border-radius: 0 0 var(--radius) var(--radius);">
                    <div class="totals-bar-item">
                        <div class="label">Total Selected</div>
                        <div class="value accent" id="totalExpensesAmount">${totalSym}${totalAmount.toFixed(2)}</div>
                    </div>
                </div>

                <div class="add-expense-block">
                    <div class="sec-header" style="margin-bottom: 14px;">
                        <div class="sec-header-icon"><i class="feather icon-plus-circle"></i></div>
                        <h6>Add New Expense</h6>
                    </div>
                    <div class="form-row" style="grid-template-columns: 2fr 3fr 2fr auto; align-items: end; gap: 10px;">
                        <div>
                            <label class="form-label">Category</label>
                            <input type="text" id="newExpenseCategory" class="form-control" placeholder="Type or select...">
                            <div id="categoryDropdown" style="position:absolute; background:white; border:1px solid var(--border); border-radius:var(--radius-sm); display:none; max-height:200px; overflow-y:auto; z-index:1000; width:250px; box-shadow:var(--shadow);"></div>
                        </div>
                        <div>
                            <label class="form-label">Description</label>
                            <input type="text" id="newExpenseDescription" class="form-control" placeholder="Expense description">
                        </div>
                        <div>
                            <label class="form-label">Amount (USD)</label>
                            <input type="number" id="newExpenseAmount" class="form-control" placeholder="0.00" min="0" step="0.01">
                        </div>
                        <div>
                            <button type="button" class="btn-primary-tqr" id="addNewExpenseBtn" style="padding: 10px 16px;">
                                <i class="feather icon-plus"></i> Add
                            </button>
                        </div>
                    </div>
                </div>
            `;

            container.innerHTML = html;
            setupExpenseCheckboxes();
            setupNewExpenseForm();
        })
        .catch(error => {
            console.error('Error loading expenses:', error);
            container.innerHTML = `<div class="tqr-info" style="background:var(--danger-light); color:var(--danger); border-color:rgba(220,38,38,0.2);">
                <i class="feather icon-alert-circle"></i> Failed to load expenses. Please try again.
            </div>`;
        });
    }

    function setupExpenseCheckboxes() {
        document.querySelectorAll('.category-select-all').forEach(checkbox => {
            checkbox.addEventListener('change', (e) => {
                const category = e.target.getAttribute('data-category');
                document.querySelectorAll(`.expense-item-checkbox[data-category="${category}"]`).forEach(item => {
                    item.checked = e.target.checked;
                });
                updateTotalExpensesAmount();
            });
        });
        document.querySelectorAll('.expense-item-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', () => updateTotalExpensesAmount());
        });
    }

    function updateTotalExpensesAmount() {
        const total = Array.from(document.querySelectorAll('.expense-item-checkbox:checked'))
            .reduce((sum, cb) => sum + parseFloat(cb.getAttribute('data-amount')), 0);
        const el = document.getElementById('totalExpensesAmount');
        if (el) el.textContent = '$' + total.toFixed(2);
    }

    function setupNewExpenseForm() {
        const categoryInput = document.getElementById('newExpenseCategory');
        const descriptionInput = document.getElementById('newExpenseDescription');
        const amountInput = document.getElementById('newExpenseAmount');
        const addBtn = document.getElementById('addNewExpenseBtn');
        const dropdown = document.getElementById('categoryDropdown');
        let allCategories = Array.from(document.querySelectorAll('.expense-category-checkbox')).map(cb => cb.value);

        categoryInput?.addEventListener('input', (e) => {
            const value = e.target.value.toLowerCase();
            if (value.length === 0) { dropdown.style.display = 'none'; return; }
            const filtered = allCategories.filter(cat => cat.toLowerCase().includes(value));
            if (filtered.length === 0) {
                dropdown.innerHTML = `<div style="padding:10px;"><button type="button" style="width:100%; text-align:left; background:none; border:none; padding:6px; cursor:pointer; font-size:13px; color:var(--accent);" data-new-category="${value}">+ Create "${value}"</button></div>`;
            } else {
                dropdown.innerHTML = filtered.map(cat => `<div style="padding:10px 12px; cursor:pointer; font-size:13.5px; border-bottom:1px solid var(--border);">${cat}</div>`).join('');
            }
            dropdown.style.display = 'block';
        });

        dropdown?.addEventListener('click', (e) => {
            const item = e.target.closest('div');
            const btn = e.target.closest('button');
            if (btn) { categoryInput.value = btn.getAttribute('data-new-category'); dropdown.style.display = 'none'; }
            else if (item && item.textContent) { categoryInput.value = item.textContent.trim(); dropdown.style.display = 'none'; }
        });

        addBtn?.addEventListener('click', () => {
            const category = categoryInput.value.trim();
            const description = descriptionInput.value.trim();
            const amount = parseFloat(amountInput.value) || 0;
            if (!category) { Swal.fire('Error', 'Please enter a category', 'error'); return; }
            if (amount <= 0) { Swal.fire('Error', 'Amount must be greater than 0', 'error'); return; }
            createNewExpense(category, description, amount);
        });

        document.addEventListener('click', (e) => {
            if (!e.target.closest('#newExpenseCategory') && !e.target.closest('#categoryDropdown')) {
                dropdown.style.display = 'none';
            }
        });
    }

    function createNewExpense(category, description, amount) {
        tempExpenses.push({ id: 'temp_' + Date.now(), category, description: description || category, total_amount: amount, isTemporary: true });
        Swal.fire({ icon: 'success', title: 'Added', text: 'Expense added to this report', timer: 1500, showConfirmButton: false });
        document.getElementById('newExpenseCategory').value = '';
        document.getElementById('newExpenseDescription').value = '';
        document.getElementById('newExpenseAmount').value = '';
        const categories = Array.from(document.querySelectorAll('.expense-category-checkbox:checked')).map(cb => cb.value);
        updateExpenseItems(categories);
    }

    // ---- Report Generation ----
    document.getElementById('generateSupplierReport')?.addEventListener('click', generateSupplierReport);
    document.getElementById('generateGeneralReport')?.addEventListener('click', generateGeneralReport);

    function generateSupplierReport() {
        const year = document.getElementById('supplierYear').value;
        const quarter = document.getElementById('supplierQuarter').value;
        const quarterStart = document.getElementById('supplierQuarterStart').value;
        const quarterEnd = document.getElementById('supplierQuarterEnd').value;
        const selectedSuppliers = Array.from(document.querySelectorAll('.supplier-checkbox:checked')).map(cb => ({
            id: cb.value, name: cb.getAttribute('data-supplier-name')
        }));

        if (selectedSuppliers.length === 0) { Swal.fire('Error', 'Please select at least one supplier', 'error'); return; }
        if (!quarter && (!quarterStart || !quarterEnd)) { Swal.fire('Error', 'Please select a quarter or specify custom date range', 'error'); return; }
        if (quarterStart && quarterEnd && new Date(quarterStart) > new Date(quarterEnd)) { Swal.fire('Error', 'Start date must be before end date', 'error'); return; }

        const suppliers = selectedSuppliers.map(supplier => {
            const card = document.getElementById(`supplier${supplier.id}`).closest('.supplier-card');
            const dataType = document.querySelector(`input[name="dataType${supplier.id}"]:checked`).value;
            const data = { id: supplier.id, name: supplier.name, dataType, exportData: null, exportError: null };
            if (dataType === 'random') {
                data.profitMin = parseInt(card.querySelector('.profit-min').value) || 1000;
                data.profitMax = parseInt(card.querySelector('.profit-max').value) || 10000;
                data.itemCount = parseInt(card.querySelector('.items-count').value) || 5;
            }
            return data;
        });

        const exchangeRate = parseFloat(document.getElementById('exchangeRate').value) || 1;
        supplierReportData = { year: year || new Date().getFullYear(), quarter: quarter || null, quarterStart: quarterStart || null, quarterEnd: quarterEnd || null, exchangeRate, suppliers, generatedAt: new Date().toLocaleString() };
        displaySupplierReportPreview();
    }

    function displaySupplierReportPreview() {
        const preview = document.getElementById('supplierReportPreview');
        const content = document.getElementById('supplierReportContent');
        const reportType = document.querySelector('input[name="reportType"]:checked').value;

        let periodDisplay = supplierReportData.quarterStart && supplierReportData.quarterEnd
            ? `${supplierReportData.quarterStart} → ${supplierReportData.quarterEnd}`
            : (supplierReportData.quarter && supplierReportData.year ? `${supplierReportData.quarter} ${supplierReportData.year}` : 'Custom Period');

        supplierReportData.reportType = reportType;

        let html = `<div class="period-badge"><i class="feather icon-calendar" style="font-size:12px;"></i> ${periodDisplay}</div>`;

        supplierReportData.suppliers.forEach(supplier => {
            supplier.exportData = null;
            supplier.exportError = null;
            html += `
                <div class="supplier-section">
                    <div class="supplier-section-head">
                        <i class="feather icon-building" style="color:var(--accent); font-size:15px;"></i>
                        <h6>${supplier.name}</h6>
                        <span class="tag ${supplier.dataType === 'actual' ? 'tag-blue' : 'tag-gray'}">${supplier.dataType === 'actual' ? 'Actual Data' : 'Random Data'}</span>
                    </div>
                    <div id="supplier-${supplier.id}-loading" class="tqr-loading">
                        <i class="feather icon-loader"></i> Loading data...
                    </div>
                    <div id="supplier-${supplier.id}-data" style="display:none;"></div>
                </div>
            `;
        });

        content.innerHTML = html;
        preview.style.display = 'block';

        const fetchPromises = supplierReportData.suppliers.map(supplier => {
            const payload = {
                action: 'generate_supplier_report',
                tenant_id: PHP_TENANT_ID,
                branch_id: PHP_BRANCH_ID,
                supplier_id: supplier.id,
                supplier_name: supplier.name,
                quarter: supplierReportData.quarter,
                year: supplierReportData.year,
                date_from: supplierReportData.quarterStart,
                date_to: supplierReportData.quarterEnd,
                data_type: supplier.dataType,
                report_type: reportType,
                exchangeRate: supplierReportData.exchangeRate
            };
            if (supplier.dataType === 'random') {
                payload.profit_min = supplier.profitMin || 1000;
                payload.profit_max = supplier.profitMax || 10000;
                payload.item_count = supplier.itemCount || 5;
            }
            return fetch('handlers/quarterly_tax_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(response => {
                if (response.success && Array.isArray(response.data)) {
                    supplier.exportData = response.data;
                    supplier.exportError = null;
                    displaySupplierTickets(supplier.id, response.data);
                } else {
                    supplier.exportData = [];
                    supplier.exportError = response.message || null;
                    document.getElementById(`supplier-${supplier.id}-loading`).innerHTML =
                        '<div class="tqr-info" style="background:var(--warning-light);color:var(--warning);border-color:rgba(217,119,6,0.2);"><i class="feather icon-alert-triangle"></i> No data found for this supplier.</div>';
                }
            })
            .catch(error => {
                supplier.exportData = null;
                supplier.exportError = error.message;
                document.getElementById(`supplier-${supplier.id}-loading`).innerHTML =
                    `<div class="tqr-info" style="background:var(--danger-light);color:var(--danger);border-color:rgba(220,38,38,0.2);"><i class="feather icon-alert-circle"></i> Error: ${error.message}</div>`;
            });
        });

        supplierReportLoadPromise = Promise.allSettled(fetchPromises);
    }

    function displaySupplierTickets(supplierId, tickets) {
        const loadingEl = document.getElementById(`supplier-${supplierId}-loading`);
        const dataEl = document.getElementById(`supplier-${supplierId}-data`);

        if (!tickets || tickets.length === 0) {
            loadingEl.innerHTML = '<div class="tqr-info" style="background:var(--warning-light);color:var(--warning);border-color:rgba(217,119,6,0.2);"><i class="feather icon-alert-triangle"></i> No tickets found for this supplier in the selected period.</div>';
            return;
        }

        loadingEl.style.display = 'none';
        const exchangeRate = supplierReportData.exchangeRate || 1;

        const tagMap = {
            ticket: ['tag-blue', 'Ticket'],
            ticket_refund: ['tag-yellow', 'Refund'],
            ticket_date_change: ['tag-cyan', 'Date Change'],
            visa: ['tag-green', 'Visa'],
            umrah: ['tag-red', 'Umrah'],
            hotel: ['tag-gray', 'Hotel']
        };

        let totalProfit = 0, totalSold = 0;
        let rows = '';

        tickets.forEach(ticket => {
            const profit = ticket.details.profit || 0;
            const soldPrice = ticket.details.sold_price || 0;
            const ttype = ticket.details.ticket_type || 'ticket';
            const [tagCls, tagLabel] = tagMap[ttype] || ['tag-gray', ttype];
            totalProfit += profit;
            totalSold += soldPrice;
            rows += `<tr>
                <td class="mono" style="font-size:12px;">${ticket.issue_date}</td>
                <td><strong>${ticket.full_name}</strong></td>
                <td><small>${ticket.sector}</small></td>
                <td><span class="tag ${tagCls}">${tagLabel}</span></td>
                <td><span class="tag tag-gray">${ticket.details.status}</span></td>
                <td class="mono">${ticket.details.pnr}</td>
                <td class="mono">$${parseFloat(ticket.details.base_price).toFixed(2)}</td>
                <td class="mono" style="font-weight:600;">$${parseFloat(soldPrice).toFixed(2)}</td>
                <td class="mono" style="color:var(--success); font-weight:700;">$${parseFloat(profit).toFixed(2)}</td>
            </tr>`;
        });

        const totalExchanged = totalProfit * exchangeRate;
        const totalTax = totalExchanged * 0.04;

        const html = `
            <div style="overflow-x:auto;">
                <table class="tqr-table">
                    <thead><tr>
                        <th>Date</th><th>Passenger</th><th>Sector</th><th>Type</th>
                        <th>Status</th><th>PNR</th><th>Base Price</th><th>Sold Price</th><th>Profit</th>
                    </tr></thead>
                    <tbody>${rows}</tbody>
                    <tfoot>
                        <tr class="row-total">
                            <td colspan="6" style="font-size:11.5px; letter-spacing:0.5px;">SUBTOTAL</td>
                            <td>—</td>
                            <td class="mono">$${totalSold.toFixed(2)}</td>
                            <td class="mono" style="color:var(--success);">$${totalProfit.toFixed(2)}</td>
                        </tr>
                        <tr class="row-exchange">
                            <td colspan="8">Exchange to AFN @ ${exchangeRate}</td>
                            <td class="mono">${totalExchanged.toFixed(2)} AFN</td>
                        </tr>
                        <tr class="row-tax">
                            <td colspan="8">Tax (4% of exchanged amount)</td>
                            <td class="mono">${totalTax.toFixed(2)} AFN</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        `;

        dataEl.innerHTML = html;
        dataEl.style.display = 'block';
    }

    function generateGeneralReport() {
        const year = document.getElementById('generalYear').value;
        const quarter = document.getElementById('generalQuarter').value;
        const quarterStart = document.getElementById('generalQuarterStart').value;
        const quarterEnd = document.getElementById('generalQuarterEnd').value;

        if (!year) { Swal.fire('Error', 'Please select a year', 'error'); return; }
        if (!quarter && (!quarterStart || !quarterEnd)) { Swal.fire('Error', 'Please select a quarter or specify custom date range', 'error'); return; }
        if (quarterStart && quarterEnd && new Date(quarterStart) > new Date(quarterEnd)) { Swal.fire('Error', 'Start date must be before end date', 'error'); return; }

        const selectedExpenses = Array.from(document.querySelectorAll('.expense-item-checkbox:checked'));
        if (selectedExpenses.length === 0) { Swal.fire('Warning', 'No expenses selected. Please select at least one expense item.', 'warning'); return; }

        const expensesByCategory = {};
        selectedExpenses.forEach(checkbox => {
            const category = checkbox.getAttribute('data-category');
            const amount = parseFloat(checkbox.getAttribute('data-amount'));
            if (!expensesByCategory[category]) expensesByCategory[category] = { category, amount: 0, items: [] };
            expensesByCategory[category].amount += amount;
            expensesByCategory[category].items.push({ amount });
        });

        const includedExpenses = Object.values(expensesByCategory);
        const quarterToUse = quarter || null;

        fetch(`handlers/quarterly_tax_handler.php?action=get_saved_reports&quarter=${quarterToUse}&year=${year}`)
        .then(res => res.json())
        .then(response => {
            const expensePromises = includedExpenses.map(expense => {
                return fetch('handlers/quarterly_tax_handler.php?action=get_expenses', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ quarter: quarterToUse, year, date_from: quarterStart, date_to: quarterEnd, categories: [expense.category] })
                })
                .then(res => res.json())
                .then(data => ({ category: expense.category, amount: expense.amount, items: data.data || [] }));
            });
            return Promise.all(expensePromises).then(enrichedExpenses => ({
                suppliers: response.data || [],
                expenses: enrichedExpenses
            }));
        })
        .then(data => {
            generalReportData = { year, quarter: quarterToUse, quarterStart: quarterStart || null, quarterEnd: quarterEnd || null, expenses: data.expenses, suppliers: data.suppliers, generatedAt: new Date().toLocaleString() };
            displayGeneralReportPreview();
        })
        .catch(error => {
            console.error('Error fetching data:', error);
            generalReportData = { year, quarter: quarterToUse, quarterStart: quarterStart || null, quarterEnd: quarterEnd || null, expenses: includedExpenses.map(e => ({category: e.category, amount: e.amount, items: []})), suppliers: [], generatedAt: new Date().toLocaleString() };
            displayGeneralReportPreview();
        });
    }

    function displayGeneralReportPreview() {
        const preview = document.getElementById('generalReportPreview');
        const content = document.getElementById('generalReportContent');

        let html = '';

        // Supplier Income Table
        if (generalReportData.suppliers && generalReportData.suppliers.length > 0) {
            let totalIncome = 0, totalTax = 0;
            let suppRows = '';
            generalReportData.suppliers.forEach(supplier => {
                const reportData = supplier.data;
                if (reportData && reportData.data) {
                    let profit = 0;
                    reportData.data.forEach(item => profit += (item.details.profit || 0));
                    const exchangeRate = reportData.exchange_rate || 1;
                    const exchanged = profit * exchangeRate;
                    const tax = exchanged * 0.04;
                    totalIncome += exchanged;
                    totalTax += tax;
                    suppRows += `<tr>
                        <td><strong>${reportData.supplier_name || 'Unknown'}</strong></td>
                        <td class="mono">$${profit.toFixed(2)}</td>
                        <td class="mono">${exchangeRate}</td>
                        <td class="mono">${exchanged.toFixed(2)} AFN</td>
                        <td class="mono" style="color:var(--danger); font-weight:600;">${tax.toFixed(2)} AFN</td>
                    </tr>`;
                }
            });
            html += `<h6 style="font-size:14px;font-weight:700;margin-bottom:12px;"><i class="feather icon-trending-up" style="color:var(--accent);margin-right:6px;"></i>Supplier Income &amp; Tax</h6>
            <div style="overflow-x:auto; margin-bottom:24px;">
                <table class="tqr-table">
                    <thead><tr><th>Supplier</th><th>Income (USD)</th><th>Rate</th><th>Income (AFN)</th><th>Tax (4%)</th></tr></thead>
                    <tbody>${suppRows}</tbody>
                    <tfoot><tr class="row-total">
                        <td colspan="3">TOTAL</td>
                        <td class="mono">${totalIncome.toFixed(2)} AFN</td>
                        <td class="mono" style="color:var(--danger);">${totalTax.toFixed(2)} AFN</td>
                    </tr></tfoot>
                </table>
            </div>`;
        } else {
            html += `<div class="tqr-info" style="margin-bottom:20px;"><i class="feather icon-info"></i> No supplier reports found for this quarter. Generate individual supplier reports first.</div>`;
        }

        // Expenses Table
        let totalExpense = 0;
        let expRows = '';
        generalReportData.expenses.forEach(expense => {
            totalExpense += expense.amount;
            expRows += `<tr><td>${expense.category}</td><td class="mono">$${expense.amount.toFixed(2)}</td></tr>`;
        });

        html += `<h6 style="font-size:14px;font-weight:700;margin-bottom:12px;"><i class="feather icon-credit-card" style="color:var(--accent);margin-right:6px;"></i>Expenses</h6>
        <div style="overflow-x:auto; margin-bottom:24px;">
            <table class="tqr-table">
                <thead><tr><th>Category</th><th>Amount</th></tr></thead>
                <tbody>${expRows}</tbody>
                <tfoot><tr class="row-total"><td>Total</td><td class="mono">$${totalExpense.toFixed(2)}</td></tr></tfoot>
            </table>
        </div>
        <div class="btn-action-row" style="border-top: none; padding-top: 0;">
            <button type="button" class="btn-success-tqr" id="saveGeneralReport">
                <i class="feather icon-save"></i> Save Report
            </button>
            <button type="button" class="btn-danger-tqr" id="discardGeneralReport">
                <i class="feather icon-x"></i> Discard
            </button>
        </div>
        <p style="font-size:12px; color:var(--ink-muted); margin-top:12px;">Generated: ${generalReportData.generatedAt}</p>`;

        content.innerHTML = html;
        preview.style.display = 'block';

        document.getElementById('saveGeneralReport')?.addEventListener('click', saveGeneralReport);
        document.getElementById('discardGeneralReport')?.addEventListener('click', () => { preview.style.display = 'none'; });
    }

    function saveGeneralReport() {
        fetch('handlers/quarterly_tax_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'save_general_report', ...generalReportData })
        })
        .then(res => res.json())
        .then(response => {
            if (response.success) {
                Swal.fire({ icon: 'success', title: 'Saved', text: 'General report saved successfully', timer: 2000, showConfirmButton: false });
                generalReportData.id = response.report_id;
            } else {
                Swal.fire('Error', response.message || 'Failed to save report', 'error');
            }
        })
        .catch(error => Swal.fire('Error', 'Failed to save report: ' + error.message, 'error'));
    }

    // ---- Export ----
    document.getElementById('exportSupplierExcel')?.addEventListener('click', () => {
        if (Object.keys(supplierReportData).length === 0) { Swal.fire('Error', 'Generate a report first', 'warning'); return; }
        serverExport('supplier', 'xlsx');
    });
    document.getElementById('exportGeneralExcel')?.addEventListener('click', () => {
        if (Object.keys(generalReportData).length === 0) { Swal.fire('Error', 'Generate a report first', 'warning'); return; }
        serverExport('general', 'xlsx');
    });
    document.getElementById('exportSupplierPDF')?.addEventListener('click', () => {
        if (Object.keys(supplierReportData).length === 0) { Swal.fire('Error', 'Generate a report first', 'warning'); return; }
        serverExport('supplier', 'pdf');
    });
    document.getElementById('exportGeneralPDF')?.addEventListener('click', () => {
        if (Object.keys(generalReportData).length === 0) { Swal.fire('Error', 'Generate a report first', 'warning'); return; }
        serverExport('general', 'pdf');
    });

    async function serverExport(reportType, format) {
        const data = reportType === 'supplier' ? supplierReportData : generalReportData;
        if (reportType === 'supplier') {
            await supplierReportLoadPromise;
            const failed = (supplierReportData.suppliers || []).filter(s => s.exportData === null);
            if (failed.length > 0) { Swal.fire('Error', 'Some supplier data is unavailable: ' + failed.map(s => s.name).join(', '), 'error'); return; }
        }
        fetch('handlers/quarterly_tax_export.php?report_type=' + reportType + '&format=' + format, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(response => { if (!response.ok) throw new Error('Export failed'); return response.blob(); })
        .then(blob => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = (data[reportType === 'supplier' ? 'supplier_name' : 'quarter'] || 'report') + '.' + format;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            a.remove();
            Swal.fire({ icon: 'success', title: 'Exported', text: `Report exported as ${format.toUpperCase()}`, timer: 2000, showConfirmButton: false });
        })
        .catch(error => Swal.fire('Error', 'Failed to export report', 'error'));
    }

    // ---- Saved Reports ----
    function setupSavedReportsQuarterButtons() {
        document.querySelectorAll('#savedReportsQuarters .quarter-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                document.querySelectorAll('#savedReportsQuarters .quarter-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                document.getElementById('savedReportsQuarter').value = btn.getAttribute('data-quarter');
            });
        });
    }

    document.getElementById('loadSavedReportsBtn')?.addEventListener('click', () => {
        const year = document.getElementById('savedReportsYear').value;
        const quarter = document.getElementById('savedReportsQuarter').value;
        if (!year) { Swal.fire('Error', 'Please select a year', 'warning'); return; }
        loadSavedReports(year, quarter);
    });

    function loadSavedReports(year, quarter) {
        const supplierContainer = document.getElementById('savedSupplierReportsContainer');
        const generalContainer = document.getElementById('savedGeneralReportsContainer');

        supplierContainer.innerHTML = '<div class="tqr-loading"><i class="feather icon-loader"></i> Loading supplier reports...</div>';
        generalContainer.innerHTML = '<div class="tqr-loading"><i class="feather icon-loader"></i> Loading general reports...</div>';

        fetch(`handlers/quarterly_tax_handler.php?action=get_all_saved_reports&year=${year}${quarter ? '&quarter=' + quarter : ''}`)
        .then(res => { if (!res.ok) throw new Error('Network error'); return res.json(); })
        .then(data => {
            if (!data.success) throw new Error(data.message || 'Failed to fetch reports');
            displaySupplierReports(data.data.filter(r => r.report_type === 'supplier'));
            displayGeneralReports(data.data.filter(r => r.report_type === 'general'));
        })
        .catch(error => {
            supplierContainer.innerHTML = `<div class="tqr-info" style="background:var(--danger-light);color:var(--danger);border-color:rgba(220,38,38,0.2);"><i class="feather icon-alert-circle"></i> ${error.message}</div>`;
            generalContainer.innerHTML = `<div class="tqr-info" style="background:var(--danger-light);color:var(--danger);border-color:rgba(220,38,38,0.2);"><i class="feather icon-alert-circle"></i> ${error.message}</div>`;
        });
    }

    function displaySupplierReports(reports) {
        const container = document.getElementById('savedSupplierReportsContainer');
        if (reports.length === 0) {
            container.innerHTML = '<div class="tqr-empty"><i class="feather icon-inbox"></i><p>No supplier reports found</p></div>';
            return;
        }
        let rows = reports.map(report => {
            const rd = report.data || {};
            return `<tr>
                <td><strong>${rd.supplier_name || 'Unknown'}</strong></td>
                <td><span class="tag tag-blue">${report.quarter}</span></td>
                <td class="mono">${report.year}</td>
                <td>${new Date(report.created_at).toLocaleDateString()}</td>
                <td>
                    <div style="display:flex; gap:6px; flex-wrap:wrap;">
                        <button class="tbl-btn tbl-btn-view" onclick="viewSupplierReport(${report.id})"><i class="feather icon-eye"></i> View</button>
                        <button class="tbl-btn tbl-btn-excel" onclick="exportSavedSupplierReport(${report.id},'xlsx')"><i class="feather icon-download"></i> Excel</button>
                        <button class="tbl-btn tbl-btn-delete" onclick="deleteSavedReport(${report.id},'supplier')"><i class="feather icon-trash-2"></i> Delete</button>
                    </div>
                </td>
            </tr>`;
        }).join('');
        container.innerHTML = `<div class="saved-table-wrap"><table class="saved-table">
            <thead><tr><th>Supplier</th><th>Quarter</th><th>Year</th><th>Created</th><th>Actions</th></tr></thead>
            <tbody>${rows}</tbody>
        </table></div>`;
    }

    function displayGeneralReports(reports) {
        const container = document.getElementById('savedGeneralReportsContainer');
        if (reports.length === 0) {
            container.innerHTML = '<div class="tqr-empty"><i class="feather icon-inbox"></i><p>No general reports found</p></div>';
            return;
        }
        let rows = reports.map(report => {
            const rd = report.data || {};
            return `<tr>
                <td><span class="tag tag-blue">${report.quarter}</span></td>
                <td class="mono">${report.year}</td>
                <td><span class="tag tag-gray">${(rd.suppliers||[]).length} supplier(s)</span></td>
                <td><span class="tag tag-yellow">${(rd.expenses||[]).length} category(ies)</span></td>
                <td>${new Date(report.created_at).toLocaleDateString()}</td>
                <td>
                    <div style="display:flex; gap:6px; flex-wrap:wrap;">
                        <button class="tbl-btn tbl-btn-view" onclick="viewGeneralReport(${report.id})"><i class="feather icon-eye"></i> View</button>
                        <button class="tbl-btn tbl-btn-excel" onclick="exportSavedGeneralReport(${report.id},'xlsx')"><i class="feather icon-download"></i> Excel</button>
                        <button class="tbl-btn tbl-btn-delete" onclick="deleteSavedReport(${report.id},'general')"><i class="feather icon-trash-2"></i> Delete</button>
                    </div>
                </td>
            </tr>`;
        }).join('');
        container.innerHTML = `<div class="saved-table-wrap"><table class="saved-table">
            <thead><tr><th>Quarter</th><th>Year</th><th>Suppliers</th><th>Expenses</th><th>Created</th><th>Actions</th></tr></thead>
            <tbody>${rows}</tbody>
        </table></div>`;
    }

    function viewSupplierReport(reportId) {
        fetch(`handlers/quarterly_tax_handler.php?action=get_report&id=${reportId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.data) {
                const rd = data.data.data || {};
                Swal.fire({ title: 'Supplier Report: ' + (rd.supplier_name || 'Unknown'), html: `<div style="text-align:left;"><p><strong>Quarter:</strong> ${rd.quarter||'N/A'}</p><p><strong>Year:</strong> ${rd.year||'N/A'}</p></div>`, confirmButtonText: 'Close', width: 500 });
            }
        })
        .catch(() => Swal.fire('Error', 'Failed to view report', 'error'));
    }

    function viewGeneralReport(reportId) {
        fetch(`handlers/quarterly_tax_handler.php?action=get_report&id=${reportId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.data) {
                const rd = data.data.data || {};
                Swal.fire({ title: `General Report — ${rd.quarter} ${rd.year}`, html: `<div style="text-align:left;"><p><strong>Suppliers:</strong> ${(rd.suppliers||[]).length}</p><p><strong>Expense Categories:</strong> ${(rd.expenses||[]).length}</p></div>`, confirmButtonText: 'Close', width: 500 });
            }
        })
        .catch(() => Swal.fire('Error', 'Failed to view report', 'error'));
    }

    function deleteSavedReport(reportId, reportType) {
        Swal.fire({ title: 'Delete Report?', text: 'This cannot be undone.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc2626', cancelButtonColor: '#6b7280', confirmButtonText: 'Delete' })
        .then(result => {
            if (result.isConfirmed) {
                fetch('handlers/quarterly_tax_handler.php?action=delete_report', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: reportId })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({ icon: 'success', title: 'Deleted', timer: 1500, showConfirmButton: false });
                        loadSavedReports(document.getElementById('savedReportsYear').value, document.getElementById('savedReportsQuarter').value);
                    } else {
                        Swal.fire('Error', data.message || 'Failed to delete', 'error');
                    }
                })
                .catch(() => Swal.fire('Error', 'Failed to delete report', 'error'));
            }
        });
    }

    function exportSavedSupplierReport(reportId, format) {
        window.location.href = `handlers/quarterly_tax_export.php?action=export_saved&id=${reportId}&format=${format}&type=supplier`;
    }

    function exportSavedGeneralReport(reportId, format) {
        window.location.href = `handlers/quarterly_tax_export.php?action=export_saved&id=${reportId}&format=${format}&type=general`;
    }

    // ---- Init ----
    document.addEventListener('DOMContentLoaded', () => {
        setupQuarterButtons();
        setupSupplierCheckboxes();
        setupCategoryCheckboxes();
        setupSavedReportsQuarterButtons();

        // Tab handling
        document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
            tab.addEventListener('click', (e) => {
                e.preventDefault();
                document.querySelectorAll('.tqr-tab').forEach(t => { t.classList.remove('active'); t.setAttribute('aria-selected', 'false'); });
                document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('show', 'active'));
                tab.classList.add('active');
                tab.setAttribute('aria-selected', 'true');
                const target = document.querySelector(tab.getAttribute('data-bs-target'));
                if (target) target.classList.add('show', 'active');
            });
        });
    });
</script>