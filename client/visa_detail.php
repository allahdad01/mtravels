<?php
require_once 'security.php';
require_once '../includes/language_helpers.php';
// Define h() function for HTML escaping
if (!function_exists('h')) {
  function h($string) {
      return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
  }
}
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

$allowed_roles = ['admin', 'finance', 'sales', 'umrah', 'client'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
   header('Location: ../login.php');
    exit();
}

require_once('../includes/db.php');

$visaId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$visaData = null;
$clientTransactions = [];
$supplierTransactions = [];
$mainAccountTransactions = [];
$error = null;

if (!$visaId) {
    $error = "No visa application ID provided";
} else {
    $visaQuery = "SELECT va.*, c.name AS client_name, c.email AS client_email, c.phone AS client_phone, s.name AS supplier_name, s.email AS supplier_email, s.phone AS supplier_phone
        FROM visa_applications va
        LEFT JOIN clients c ON va.sold_to = c.id
        LEFT JOIN suppliers s ON va.supplier = s.id
        WHERE va.id = ? AND va.tenant_id = ? AND va.branch_id = ? AND (c.id IS NULL OR c.branch_id = ?) AND (s.id IS NULL OR s.branch_id = ?)";
    $stmt = $pdo->prepare($visaQuery);
    $stmt->execute([$visaId, $tenant_id, $branch_id, $branch_id, $branch_id]);
    $visaData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$visaData) {
        $error = "Visa application not found";
    } else {

        $stmt = $pdo->prepare("SELECT 'Client' AS transaction_type, ct.id, ct.type, ct.amount, ct.currency, ct.description, ct.transaction_of, ct.created_at AS transaction_date FROM client_transactions ct WHERE ct.reference_id = ? AND ct.transaction_of = 'visa_sale' AND ct.tenant_id = ? AND ct.branch_id = ? ORDER BY ct.created_at DESC");
        $stmt->execute([$visaId, $tenant_id, $branch_id]);
        $clientTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);


    }
}

include '../includes/header_client.php';

// helpers
$s      = $visaData['status'] ?? '';
$scls   = match(strtolower($s)) {
    'approved'   => 'st-approved',
    'processing' => 'st-processing',
    'rejected'   => 'st-rejected',
    'pending'    => 'st-pending',
    default      => 'st-default'
};

$allTx = array_merge($clientTransactions, $supplierTransactions, $mainAccountTransactions);
usort($allTx, fn($a,$b) => strtotime($b['transaction_date']) - strtotime($a['transaction_date']));
?>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=IBM+Plex+Mono:wght@400;500;600&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
/* ═══════════════════════════════════════════════════
   VISA DOCUMENT THEME
   The detail page IS the visa — passport-page layout
   with MRZ strip, hologram effect, stamps, watermark
═══════════════════════════════════════════════════ */
:root {
    --grad:      linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    --grad-r:    linear-gradient(135deg, #2ed8b6 0%, #4099ff 100%);
    --navy:      #0d1f3c;
    --navy2:     #162b50;
    --gold:      #c8a84b;
    --gold-lt:   #f5e6b8;
    --teal:      #2ed8b6;
    --blue:      #4099ff;
    --bg:        #e8edf5;
    --surface:   #ffffff;
    --border:    #dde3ef;
    --text-1:    #0d1f3c;
    --text-2:    #3d5278;
    --text-3:    #8899bb;
    --green:     #10b981;
    --red:       #ef4444;
    --amber:     #f59e0b;
    --font-disp: 'Playfair Display', serif;
    --font-body: 'Outfit', sans-serif;
    --font-mono: 'IBM Plex Mono', monospace;
}

* { box-sizing: border-box; }
body, .pcoded-main-container { background: var(--bg) !important; font-family: var(--font-body) !important; }

/* ── PAGE CHROME ───────────────────────────── */
.vp-wrap {
    max-width: 960px;
    margin: 0 auto;
    padding: 28px 20px 60px;
    animation: vpFade .5s ease both;
}

.vp-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 28px;
    flex-wrap: wrap;
    gap: 12px;
}

.vp-back {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 20px;
    background: var(--surface);
    border: 1.5px solid var(--border);
    border-radius: 50px;
    font-size: 13px;
    font-weight: 500;
    color: var(--text-2);
    text-decoration: none;
    transition: all .2s;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
}
.vp-back:hover { border-color: var(--blue); color: var(--blue); transform: translateX(-3px); text-decoration: none; }

/* ── THE VISA DOCUMENT ─────────────────────── */
.visa-doc {
    background: var(--surface);
    border-radius: 20px;
    overflow: hidden;
    box-shadow:
        0 2px 4px rgba(0,0,0,.04),
        0 8px 32px rgba(13,31,60,.12),
        0 32px 64px rgba(13,31,60,.08);
    position: relative;
    margin-bottom: 28px;
    border: 1.5px solid rgba(200,168,75,.25);
}

/* subtle diagonal security pattern */
.visa-doc::before {
    content: '';
    position: absolute;
    inset: 0;
    background-image:
        repeating-linear-gradient(
            58deg,
            transparent,
            transparent 18px,
            rgba(64,153,255,.022) 18px,
            rgba(64,153,255,.022) 19px
        ),
        repeating-linear-gradient(
            -58deg,
            transparent,
            transparent 18px,
            rgba(46,216,182,.018) 18px,
            rgba(46,216,182,.018) 19px
        );
    pointer-events: none;
    z-index: 0;
}

/* ── VISA HEADER BAND ──────────────────────── */
.visa-header-band {
    background: var(--navy);
    background-image:
        radial-gradient(ellipse at 20% 50%, rgba(64,153,255,.18) 0%, transparent 60%),
        radial-gradient(ellipse at 80% 50%, rgba(46,216,182,.14) 0%, transparent 60%);
    padding: 0 32px;
    display: flex;
    align-items: stretch;
    gap: 0;
    position: relative;
    overflow: hidden;
    min-height: 110px;
    z-index: 1;
}

/* watermark globe in header */
.visa-header-band::after {
    content: '⬡';
    position: absolute;
    right: -20px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 220px;
    color: rgba(255,255,255,.03);
    line-height: 1;
    pointer-events: none;
    font-family: var(--font-mono);
}

.visa-header-left {
    display: flex;
    align-items: center;
    gap: 20px;
    flex: 1;
    padding: 20px 0;
}

.visa-emblem {
    width: 68px; height: 68px;
    border-radius: 50%;
    background: var(--grad);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 0 0 3px rgba(255,255,255,.1), 0 0 0 6px rgba(64,153,255,.2), 0 8px 24px rgba(64,153,255,.4);
    font-size: 28px;
    color: #fff;
    position: relative;
}
.visa-emblem::after {
    content: '';
    position: absolute;
    inset: -4px;
    border-radius: 50%;
    border: 1px dashed rgba(255,255,255,.2);
}

.visa-header-titles {}
.visa-doc-type {
    font-family: var(--font-mono);
    font-size: 10px;
    letter-spacing: .2em;
    color: var(--teal);
    text-transform: uppercase;
    margin-bottom: 4px;
    opacity: .9;
}
.visa-country-name {
    font-family: var(--font-disp);
    font-size: 22px;
    color: #fff;
    line-height: 1.1;
    font-weight: 900;
}
.visa-subtitle {
    font-size: 11px;
    color: rgba(255,255,255,.45);
    letter-spacing: .06em;
    text-transform: uppercase;
    margin-top: 3px;
    font-family: var(--font-mono);
}

.visa-header-right {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 20px 0;
    flex-shrink: 0;
}

/* status stamp */
.visa-stamp {
    width: 80px; height: 80px;
    border-radius: 50%;
    border: 3px solid currentColor;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    font-family: var(--font-mono);
    font-size: 9px;
    font-weight: 600;
    letter-spacing: .08em;
    text-transform: uppercase;
    transform: rotate(-12deg);
    position: relative;
    flex-shrink: 0;
}
.visa-stamp::before {
    content: '';
    position: absolute;
    inset: 4px;
    border-radius: 50%;
    border: 1px solid currentColor;
    opacity: .4;
}
.stamp-approved   { color: #4ade80; border-color: #4ade80; }
.stamp-processing { color: #fcd34d; border-color: #fcd34d; }
.stamp-rejected   { color: #f87171; border-color: #f87171; }
.stamp-pending    { color: #93c5fd; border-color: #93c5fd; }
.stamp-default    { color: rgba(255,255,255,.4); border-color: rgba(255,255,255,.4); }
.visa-stamp-text  { font-size: 11px; font-weight: 700; letter-spacing: .06em; }
.visa-stamp-sub   { font-size: 7.5px; opacity: .7; }

/* visa number top-right */
.visa-number {
    text-align: right;
}
.visa-number-label {
    font-family: var(--font-mono);
    font-size: 9px;
    color: rgba(255,255,255,.4);
    letter-spacing: .12em;
    text-transform: uppercase;
    margin-bottom: 3px;
}
.visa-number-val {
    font-family: var(--font-mono);
    font-size: 18px;
    font-weight: 600;
    background: var(--grad);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    letter-spacing: .08em;
}

/* ── VISA BODY ─────────────────────────────── */
.visa-body {
    padding: 28px 32px 0;
    position: relative;
    z-index: 1;
}

/* ── PHOTO + FIELDS ROW ────────────────────── */
.visa-main-row {
    display: grid;
    grid-template-columns: 110px 1fr 1fr;
    gap: 28px;
    margin-bottom: 24px;
    align-items: start;
}

@media (max-width: 680px) {
    .visa-main-row { grid-template-columns: 1fr 1fr; }
    .visa-photo-col { display: none; }
}

/* photo placeholder */
.visa-photo-col {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
}
.visa-photo {
    width: 100px; height: 120px;
    background: linear-gradient(160deg, #e8edf5, #d0d9ee);
    border-radius: 8px;
    border: 2px solid var(--border);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: var(--text-3);
    font-size: 11px;
    gap: 6px;
    position: relative;
    overflow: hidden;
}
.visa-photo::after {
    content: '';
    position: absolute;
    inset: 0;
    background: repeating-linear-gradient(
        45deg, transparent, transparent 4px,
        rgba(64,153,255,.04) 4px, rgba(64,153,255,.04) 5px
    );
}
.visa-photo i { font-size: 32px; opacity: .3; }
.visa-photo-label {
    font-size: 10px;
    color: var(--text-3);
    text-align: center;
    font-family: var(--font-mono);
    letter-spacing: .06em;
}

/* field groups */
.visa-field-group {}
.visa-field-group-title {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .14em;
    text-transform: uppercase;
    color: var(--blue);
    margin-bottom: 12px;
    padding-bottom: 6px;
    border-bottom: 1.5px solid rgba(64,153,255,.15);
    display: flex;
    align-items: center;
    gap: 7px;
}
.visa-field-group-title::before {
    content: '';
    width: 14px; height: 2px;
    background: var(--grad);
    border-radius: 2px;
    display: block;
}

.visa-field {
    margin-bottom: 14px;
}
.visa-field-label {
    font-family: var(--font-mono);
    font-size: 9px;
    letter-spacing: .16em;
    text-transform: uppercase;
    color: var(--text-3);
    margin-bottom: 3px;
}
.visa-field-value {
    font-size: 13.5px;
    font-weight: 600;
    color: var(--text-1);
    line-height: 1.3;
}
.visa-field-value.mono { font-family: var(--font-mono); font-size: 13px; letter-spacing: .04em; }
.visa-field-value .nil { color: var(--text-3); font-weight: 400; font-style: italic; font-size: 12px; }

/* ── FINANCIAL ROW ─────────────────────────── */
.visa-financial-band {
    background: linear-gradient(135deg, var(--navy) 0%, var(--navy2) 100%);
    margin: 0 -32px;
    padding: 20px 32px;
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1px;
    position: relative;
    overflow: hidden;
}
.visa-financial-band::before {
    content: '';
    position: absolute;
    inset: 0;
    background: repeating-linear-gradient(
        90deg,
        transparent, transparent 1fr,
        rgba(255,255,255,.04) 1fr, rgba(255,255,255,.04) calc(1fr + 1px)
    );
}
@media (max-width: 600px) { .visa-financial-band { grid-template-columns: 1fr; } }

.visa-fin-item {
    padding: 12px 20px;
    border-right: 1px solid rgba(255,255,255,.08);
    position: relative;
    z-index: 1;
}
.visa-fin-item:last-child { border-right: none; }
.visa-fin-label {
    font-family: var(--font-mono);
    font-size: 9px;
    letter-spacing: .16em;
    text-transform: uppercase;
    color: rgba(255,255,255,.4);
    margin-bottom: 6px;
}
.visa-fin-value {
    font-family: var(--font-mono);
    font-size: 20px;
    font-weight: 600;
    color: #fff;
}
.visa-fin-value.profit-pos { background: linear-gradient(90deg, var(--teal), #34d399); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
.visa-fin-value.profit-neg { color: #f87171; }
.visa-fin-currency {
    font-size: 11px;
    color: rgba(255,255,255,.4);
    margin-top: 3px;
    font-family: var(--font-mono);
    letter-spacing: .08em;
}

/* ── PARTY ROW ─────────────────────────────── */
.visa-parties {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    padding: 24px 0;
}
@media (max-width: 600px) { .visa-parties { grid-template-columns: 1fr; } }

.visa-party-card {
    background: linear-gradient(135deg, #f7f9fd, #eef2fb);
    border-radius: 12px;
    border: 1.5px solid var(--border);
    padding: 16px 18px;
    position: relative;
    overflow: hidden;
}
.visa-party-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: var(--grad);
    border-radius: 12px 12px 0 0;
}
.visa-party-role {
    font-family: var(--font-mono);
    font-size: 9px;
    letter-spacing: .2em;
    text-transform: uppercase;
    color: var(--blue);
    margin-bottom: 10px;
}
.visa-party-name-big {
    font-size: 15px;
    font-weight: 700;
    color: var(--text-1);
    margin-bottom: 6px;
}
.visa-party-meta {
    font-size: 12px;
    color: var(--text-3);
    display: flex;
    flex-direction: column;
    gap: 3px;
}
.visa-party-meta span { display: flex; align-items: center; gap: 6px; }
.visa-party-link {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin-top: 12px;
    padding: 6px 14px;
    border-radius: 50px;
    background: var(--surface);
    border: 1.5px solid rgba(64,153,255,.25);
    color: var(--blue);
    font-size: 11.5px;
    font-weight: 600;
    text-decoration: none;
    transition: all .2s;
}
.visa-party-link:hover { background: var(--blue); color: #fff; border-color: var(--blue); text-decoration: none; }
.visa-party-none { color: var(--text-3); font-size: 12.5px; font-style: italic; }

/* ── REMARKS ───────────────────────────────── */
.visa-remarks-section {
    padding: 0 0 24px;
}
.visa-remarks-box {
    background: rgba(64,153,255,.04);
    border: 1px solid rgba(64,153,255,.12);
    border-radius: 10px;
    padding: 14px 18px;
    font-size: 13px;
    color: var(--text-2);
    line-height: 1.7;
}

/* ── MRZ STRIP ─────────────────────────────── */
.visa-mrz {
    background: #f0f3f9;
    border-top: 1.5px solid var(--border);
    padding: 14px 32px;
    font-family: var(--font-mono);
    font-size: 11px;
    color: var(--text-3);
    letter-spacing: .12em;
    line-height: 1.8;
    position: relative;
    z-index: 1;
    overflow: hidden;
}
.visa-mrz::before {
    content: 'MACHINE READABLE ZONE';
    display: block;
    font-size: 8px;
    letter-spacing: .25em;
    color: var(--text-3);
    margin-bottom: 6px;
    opacity: .6;
}
.mrz-line {
    display: block;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: clip;
    color: var(--navy);
    opacity: .55;
    font-size: 10.5px;
}

/* hologram shimmer overlay in bottom-right */
.visa-holo {
    position: absolute;
    bottom: 12px; right: 24px;
    width: 56px; height: 56px;
    border-radius: 50%;
    background:
        conic-gradient(
            from 0deg,
            rgba(64,153,255,.7),
            rgba(46,216,182,.7),
            rgba(200,168,75,.5),
            rgba(64,153,255,.7)
        );
    opacity: .25;
    filter: blur(1px);
    animation: holoSpin 6s linear infinite;
}
@keyframes holoSpin { to { transform: rotate(360deg); } }

/* ── LEDGER / TRANSACTIONS ─────────────────── */
.vp-ledger {
    background: var(--surface);
    border-radius: 20px;
    border: 1.5px solid var(--border);
    box-shadow: 0 4px 20px rgba(13,31,60,.07);
    overflow: hidden;
    margin-bottom: 28px;
}

.vp-ledger-header {
    background: var(--navy);
    background-image: radial-gradient(ellipse at 20% 50%, rgba(64,153,255,.15) 0%, transparent 60%);
    padding: 16px 28px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
}
.vp-ledger-title {
    font-family: var(--font-mono);
    font-size: 11px;
    letter-spacing: .2em;
    text-transform: uppercase;
    color: rgba(255,255,255,.9);
    display: flex;
    align-items: center;
    gap: 10px;
}
.vp-ledger-title::before {
    content: '';
    width: 20px; height: 2px;
    background: var(--grad);
    display: block;
    border-radius: 2px;
}

.vp-filter-pills {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}
.vp-pill {
    padding: 5px 14px;
    border-radius: 50px;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    border: 1.5px solid rgba(255,255,255,.15);
    background: transparent;
    color: rgba(255,255,255,.55);
    transition: all .2s;
    font-family: var(--font-body);
    display: flex;
    align-items: center;
    gap: 5px;
}
.vp-pill:hover { border-color: rgba(255,255,255,.4); color: rgba(255,255,255,.85); }
.vp-pill.active {
    background: var(--grad);
    border-color: transparent;
    color: #fff;
    box-shadow: 0 3px 12px rgba(64,153,255,.4);
}
.vp-pill-ct {
    background: rgba(255,255,255,.15);
    border-radius: 50px;
    padding: 1px 7px;
    font-size: 10px;
}
.vp-pill.active .vp-pill-ct { background: rgba(255,255,255,.25); }

.vp-ledger-body { padding: 20px 28px 24px; }

/* ledger table */
.vp-ltable {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 5px;
}
.vp-ltable thead th {
    font-family: var(--font-mono);
    font-size: 9px;
    letter-spacing: .15em;
    text-transform: uppercase;
    color: var(--text-3);
    padding: 4px 12px;
    font-weight: 600;
    background: transparent;
    border: none;
}
.vp-ltable tbody tr {
    background: #f7f9fd;
    transition: background .15s;
}
.vp-ltable tbody tr:hover { background: rgba(64,153,255,.06); }
.vp-ltable tbody td {
    padding: 11px 12px;
    border: none;
    font-size: 13px;
    color: var(--text-2);
    vertical-align: middle;
}
.vp-ltable tbody td:first-child { border-radius: 8px 0 0 8px; }
.vp-ltable tbody td:last-child  { border-radius: 0 8px 8px 0; }

.lt-date { font-family: var(--font-mono); font-size: 11.5px; color: var(--text-3); }

.lt-party {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 10px; border-radius: 50px;
    font-size: 11px; font-weight: 600;
}
.lt-party-client   { background: rgba(64,153,255,.1); color: #2563eb; }
.lt-party-supplier { background: rgba(245,158,11,.1); color: #b45309; }
.lt-party-main     { background: rgba(99,102,241,.1); color: #4f46e5; }

.lt-type {
    display: inline-flex; align-items: center;
    padding: 3px 10px; border-radius: 50px;
    font-size: 11px; font-weight: 600;
}
.lt-credit { background: rgba(16,185,129,.1); color: #059669; }
.lt-debit  { background: rgba(239,68,68,.1);  color: #dc2626; }

.lt-amt { font-family: var(--font-mono); font-size: 13px; font-weight: 600; }
.lt-pos  { color: var(--green); }
.lt-neg  { color: var(--red); }

.vp-empty {
    text-align: center;
    padding: 48px 20px;
    color: var(--text-3);
    font-size: 13px;
}
.vp-empty i { font-size: 36px; display: block; margin-bottom: 12px; opacity: .3; }

/* ── ANIMATIONS ────────────────────────────── */
@keyframes vpFade {
    from { opacity: 0; transform: translateY(16px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* perforated left edge */
.visa-doc-perforated {
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 18px;
    display: flex;
    flex-direction: column;
    justify-content: space-around;
    align-items: center;
    pointer-events: none;
    z-index: 2;
}
.perf-hole {
    width: 10px; height: 10px;
    border-radius: 50%;
    background: var(--bg);
    box-shadow: inset 0 1px 2px rgba(0,0,0,.12);
}

/* corner bracket accents */
.visa-corner {
    position: absolute;
    width: 22px; height: 22px;
    border-color: rgba(200,168,75,.35);
    border-style: solid;
    z-index: 2;
}
.visa-corner-tl { top: 10px; left: 24px; border-width: 2px 0 0 2px; }
.visa-corner-tr { top: 10px; right: 10px; border-width: 2px 2px 0 0; }
.visa-corner-bl { bottom: 10px; left: 24px; border-width: 0 0 2px 2px; }
.visa-corner-br { bottom: 10px; right: 10px; border-width: 0 2px 2px 0; }
</style>

<div class="pcoded-main-container">
  <div class="pcoded-wrapper">
    <div class="pcoded-content">
      <div class="pcoded-inner-content">
        <div class="main-body">
          <div class="page-wrapper">
          <div class="vp-wrap">

            <?php if ($error): ?>
              <div class="alert alert-danger"><?= h($error) ?></div>
              <a href="visa.php" class="vp-back">← <?= __('back_to_visa') ?></a>
            <?php else: ?>

            <!-- TOPBAR -->
            <div class="vp-topbar">
              <a href="visa.php" class="vp-back">
                <i class="feather icon-arrow-left"></i> <?= __('back_to_visa') ?>
              </a>
            </div>

            <!-- ══════════════════════════════════
                 THE VISA DOCUMENT
            ══════════════════════════════════ -->
            <div class="visa-doc">

              <!-- perforated edge -->
              <div class="visa-doc-perforated">
                <?php for($i=0;$i<14;$i++): ?><div class="perf-hole"></div><?php endfor; ?>
              </div>

              <!-- corner brackets -->
              <div class="visa-corner visa-corner-tl"></div>
              <div class="visa-corner visa-corner-tr"></div>
              <div class="visa-corner visa-corner-bl"></div>
              <div class="visa-corner visa-corner-br"></div>

              <!-- hologram spot -->
              <div class="visa-holo"></div>

              <!-- ── HEADER BAND ── -->
              <div class="visa-header-band">
                <div class="visa-header-left">
                  <div class="visa-emblem">
                    <i class="feather icon-globe"></i>
                  </div>
                  <div class="visa-header-titles">
                    <div class="visa-doc-type">Travel Document · Visa</div>
                    <div class="visa-country-name">
                      <?= !empty($visaData['country']) ? h($visaData['country']) : __('visa_application') ?>
                    </div>
                    <div class="visa-subtitle"><?= h($visaData['visa_type'] ?? __('general_visa')) ?></div>
                  </div>
                </div>
                <div class="visa-header-right">
                  <div class="visa-number">
                    <div class="visa-number-label"><?= __('application_no') ?></div>
                    <div class="visa-number-val"><?= str_pad($visaId, 8, '0', STR_PAD_LEFT) ?></div>
                  </div>
                  <?php
                    $stampClass = match(strtolower($s)) {
                      'approved'   => 'stamp-approved',
                      'processing' => 'stamp-processing',
                      'rejected'   => 'stamp-rejected',
                      'pending'    => 'stamp-pending',
                      default      => 'stamp-default'
                    };
                  ?>
                  <div class="visa-stamp <?= $stampClass ?>">
                    <span class="visa-stamp-text"><?= h($s ?: 'N/A') ?></span>
                    <span class="visa-stamp-sub">STATUS</span>
                  </div>
                </div>
              </div>

              <!-- ── BODY ── -->
              <div class="visa-body">

                <!-- Main applicant + dates row -->
                <div class="visa-main-row">

                  <!-- Photo placeholder -->
                  <div class="visa-photo-col">
                    <div class="visa-photo">
                      <i class="feather icon-user"></i>
                    </div>
                    <div class="visa-photo-label">PHOTO</div>
                  </div>

                  <!-- Applicant fields -->
                  <div class="visa-field-group">
                    <div class="visa-field-group-title"><?= __('applicant_details') ?></div>

                    <div class="visa-field">
                      <div class="visa-field-label"><?= __('surname_given_names') ?></div>
                      <div class="visa-field-value"><?= !empty($visaData['applicant_name']) ? h($visaData['applicant_name']) : '<span class="nil">—</span>' ?></div>
                    </div>
                    <div class="visa-field">
                      <div class="visa-field-label"><?= __('passport_number') ?></div>
                      <div class="visa-field-value mono"><?= !empty($visaData['passport_number']) ? h($visaData['passport_number']) : '<span class="nil">—</span>' ?></div>
                    </div>
                    <div class="visa-field">
                      <div class="visa-field-label"><?= __('phone') ?></div>
                      <div class="visa-field-value"><?= !empty($visaData['phone']) ? h($visaData['phone']) : '<span class="nil">—</span>' ?></div>
                    </div>
                  </div>

                  <!-- Dates + type -->
                  <div class="visa-field-group">
                    <div class="visa-field-group-title"><?= __('visa_details') ?></div>

                    <div class="visa-field">
                      <div class="visa-field-label"><?= __('visa_type') ?></div>
                      <div class="visa-field-value"><?= !empty($visaData['visa_type']) ? h($visaData['visa_type']) : '<span class="nil">—</span>' ?></div>
                    </div>
                    <div class="visa-field">
                      <div class="visa-field-label"><?= __('date_of_application') ?></div>
                      <div class="visa-field-value mono">
                        <?= !empty($visaData['applied_date']) ? date('d MMM Y', strtotime($visaData['applied_date'])) : '<span class="nil">' . __('not_available') . '</span>' ?>
                      </div>
                    </div>
                    <div class="visa-field">
                      <div class="visa-field-label"><?= __('date_of_issue') ?></div>
                      <div class="visa-field-value mono">
                        <?= !empty($visaData['issued_date']) ? date('d M Y', strtotime($visaData['issued_date'])) : '<span class="nil">' . __('not_issued_yet') . '</span>' ?>
                      </div>
                    </div>
                    <div class="visa-field">
                      <div class="visa-field-label"><?= __('created_at') ?></div>
                      <div class="visa-field-value mono" style="font-size:12px;">
                        <?= isset($visaData['created_at']) ? date('d M Y · H:i', strtotime($visaData['created_at'])) : '—' ?>
                      </div>
                    </div>
                  </div>

                </div><!-- end visa-main-row -->

                <!-- ── FINANCIAL DARK BAND ── -->
                <div class="visa-financial-band">
                  <div class="visa-fin-item">
                    <div class="visa-fin-label"><?= __('sold_amount') ?></div>
                    <div class="visa-fin-value">
                      <?= isset($visaData['sold']) ? h($visaData['sold']) : '—' ?>
                    </div>
                    <div class="visa-fin-currency"><?= isset($visaData['currency']) ? h($visaData['currency']) : '' ?> · <?= __('charged_to_client') ?></div>
                  </div>
                </div>

                <!-- ── PARTIES ── -->
                <div class="visa-parties">

                  <div class="visa-party-card">
                    <div class="visa-party-role"><i class="feather icon-user" style="font-size:10px;margin-right:4px"></i><?= __('sold_to_client') ?></div>
                    <?php if (!empty($visaData['sold_to'])): ?>
                      <div class="visa-party-name-big"><?= h($visaData['client_name'] ?? '—') ?></div>
                      <div class="visa-party-meta">
                        <?php if (!empty($visaData['client_email'])): ?>
                          <span><i class="feather icon-mail" style="font-size:11px;opacity:.5"></i><?= h($visaData['client_email']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($visaData['client_phone'])): ?>
                          <span><i class="feather icon-phone" style="font-size:11px;opacity:.5"></i><?= h($visaData['client_phone']) ?></span>
                        <?php endif; ?>
                      </div>
                      <a href="client_detail.php?id=<?= h($visaData['sold_to']) ?>" class="visa-party-link">
                        <i class="feather icon-external-link" style="font-size:11px"></i> <?= __('view_profile') ?>
                      </a>
                    <?php else: ?>
                      <div class="visa-party-none"><?= __('no_client_associated') ?></div>
                    <?php endif; ?>
                  </div>

                </div>

                <!-- REMARKS -->
                <?php if (!empty($visaData['remarks'])): ?>
                <div class="visa-remarks-section">
                  <div class="visa-field-group-title" style="margin-bottom:10px;"><?= __('remarks') ?></div>
                  <div class="visa-remarks-box"><?= nl2br(htmlspecialchars($visaData['remarks'])) ?></div>
                </div>
                <?php endif; ?>

              </div><!-- end visa-body -->

              <!-- ── MRZ STRIP ── -->
              <?php
                $applicantUpper = strtoupper(str_replace(' ', '<', $visaData['applicant_name'] ?? 'UNKNOWN'));
                $passportPad    = str_pad(strtoupper($visaData['passport_number'] ?? ''), 9, '<');
                $countryCode    = strtoupper(substr($visaData['country'] ?? 'XXX', 0, 3));
                $visaNum        = str_pad($visaId, 9, '0', STR_PAD_LEFT);
                $mrzLine1       = 'V<' . $countryCode . str_pad($applicantUpper, 39, '<');
                $mrzLine2       = $visaNum . '0' . $countryCode . '0000000' . 'M' . '9912310' . str_pad('', 14, '<') . '0';
              ?>
              <div class="visa-mrz">
                <span class="mrz-line"><?= h(substr($mrzLine1, 0, 44)) ?></span>
                <span class="mrz-line"><?= h(substr($mrzLine2, 0, 44)) ?></span>
              </div>

            </div><!-- end visa-doc -->


            <!-- ══════════════════════════════════
                 TRANSACTION LEDGER
            ══════════════════════════════════ -->
            <?php
              $cCount = count($clientTransactions);
              $aCount = count($allTx);
            ?>
            <div class="vp-ledger">
              <div class="vp-ledger-header">
                <div class="vp-ledger-title"><?= __('transaction_ledger') ?></div>
                <div class="vp-filter-pills">
                  <button class="vp-pill active" data-filter="all">
                    <?= __('all') ?> <span class="vp-pill-ct"><?= $aCount ?></span>
                  </button>
                  <button class="vp-pill" data-filter="client">
                    <?= __('client') ?> <span class="vp-pill-ct"><?= $cCount ?></span>
                  </button>
                </div>
              </div>

              <div class="vp-ledger-body">
                <?php if ($aCount > 0): ?>
                <div class="table-responsive">
                  <table class="vp-ltable" id="ltTable">
                    <thead>
                      <tr>
                        <th><?= __('date') ?></th>
                        <th><?= __('party') ?></th>
                        <th><?= __('type') ?></th>
                        <th><?= __('amount') ?></th>
                        <th><?= __('description') ?></th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($allTx as $tx):
                        $party     = $tx['transaction_type'] ?? '';
                        $pf        = match($party) { 'Client'=>'client',default=>'client' };
                        $type      = strtolower($tx['type'] ?? '');
                        $isDebit   = ($type === 'debit');
                        $pbadge    = match($party) { 'Client'=>'lt-party-client',default=>'' };
                      ?>
                      <tr data-party="<?= $pf ?>">
                        <td class="lt-date"><?= date('d M Y', strtotime($tx['transaction_date'])) ?></td>
                        <td><span class="lt-party <?= $pbadge ?>"><?= h($party) ?></span></td>
                        <td><span class="lt-type <?= $isDebit ? 'lt-debit' : 'lt-credit' ?>"><?= ucfirst($type ?: '—') ?></span></td>
                        <td>
                          <span class="lt-amt <?= $isDebit ? 'lt-neg' : 'lt-pos' ?>">
                            <?= (isset($tx['currency']) && isset($tx['amount'])) ? h($tx['currency']).' '.h($tx['amount']) : '—' ?>
                          </span>
                        </td>
                        <td style="font-size:12.5px;color:var(--text-2);"><?= h($tx['description'] ?? '—') ?></td>
                      </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
                <?php else: ?>
                <div class="vp-empty">
                  <i class="feather icon-inbox"></i>
                  <?= __('no_transactions_found_for_this_application') ?>
                </div>
                <?php endif; ?>
              </div>
            </div>

            <?php endif; // end !$error ?>

          </div><!-- vp-wrap -->
          </div><!-- page-wrapper -->
        </div><!-- main-body -->
      </div>
    </div>
  </div>
</div>

<?php include '../includes/admin_footer.php'; ?>
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script>
(function () {
  const pills = document.querySelectorAll('.vp-pill');
  const rows  = document.querySelectorAll('#ltTable tbody tr');
  pills.forEach(pill => {
    pill.addEventListener('click', () => {
      pills.forEach(p => p.classList.remove('active'));
      pill.classList.add('active');
      const f = pill.dataset.filter;
      rows.forEach(row => {
        row.style.display = (f === 'all' || row.dataset.party === f) ? '' : 'none';
      });
    });
  });
})();
</script>