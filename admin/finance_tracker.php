<?php
session_start();

require_once '../config.php';
require_once 'security.php';
enforce_auth();

$allowed_roles = ['finance'];
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
    header('Location: ../access_denied.php');
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
$csrf_token = $_SESSION['csrf_token'] ?? '';
include '../includes/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance Tracker</title>
    <link href="../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg-page:      #f4f6f9;
            --bg-surface:   #ffffff;
            --bg-muted:     #f0f2f5;
            --border:       rgba(0,0,0,0.08);
            --border-md:    rgba(0,0,0,0.13);
            --text-primary: #0f172a;
            --text-muted:   #64748b;
            --text-hint:    #94a3b8;
            --blue:         #2563eb;
            --blue-bg:      #eff6ff;
            --blue-text:    #1d4ed8;
            --green:        #16a34a;
            --green-bg:     #f0fdf4;
            --green-text:   #15803d;
            --red:          #dc2626;
            --red-bg:       #fef2f2;
            --red-text:     #b91c1c;
            --amber:        #d97706;
            --amber-bg:     #fffbeb;
            --amber-text:   #b45309;
            --radius-sm:    6px;
            --radius-md:    8px;
            --radius-lg:    12px;
            --shadow-sm:    0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
        }

        body {
            background: var(--bg-page);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
            color: var(--text-primary);
            font-size: 14px;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        /* ── Layout ── */
        .page { max-width: 1400px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }
        .currency-split { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; }
        .currency-section { display: flex; flex-direction: column; gap: 1.5rem; }
        .currency-section-title { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: var(--text-muted); padding: 0 0 0.5rem 0; border-bottom: 2px solid var(--border); }

        /* ── Topbar ── */
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
        }
        .topbar-title { font-size: 18px; font-weight: 600; color: var(--text-primary); }
        .topbar-sub  { font-size: 13px; color: var(--text-muted); margin-top: 1px; }
        .role-badge {
            font-size: 11px;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 20px;
            background: var(--blue-bg);
            color: var(--blue-text);
            border: 1px solid #bfdbfe;
            letter-spacing: 0.2px;
        }

        /* ── Alert ── */
        .alert-bar {
            display: none;
            padding: 10px 14px;
            border-radius: var(--radius-md);
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 1.25rem;
            border-left: 3px solid transparent;
        }
        .alert-bar.success { display: block; background: var(--green-bg); color: var(--green-text); border-left-color: var(--green); }
        .alert-bar.danger  { display: block; background: var(--red-bg);   color: var(--red-text);   border-left-color: var(--red); }

        /* ── Metric cards ── */
        .cards { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 12px; margin-bottom: 1.25rem; }
        .card {
             background: var(--bg-surface);
             border-radius: var(--radius-lg);
             border: 1px solid var(--border);
             padding: 0.75rem 0.9rem 0.65rem;
             box-shadow: var(--shadow-sm);
             position: relative;
             overflow: hidden;
        }
        .card::before {
             content: '';
             position: absolute;
             inset: 0 auto 0 0;
             width: 3px;
             border-radius: var(--radius-lg) 0 0 var(--radius-lg);
        }
        .card.blue::before   { background: var(--blue); }
        .card.amber::before  { background: var(--amber); }
        .card.green::before  { background: var(--green); }
        .card.red::before    { background: var(--red); }

        .card-label { font-size: 10px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 4px; }
        .card-value { font-size: 18px; font-weight: 700; color: var(--text-primary); line-height: 1.1; }
        .card-sub   { font-size: 11px; color: var(--text-muted); margin-top: 2px; }

        /* ── Action bar ── */
        .action-bar { display: flex; align-items: center; gap: 8px; margin-bottom: 1.25rem; flex-wrap: wrap; }
        .spacer { flex: 1; }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 500;
            padding: 7px 14px;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-md);
            background: var(--bg-surface);
            color: var(--text-primary);
            cursor: pointer;
            transition: background 0.15s, box-shadow 0.15s;
            white-space: nowrap;
            line-height: 1;
            box-shadow: var(--shadow-sm);
        }
        .btn:hover { background: var(--bg-muted); }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; }
        .btn svg { width: 13px; height: 13px; flex-shrink: 0; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

        .btn-green  { background: var(--green);  color: #fff; border-color: var(--green);  }
        .btn-green:hover  { background: #15803d; border-color: #15803d; }
        .btn-red    { background: var(--red);    color: #fff; border-color: var(--red);    }
        .btn-red:hover    { background: #b91c1c; border-color: #b91c1c; }
        .btn-blue   { background: var(--blue);   color: #fff; border-color: var(--blue);   }
        .btn-blue:hover   { background: #1d4ed8; border-color: #1d4ed8; }
        .btn-amber  { background: var(--amber);  color: #fff; border-color: var(--amber);  }
        .btn-amber:hover  { background: #b45309; border-color: #b45309; }
        .btn-ghost-danger { background: var(--red-bg); color: var(--red-text); border-color: #fecaca; }
        .btn-ghost-danger:hover { background: #fecaca; }

        /* ── Table ── */
        .section-label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); margin-bottom: 10px; }

        .table-wrap {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        .t-head, .t-row {
            display: grid;
            grid-template-columns: 120px 90px 130px 1fr 72px;
            gap: 12px;
            padding: 10px 16px;
            align-items: center;
        }
        .t-head {
            background: var(--bg-muted);
            border-bottom: 1px solid var(--border);
        }
        .t-head span { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.4px; color: var(--text-muted); }

        .t-row { border-bottom: 1px solid var(--border); transition: background 0.1s; }
        .t-row:last-child { border-bottom: none; }
        .t-row:hover { background: var(--bg-muted); }

        .t-date { font-size: 13px; color: var(--text-muted); }
        .t-desc { font-size: 13px; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        .pill {
            display: inline-flex;
            align-items: center;
            font-size: 11px;
            font-weight: 600;
            padding: 3px 9px;
            border-radius: 20px;
            text-transform: capitalize;
        }
        .pill.income    { background: var(--green-bg);  color: var(--green-text); }
        .pill.expense   { background: var(--red-bg);    color: var(--red-text); }
        .pill.exchange  { background: #f3e8ff;          color: #7e22ce; }

        .t-amount { font-size: 14px; font-weight: 600; }
        .t-amount.income    { color: var(--green); }
        .t-amount.expense   { color: var(--red); }
        .t-amount.exchange  { color: #7e22ce; }

        .t-actions { display: flex; gap: 4px; }
        .icon-btn {
            display: flex; align-items: center; justify-content: center;
            width: 28px; height: 28px;
            border-radius: var(--radius-sm);
            background: none; border: none; cursor: pointer;
            color: var(--text-muted);
            transition: background 0.1s, color 0.1s;
        }
        .icon-btn:hover { background: var(--bg-muted); color: var(--text-primary); }
        .icon-btn.del:hover { background: var(--red-bg); color: var(--red); }
        .icon-btn svg { width: 13px; height: 13px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

        .empty-state { padding: 2.5rem 1rem; text-align: center; font-size: 13px; color: var(--text-hint); }

        /* ── Modals ── */
        /*
         * KEY FIX: Modals use position:fixed but pcoded-wrapper applies CSS transform
         * for its sidebar animation, which creates a new stacking context and breaks
         * fixed positioning. By moving modals outside .pcoded-main-container and
         * appending them to <body> directly, fixed positioning works correctly.
         * The overlay is appended to body via JS to guarantee escape from any
         * transformed ancestor.
         */
        .overlay {
             display: none !important;
             position: fixed;
             top: 0;
             left: 0;
             width: 100vw;
             height: 100vh;
             background: rgba(15,23,42,0.45);
             backdrop-filter: blur(2px);
             -webkit-backdrop-filter: blur(2px);
             z-index: 99999;
             align-items: center;
             justify-content: center;
             padding: 1rem;
             overflow-y: auto;
        }
        .overlay.open {
             display: flex !important;
        }

        .modal {
             background: var(--bg-surface) !important;
             border: 1px solid var(--border) !important;
             border-radius: var(--radius-lg);
             padding: 1.5rem;
             width: 440px;
             max-width: 100%;
             box-shadow: 0 20px 60px rgba(0,0,0,0.18);
             animation: modalIn 0.2s ease-out;
             position: relative;
             margin: auto;
             flex-shrink: 0;
             z-index: 100000;
             min-height: auto;
             display: block !important;
             visibility: visible !important;
             opacity: 1 !important;
        }
        @keyframes modalIn {
            from { opacity: 0; transform: translateY(10px) scale(0.98); }
            to   { opacity: 1; transform: translateY(0)   scale(1); }
        }

        .modal-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.25rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border);
        }
        .modal-head h2 { font-size: 15px; font-weight: 600; }
        .modal-close {
            width: 28px; height: 28px; display: flex; align-items: center; justify-content: center;
            border-radius: var(--radius-sm); background: none; border: none; cursor: pointer;
            font-size: 18px; line-height: 1; color: var(--text-muted);
        }
        .modal-close:hover { background: var(--bg-muted); color: var(--text-primary); }

        .field { margin-bottom: 1rem; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--text-muted); margin-bottom: 5px; text-transform: uppercase; letter-spacing: 0.3px; }
        .field input,
        .field select,
        .field textarea {
            width: 100%;
            padding: 8px 10px;
            font-size: 13px;
            border: 1px solid var(--border-md);
            border-radius: var(--radius-md);
            background: var(--bg-surface);
            color: var(--text-primary);
            font-family: inherit;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .field input:focus,
        .field select:focus,
        .field textarea:focus {
            outline: none;
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
        }
        .field textarea { resize: vertical; }
        .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }

        .modal-footer { display: flex; gap: 8px; margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid var(--border); }
        .modal-footer .btn { flex: 1; justify-content: center; }

        .warn-box {
            background: var(--amber-bg);
            border-left: 3px solid var(--amber);
            border-radius: 0 var(--radius-md) var(--radius-md) 0;
            padding: 10px 12px;
            font-size: 13px;
            color: var(--amber-text);
            margin-bottom: 1rem;
            line-height: 1.5;
        }

        .exchange-preview {
            display: none;
            background: var(--bg-muted);
            border-radius: var(--radius-md);
            padding: 10px 12px;
            font-size: 13px;
            color: var(--text-muted);
            margin-top: -4px;
            margin-bottom: 1rem;
        }
        .exchange-preview strong { color: var(--text-primary); }

        /* ── Responsive ── */
        @media (max-width: 1400px) {
            .cards { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 1100px) {
            .cards { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 900px) {
            .cards { grid-template-columns: repeat(2, 1fr); }
            .currency-split { grid-template-columns: 1fr; }
        }
        @media (max-width: 680px) {
            .t-head, .t-row { grid-template-columns: 100px 80px 110px 56px; }
            .t-head .col-desc, .t-row .col-desc { display: none; }
            .action-bar { gap: 6px; }
            .btn { padding: 7px 10px; font-size: 12px; }
        }
        @media (max-width: 460px) {
            .cards { grid-template-columns: 1fr 1fr; }
            .page { padding: 1rem 1rem 3rem; }
        }
    </style>
</head>
<body>
<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="page">

            <!-- Topbar -->
            <div class="topbar">
                <div>
                    <div class="topbar-title">Finance tracker</div>
                    <div class="topbar-sub">Income &amp; expense management</div>
                </div>
                <span class="role-badge">Finance Admin</span>
            </div>

            <!-- Alert -->
            <div id="alertBar" class="alert-bar"></div>

            <div class="currency-split">
                <!-- LEFT: AFN -->
                <div class="currency-section">
                    <div class="currency-section-title">🇦🇫 Afghan Afghani (AFN)</div>

                    <div class="cards">
                         <div class="card green">
                             <div class="card-label">Total Income</div>
                             <div class="card-value" id="totalIncomeAfs">&#x60B;0.00</div>
                             <div class="card-sub">All time</div>
                         </div>
                         <div class="card red">
                             <div class="card-label">Total Spent</div>
                             <div class="card-value" id="totalExpenseAfs">&#x60B;0.00</div>
                             <div class="card-sub">All time</div>
                         </div>
                         <div class="card amber">
                             <div class="card-label">Remaining Balance</div>
                             <div class="card-value" id="afsBalance">&#x60B;0.00</div>
                             <div class="card-sub">After all expenses</div>
                         </div>
                         <div class="card blue">
                             <div class="card-label">Today Income</div>
                             <div class="card-value" id="todayIncomeAfs">&#x60B;0.00</div>
                             <div class="card-sub">Today only</div>
                         </div>
                         <div class="card red">
                             <div class="card-label">Today Spent</div>
                             <div class="card-value" id="todayExpenseAfs">&#x60B;0.00</div>
                             <div class="card-sub">Today only</div>
                         </div>
                     </div>

                    <div class="action-bar">
                        <button class="btn btn-green" onclick="showAddModal('income', 'afs')">
                            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Add Income
                        </button>
                        <button class="btn btn-red" onclick="showAddModal('expense', 'afs')">
                            <svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Add Expense
                        </button>
                    </div>

                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                        <div class="section-label" style="margin: 0;">AFN Transactions</div>
                        <div style="display: flex; gap: 6px;">
                            <button class="btn filter-btn filter-all afs-filter" onclick="filterTransactions('afs', 'all')" style="font-size: 12px; padding: 5px 10px; background: var(--blue); color: white; border-color: var(--blue);">All</button>
                            <button class="btn filter-btn afs-filter" onclick="filterTransactions('afs', 'income')" style="font-size: 12px; padding: 5px 10px;">Income</button>
                            <button class="btn filter-btn afs-filter" onclick="filterTransactions('afs', 'expense')" style="font-size: 12px; padding: 5px 10px;">Expense</button>
                            <button class="btn filter-btn afs-filter" onclick="filterTransactions('afs', 'exchange')" style="font-size: 12px; padding: 5px 10px;">Exchange</button>
                        </div>
                    </div>
                    <div class="table-wrap">
                         <div class="t-head">
                             <span>Date</span>
                             <span>Type</span>
                             <span>Amount</span>
                             <span class="col-desc">Description</span>
                             <span></span>
                         </div>
                         <div id="txRowsAfs"></div>
                     </div>
                </div>

                <!-- RIGHT: USD -->
                <div class="currency-section">
                    <div class="currency-section-title">🇺🇸 United States Dollar (USD)</div>

                    <div class="cards">
                         <div class="card green">
                             <div class="card-label">Total Income</div>
                             <div class="card-value" id="totalIncomeUsd">$0.00</div>
                             <div class="card-sub">All time</div>
                         </div>
                         <div class="card red">
                             <div class="card-label">Total Spent</div>
                             <div class="card-value" id="totalExpenseUsd">$0.00</div>
                             <div class="card-sub">All time</div>
                         </div>
                         <div class="card blue">
                             <div class="card-label">Remaining Balance</div>
                             <div class="card-value" id="usdBalance">$0.00</div>
                             <div class="card-sub">After all expenses</div>
                         </div>
                         <div class="card amber">
                             <div class="card-label">Today Income</div>
                             <div class="card-value" id="todayIncomeUsd">$0.00</div>
                             <div class="card-sub">Today only</div>
                         </div>
                         <div class="card red">
                             <div class="card-label">Today Spent</div>
                             <div class="card-value" id="todayExpenseUsd">$0.00</div>
                             <div class="card-sub">Today only</div>
                         </div>
                     </div>

                    <div class="action-bar">
                        <button class="btn btn-green" onclick="showAddModal('income', 'usd')">
                            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Add Income
                        </button>
                        <button class="btn btn-red" onclick="showAddModal('expense', 'usd')">
                            <svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Add Expense
                        </button>
                        <button class="btn btn-blue" onclick="showExchangeModal()">
                            <svg viewBox="0 0 24 24"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
                            Exchange
                        </button>
                        <span class="spacer"></span>
                        <button class="btn btn-ghost-danger" onclick="showClearModal()">
                            <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                            Clear all
                        </button>
                    </div>

                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                        <div class="section-label" style="margin: 0;">USD Transactions</div>
                        <div style="display: flex; gap: 6px;">
                            <button class="btn filter-btn filter-all usd-filter" onclick="filterTransactions('usd', 'all')" style="font-size: 12px; padding: 5px 10px; background: var(--blue); color: white; border-color: var(--blue);">All</button>
                            <button class="btn filter-btn usd-filter" onclick="filterTransactions('usd', 'income')" style="font-size: 12px; padding: 5px 10px;">Income</button>
                            <button class="btn filter-btn usd-filter" onclick="filterTransactions('usd', 'expense')" style="font-size: 12px; padding: 5px 10px;">Expense</button>
                            <button class="btn filter-btn usd-filter" onclick="filterTransactions('usd', 'exchange')" style="font-size: 12px; padding: 5px 10px;">Exchange</button>
                        </div>
                    </div>
                    <div class="table-wrap">
                         <div class="t-head">
                             <span>Date</span>
                             <span>Type</span>
                             <span>Amount</span>
                             <span class="col-desc">Description</span>
                             <span></span>
                         </div>
                         <div id="txRowsUsd"></div>
                     </div>
                </div>
            </div>

        </div><!-- /page -->
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     MODALS — intentionally placed directly before </body>,
     OUTSIDE .pcoded-main-container.

     WHY: pcoded.min.js applies CSS `transform` to .pcoded-wrapper
     for its sidebar slide animation. Any CSS transform/filter/
     will-change on an ancestor creates a new containing block for
     `position: fixed`, causing fixed children to be clipped or
     positioned relative to that element instead of the viewport.
     Moving modals here guarantees they are direct children of
     <body> with no transformed ancestor.
═══════════════════════════════════════════════════════════════════ -->

<!-- ══ Add / Edit Transaction Modal ══ -->
<div class="overlay" id="txModal">
    <div class="modal">
        <div class="modal-head">
            <h2 id="txModalTitle">Add income</h2>
            <button class="modal-close" onclick="closeModal('txModal')">&times;</button>
        </div>
        <input type="hidden" id="txId">
        <input type="hidden" id="txType" value="income">
        <div class="field">
            <label>Date</label>
            <input type="date" id="txDate">
        </div>
        <div class="field-row">
            <div class="field">
                <label>Amount</label>
                <input type="number" id="txAmount" step="0.01" min="0.01" placeholder="0.00">
            </div>
            <div class="field">
                <label>Currency</label>
                <select id="txCurrency">
                    <option value="usd">USD</option>
                    <option value="afs">AFN</option>
                </select>
            </div>
        </div>
        <div class="field">
            <label>Description</label>
            <textarea id="txDesc" rows="2" placeholder="Optional note..."></textarea>
        </div>
        <div class="modal-footer">
            <button class="btn btn-blue" onclick="saveTransaction(event)">Save transaction</button>
            <button class="btn" onclick="closeModal('txModal')">Cancel</button>
        </div>
    </div>
</div>

<!-- ══ Exchange Modal ══ -->
<div class="overlay" id="exModal">
    <div class="modal">
        <div class="modal-head">
            <h2>Exchange currency</h2>
            <button class="modal-close" onclick="closeModal('exModal')">&times;</button>
        </div>
        <div class="field-row">
            <div class="field">
                <label>From</label>
                <select id="exFrom" onchange="swapTo()">
                    <option value="usd">USD</option>
                    <option value="afs">AFN</option>
                </select>
            </div>
            <div class="field">
                <label>To</label>
                <select id="exTo">
                    <option value="afs">AFN</option>
                    <option value="usd">USD</option>
                </select>
            </div>
        </div>
        <div class="field-row">
            <div class="field">
                <label>Amount</label>
                <input type="number" id="exAmount" step="0.01" min="0.01" placeholder="0.00" oninput="previewExchange()">
            </div>
            <div class="field">
                <label>Exchange rate</label>
                <input type="number" id="exRate" step="0.01" min="0.01" placeholder="75" oninput="previewExchange()">
            </div>
        </div>
        <div class="field">
            <label>Description</label>
            <input type="text" id="exDesc" placeholder="e.g. Bank exchange">
        </div>
        <div class="exchange-preview" id="exPreview">
            Converting <strong id="exFromVal">—</strong> &rarr; <strong id="exToVal">—</strong>
        </div>
        <div class="modal-footer">
            <button class="btn btn-blue" onclick="performExchange(event)">Exchange now</button>
            <button class="btn" onclick="closeModal('exModal')">Cancel</button>
        </div>
    </div>
</div>

<!-- ══ Clear All Modal ══ -->
<div class="overlay" id="clearModal">
    <div class="modal">
        <div class="modal-head">
            <h2>Clear all data</h2>
            <button class="modal-close" onclick="closeModal('clearModal')">&times;</button>
        </div>
        <div class="warn-box">
            <strong>Warning:</strong> This will permanently delete all finance tracker records for this branch. This action cannot be undone.
        </div>
        <div class="field">
            <label>Type "CLEAR ALL" to confirm</label>
            <input type="text" id="clearConfirm" placeholder="CLEAR ALL" oninput="toggleClearBtn()">
        </div>
        <div class="modal-footer">
            <button class="btn btn-red" id="clearBtn" disabled onclick="confirmClear(event)" style="opacity:0.45">Clear all data</button>
            <button class="btn" onclick="closeModal('clearModal')">Cancel</button>
        </div>
    </div>
</div>

<script>
    const BASE_URL   = '../api/finance/finance_tracker_actions.php';
    const CSRF_TOKEN = '<?php echo htmlspecialchars($csrf_token, ENT_QUOTES); ?>';
    const AFN        = '\u060B'; // ؋

    function fmt(val, currency) {
        const n = parseFloat(val) || 0;
        const sym = currency && currency.toUpperCase() === 'USD' ? '$' : AFN;
        return sym + n.toFixed(2);
    }

    /* ── Alerts ── */
    let alertTimer = null;
    function showAlert(msg, type) {
        const el = document.getElementById('alertBar');
        el.textContent = msg;
        el.className = 'alert-bar ' + type;
        clearTimeout(alertTimer);
        alertTimer = setTimeout(() => { el.className = 'alert-bar'; }, 5000);
    }

    /* ── Modals ── */
    function openModal(id) {
        const el = document.getElementById(id);
        if (el) {
            el.classList.add('open');
            console.log('Opened modal:', id, 'Classes:', el.className);
        } else {
            console.error('Modal not found:', id);
        }
    }
    function closeModal(id) {
        const el = document.getElementById(id);
        if (el) {
            el.classList.remove('open');
            console.log('Closed modal:', id);
        }
    }

    // Close on backdrop click
    document.querySelectorAll('.overlay').forEach(el => {
        el.addEventListener('click', function(e) {
            if (e.target === this) closeModal(this.id);
        });
    });

    // Close on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.overlay.open').forEach(el => closeModal(el.id));
        }
    });

    /* ── Add / Edit modal ── */
    function showAddModal(type, currency) {
        document.getElementById('txId').value       = '';
        document.getElementById('txType').value     = type;
        document.getElementById('txDate').value     = new Date().toISOString().split('T')[0];
        document.getElementById('txAmount').value   = '';
        document.getElementById('txCurrency').value = currency || 'usd';
        document.getElementById('txDesc').value     = '';
        document.getElementById('txModalTitle').textContent = type === 'income' ? 'Add income' : 'Add expense';
        
        // Reset button states
        const saveBtn = document.querySelector('#txModal .btn-blue');
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.textContent = type === 'income' ? 'Add income' : 'Add expense';
            saveBtn.style.opacity = '1';
        }
        
        openModal('txModal');
    }

    /* ── Exchange modal ── */
    function showExchangeModal() {
        ['exAmount', 'exRate', 'exDesc'].forEach(id => { document.getElementById(id).value = ''; });
        document.getElementById('exFrom').value = 'usd';
        document.getElementById('exTo').value   = 'afs';
        document.getElementById('exPreview').style.display = 'none';
        
        // Reset button states
        const exBtn = document.querySelector('#exModal .btn-green');
        if (exBtn) {
            exBtn.disabled = false;
            exBtn.textContent = 'Exchange';
            exBtn.style.opacity = '1';
        }
        
        openModal('exModal');
    }

    function swapTo() {
        const from = document.getElementById('exFrom').value;
        document.getElementById('exTo').value = from === 'usd' ? 'afs' : 'usd';
        previewExchange();
    }

    function previewExchange() {
        const amt  = parseFloat(document.getElementById('exAmount').value) || 0;
        const rate = parseFloat(document.getElementById('exRate').value) || 0;
        const from = document.getElementById('exFrom').value.toUpperCase();
        const to   = document.getElementById('exTo').value.toUpperCase();
        const preview = document.getElementById('exPreview');
        if (amt > 0 && rate > 0) {
            const fromSym = from === 'USD' ? '$' : AFN;
            const toSym   = to   === 'USD' ? '$' : AFN;
            document.getElementById('exFromVal').textContent = fromSym + amt.toFixed(2) + ' ' + from;
            document.getElementById('exToVal').textContent   = toSym + (amt * rate).toFixed(2) + ' ' + to;
            preview.style.display = 'block';
        } else {
            preview.style.display = 'none';
        }
    }

    /* ── Clear all modal ── */
    function showClearModal() {
        document.getElementById('clearConfirm').value = '';
        const btn = document.getElementById('clearBtn');
        btn.disabled = true;
        btn.textContent = 'Clear all data';
        btn.style.opacity = '0.45';
        openModal('clearModal');
    }

    function toggleClearBtn() {
        const ok  = document.getElementById('clearConfirm').value === 'CLEAR ALL';
        const btn = document.getElementById('clearBtn');
        btn.disabled = !ok;
        btn.style.opacity = ok ? '1' : '0.45';
    }

    /* ── API helpers ── */
    async function apiFetch(url) {
        const r = await fetch(url);
        return r.json();
    }
    async function apiPost(formData) {
        const r = await fetch(BASE_URL, { method: 'POST', body: formData });
        return r.json();
    }
    function fd(...pairs) {
        const f = new FormData();
        f.append('csrf_token', CSRF_TOKEN);
        for (let i = 0; i < pairs.length; i += 2) f.append(pairs[i], pairs[i + 1]);
        return f;
    }

    /* ── Load balances ── */
    async function loadBalances() {
        try {
            const d = await apiFetch(BASE_URL + '?action=get_balances');
            if (!d.success) return;
            document.getElementById('usdBalance').textContent      = '$'  + parseFloat(d.usd_balance).toFixed(2);
            document.getElementById('afsBalance').textContent      = AFN  + parseFloat(d.afs_balance).toFixed(2);
            document.getElementById('totalIncomeUsd').textContent  = '$'  + parseFloat(d.total_income_usd).toFixed(2);
            document.getElementById('totalIncomeAfs').textContent  = AFN  + parseFloat(d.total_income_afs).toFixed(2);
            document.getElementById('totalExpenseUsd').textContent = '$'  + parseFloat(d.total_expense_usd).toFixed(2);
            document.getElementById('totalExpenseAfs').textContent = AFN  + parseFloat(d.total_expense_afs).toFixed(2);
            document.getElementById('todayIncomeUsd').textContent  = '$'  + parseFloat(d.today_income_usd).toFixed(2);
            document.getElementById('todayIncomeAfs').textContent  = AFN  + parseFloat(d.today_income_afs).toFixed(2);
            document.getElementById('todayExpenseUsd').textContent = '$'  + parseFloat(d.today_expense_usd).toFixed(2);
            document.getElementById('todayExpenseAfs').textContent = AFN  + parseFloat(d.today_expense_afs).toFixed(2);
        } catch(e) { console.error('loadBalances', e); }
    }

    /* ── Transaction storage and filtering ── */
    let allTransactions = { usd: [], afs: [] };
    let currentFilter = { usd: 'all', afs: 'all' };

    /* ── Load transactions ── */
    async function loadTransactions() {
        const usdContainer = document.getElementById('txRowsUsd');
        const afsContainer = document.getElementById('txRowsAfs');
        try {
            const d = await apiFetch(BASE_URL + '?action=get_recent_transactions&limit=20');
            if (!d.success || !d.transactions.length) {
                usdContainer.innerHTML = '<div class="empty-state">No USD transactions yet.</div>';
                afsContainer.innerHTML = '<div class="empty-state">No AFN transactions yet.</div>';
                return;
            }

            // Store transactions by currency
            allTransactions = { usd: [], afs: [] };
            d.transactions.forEach(tx => {
                if (tx.currency.toLowerCase() === 'usd') {
                    allTransactions.usd.push(tx);
                } else {
                    allTransactions.afs.push(tx);
                }
            });

            // Render with current filters
            renderTransactions('usd', usdContainer);
            renderTransactions('afs', afsContainer);
        } catch(e) {
            usdContainer.innerHTML = '<div class="empty-state">Error loading transactions.</div>';
            afsContainer.innerHTML = '<div class="empty-state">Error loading transactions.</div>';
        }
    }

    function renderTransactions(currency, container) {
        let rows = [];
        const transactions = allTransactions[currency] || [];
        const filter = currentFilter[currency] || 'all';

        transactions.forEach(tx => {
            const isExchange = tx.description && tx.description.toLowerCase().startsWith('exchange:');
            const txType = isExchange ? 'exchange' : tx.type;
            
            // Apply filter
            if (filter !== 'all' && txType !== filter) return;
            
            const row = createTransactionRow(tx);
            rows.push(row);
        });

        const currencyName = currency.toUpperCase();
        container.innerHTML = rows.length ? rows.join('') : `<div class="empty-state">No ${filter} transactions yet.</div>`;
    }

    function filterTransactions(currency, type) {
        currentFilter[currency] = type;
        const container = currency === 'usd' ? document.getElementById('txRowsUsd') : document.getElementById('txRowsAfs');
        renderTransactions(currency, container);

        // Update button styles
        const buttons = document.querySelectorAll(`.${currency}-filter`);
        buttons.forEach(btn => {
            btn.classList.remove('filter-all');
            btn.style.background = '';
            btn.style.color = '';
            btn.style.borderColor = '';
        });

        const activeBtn = document.querySelector(`.${currency}-filter[onclick*="'${type}'"]`);
        if (activeBtn) {
            activeBtn.classList.add('filter-all');
            activeBtn.style.background = 'var(--blue)';
            activeBtn.style.color = 'white';
            activeBtn.style.borderColor = 'var(--blue)';
        }
    }

    function createTransactionRow(tx) {
        const date        = new Date(tx.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        const isExchange  = tx.description && tx.description.toLowerCase().startsWith('exchange:');
        const displayType = isExchange ? 'exchange' : tx.type;
        const displayLabel = isExchange ? 'Exchange' : tx.type;
        const sym  = tx.currency.toUpperCase() === 'USD' ? '$' : AFN;
        const curr = tx.currency.toUpperCase() === 'USD' ? 'USD' : 'AFN';
        return `<div class="t-row">
            <div class="t-date">${date}</div>
            <div><span class="pill ${displayType}">${displayLabel}</span></div>
            <div class="t-amount ${displayType}">${sym}${parseFloat(tx.amount).toFixed(2)} <span style="font-size:11px;font-weight:500;opacity:.6">${curr}</span></div>
            <div class="t-desc col-desc">${tx.description || '—'}</div>
            <div class="t-actions">
                <button class="icon-btn" onclick="editTx(${tx.id})" title="Edit">
                    <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
                <button class="icon-btn del" onclick="deleteTx(${tx.id}, event)" title="Delete">
                    <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                </button>
            </div>
        </div>`;
    }

    function loadAll() { loadBalances(); loadTransactions(); }

    /* ── Save transaction ── */
    async function saveTransaction(e) {
        const btn = e.target.closest('.btn');
        if (btn.disabled) return;

        const originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Processing...';

        const id = document.getElementById('txId').value;
        const data = fd(
            'action',                 id ? 'update_transaction' : 'add_transaction',
            'transactionId',          id,
            'type',                   document.getElementById('txType').value,
            'transactionDate',        document.getElementById('txDate').value,
            'transactionAmount',      document.getElementById('txAmount').value,
            'transactionCurrency',    document.getElementById('txCurrency').value,
            'transactionDescription', document.getElementById('txDesc').value
        );
        if (id) data.append('id', id);

        try {
            const d = await apiPost(data);
            if (d.success) {
                showAlert(d.message, 'success');
                closeModal('txModal');
                loadAll();
            } else {
                showAlert(d.message, 'danger');
                btn.disabled = false;
                btn.textContent = originalText;
            }
        } catch(err) {
            showAlert('Error saving transaction.', 'danger');
            btn.disabled = false;
            btn.textContent = originalText;
        }
    }

    /* ── Edit transaction ── */
    async function editTx(id) {
        try {
            const d = await apiFetch(`${BASE_URL}?action=get_transaction&id=${id}`);
            if (!d.success) { showAlert('Error loading transaction.', 'danger'); return; }
            const tx = d.transaction;
            document.getElementById('txId').value       = tx.id;
            document.getElementById('txType').value     = tx.type;
            document.getElementById('txDate').value     = tx.date;
            document.getElementById('txAmount').value   = tx.amount;
            document.getElementById('txCurrency').value = tx.currency;
            document.getElementById('txDesc').value     = tx.description || '';
            document.getElementById('txModalTitle').textContent = tx.type === 'income' ? 'Edit income' : 'Edit expense';
            openModal('txModal');
        } catch(err) { showAlert('Error loading transaction.', 'danger'); }
    }

    /* ── Delete transaction ── */
    async function deleteTx(id, e) {
        if (!confirm('Delete this transaction?')) return;
        const btn = e.target.closest('.icon-btn');
        if (!btn || btn.disabled) return;
        btn.disabled = true;
        const originalHTML = btn.innerHTML;
        btn.style.opacity = '0.6';

        try {
            const d = await apiPost(fd('action', 'delete_transaction', 'id', id));
            if (d.success) {
                showAlert('Transaction deleted.', 'success');
                loadAll();
            } else {
                showAlert(d.message, 'danger');
                btn.disabled = false;
                btn.innerHTML = originalHTML;
                btn.style.opacity = '1';
            }
        } catch(err) {
            showAlert('Error deleting transaction.', 'danger');
            btn.disabled = false;
            btn.innerHTML = originalHTML;
            btn.style.opacity = '1';
        }
    }

    /* ── Exchange ── */
    async function performExchange(e) {
        const btn = e.target.closest('.btn');
        if (btn.disabled) return;

        const originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Processing...';

        try {
            const d = await apiPost(fd(
                'action',               'exchange_currency',
                'exchangeFromCurrency', document.getElementById('exFrom').value,
                'exchangeToCurrency',   document.getElementById('exTo').value,
                'exchangeFromAmount',   document.getElementById('exAmount').value,
                'exchangeRate',         document.getElementById('exRate').value,
                'exchangeDescription',  document.getElementById('exDesc').value
            ));
            if (d.success) {
                showAlert('Exchange recorded.', 'success');
                closeModal('exModal');
                loadAll();
            } else {
                showAlert(d.message, 'danger');
                btn.disabled = false;
                btn.textContent = originalText;
            }
        } catch(err) {
            showAlert('Error performing exchange.', 'danger');
            btn.disabled = false;
            btn.textContent = originalText;
        }
    }

    /* ── Clear all ── */
    async function confirmClear(e) {
        if (!confirm('This will delete ALL data permanently. Are you absolutely sure?')) return;
        const btn = e.target.closest('.btn');
        if (btn.disabled) return;

        const originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Processing...';

        try {
            const d = await apiPost(fd('action', 'clear_all', 'confirmation', 'CLEAR_ALL_FINANCE_DATA'));
            if (d.success) {
                showAlert(`Cleared. ${d.deleted_count} records deleted.`, 'success');
                closeModal('clearModal');
                loadAll();
            } else {
                showAlert(d.message, 'danger');
                btn.disabled = false;
                btn.textContent = originalText;
            }
        } catch(err) {
            showAlert('Error clearing data.', 'danger');
            btn.disabled = false;
            btn.textContent = originalText;
        }
    }

    /* ── Init ── */
    loadAll();
    setInterval(loadBalances, 30000);
</script>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<?php include '../includes/admin_footer.php'; ?>
</body>
</html>