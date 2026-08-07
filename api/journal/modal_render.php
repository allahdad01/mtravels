<?php
/**
 * Payments Journal — server-side renderer for the existing per-module
 * transaction modals so they can be embedded in-place on the journal page.
 *
 * The journal page loads ONE module modal at a time (into #pjlModuleMount);
 * each module's transaction_modal.php is included here so __()/h() and any
 * page-specific variables ($mainAccounts, $categories, …) are rendered with
 * the same data as on the real host pages.
 *
 * GET params:
 *   modal  required — ticket | visa | umrah | hotel | additional_payment |
 *                     fund_client | fund_supplier | withdraw | transfer | expense
 *
 * Roles: admin, finance.
 */

session_status() === PHP_SESSION_NONE && session_start();

require_once __DIR__ . '/../../admin/security.php';
enforce_auth();

$allowed_roles = ['admin', 'finance'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/language_helpers.php';

if (!function_exists('h')) {
    function h(?string $string): string {
        return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
    }
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

$modal = isset($_GET['modal']) ? trim($_GET['modal']) : '';
$allowed = [
    'ticket', 'visa', 'umrah', 'hotel', 'additional_payment',
    'fund_client', 'fund_supplier', 'withdraw_main', 'withdraw_supplier', 'withdraw_client', 'transfer', 'expense',
    'jv_payment',
    'ticket_date_change', 'ticket_refund', 'ticket_weight', 'ticket_reserve',
    'hotel_refund', 'visa_refund', 'umrah_refund',
    'sarafi_deposit', 'sarafi_withdraw', 'sarafi_hawala', 'sarafi_exchange',
    'salary_regular', 'salary_advance', 'salary_bonus', 'salary_deduction',
];
if (!in_array($modal, $allowed, true)) {
    http_response_code(400);
    echo 'Invalid modal';
    exit;
}

/* ── Shared page variables needed by some modals ── */
$mainAccounts = [];
$categories    = [];
$internal      = [];
$clients       = [];
$suppliers     = [];

if (in_array($modal, ['fund_client', 'transfer', 'additional_payment'], true)) {
    try {
        $stmt = $pdo->prepare("SELECT id, name FROM main_account WHERE status = 'active' AND tenant_id = ? AND branch_id = ? ORDER BY name");
        $stmt->execute([$tenant_id, $branch_id]);
        $mainAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $mainAccounts = [];
    }
}

if ($modal === 'expense') {
    try {
        $stmt = $pdo->prepare("SELECT id, name FROM main_account WHERE status = 'active' AND tenant_id = ? AND branch_id = ? ORDER BY name");
        $stmt->execute([$tenant_id, $branch_id]);
        $internal = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $internal = [];
    }
    try {
        $stmt = $pdo->prepare("SELECT * FROM expense_categories WHERE tenant_id = ? AND branch_id = ? ORDER BY name");
        $stmt->execute([$tenant_id, $branch_id]);
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $categories = [];
    }
}

if ($modal === 'jv_payment') {
    try {
        $stmt = $pdo->prepare("SELECT id, name, usd_balance, afs_balance, status FROM clients WHERE tenant_id = ? AND branch_id = ? AND status = 'active' ORDER BY name");
        $stmt->execute([$tenant_id, $branch_id]);
        $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $clients = [];
    }
    try {
        $stmt = $pdo->prepare("SELECT id, name, currency, balance, status FROM suppliers WHERE tenant_id = ? AND branch_id = ? AND status = 'active' ORDER BY name");
        $stmt->execute([$tenant_id, $branch_id]);
        $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $suppliers = [];
    }
}

if (in_array($modal, ['sarafi_deposit', 'sarafi_withdraw', 'sarafi_hawala', 'sarafi_exchange'], true)) {
    $customers = [];
    $main_accounts = [];
    try {
        $stmt = $pdo->prepare("SELECT id, name FROM customers WHERE status = 'active' AND tenant_id = ? AND branch_id = ? ORDER BY name ASC");
        $stmt->execute([$tenant_id, $branch_id]);
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $customers = [];
    }
    try {
        $stmt = $pdo->prepare("SELECT id, name FROM main_account WHERE status = 'active' AND tenant_id = ? AND branch_id = ? ORDER BY name ASC");
        $stmt->execute([$tenant_id, $branch_id]);
        $main_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $main_accounts = [];
    }
}

if (in_array($modal, ['salary_regular', 'salary_advance', 'salary_bonus', 'salary_deduction'], true)) {
    $employees = [];
    $accounts  = [];
    $users_with_salary = [];
    try {
        $stmt = $pdo->prepare("SELECT u.id, u.name, sm.base_salary, sm.currency FROM users u JOIN salary_management sm ON u.id=sm.user_id WHERE sm.status='active' AND u.fired=0 AND u.tenant_id=? AND u.branch_id=? ORDER BY u.name");
        $stmt->execute([$tenant_id, $branch_id]);
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $employees = [];
    }
    try {
        $stmt = $pdo->prepare("SELECT id, name FROM main_account WHERE status='active' AND tenant_id=? AND branch_id=? ORDER BY name");
        $stmt->execute([$tenant_id, $branch_id]);
        $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $accounts = [];
    }
    $users_with_salary = $employees;
}

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');

switch ($modal) {
    case 'ticket':
        include __DIR__ . '/../../modals/ticket/transaction_modal.php';
        break;
    case 'visa':
        include __DIR__ . '/../../modals/visa/transaction_modal.php';
        include __DIR__ . '/../../modals/visa/edit_transaction_modal.php';
        break;
    case 'umrah':
        include __DIR__ . '/../../modals/umrah/transaction_modal.php';
        include __DIR__ . '/../../modals/umrah/edit_transaction_modal.php';
        break;
    case 'hotel':
        include __DIR__ . '/../../modals/hotel/transaction_modal.php';
        include __DIR__ . '/../../modals/hotel/edit_transaction_modal.php';
        break;
    case 'additional_payment':
        include __DIR__ . '/../../modals/additional_payment/add_transaction_modal.php';
        include __DIR__ . '/../../modals/additional_payment/edit_transaction_modal.php';
        break;
    case 'fund_client':
        include __DIR__ . '/../../modals/accounts/client_payment_modal.php';
        break;
    case 'fund_supplier':
        include __DIR__ . '/../../modals/accounts/fund_supplier_modal.php';
        break;
    case 'withdraw_main':
        include __DIR__ . '/../../modals/accounts/withdraw_main_modal.php';
        break;
    case 'withdraw_supplier':
        include __DIR__ . '/../../modals/accounts/withdraw_supplier_modal.php';
        break;
    case 'withdraw_client':
        include __DIR__ . '/../../modals/accounts/client_withdraw_modal.php';
        break;
    case 'transfer':
        include __DIR__ . '/../../modals/accounts/transfer_modal.php';
        break;
    case 'expense':
        include __DIR__ . '/../../modals/expense/expense_modal.php';
        break;
    case 'jv_payment':
        include __DIR__ . '/../../modals/journal/jv_payment_modal.php';
        break;
    case 'ticket_date_change':
        include __DIR__ . '/../../modals/ticket_date_change/transaction_modal.php';
        include __DIR__ . '/../../modals/ticket_date_change/edit_transaction.php';
        break;
    case 'ticket_refund':
        include __DIR__ . '/../../modals/ticket_refund/transaction_modal.php';
        include __DIR__ . '/../../modals/ticket_refund/edit_transaction_modal.php';
        break;
    case 'ticket_weight':
        include __DIR__ . '/../../modals/ticket_weight/transaction_modal.php';
        break;
    case 'ticket_reserve':
        include __DIR__ . '/../../modals/ticket_reserve/transaction_modal.php';
        break;
    case 'hotel_refund':
        include __DIR__ . '/../../modals/hotel_refund/transaction_modal.php';
        break;
    case 'visa_refund':
        include __DIR__ . '/../../modals/visa_refund/transaction_modal.php';
        include __DIR__ . '/../../modals/visa_refund/edit_transaction_modal.php';
        break;
    case 'umrah_refund':
        include __DIR__ . '/../../modals/umrah_refund/transaction_modal.php';
        include __DIR__ . '/../../modals/umrah_refund/edit_transaction_modal.php';
        break;
    case 'sarafi_deposit':
        include __DIR__ . '/../../modals/sarafi/deposit_modal.php';
        break;
    case 'sarafi_withdraw':
        include __DIR__ . '/../../modals/sarafi/withdrawal_modal.php';
        break;
    case 'sarafi_hawala':
        include __DIR__ . '/../../modals/sarafi/hawala_modal.php';
        break;
    case 'sarafi_exchange':
        include __DIR__ . '/../../modals/sarafi/exchange_modal.php';
        break;
    case 'salary_regular':
        include __DIR__ . '/../../modals/salary/salary_modal.php';
        break;
    case 'salary_advance':
        include __DIR__ . '/../../modals/salary/advance_modal.php';
        break;
    case 'salary_bonus':
        include __DIR__ . '/../../modals/salary/bonus_modal.php';
        break;
    case 'salary_deduction':
        include __DIR__ . '/../../modals/salary/deduction_modal.php';
        break;
}
