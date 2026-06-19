<?php
// Include database security module for input validation
require_once '../../admin/includes/db_security.php';

// Include security module
require_once '../../admin/security.php';

// Include language helper
require_once '../../includes/language_helpers.php';

// Load input validation helper
require_once '../../includes/InputValidator.php';

// Enforce authentication
enforce_auth();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
require_once '../../includes/db.php';

// Handle debtor deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['debtor_id'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $_SESSION['error_message'] = "Security validation failed. Please try again.";
        header('Location: ../../admin/debtors.php');
        exit();
    }

    $debtor_id = intval($_POST['debtor_id']);

    try {
        // Get debtor information
        $stmt = $pdo->prepare("SELECT * FROM debtors WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->execute([$debtor_id, $tenant_id, $branch_id]);
        $debtor = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$debtor) {
            throw new Exception("Debtor not found");
        }

        // Check if debtor has any transactions
        $stmt = $pdo->prepare("SELECT COUNT(*) as transaction_count FROM debtor_transactions WHERE debtor_id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->execute([$debtor_id, $tenant_id, $branch_id]);
        $transaction_count = $stmt->fetch(PDO::FETCH_ASSOC)['transaction_count'];

        if ($transaction_count > 0) {
            $_SESSION['error_message'] = "Cannot delete debtor. Please delete all transactions for this debtor first, then come back and delete the debtor.";
            header('Location: ../../admin/debtors.php');
            exit();
        }

        // Check if debtor has any main account transactions
        // reference_id in main_account_transactions stores debtor_transaction.id, not debtor.id
        $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM main_account_transactions mat JOIN debtor_transactions dt ON dt.id = mat.reference_id AND mat.transaction_of = 'debtor' WHERE dt.debtor_id = ? AND mat.tenant_id = ? AND mat.branch_id = ?");
        $stmt->execute([$debtor_id, $tenant_id, $branch_id]);
        $main_tx_count = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];

        if ($main_tx_count > 0) {
            $_SESSION['error_message'] = "Cannot delete debtor. Please delete all main account transactions first, then try deleting the debtor.";
            header('Location: ../../admin/debtors.php');
            exit();
        }

        // If no transactions, proceed with deletion
        $pdo->beginTransaction();

        // Delete the debtor
        $stmt = $pdo->prepare("DELETE FROM debtors WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->execute([$debtor_id, $tenant_id, $branch_id]);

        $pdo->commit();

        $_SESSION['success_message'] = "Debtor deleted successfully!";
        header('Location: ../../admin/debtors.php');
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Error deleting debtor: " . $e->getMessage();
        header('Location: ../../admin/debtors.php');
        exit();
    }
}
?>