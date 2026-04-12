<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();

// Check if user is logged in with proper role
$allowed_roles = ['admin', 'finance'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    header('Location: ../login.php');
    exit();
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

include '../includes/db.php';

// Initialize search variables
$searchTerm = '';
$searchResults = [];
$resultMessage = '';
$searchPerformed = false;

// Process search form submission
if (isset($_POST['search'])) {
    $searchTerm = trim($_POST['searchTerm'] ?? '');
    $searchPerformed = true;

    if (empty($searchTerm)) {
        $resultMessage = "Please enter a search term";
    } else {
        $searchResults = [];
        $likeParam = "%$searchTerm%";

        // Ticket bookings
        $stmt = $pdo->prepare("SELECT 
            'Ticket' AS record_type, tb.id,
            tb.passenger_name AS name, tb.pnr AS reference, tb.phone, tb.gender,
            c.name AS client_name, c.id AS client_id,
            s.name AS supplier_name, s.id AS supplier_id,
            tb.origin, tb.destination, tb.departure_date, tb.issue_date,
            tb.status, tb.currency, tb.sold AS amount, NULL AS passport_number
            FROM ticket_bookings tb
            LEFT JOIN clients c ON tb.sold_to = c.id AND c.branch_id = ?
            LEFT JOIN suppliers s ON tb.supplier = s.id AND s.branch_id = ?
            WHERE tb.tenant_id = ? AND tb.branch_id = ? AND
            (tb.passenger_name LIKE ? OR tb.pnr LIKE ? OR tb.phone LIKE ?)");
        $stmt->execute([$branch_id, $branch_id, $tenant_id, $branch_id, $likeParam, $likeParam, $likeParam]);
        $searchResults = array_merge($searchResults, $stmt->fetchAll(PDO::FETCH_ASSOC));

        // Ticket reservations
        $stmt = $pdo->prepare("SELECT 
            'Ticket Reservation' AS record_type, tb.id,
            tb.passenger_name AS name, tb.pnr AS reference, tb.phone, tb.gender,
            c.name AS client_name, c.id AS client_id,
            s.name AS supplier_name, s.id AS supplier_id,
            tb.origin, tb.destination, tb.departure_date, tb.issue_date,
            tb.status, tb.currency, tb.sold AS amount, NULL AS passport_number
            FROM ticket_reservations tb
            LEFT JOIN clients c ON tb.sold_to = c.id AND c.branch_id = ?
            LEFT JOIN suppliers s ON tb.supplier = s.id AND s.branch_id = ?
            WHERE tb.tenant_id = ? AND tb.branch_id = ? AND
            (tb.passenger_name LIKE ? OR tb.pnr LIKE ? OR tb.phone LIKE ?)");
        $stmt->execute([$branch_id, $branch_id, $tenant_id, $branch_id, $likeParam, $likeParam, $likeParam]);
        $searchResults = array_merge($searchResults, $stmt->fetchAll(PDO::FETCH_ASSOC));

        // Visa applications
        $stmt = $pdo->prepare("SELECT 
            'Visa' AS record_type, va.id,
            va.applicant_name AS name, va.passport_number AS reference, va.phone, va.gender,
            c.name AS client_name, c.id AS client_id,
            s.name AS supplier_name, s.id AS supplier_id,
            va.country AS origin, va.visa_type AS destination,
            va.applied_date AS departure_date, va.receive_date AS issue_date,
            va.status, va.currency, va.sold AS amount, va.passport_number
            FROM visa_applications va
            LEFT JOIN clients c ON va.sold_to = c.id AND c.branch_id = ?
            LEFT JOIN suppliers s ON va.supplier = s.id AND s.branch_id = ?
            WHERE va.tenant_id = ? AND va.branch_id = ? AND
            (va.applicant_name LIKE ? OR va.passport_number LIKE ? OR va.phone LIKE ?)");
        $stmt->execute([$branch_id, $branch_id, $tenant_id, $branch_id, $likeParam, $likeParam, $likeParam]);
        $searchResults = array_merge($searchResults, $stmt->fetchAll(PDO::FETCH_ASSOC));

        // Hotel bookings
        $stmt = $pdo->prepare("SELECT 
            'Hotel' AS record_type, hb.id,
            CONCAT(hb.first_name, ' ', hb.last_name) AS name,
            hb.order_id AS reference, hb.contact_no AS phone, hb.gender,
            hb.sold_to AS client_name, NULL AS client_id,
            s.name AS supplier_name, s.id AS supplier_id,
            'Hotel' AS origin, hb.accommodation_details AS destination,
            hb.check_in_date AS departure_date, hb.issue_date,
            'Booked' AS status, hb.currency, hb.sold_amount AS amount, NULL AS passport_number
            FROM hotel_bookings hb
            LEFT JOIN suppliers s ON hb.supplier_id = s.id AND s.branch_id = ?
            WHERE hb.tenant_id = ? AND hb.branch_id = ? AND
            (CONCAT(hb.first_name, ' ', hb.last_name) LIKE ? OR hb.order_id LIKE ? OR hb.contact_no LIKE ?)");
        $stmt->execute([$branch_id, $tenant_id, $branch_id, $likeParam, $likeParam, $likeParam]);
        $searchResults = array_merge($searchResults, $stmt->fetchAll(PDO::FETCH_ASSOC));

        // Umrah bookings
        $stmt = $pdo->prepare("SELECT 
            'Umrah' AS record_type, ub.booking_id AS id,
            ub.name, ub.passport_number AS reference,
            NULL AS phone, NULL AS gender,
            c.name AS client_name, ub.sold_to AS client_id,
            NULL AS supplier_name, NULL AS supplier_id,
            'Mecca/Medina' AS origin, ub.room_type AS destination,
            ub.flight_date AS departure_date, ub.created_at AS issue_date,
            'Booked' AS status, ub.currency, ub.sold_price AS amount, ub.passport_number
            FROM umrah_bookings ub
            LEFT JOIN clients c ON ub.sold_to = c.id AND c.branch_id = ?
            WHERE ub.tenant_id = ? AND ub.branch_id = ? AND
            (ub.name LIKE ? OR ub.passport_number LIKE ? OR ub.id_type LIKE ?)");
        $stmt->execute([$branch_id, $tenant_id, $branch_id, $likeParam, $likeParam, $likeParam]);
        $searchResults = array_merge($searchResults, $stmt->fetchAll(PDO::FETCH_ASSOC));

        // Additional payments
        $stmt = $pdo->prepare("SELECT 
            'Additional Payment' AS record_type, ap.id,
            ap.description, ap.payment_type AS name, ap.id AS reference,
            NULL AS phone, NULL AS gender,
            NULL AS client_name, NULL AS client_id,
            NULL AS supplier_name, NULL AS supplier_id,
            ap.payment_type AS origin, ap.description AS destination,
            ap.created_at AS departure_date, ap.created_at AS issue_date,
            ap.payment_type AS status, ap.currency, ap.sold_amount AS amount, NULL AS passport_number
            FROM additional_payments ap
            WHERE ap.tenant_id = ? AND ap.branch_id = ? AND
            (ap.description LIKE ? OR ap.payment_type LIKE ?)");
        $stmt->execute([$tenant_id, $branch_id, $likeParam, $likeParam]);
        $searchResults = array_merge($searchResults, $stmt->fetchAll(PDO::FETCH_ASSOC));

        // Expenses
        $stmt = $pdo->prepare("SELECT 
            'Expense' AS record_type, e.id,
            ec.name AS name, e.id AS reference,
            NULL AS phone, NULL AS gender,
            NULL AS client_name, NULL AS client_id,
            NULL AS supplier_name, NULL AS supplier_id,
            'Expense' AS origin, e.description AS destination,
            e.date AS departure_date, e.created_at AS issue_date,
            'Paid' AS status, e.currency, e.amount, NULL AS passport_number
            FROM expenses e
            LEFT JOIN expense_categories ec ON e.category_id = ec.id AND ec.branch_id = ?
            WHERE e.tenant_id = ? AND e.branch_id = ? AND
            e.description LIKE ? OR ec.name LIKE ?");
        $stmt->execute([$branch_id, $tenant_id, $branch_id, $likeParam, $likeParam]);
        $searchResults = array_merge($searchResults, $stmt->fetchAll(PDO::FETCH_ASSOC));

        // Creditors
        $stmt = $pdo->prepare("SELECT 
            'Creditor' AS record_type, cr.id,
            cr.name AS name, cr.id AS reference, cr.phone,
            NULL AS gender, NULL AS client_name, NULL AS client_id,
            NULL AS supplier_name, NULL AS supplier_id,
            'Credit' AS origin, cr.address AS destination,
            cr.created_at AS departure_date, cr.created_at AS issue_date,
            cr.status, cr.currency, cr.balance AS amount, NULL AS passport_number
            FROM creditors cr
            WHERE cr.tenant_id = ? AND cr.branch_id = ? AND
            cr.name LIKE ? OR cr.email LIKE ? OR cr.phone LIKE ?");
        $stmt->execute([$tenant_id, $branch_id, $likeParam, $likeParam, $likeParam]);
        $searchResults = array_merge($searchResults, $stmt->fetchAll(PDO::FETCH_ASSOC));

        // Debtors
        $stmt = $pdo->prepare("SELECT 
            'Debtor' AS record_type, db.id,
            db.name AS name, db.id AS reference, db.phone,
            NULL AS gender, NULL AS client_name, NULL AS client_id,
            NULL AS supplier_name, NULL AS supplier_id,
            'Debt' AS origin, db.address AS destination,
            db.created_at AS departure_date, db.created_at AS issue_date,
            db.status, db.currency, db.balance AS amount, NULL AS passport_number
            FROM debtors db
            WHERE db.tenant_id = ? AND db.branch_id = ? AND
            db.name LIKE ? OR db.email LIKE ? OR db.phone LIKE ?");
        $stmt->execute([$tenant_id, $branch_id, $likeParam, $likeParam, $likeParam]);
        $searchResults = array_merge($searchResults, $stmt->fetchAll(PDO::FETCH_ASSOC));

        // Fetch related transactions for each result
        foreach ($searchResults as $key => $result) {
            $transactions = [];
            $txnOf = match($result['record_type']) {
                'Ticket'             => 'ticket_sale',
                'Ticket Reservation' => 'ticket_reservation',
                'Visa'               => 'visa_sale',
                'Hotel'              => 'hotel_booking',
                'Umrah'              => 'umrah_booking',
                'Additional Payment' => 'additional_payment',
                'Expense'            => 'expense',
                'Creditor'           => 'creditor',
                'Debtor'             => 'debtor',
                default              => null,
            };

            if ($txnOf) {
                $extraWhere = $result['record_type'] === 'Ticket'
                    ? "AND (mat.transaction_of = 'ticket_sale' OR mat.transaction_of = 'ticket_refund' OR mat.transaction_of = 'date_change')"
                    : "AND mat.transaction_of = '$txnOf'";

                $txnStmt = $pdo->prepare("SELECT 
                    'Main Account' AS transaction_type,
                    mat.type, mat.amount, mat.currency, mat.description,
                    mat.transaction_of, mat.created_at AS transaction_date
                    FROM main_account_transactions mat
                    WHERE mat.reference_id = ? AND mat.tenant_id = ? AND mat.branch_id = ?
                    $extraWhere");
                $txnStmt->execute([$result['id'], $tenant_id, $branch_id]);
                $transactions = $txnStmt->fetchAll(PDO::FETCH_ASSOC);
            }

            $searchResults[$key]['transactions'] = $transactions;
        }

        if (empty($searchResults)) {
            $resultMessage = "No results found for: " . htmlspecialchars($searchTerm);
        }
    }
}

// Validate inputs
$searchTerm = isset($_POST['searchTerm']) ? DbSecurity::validateInput($_POST['searchTerm'], 'string', ['maxlength' => 255]) : ($searchTerm ?? '');

include '../includes/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
  --brand-start: #4099ff;
  --brand-end:   #2ed8b6;
  --brand-grad:  linear-gradient(135deg, var(--brand-start) 0%, var(--brand-end) 100%);
  --surface:     #f4f6fb;
  --card-bg:     #ffffff;
  --border:      #e4e9f0;
  --text-primary:#1a2035;
  --text-muted:  #6b7a99;
  --radius-lg:   16px;
  --shadow-sm:   0 1px 3px rgba(0,0,0,.07), 0 1px 2px rgba(0,0,0,.05);
  --shadow-md:   0 4px 16px rgba(0,0,0,.08);
  --transition:  all .2s cubic-bezier(.4,0,.2,1);
}

*, *::before, *::after { box-sizing: border-box; }

body {
  font-family: 'Plus Jakarta Sans', sans-serif;
  background: var(--surface);
  color: var(--text-primary);
}

/* ── HERO ── */
.search-hero {
  background: var(--brand-grad);
  padding: 48px 24px 80px;
  position: relative;
  overflow: hidden;
}
.search-hero::before {
  content: '';
  position: absolute;
  inset: 0;
  background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
}
.search-hero-inner {
  max-width: 720px;
  margin: 0 auto;
  position: relative;
  text-align: center;
}
.search-hero h1 {
  color: #fff;
  font-size: clamp(1.5rem, 3vw, 2rem);
  font-weight: 700;
  margin: 0 0 6px;
  letter-spacing: -.3px;
}
.search-hero p {
  color: rgba(255,255,255,.8);
  font-size: .95rem;
  margin: 0 0 28px;
}

/* Search pill */
.search-input-wrap {
  display: flex;
  align-items: center;
  background: #fff;
  border-radius: 50px;
  box-shadow: 0 8px 32px rgba(0,0,0,.15);
  padding: 6px 6px 6px 20px;
  gap: 8px;
  transition: var(--transition);
}
.search-input-wrap:focus-within {
  box-shadow: 0 12px 40px rgba(0,0,0,.2), 0 0 0 3px rgba(255,255,255,.4);
}
.search-input-wrap .si { color: var(--brand-start); font-size: 1.1rem; flex-shrink: 0; }
.search-input-wrap input {
  flex: 1;
  border: none;
  outline: none;
  font-family: inherit;
  font-size: 1rem;
  font-weight: 500;
  color: var(--text-primary);
  background: transparent;
  padding: 8px 0;
  min-width: 0;
}
.search-input-wrap input::placeholder { color: #aab4c8; font-weight: 400; }
.search-input-wrap button {
  flex-shrink: 0;
  background: var(--brand-grad);
  color: #fff;
  border: none;
  border-radius: 40px;
  padding: 12px 28px;
  font-family: inherit;
  font-size: .9rem;
  font-weight: 600;
  cursor: pointer;
  transition: var(--transition);
  white-space: nowrap;
}
.search-input-wrap button:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 14px rgba(64,153,255,.4);
}

/* ── RESULTS AREA ── */
.results-outer {
  max-width: 1200px;
  margin: -40px auto 0;
  padding: 0 24px 60px;
  position: relative;
  z-index: 2;
}

/* Stats bar */
.stats-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: var(--card-bg);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 14px 20px;
  margin-bottom: 20px;
  box-shadow: var(--shadow-sm);
  flex-wrap: wrap;
  gap: 10px;
}
.stats-bar .result-count { font-weight: 700; font-size: 1rem; color: var(--text-primary); }
.stats-bar .result-count span {
  background: var(--brand-grad);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

/* Filter chips */
.filter-chips { display: flex; gap: 6px; flex-wrap: wrap; }
.chip {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 5px 12px;
  border-radius: 20px;
  font-size: .78rem;
  font-weight: 600;
  cursor: pointer;
  border: 1.5px solid transparent;
  transition: var(--transition);
  background: var(--surface);
  color: var(--text-muted);
  white-space: nowrap;
  user-select: none;
}
.chip.active, .chip:hover {
  border-color: var(--brand-start);
  color: var(--brand-start);
  background: rgba(64,153,255,.08);
}

/* ── RESULT CARD ── */
.result-card {
  background: var(--card-bg);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  margin-bottom: 12px;
  box-shadow: var(--shadow-sm);
  overflow: hidden;
  transition: var(--transition);
  animation: cardIn .25s ease both;
}
@keyframes cardIn {
  from { opacity:0; transform:translateY(8px); }
  to   { opacity:1; transform:translateY(0); }
}
.result-card:hover {
  border-color: rgba(64,153,255,.3);
  box-shadow: var(--shadow-md);
  transform: translateY(-1px);
}
.card-main {
  display: grid;
  grid-template-columns: auto 1fr auto;
  align-items: center;
  gap: 16px;
  padding: 16px 20px;
}

/* Type col */
.card-type-col {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  min-width: 68px;
}
.type-icon {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.1rem;
  color: #fff;
}
.type-badge {
  font-size: .65rem;
  font-weight: 700;
  letter-spacing: .3px;
  text-transform: uppercase;
  padding: 3px 8px;
  border-radius: 10px;
  white-space: nowrap;
  text-align: center;
}
/* Colors */
.type-ticket     { background: linear-gradient(135deg,#4099ff,#6eb5ff); }
.type-ticket-res { background: linear-gradient(135deg,#7c5cfc,#a78bfa); }
.type-visa       { background: linear-gradient(135deg,#f97316,#fb923c); }
.type-hotel      { background: linear-gradient(135deg,#2ed8b6,#34d399); }
.type-umrah      { background: linear-gradient(135deg,#f59e0b,#fbbf24); }
.type-payment    { background: linear-gradient(135deg,#06b6d4,#38bdf8); }
.type-expense    { background: linear-gradient(135deg,#6b7a99,#94a3b8); }
.type-creditor   { background: linear-gradient(135deg,#ef4444,#f87171); }
.type-debtor     { background: linear-gradient(135deg,#8b5cf6,#a78bfa); }

.badge-ticket     { background:#e8f1ff; color:#4099ff; }
.badge-ticket-res { background:#f0ebff; color:#7c5cfc; }
.badge-visa       { background:#fff3ec; color:#f97316; }
.badge-hotel      { background:#eafaf6; color:#059669; }
.badge-umrah      { background:#fffbeb; color:#d97706; }
.badge-payment    { background:#ecfeff; color:#0891b2; }
.badge-expense    { background:#f1f5f9; color:#475569; }
.badge-creditor   { background:#fef2f2; color:#ef4444; }
.badge-debtor     { background:#f5f3ff; color:#7c3aed; }

/* Info grid */
.card-info {
  min-width: 0;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
  gap: 4px 16px;
}
.info-name {
  font-size: 1rem;
  font-weight: 700;
  color: var(--text-primary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  grid-column: 1 / -1;
  margin-bottom: 6px;
}
.info-row { display: flex; flex-direction: column; gap: 1px; min-width: 0; }
.info-label {
  font-size: .67rem;
  font-weight: 600;
  letter-spacing: .5px;
  text-transform: uppercase;
  color: var(--text-muted);
}
.info-value {
  font-size: .82rem;
  font-weight: 500;
  color: var(--text-primary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.info-value.mono { font-family: 'DM Mono', monospace; font-size: .78rem; color: var(--text-muted); }
.info-value.amount {
  font-family: 'DM Mono', monospace;
  font-weight: 700;
  font-size: .9rem;
  background: var(--brand-grad);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}
.status-pill {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 10px;
  font-size: .72rem;
  font-weight: 600;
  background: #e8f5e9;
  color: #2e7d32;
}

/* Actions */
.card-actions { display: flex; flex-direction: column; gap: 8px; align-items: flex-end; }
.btn-view {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 9px 18px;
  background: var(--brand-grad);
  color: #fff;
  border: none;
  border-radius: 8px;
  font-family: inherit;
  font-size: .82rem;
  font-weight: 600;
  cursor: pointer;
  text-decoration: none;
  transition: var(--transition);
  white-space: nowrap;
}
.btn-view:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(64,153,255,.35); color:#fff; text-decoration:none; }
.btn-txn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 18px;
  background: transparent;
  color: var(--text-muted);
  border: 1.5px solid var(--border);
  border-radius: 8px;
  font-family: inherit;
  font-size: .82rem;
  font-weight: 600;
  cursor: pointer;
  transition: var(--transition);
  white-space: nowrap;
}
.btn-txn:hover, .btn-txn.open { border-color: var(--brand-start); color: var(--brand-start); background: rgba(64,153,255,.05); }
.btn-txn .chevron { transition: transform .25s ease; }
.btn-txn.open .chevron { transform: rotate(180deg); }

/* ── TRANSACTION PANEL ── */
.txn-panel {
  display: none;
  border-top: 1px solid var(--border);
  background: #fafbfd;
  padding: 16px 20px;
  animation: fadeSlide .2s ease;
}
@keyframes fadeSlide {
  from { opacity:0; transform:translateY(-6px); }
  to   { opacity:1; transform:translateY(0); }
}
.txn-panel.open { display: block; }
.txn-title { font-size: .82rem; font-weight: 700; color: var(--text-primary); margin: 0 0 12px; }
.txn-table { width: 100%; border-collapse: collapse; font-size: .83rem; }
.txn-table thead tr { border-bottom: 1.5px solid var(--border); }
.txn-table th {
  padding: 8px 12px;
  font-size: .7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .5px;
  color: var(--text-muted);
  text-align: left;
}
.txn-table tbody tr { border-bottom: 1px solid var(--border); transition: background .15s; }
.txn-table tbody tr:last-child { border-bottom: none; }
.txn-table tbody tr:hover { background: rgba(64,153,255,.04); }
.txn-table td { padding: 10px 12px; vertical-align: middle; }
.txn-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 8px;
  border-radius: 6px;
  font-size: .72rem;
  font-weight: 700;
}
.txn-credit { background: #e8f5e9; color: #2e7d32; }
.txn-debit  { background: #fef2f2; color: #c62828; }
.txn-empty { text-align: center; padding: 24px; color: var(--text-muted); font-size: .85rem; }
.txn-footer {
  margin-top: 14px;
  padding-top: 14px;
  border-top: 1px solid var(--border);
  display: flex;
  justify-content: flex-end;
}
.btn-outline-sm {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 16px;
  border: 1.5px solid var(--brand-start);
  color: var(--brand-start);
  border-radius: 7px;
  font-family: inherit;
  font-size: .8rem;
  font-weight: 600;
  cursor: pointer;
  text-decoration: none;
  transition: var(--transition);
  background: transparent;
}
.btn-outline-sm:hover { background: rgba(64,153,255,.08); text-decoration: none; color: var(--brand-start); }

/* ── EMPTY STATE ── */
.state-card {
  background: var(--card-bg);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  text-align: center;
  padding: 56px 24px;
  box-shadow: var(--shadow-sm);
}
.state-card .state-icon { font-size: 2.5rem; margin-bottom: 14px; opacity: .4; }
.state-card h3 { font-size: 1.1rem; font-weight: 700; color: var(--text-primary); margin: 0 0 6px; }
.state-card p { color: var(--text-muted); font-size: .9rem; margin: 0; }

/* ── RESPONSIVE ── */
@media (max-width: 768px) {
  .search-hero { padding: 36px 16px 64px; }
  .card-main { grid-template-columns: auto 1fr; grid-template-rows: auto auto; }
  .card-actions {
    grid-column: 1 / -1;
    flex-direction: row;
    justify-content: flex-start;
    border-top: 1px solid var(--border);
    padding-top: 12px;
    margin-top: 4px;
  }
  .card-info { grid-template-columns: 1fr 1fr; }
  .stats-bar { flex-direction: column; align-items: flex-start; }
  .results-outer { padding: 0 12px 40px; }
}
@media (max-width: 480px) {
  .search-input-wrap { border-radius: 14px; flex-wrap: wrap; padding: 10px 14px; }
  .search-input-wrap button { width: 100%; justify-content: center; border-radius: 10px; }
  .card-info { grid-template-columns: 1fr; }
}
</style>
</head>
<body>
<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">

                <div class="main-body">
                    <div class="page-wrapper">
<!-- ═══════════ HERO SEARCH ═══════════ -->
<div class="search-hero">
  <div class="search-hero-inner">
    <h1><i class="fas fa-search" style="margin-right:10px;opacity:.85"></i><?= __('search') ?></h1>
    <p><?= __('search_for_people') ?></p>
    <form method="POST" action="">
      <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
      <div class="search-input-wrap">
        <i class="fas fa-search si"></i>
        <input
          type="text"
          name="searchTerm"
          id="searchTerm"
          placeholder="<?= __('search_by_name_passport_number_phone_number_or_any_other_identifier') ?>"
          value="<?php echo htmlspecialchars($searchTerm ?? ''); ?>"
          autocomplete="off"
          required
        >
        <button type="submit" name="search">
          <i class="fas fa-search" style="margin-right:6px"></i><?= __('search') ?>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ═══════════ RESULTS ═══════════ -->
<div class="results-outer">

<?php if ($searchPerformed): ?>

  <?php if (!empty($resultMessage)): ?>
    <div class="state-card">
      <div class="state-icon">🔍</div>
      <h3><?= __('no_results_found') ?></h3>
      <p><?php echo htmlspecialchars($resultMessage); ?></p>
    </div>

  <?php elseif (!empty($searchResults)): ?>

    <!-- Stats + filter chips -->
    <div class="stats-bar">
      <div class="result-count">
        <span><?php echo count($searchResults); ?></span>
        result<?php echo count($searchResults) !== 1 ? 's' : ''; ?>
        for "<strong><?php echo htmlspecialchars($searchTerm); ?></strong>"
      </div>
      <div class="filter-chips" id="filterChips">
        <span class="chip active" data-filter="all"><?= __('all') ?? 'All' ?></span>
        <?php
          $types = array_unique(array_column($searchResults, 'record_type'));
          foreach ($types as $t):
        ?>
        <span class="chip" data-filter="<?php echo htmlspecialchars($t); ?>">
          <?php echo htmlspecialchars($t); ?>
        </span>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Result cards -->
    <div id="resultsList">
    <?php foreach ($searchResults as $index => $result):
      $rt = $result['record_type'];

      $typeClass = match($rt) {
        'Ticket'             => 'ticket',
        'Ticket Reservation' => 'ticket-res',
        'Visa'               => 'visa',
        'Hotel'              => 'hotel',
        'Umrah'              => 'umrah',
        'Additional Payment' => 'payment',
        'Expense'            => 'expense',
        'Creditor'           => 'creditor',
        'Debtor'             => 'debtor',
        default              => 'expense',
      };

      $icon = match($rt) {
        'Ticket', 'Ticket Reservation' => 'fa-plane',
        'Visa'               => 'fa-passport',
        'Hotel'              => 'fa-hotel',
        'Umrah'              => 'fa-mosque',
        'Additional Payment' => 'fa-credit-card',
        'Expense'            => 'fa-receipt',
        'Creditor'           => 'fa-hand-holding-usd',
        'Debtor'             => 'fa-user-check',
        default              => 'fa-file-alt',
      };

      $detailUrl = match($rt) {
        'Ticket'             => "ticket_detail.php?id=" . (int)$result['id'],
        'Ticket Reservation' => "ticket_reservation_detail.php?id=" . (int)$result['id'],
        'Visa'               => "visa_detail.php?id=" . (int)$result['id'],
        'Hotel'              => "hotel_detail.php?id=" . (int)$result['id'],
        'Umrah'              => "umrah_detail.php?id=" . (int)$result['id'],
        'Additional Payment' => "additional_payments_detail.php?id=" . (int)$result['id'],
        'Creditor'           => "creditors_detail.php?id=" . (int)$result['id'],
        'Debtor'             => "debtors_detail.php?id=" . (int)$result['id'],
        'Expense'            => "expense_detail.php?id=" . (int)$result['id'],
        default              => '#',
      };
    ?>
    <div class="result-card" data-type="<?php echo htmlspecialchars($rt); ?>">
      <div class="card-main">

        <!-- Type -->
        <div class="card-type-col">
          <div class="type-icon type-<?php echo $typeClass; ?>">
            <i class="fas <?php echo $icon; ?>"></i>
          </div>
          <span class="type-badge badge-<?php echo $typeClass; ?>"><?php echo htmlspecialchars($rt); ?></span>
        </div>

        <!-- Info -->
        <div class="card-info">
          <div class="info-name"><?php echo htmlspecialchars($result['name'] ?? '—'); ?></div>

          <?php if (!empty($result['reference'])): ?>
          <div class="info-row">
            <span class="info-label"><?= __('reference') ?></span>
            <span class="info-value mono"><?php echo htmlspecialchars($result['reference']); ?></span>
          </div>
          <?php endif; ?>

          <?php if (!empty($result['phone'])): ?>
          <div class="info-row">
            <span class="info-label"><?= __('contact') ?></span>
            <span class="info-value"><?php echo htmlspecialchars($result['phone']); ?></span>
          </div>
          <?php endif; ?>

          <?php if (!empty($result['client_name'])): ?>
          <div class="info-row">
            <span class="info-label"><?= __('client') ?></span>
            <span class="info-value"><?php echo htmlspecialchars($result['client_name']); ?></span>
          </div>
          <?php endif; ?>

          <?php if (!empty($result['supplier_name'])): ?>
          <div class="info-row">
            <span class="info-label"><?= __('supplier') ?></span>
            <span class="info-value"><?php echo htmlspecialchars($result['supplier_name']); ?></span>
          </div>
          <?php endif; ?>

          <?php if (!empty($result['origin']) || !empty($result['destination'])): ?>
          <div class="info-row">
            <span class="info-label"><?php echo in_array($rt, ['Ticket','Ticket Reservation']) ? __('route') ?? 'Route' : __('details') ?></span>
            <span class="info-value">
              <?php
                if (in_array($rt, ['Ticket','Ticket Reservation'])) {
                  echo htmlspecialchars($result['origin'] ?? '') . ' → ' . htmlspecialchars($result['destination'] ?? '');
                } else {
                  echo htmlspecialchars($result['origin'] ?? '') . ': ' . htmlspecialchars($result['destination'] ?? '');
                }
              ?>
            </span>
          </div>
          <?php endif; ?>

          <?php if (!empty($result['passport_number'])): ?>
          <div class="info-row">
            <span class="info-label">Passport</span>
            <span class="info-value mono"><?php echo htmlspecialchars($result['passport_number']); ?></span>
          </div>
          <?php endif; ?>

          <?php if (!empty($result['departure_date'])): ?>
          <div class="info-row">
            <span class="info-label"><?= __('date') ?></span>
            <span class="info-value"><?php echo date('d M Y', strtotime($result['departure_date'])); ?></span>
          </div>
          <?php endif; ?>

          <?php if (!empty($result['status'])): ?>
          <div class="info-row">
            <span class="info-label"><?= __('status') ?></span>
            <span class="status-pill"><?php echo htmlspecialchars($result['status']); ?></span>
          </div>
          <?php endif; ?>

          <?php if (!empty($result['amount'])): ?>
          <div class="info-row">
            <span class="info-label"><?= __('amount') ?></span>
            <span class="info-value amount"><?php echo htmlspecialchars($result['currency'] ?? ''); ?> <?php echo htmlspecialchars($result['amount']); ?></span>
          </div>
          <?php endif; ?>
        </div>

        <!-- Actions -->
        <div class="card-actions">
          <a href="<?php echo $detailUrl; ?>" class="btn-view">
            <i class="fas fa-arrow-right"></i> <?= __('view') ?? 'View' ?>
          </a>
          <button class="btn-txn" onclick="toggleTxn(this, 'txn-<?php echo $index; ?>')">
            <i class="fas fa-list-ul"></i> <?= __('transactions') ?>
            <i class="fas fa-chevron-down chevron"></i>
          </button>
        </div>

      </div><!-- /card-main -->

      <!-- Transaction panel -->
      <div class="txn-panel" id="txn-<?php echo $index; ?>">
        <p class="txn-title"><i class="fas fa-history" style="margin-right:6px;opacity:.6"></i><?= __('transaction_history') ?></p>

        <?php if (!empty($result['transactions'])): ?>
          <div style="overflow-x:auto">
            <table class="txn-table">
              <thead>
                <tr>
                  <th><?= __('type') ?></th>
                  <th><?= __('transaction') ?></th>
                  <th><?= __('amount') ?></th>
                  <th><?= __('description') ?></th>
                  <th><?= __('date') ?></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($result['transactions'] as $txn):
                  $isDebit  = stripos($txn['type'], 'debit') !== false;
                  $txnClass = $isDebit ? 'txn-debit' : 'txn-credit';
                  $txnIcon  = $isDebit ? '↓' : '↑';
                ?>
                <tr>
                  <td>
                    <span class="txn-badge <?php echo $txnClass; ?>">
                      <?php echo $txnIcon . ' ' . htmlspecialchars($txn['transaction_type']); ?>
                    </span>
                  </td>
                  <td><?php echo htmlspecialchars($txn['type']); ?></td>
                  <td style="font-family:'DM Mono',monospace;font-weight:600">
                    <?php echo htmlspecialchars($txn['currency'] ?? ''); ?> <?php echo htmlspecialchars($txn['amount']); ?>
                  </td>
                  <td style="color:var(--text-muted)"><?php echo htmlspecialchars($txn['description']); ?></td>
                  <td style="font-family:'DM Mono',monospace;font-size:.75rem;color:var(--text-muted)">
                    <?php echo date('d M Y', strtotime($txn['transaction_date'])); ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="txn-empty">
            <i class="fas fa-inbox" style="font-size:1.4rem;opacity:.3;display:block;margin-bottom:8px"></i>
            <?= __('no_transactions_found_for_this_item') ?>
          </div>
        <?php endif; ?>

        <div class="txn-footer">
          <a href="<?php echo $detailUrl; ?>" class="btn-outline-sm">
            <i class="fas fa-external-link-alt"></i> <?= __('view_all_transactions') ?>
          </a>
        </div>
      </div>

    </div><!-- /result-card -->
    <?php endforeach; ?>
    </div><!-- /resultsList -->

  <?php endif; ?>

<?php else: ?>
  <!-- Initial state -->
  <div class="state-card">
    <div class="state-icon">🔍</div>
    <h3><?= __('search') ?></h3>
    <p><?= __('search_by_name_passport_number_phone_number_or_any_other_identifier') ?></p>
  </div>
<?php endif; ?>

</div><!-- /results-outer -->
</div><!-- end page-wrapper -->
                </div><!-- end main-body -->
            </div><!-- end pcoded-inner-content -->
        </div><!-- end pcoded-content -->
    </div><!-- end pcoded-wrapper -->
</div><!-- end pcoded-main-container -->
<!-- [ Main Content ] end -->
<!-- Scripts -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>

<script>
function toggleTxn(btn, panelId) {
  const panel = document.getElementById(panelId);
  const isOpen = panel.classList.contains('open');
  panel.classList.toggle('open', !isOpen);
  btn.classList.toggle('open', !isOpen);
}

document.querySelectorAll('#filterChips .chip').forEach(chip => {
  chip.addEventListener('click', () => {
    document.querySelectorAll('#filterChips .chip').forEach(c => c.classList.remove('active'));
    chip.classList.add('active');
    const filter = chip.dataset.filter;
    document.querySelectorAll('#resultsList .result-card').forEach(card => {
      card.style.display = (filter === 'all' || card.dataset.type === filter) ? '' : 'none';
    });
  });
});
</script>

<?php include '../includes/admin_footer.php'; ?>
</body>
</html>