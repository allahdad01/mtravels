<?php
// Include database security module for input validation
require_once '../../admin/includes/db_security.php';

// Include security module
require_once '../../admin/security.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Enforce authentication
enforce_auth();

include '../../includes/db.php';

// Define helper function for HTML escaping
if (!function_exists('h')) {
    function h(string $string): string {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}

// Validate invoiceData
$invoiceData = isset($_POST['invoiceData']) ? DbSecurity::validateInput($_POST['invoiceData'], 'string', ['maxlength' => 255]) : null;

// Check if the user is logged in
if (!isset($_SESSION['name'])) {
    die('You must be logged in to access this resource');
}

// Check if invoice data is provided
if (!isset($_POST['invoiceData'])) {
    die('No invoice data provided');
}

// Decode the JSON data
$invoiceData = json_decode($_POST['invoiceData'], true);

// Validate the data
if (!isset($invoiceData['tickets']) || !is_array($invoiceData['tickets']) || count($invoiceData['tickets']) === 0) {
    die('No tickets selected for invoice');
}

// Fetch settings data
try {
    $settingStmt = $pdo->prepare("SELECT * FROM settings WHERE tenant_id = ?");
    $settingStmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
    $settingStmt->execute();
    $settings = $settingStmt->fetch(PDO::FETCH_ASSOC);
    if (!$settings) {
        $settings = ['agency_name' => 'Travel Agency'];
    }
} catch (Exception $e) {
    $settings = ['agency_name' => 'Travel Agency'];
}

// Fetch branch data
try {
    $branchStmt = $pdo->prepare("SELECT name, code, phone, address, email FROM branches WHERE id = ? AND tenant_id = ?");
    $branchStmt->bindParam(1, $branch_id, PDO::PARAM_INT);
    $branchStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $branchStmt->execute();
    $branch = $branchStmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branch = null;
}

// Fetch tickets
$ticketIds = array_map('intval', $invoiceData['tickets'] ?? []);
$tickets = [];
$totalAmount = 0;

if (!empty($ticketIds)) {
    $placeholders = implode(',', array_fill(0, count($ticketIds), '?'));

    $ticketsQuery = "
        SELECT tb.id, tb.passenger_name, tb.pnr, tb.origin, tb.destination,
               tb.airline, tb.departure_date, tb.sold, tb.trip_type, tb.return_destination,
               tb.return_date, tb.currency, c.name as client_name
        FROM ticket_bookings tb
        JOIN clients c ON c.id = tb.sold_to
        WHERE tb.id IN ($placeholders) AND tb.tenant_id = ? AND tb.branch_id = ?
        ORDER BY tb.id
    ";

    $stmt = $pdo->prepare($ticketsQuery);
    $stmt->execute([...$ticketIds, $tenant_id, $branch_id]);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($tickets as $row) {
        $totalAmount += floatval($row['sold']);
    }
}

// Fetch paid amounts
$paidQuery = "SELECT reference_id, currency, SUM(amount) as paid FROM main_account_transactions WHERE transaction_of = 'ticket_sale' AND reference_id IN ($placeholders) AND type = 'credit' AND tenant_id = ? AND branch_id = ? GROUP BY reference_id, currency";
$stmt = $pdo->prepare($paidQuery);
$stmt->execute([...$ticketIds, $tenant_id, $branch_id]);
$paidAmounts = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $paidAmounts[$row['reference_id']][$row['currency']] = $row['paid'];
}

$totalPaid = [];
foreach ($paidAmounts as $ticketPaid) {
    foreach ($ticketPaid as $curr => $amt) {
        $totalPaid[$curr] = ($totalPaid[$curr] ?? 0) + $amt;
    }
}

$currency  = $invoiceData['currency'];
$comments  = $invoiceData['comment'];
$clientName = $invoiceData['clientName'];

$invoiceNumber = 'INV-' . time() . '-' . rand(1000, 9999);
$invoiceDate   = date('Y-m-d');

// Fetch bank accounts
try {
    $bankAccountsQuery = "SELECT name, bank_name, bank_account_number, bank_account_afs_number FROM main_account WHERE tenant_id = ? AND branch_id = ? AND status = 'active' AND account_type = 'bank' AND bank_account_number IS NOT NULL AND bank_account_number <> '' ORDER BY name";
    $stmt = $pdo->prepare($bankAccountsQuery);
    $stmt->execute([$tenant_id, $branch_id]);
    $bankAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $bankAccounts = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?php echo h($invoiceNumber); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --navy:      #0f2340;
            --navy-mid:  #1a3560;
            --accent:    #c8a84b;
            --accent-lt: #f5edd8;
            --gray-100:  #f7f8fa;
            --gray-200:  #eef0f4;
            --gray-400:  #9aa3b2;
            --gray-600:  #5c6478;
            --gray-800:  #2d3344;
            --white:     #ffffff;
            --text:      #1e2533;
            --radius:    6px;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--gray-200);
            color: var(--text);
            padding: 40px 20px;
            font-size: 13px;
            line-height: 1.6;
        }

        /* ── Print button ── */
        .print-bar {
            max-width: 860px;
            margin: 0 auto 16px;
            display: flex;
            justify-content: flex-end;
        }
        .print-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--navy);
            color: var(--white);
            border: none;
            border-radius: var(--radius);
            padding: 10px 22px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            letter-spacing: 0.03em;
            transition: background 0.18s;
        }
        .print-btn:hover { background: var(--navy-mid); }
        .print-btn svg { flex-shrink: 0; }

        /* ── Invoice card ── */
        .invoice {
            max-width: 860px;
            margin: 0 auto;
            background: var(--white);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 32px rgba(15,35,64,.10);
        }

        /* ── Header band ── */
        .inv-header {
            background: var(--navy);
            padding: 32px 40px;
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            gap: 20px;
        }
        .inv-brand { }
        .inv-brand .company-name {
            font-size: 22px;
            font-weight: 700;
            color: var(--white);
            letter-spacing: -0.01em;
        }
        .inv-brand .tagline {
            font-size: 11px;
            font-weight: 400;
            color: var(--accent);
            letter-spacing: 0.12em;
            text-transform: uppercase;
            margin-top: 3px;
        }
        .inv-logo-wrap {
            text-align: center;
        }
        .inv-logo-wrap img {
            max-height: 70px;
            max-width: 160px;
            object-fit: contain;
        }
        .inv-meta {
            text-align: right;
        }
        .inv-label {
            font-size: 10px;
            font-weight: 600;
            color: var(--accent);
            letter-spacing: 0.14em;
            text-transform: uppercase;
            margin-bottom: 2px;
        }
        .inv-number {
            font-family: 'DM Mono', monospace;
            font-size: 15px;
            font-weight: 500;
            color: var(--white);
        }
        .inv-date {
            font-size: 12px;
            color: rgba(255,255,255,0.6);
            margin-top: 6px;
        }

        /* ── Accent stripe ── */
        .accent-stripe {
            height: 4px;
            background: linear-gradient(90deg, var(--accent) 0%, #e8c56a 50%, var(--accent) 100%);
        }

        /* ── Body ── */
        .inv-body {
            padding: 36px 40px;
        }

        /* ── Addresses ── */
        .addresses {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 36px;
            padding-bottom: 28px;
            border-bottom: 1.5px solid var(--gray-200);
        }
        .addr-block { }
        .addr-label {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--accent);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .addr-label::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--gray-200);
        }
        .addr-name {
            font-size: 14px;
            font-weight: 600;
            color: var(--navy);
            margin-bottom: 4px;
        }
        .addr-line {
            font-size: 12px;
            color: var(--gray-600);
            line-height: 1.7;
        }

        /* ── Section title ── */
        .section-title {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: var(--navy);
            margin-bottom: 14px;
        }

        /* ── Tickets table ── */
        .ticket-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 8px;
            font-size: 12.5px;
        }
        .ticket-table thead tr {
            background: var(--navy);
        }
        .ticket-table thead th {
            padding: 11px 14px;
            text-align: left;
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: rgba(255,255,255,0.75);
            white-space: nowrap;
        }
        .ticket-table thead th:first-child { border-radius: var(--radius) 0 0 0; }
        .ticket-table thead th:last-child  { border-radius: 0 var(--radius) 0 0; text-align: right; }
        .ticket-table thead th.r { text-align: right; }

        .ticket-table tbody tr {
            transition: background 0.12s;
        }
        .ticket-table tbody tr:nth-child(even) { background: var(--gray-100); }
        .ticket-table tbody tr:hover { background: var(--accent-lt); }
        .ticket-table tbody td {
            padding: 13px 14px;
            border-bottom: 1px solid var(--gray-200);
            vertical-align: top;
            color: var(--gray-800);
        }
        .ticket-table tbody td.r { text-align: right; }

        .td-index {
            font-family: 'DM Mono', monospace;
            font-size: 11px;
            color: var(--gray-400);
            font-weight: 500;
            width: 32px;
        }
        .td-name { font-weight: 600; color: var(--navy); }
        .td-pnr {
            font-family: 'DM Mono', monospace;
            font-size: 11.5px;
            background: var(--gray-200);
            display: inline-block;
            padding: 2px 7px;
            border-radius: 3px;
            letter-spacing: 0.05em;
        }
        .sector-main { font-weight: 500; color: var(--navy); }
        .sector-sub {
            font-size: 11px;
            color: var(--gray-400);
            margin-top: 3px;
        }
        .sector-sub span { display: block; }
        .airline-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 11.5px;
            font-weight: 500;
        }
        .amount-cell { font-family: 'DM Mono', monospace; font-weight: 500; }
        .paid-cell   { font-family: 'DM Mono', monospace; font-size: 11.5px; color: var(--gray-600); }

        /* ── Totals row ── */
        .totals-row td {
            background: var(--navy) !important;
            color: var(--white) !important;
            font-weight: 600;
            padding: 14px 14px !important;
            border-bottom: none !important;
        }
        .totals-row td:first-child { border-radius: 0 0 0 var(--radius); }
        .totals-row td:last-child  { border-radius: 0 0 var(--radius) 0; text-align: right; }
        .totals-row .r { text-align: right; }
        .totals-label {
            font-size: 10px;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: rgba(255,255,255,0.55);
        }
        .totals-amount {
            font-family: 'DM Mono', monospace;
            font-size: 16px;
            color: var(--accent);
        }
        .totals-paid {
            font-family: 'DM Mono', monospace;
            font-size: 13px;
        }

        /* ── Comments ── */
        .comments-box {
            margin-top: 28px;
            background: var(--gray-100);
            border-left: 3px solid var(--accent);
            border-radius: 0 var(--radius) var(--radius) 0;
            padding: 16px 20px;
        }
        .comments-box .section-title { margin-bottom: 8px; }
        .comments-text { font-size: 12.5px; color: var(--gray-600); }

        /* ── Footer ── */
        .inv-footer {
            margin-top: 40px;
            padding-top: 28px;
            border-top: 1.5px solid var(--gray-200);
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 32px;
        }

        /* Bank details */
        .bank-section .section-title { margin-bottom: 14px; }
        .bank-card {
            background: var(--gray-100);
            border-radius: var(--radius);
            padding: 14px 16px;
            margin-bottom: 10px;
        }
        .bank-name {
            font-size: 12px;
            font-weight: 700;
            color: var(--navy);
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .bank-name::before {
            content: '';
            display: inline-block;
            width: 8px; height: 8px;
            background: var(--accent);
            border-radius: 50%;
        }
        .bank-row {
            display: flex;
            justify-content: space-between;
            font-size: 11.5px;
            padding: 3px 0;
            color: var(--gray-600);
            border-bottom: 1px dashed var(--gray-200);
        }
        .bank-row:last-child { border-bottom: none; }
        .bank-row strong {
            font-family: 'DM Mono', monospace;
            font-weight: 500;
            color: var(--gray-800);
        }

        /* Signature section */
        .sig-section { }
        .sig-boxes {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-top: 14px;
        }
        .sig-box {
            border: 1.5px dashed var(--gray-200);
            border-radius: var(--radius);
            padding: 12px 14px;
            min-height: 90px;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
        }
        .sig-box-label {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--gray-400);
            margin-top: 12px;
            padding-top: 10px;
            border-top: 1px solid var(--gray-200);
        }
        .validity-note {
            text-align: center;
            margin-top: 20px;
            font-size: 10.5px;
            color: var(--gray-400);
            font-style: italic;
            padding: 10px;
            background: var(--gray-100);
            border-radius: var(--radius);
        }

        /* ── Bottom band ── */
        .inv-bottom {
            background: var(--navy);
            padding: 14px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .inv-bottom-left {
            font-size: 11px;
            color: rgba(255,255,255,0.4);
        }
        .inv-bottom-right {
            font-size: 11px;
            color: var(--accent);
            font-weight: 600;
            letter-spacing: 0.06em;
        }

        /* ── Print styles ── */
        @media print {
            body { background: #fff; padding: 0; }
            .invoice { box-shadow: none; border-radius: 0; }
            .print-bar { display: none; }
            .ticket-table tbody tr:hover { background: inherit; }
        }
    </style>
</head>
<body>

    <div class="print-bar">
        <button class="print-btn" onclick="window.print()">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                <rect x="6" y="14" width="12" height="8"/>
            </svg>
            Print Invoice
        </button>
    </div>

    <div class="invoice">

        <!-- HEADER -->
        <div class="inv-header">
            <div class="inv-brand">
                <div class="company-name"><?php echo h($settings['title']); ?></div>
                <div class="tagline">Professional Travel Services</div>
            </div>

            <div class="inv-logo-wrap">
                <?php if (!empty($settings['logo'])): ?>
                <img src="<?php echo h('../../uploads/logo/' . $settings['logo']); ?>" alt="Company Logo">
                <?php endif; ?>
            </div>

            <div class="inv-meta">
                <div class="inv-label">Invoice</div>
                <div class="inv-number"><?php echo h($invoiceNumber); ?></div>
                <div class="inv-date">Issued: <?php echo date('d F Y', strtotime($invoiceDate)); ?></div>
            </div>
        </div>

        <div class="accent-stripe"></div>

        <!-- BODY -->
        <div class="inv-body">

            <!-- Addresses -->
            <div class="addresses">
                <div class="addr-block">
                    <div class="addr-label">From</div>
                    <div class="addr-name"><?php echo h($settings['title']); ?></div>
                    <?php foreach (explode(',', $branch['address'] ?? '') as $line): ?>
                        <div class="addr-line"><?php echo h(trim($line)); ?></div>
                    <?php endforeach; ?>
                    <?php if (!empty($branch['phone'])): ?>
                        <div class="addr-line">&#9990; <?php echo h($branch['phone']); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($branch['email'])): ?>
                        <div class="addr-line">&#9993; <?php echo h($branch['email']); ?></div>
                    <?php endif; ?>
                </div>

                <div class="addr-block">
                    <div class="addr-label">Bill To</div>
                    <div class="addr-name"><?php echo h($clientName); ?></div>
                    <div class="addr-line">&nbsp;</div>
                </div>
            </div>

            <!-- Tickets -->
            <div class="section-title">Ticket Details</div>
            <table class="ticket-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Passenger</th>
                        <th>PNR</th>
                        <th>Client</th>
                        <th>Sector / Dates</th>
                        <th>Airline</th>
                        <th class="r">Amount (<?php echo h($currency); ?>)</th>
                        <th class="r">Paid</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $index => $ticket): ?>
                    <tr>
                        <td class="td-index"><?php echo str_pad($index + 1, 2, '0', STR_PAD_LEFT); ?></td>
                        <td class="td-name"><?php echo h($ticket['passenger_name']); ?></td>
                        <td><span class="td-pnr"><?php echo h($ticket['pnr']); ?></span></td>
                        <td><?php echo h($ticket['client_name']); ?></td>
                        <td>
                            <div class="sector-main">
                                <?php echo h($ticket['origin']); ?> &rarr; <?php echo h($ticket['destination']); ?>
                            </div>
                            <div class="sector-sub">
                                <span>&#9992; Dep: <?php echo date('d M Y', strtotime($ticket['departure_date'])); ?></span>
                                <?php if ($ticket['trip_type'] === 'round_trip' && $ticket['return_destination']): ?>
                                <span>&#8617; Ret: <?php echo h($ticket['return_destination']); ?>
                                    <?php if ($ticket['return_date']): ?>
                                        &mdash; <?php echo date('d M Y', strtotime($ticket['return_date'])); ?>
                                    <?php endif; ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <span class="airline-badge">
                                &#9992; <?php echo h($ticket['airline']); ?>
                            </span>
                        </td>
                        <td class="r amount-cell"><?php echo number_format($ticket['sold'], 2); ?></td>
                        <td class="r paid-cell">
                            <?php
                            $paidStr = '';
                            if (isset($paidAmounts[$ticket['id']])) {
                                ksort($paidAmounts[$ticket['id']]);
                                foreach ($paidAmounts[$ticket['id']] as $curr => $amt) {
                                    $paidStr .= number_format($amt, 2) . ' ' . $curr . '<br>';
                                }
                            }
                            echo $paidStr ?: '<span style="color:var(--gray-400)">—</span>';
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>

                    <!-- Totals -->
                    <tr class="totals-row">
                        <td colspan="5"></td>
                        <td style="text-align:right">
                            <div class="totals-label">Total</div>
                        </td>
                        <td class="r">
                            <div class="totals-amount"><?php echo h($currency); ?> <?php echo number_format($totalAmount, 2); ?></div>
                        </td>
                        <td class="r">
                            <div class="totals-paid">
                                <?php
                                if (!empty($totalPaid)) {
                                    ksort($totalPaid);
                                    foreach ($totalPaid as $curr => $amt) {
                                        echo number_format($amt, 2) . ' ' . h($curr) . '<br>';
                                    }
                                } else {
                                    echo '—';
                                }
                                ?>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Comments -->
            <?php if (!empty($comments)): ?>
            <div class="comments-box">
                <div class="section-title">Notes &amp; Comments</div>
                <div class="comments-text"><?php echo nl2br(h($comments)); ?></div>
            </div>
            <?php endif; ?>

            <!-- Footer: Bank + Signatures -->
            <div class="inv-footer">

                <!-- Bank Accounts -->
                <div class="bank-section">
                    <div class="section-title">Payment / Bank Details</div>
                    <?php if (!empty($bankAccounts)): ?>
                        <?php foreach ($bankAccounts as $bank): ?>
                            <?php
                                $label = !empty($bank['bank_name']) ? $bank['bank_name'] : $bank['name'];
                                $usd   = trim((string)($bank['bank_account_number'] ?? ''));
                                $afs   = trim((string)($bank['bank_account_afs_number'] ?? ''));
                            ?>
                            <div class="bank-card">
                                <div class="bank-name"><?php echo h($label); ?></div>
                                <?php if ($usd !== ''): ?>
                                <div class="bank-row">
                                    <span>USD Account</span>
                                    <strong><?php echo h($usd); ?></strong>
                                </div>
                                <?php endif; ?>
                                <?php if ($afs !== ''): ?>
                                <div class="bank-row">
                                    <span>AFS Account</span>
                                    <strong><?php echo h($afs); ?></strong>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="font-size:12px;color:var(--gray-400)">No bank accounts on file.</p>
                    <?php endif; ?>
                </div>

                <!-- Signatures -->
                <div class="sig-section">
                    <div class="section-title">Authorization</div>
                    <div class="sig-boxes">
                        <div class="sig-box">
                            <div class="sig-box-label">Company Stamp</div>
                        </div>
                        <div class="sig-box">
                            <div class="sig-box-label">Authorized Signature</div>
                        </div>
                    </div>
                    <div class="validity-note">
                        This invoice is not valid without company stamp and authorized signature.
                    </div>
                </div>

            </div>
        </div>

        <!-- BOTTOM BAND -->
        <div class="inv-bottom">
            <div class="inv-bottom-left">Generated <?php echo date('d F Y, H:i'); ?></div>
            <div class="inv-bottom-right"><?php echo h($settings['title']); ?></div>
        </div>

    </div>

</body>
</html>