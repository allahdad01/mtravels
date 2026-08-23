<?php
/**
 * Finance â†’ Admin Cash Settlement ("Handover") API
 *
 * Permissions:
 *   finance.cash_settlement         → summary / create / delete / list (own data)
 *   finance.cash_settlement_approve → confirm / reject + view all data
 *
 * The finance counter is AUTO-derived from `main_account_transactions`
 * per user (`created_by`), per currency:
 *
 *   remaining = (user's credit − user's debit) − confirmed settlements
 *   available = remaining − pending
 *
 * Partial settlements are fully supported — confirming 400 of 1000 leaves
 * 600 visible, and the counter only reaches zero once the full amount has
 * been confirmed. Tracking only: no ledger rows are written on confirm.
 *
 * Actions:
 *   summary  (GET)   per-currency auto counter for a finance user
 *   create   (POST)  finance submits an amount (<= available)
 *   confirm  (POST)  admin confirms a pending settlement
 *   reject   (POST)  admin rejects a pending settlement with a reason
 *   list     (GET)   history for a user (or all) with status filter
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once '../../admin/security.php';
require_once '../../includes/db.php';
enforce_auth();
require_permission('finance.cash_settlement');
$canApprove = user_can('finance.cash_settlement_approve');

$tenant_id = (int) $_SESSION['tenant_id'];
$branch_id = (int) $_SESSION['branch_id'];
$current_user = (int) ($_SESSION['user_id'] ?? 0);

// CSRF on POST
if (($_SERVER['REQUEST_METHOD'] === 'POST') && !verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

$currencies = ['USD', 'AFS', 'EUR', 'DARHAM', 'SAR'];

function logActivity(PDO $pdo, $user, $tenant, $branch, $action, $recordId, $oldV, $newV) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $stmt = $pdo->prepare("INSERT INTO activity_log
        (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
        VALUES (?, ?, 'cash_settlements', ?, ?, ?, ?, ?, NOW(), ?, ?)");
    $stmt->execute([$user, $action, $recordId, $oldV, $newV, $ip, $ua, $tenant, $branch]);
}

function pushNotification(PDO $pdo, $tenant, $branch, $recipientRole, $message, $refId = null, $type = 'cash_settlement') {
    $stmt = $pdo->prepare("INSERT INTO notifications
        (transaction_id, transaction_type, message, recipient_role, status, created_at, tenant_id, branch_id)
        VALUES (?, ?, ?, ?, 'Unread', NOW(), ?, ?)");
    $stmt->execute([$refId, $type, $message, $recipientRole, $tenant, $branch]);
}

/**
 * Auto counter for a finance user, per currency.
 *
 * remaining = (user's credit − user's debit) − SUM(confirmed settlements)
 *
 * Using confirmed-sum subtraction (rather than a "since last settlement"
 * timestamp) keeps partial settlements correct: confirming 400 of 1000
 * leaves 600 visible, and the counter only reaches zero when the full
 * amount has been confirmed — matching "admin confirms → counter = 0".
 */
function autoCounter(PDO $pdo, $tenant, $branch, $user) {
    $creditIn = []; $debitOut = [];

    // Only count movements on INTERNAL main accounts (cash handed over).
    // Bank main accounts (e.g. AZIZI BANK) are excluded from the settlement.
    $stmt = $pdo->prepare("SELECT m.currency, m.type, COALESCE(SUM(m.amount),0) AS tot
        FROM main_account_transactions m
        JOIN main_account ma ON ma.id = m.main_account_id
        WHERE m.tenant_id = ? AND m.branch_id = ? AND m.created_by = ?
          AND ma.account_type = 'internal'
        GROUP BY m.currency, m.type");
    $stmt->execute([$tenant, $branch, $user]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $c = strtoupper($r['currency']);
        if ($r['type'] === 'credit')    $creditIn[$c] = (float) $r['tot'];
        elseif ($r['type'] === 'debit') $debitOut[$c] = (float) $r['tot'];
    }

    // confirmed settlements deduct from the running total
    $stmt = $pdo->prepare("SELECT currency, COALESCE(SUM(amount),0) AS tot
        FROM cash_settlements
        WHERE tenant_id = ? AND branch_id = ? AND user_id = ? AND status = 'confirmed'
        GROUP BY currency");
    $stmt->execute([$tenant, $branch, $user]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $c = strtoupper($r['currency']);
        if (isset($creditIn[$c])) $creditIn[$c] -= (float) $r['tot'];
    }

    return [$creditIn, $debitOut];
}

/* ------------------------- switch ------------------------- */
try {
    switch ($action) {

        case 'summary':
            $targetUser = (int) ($_GET['user'] ?? $current_user);
            if (!$canApprove && $targetUser !== $current_user) {
                $targetUser = $current_user; // non-approvers only see their own
            }
            [$creditIn, $debitOut] = autoCounter($pdo, $tenant_id, $branch_id, $targetUser);

            $result = [];
            foreach ($currencies as $cur) {
                $in   = $creditIn[$cur]  ?? 0.0;
                $out  = $debitOut[$cur]  ?? 0.0;
                $remaining = $in - $out;
                $result[$cur] = [
                    'credit' => $in,
                    'debit'  => $out,
                    'remaining' => $remaining,
                ];
            }

            // pending + confirmed totals per currency
            $stmt = $pdo->prepare("SELECT currency, status, COALESCE(SUM(amount),0) AS total
                FROM cash_settlements
                WHERE tenant_id = ? AND branch_id = ? AND user_id = ?
                GROUP BY currency, status");
            $stmt->execute([$tenant_id, $branch_id, $targetUser]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $cur = strtoupper($row['currency']);
                $key = strtolower($row['status']);
                if (isset($result[$cur])) {
                    $result[$cur][$key] = (float) $row['total'];
                }
            }
            foreach ($result as $cur => &$v) {
                $v['pending']   = $v['pending']   ?? 0.0;
                $v['confirmed'] = $v['confirmed'] ?? 0.0;
                $v['rejected']  = $v['rejected']  ?? 0.0;
                $v['available'] = $v['remaining'] - $v['pending']; // what finance can submit now
                $v['available'] = $v['available'] < 0 ? 0.0 : $v['available'];
            }
            unset($v);

            echo json_encode(['success' => true, 'user' => $targetUser, 'currencies' => $result]);
            break;

        case 'transactions':
            // Breakdown of the actual main_account_transactions rows that make
            // up a finance user's counter, for one currency (or all).
            $targetUser = (int) ($_GET['user'] ?? $current_user);
            if (!$canApprove && $targetUser !== $current_user) {
                $targetUser = $current_user; // non-approvers only see their own
            }
            $currency = strtoupper(trim($_GET['currency'] ?? ''));
            if ($currency && !in_array($currency, $currencies, true)) {
                throw new Exception('Invalid currency');
            }

            $sql = "SELECT mat.id, mat.type, mat.amount, mat.currency, mat.description, mat.transaction_of,
                           mat.reference_id, mat.created_at, mat.receipt
                    FROM main_account_transactions mat
                    JOIN main_account ma ON ma.id = mat.main_account_id
                    WHERE mat.tenant_id = ? AND mat.branch_id = ? AND mat.created_by = ?
                      AND ma.account_type = 'internal'";
            $params = [$tenant_id, $branch_id, $targetUser];
            if ($currency) {
                $sql .= " AND UPPER(mat.currency) = ?";
                $params[] = $currency;
            }
            $sql .= " ORDER BY mat.created_at DESC, mat.id DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$r) {
                $r['amount']    = (float) $r['amount'];
                $r['type']      = strtolower($r['type']);
                $r['currency']  = strtoupper($r['currency']);
            }
            unset($r);

            // Settlement deductions for the same user+currency so the list ties
            // back to the counter: remaining = credit - debit - confirmed.
            $sqlS = "SELECT id, amount, status, created_at, request_note
                     FROM cash_settlements
                     WHERE tenant_id = ? AND branch_id = ? AND user_id = ?";
            $paramsS = [$tenant_id, $branch_id, $targetUser];
            if ($currency) {
                $sqlS .= " AND currency = ?";
                $paramsS[] = $currency;
            }
            $sqlS .= " ORDER BY created_at DESC";
            $stmtS = $pdo->prepare($sqlS);
            $stmtS->execute($paramsS);
            $settlements = $stmtS->fetchAll(PDO::FETCH_ASSOC);
            foreach ($settlements as &$s) {
                $s['amount'] = (float) $s['amount'];
            }
            unset($s);

            echo json_encode([
                'success' => true,
                'user' => $targetUser,
                'currency' => $currency,
                'transactions' => $rows,
                'settlements' => $settlements,
            ]);
            break;

        case 'create':
            $currency = strtoupper(trim($_POST['currency'] ?? ''));
            $amount   = (float) ($_POST['amount'] ?? 0);
            $note     = trim($_POST['note'] ?? '');

            if (!in_array($currency, $currencies, true)) throw new Exception('Invalid currency');
            if ($amount <= 0) throw new Exception('Amount must be greater than 0');
            if (mb_strlen($note) > 1000) throw new Exception('Note too long');

            // enforce <= available
            [$creditIn, $debitOut] = autoCounter($pdo, $tenant_id, $branch_id, $current_user);
            $remaining = ($creditIn[$currency] ?? 0.0) - ($debitOut[$currency] ?? 0.0);
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0)
                FROM cash_settlements WHERE tenant_id=? AND branch_id=? AND user_id=? AND currency=? AND status='pending'");
            $stmt->execute([$tenant_id, $branch_id, $current_user, $currency]);
            $pending = (float) $stmt->fetchColumn();
            $available = $remaining - $pending;
            if ($available < 0) $available = 0;
            if ($amount > $available) {
                throw new Exception("Amount exceeds available balance (" . number_format($available, 2) . " {$currency})");
            }

            $stmt = $pdo->prepare("INSERT INTO cash_settlements
                (tenant_id, branch_id, user_id, currency, amount, status, request_note, requested_by)
                VALUES (?, ?, ?, ?, ?, 'pending', ?, ?)");
            $stmt->execute([$tenant_id, $branch_id, $current_user, $currency, $amount, $note, $current_user]);
            $id = (int) $pdo->lastInsertId();

            logActivity($pdo, $current_user, $tenant_id, $branch_id, 'create', $id, '{}', json_encode([
                'user_id' => $current_user, 'currency' => $currency, 'amount' => $amount, 'note' => $note,
            ], JSON_UNESCAPED_UNICODE));

            $msg = "Finance submitted {$currency} {$amount} for settlement.";
            if ($canApprove) {
                $msg = "You submitted {$currency} {$amount} for settlement.";
            }
            pushNotification($pdo, $tenant_id, $branch_id, 'admin', $msg, $id);

            echo json_encode(['success' => true, 'message' => 'Settlement submitted for admin approval', 'id' => $id]);
            break;

        case 'confirm':
            if (!$canApprove) throw new Exception('You are not allowed to confirm a settlement');
            $id = (int) ($_POST['id'] ?? 0);
            if (!$id) throw new Exception('Settlement ID required');

            // Admin signature (drawn on a canvas, sent as a PNG data URL) is
            // required as proof that the cash was actually handed over.
            $signature = trim($_POST['signature'] ?? '');
            if ($signature === '') throw new Exception('Admin signature is required to confirm the settlement');
            if (strlen($signature) > 300000) throw new Exception('Signature image too large');
            if (!preg_match('/^data:image\/png;base64,[A-Za-z0-9+\/=]+$/', $signature)) {
                throw new Exception('Invalid signature image');
            }

            $stmt = $pdo->prepare("SELECT * FROM cash_settlements WHERE id=? AND tenant_id=? AND branch_id=? AND status='pending'");
            $stmt->execute([$id, $tenant_id, $branch_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new Exception('Pending settlement not found');

            $stmt = $pdo->prepare("UPDATE cash_settlements
                SET status='confirmed', confirmed_by=?, confirmed_at=NOW(), signature_data=?, signed_at=NOW()
                WHERE id=?");
            $stmt->execute([$current_user, $signature, $id]);

            logActivity($pdo, $current_user, $tenant_id, $branch_id, 'confirm', $id,
                json_encode($row, JSON_UNESCAPED_UNICODE), json_encode(['status' => 'confirmed']));

            $msg = "Your {$row['currency']} {$row['amount']} settlement was confirmed by admin.";
            pushNotification($pdo, $tenant_id, $branch_id, 'finance', $msg, $id);

            echo json_encode(['success' => true, 'message' => 'Settlement confirmed']);
            break;

        case 'reject':
            if (!$canApprove) throw new Exception('You are not allowed to reject a settlement');
            $id = (int) ($_POST['id'] ?? 0);
            $reason = trim($_POST['reason'] ?? '');
            if (!$id) throw new Exception('Settlement ID required');
            if ($reason === '') throw new Exception('A reason is required to reject');

            $stmt = $pdo->prepare("SELECT * FROM cash_settlements WHERE id=? AND tenant_id=? AND branch_id=? AND status='pending'");
            $stmt->execute([$id, $tenant_id, $branch_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new Exception('Pending settlement not found');

            $stmt = $pdo->prepare("UPDATE cash_settlements
                SET status='rejected', rejected_by=?, rejected_at=NOW(), reject_reason=?
                WHERE id=?");
            $stmt->execute([$current_user, $reason, $id]);

            logActivity($pdo, $current_user, $tenant_id, $branch_id, 'reject', $id,
                json_encode($row, JSON_UNESCAPED_UNICODE), json_encode(['status' => 'rejected', 'reason' => $reason]));

            $msg = "Your {$row['currency']} {$row['amount']} settlement was rejected. Reason: {$reason}";
            pushNotification($pdo, $tenant_id, $branch_id, 'finance', $msg, $id);

            echo json_encode(['success' => true, 'message' => 'Settlement rejected']);
            break;

        case 'delete':
            // Finance (or admin settling their own cash) can retract a still-pending
            // submission (e.g. wrong amount) before it is acted on.
            if (!user_can('finance.cash_settlement')) {
                throw new Exception('You are not allowed to delete a settlement');
            }
            $id = (int) ($_POST['id'] ?? 0);
            if (!$id) throw new Exception('Settlement ID required');

            $stmt = $pdo->prepare("SELECT * FROM cash_settlements WHERE id=? AND tenant_id=? AND branch_id=? AND user_id=? AND status='pending'");
            $stmt->execute([$id, $tenant_id, $branch_id, $current_user]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new Exception('Pending settlement not found');

            $stmt = $pdo->prepare("DELETE FROM cash_settlements WHERE id=?");
            $stmt->execute([$id]);

            logActivity($pdo, $current_user, $tenant_id, $branch_id, 'delete', $id,
                json_encode($row, JSON_UNESCAPED_UNICODE), '{}');

            echo json_encode(['success' => true, 'message' => 'Settlement deleted']);
            break;

        case 'breakdown':
            // Income items that make up a single settlement (FIFO match),
            // mirroring the print receipt.
            $id = (int) ($_GET['id'] ?? 0);
            if (!$id) throw new Exception('Settlement ID required');

            $stmt = $pdo->prepare("SELECT cs.*, u.name AS user_name, cu.name AS confirmed_name
                FROM cash_settlements cs
                LEFT JOIN users u  ON u.id  = cs.user_id
                LEFT JOIN users cu ON cu.id = cs.confirmed_by
                WHERE cs.id=? AND cs.tenant_id=? AND cs.branch_id=?");
            $stmt->execute([$id, $tenant_id, $branch_id]);
            $s = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$s) throw new Exception('Settlement not found');
            if (!$canApprove && (int) $s['user_id'] !== $current_user) {
                throw new Exception('You can only view your own settlements');
            }

            $stmt = $pdo->prepare("SELECT mat.id, mat.description, mat.transaction_of, mat.reference_id, mat.created_at, mat.amount
                FROM main_account_transactions mat
                JOIN main_account ma ON ma.id = mat.main_account_id
                WHERE mat.tenant_id=? AND mat.branch_id=? AND mat.created_by=?
                  AND UPPER(mat.currency)=? AND ma.account_type='internal' AND mat.type='credit'
                ORDER BY mat.created_at ASC, mat.id ASC");
            $stmt->execute([$tenant_id, $branch_id, $s['user_id'], strtoupper($s['currency'])]);
            $credits = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $pdo->prepare("SELECT id, amount FROM cash_settlements
                WHERE tenant_id=? AND branch_id=? AND user_id=? AND currency=? AND status='confirmed'
                ORDER BY confirmed_at ASC, id ASC");
            $stmt->execute([$tenant_id, $branch_id, $s['user_id'], $s['currency']]);
            $settledList = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $targetIdx = -1;
            $before = 0.0;
            foreach ($settledList as $i => $se) {
                if ((int) $se['id'] === (int) $id) { $targetIdx = $i; break; }
                $before += (float) $se['amount'];
            }

            $items = [];
            $total = 0.0;
            $note = '';
            if ($targetIdx >= 0) {
                $start = $before;
                $end = $before + (float) $s['amount'];
                $running = 0.0;
                foreach ($credits as $c) {
                    $amt = (float) $c['amount'];
                    $cStart = $running;
                    $cEnd = $running + $amt;
                    $running = $cEnd;
                    if ($cEnd <= $start || $cStart >= $end) continue;
                    $covered = min($cEnd, $end) - max($cStart, $start);
                    $items[] = [
                        'id'           => (int) $c['id'],
                        'description'  => $c['description'],
                        'source'       => str_replace('_', ' ', $c['transaction_of']),
                        'reference_id' => (int) $c['reference_id'],
                        'created_at'   => $c['created_at'],
                        'covered'      => round($covered, 2),
                        'partial'      => $covered < $amt - 0.005,
                    ];
                    $total += $covered;
                    if ($cEnd >= $end) break;
                }
                if (!$items) $note = 'No income items found for this settlement.';
            } else {
                foreach ($credits as $c) {
                    $items[] = [
                        'id'           => (int) $c['id'],
                        'description'  => $c['description'],
                        'source'       => str_replace('_', ' ', $c['transaction_of']),
                        'reference_id' => (int) $c['reference_id'],
                        'created_at'   => $c['created_at'],
                        'covered'      => round((float) $c['amount'], 2),
                        'partial'      => false,
                    ];
                    $total += (float) $c['amount'];
                }
                $total = round($total, 2);
                if ($items) $note = 'Not yet confirmed — showing all income items for ' . $s['currency'] . '.';
            }

            echo json_encode([
                'success' => true,
                'settlement' => [
                    'id' => (int) $s['id'], 'user_name' => $s['user_name'],
                    'currency' => $s['currency'], 'amount' => (float) $s['amount'],
                    'status' => $s['status'], 'created_at' => $s['created_at'],
                    'request_note' => $s['request_note'],
                    'confirmed_name' => $s['confirmed_name'],
                    'confirmed_at' => $s['confirmed_at'],
                    'signed_at' => $s['signed_at'],
                ],
                'items' => $items,
                'total' => round($total, 2),
                'note' => $note,
            ]);
            break;

        case 'list':
            $targetUser = (int) ($_GET['user'] ?? 0);
            $statusFilter = $_GET['status'] ?? '';
            $searchUserId = (!$canApprove) ? $current_user : ($targetUser ?: 0);

            $sql = "SELECT cs.id, cs.user_id, cs.currency, cs.amount, cs.status, cs.request_note,
                           cs.requested_by, cs.created_at, cs.confirmed_by, cs.confirmed_at,
                           cs.signed_at, cs.rejected_by, cs.rejected_at, cs.reject_reason,
                           u.name AS user_name, cu.name AS confirmed_name
                    FROM cash_settlements cs
                    LEFT JOIN users u  ON u.id  = cs.user_id
                    LEFT JOIN users cu ON cu.id = cs.confirmed_by
                    WHERE cs.tenant_id=? AND cs.branch_id=?";
            $params = [$tenant_id, $branch_id];
            if ($searchUserId) {
                $sql .= " AND cs.user_id=?";
                $params[] = $searchUserId;
            }
            if ($statusFilter && in_array($statusFilter, ['pending','confirmed','rejected'], true)) {
                $sql .= " AND cs.status=?";
                $params[] = $statusFilter;
            }
            $sql .= " ORDER BY cs.created_at DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$r) {
                $r['amount'] = (float)$r['amount'];
            }
            unset($r);
            echo json_encode(['success' => true, 'settlements' => $rows]);
            break;

        default:
            throw new Exception('Invalid action: ' . htmlspecialchars($action));
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}