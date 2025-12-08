<?php
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';

// Include language helper
require_once '../includes/language_helpers.php';

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
require_once '../includes/db.php';

// Handle debtor deletion via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_debtor'])) {
    $debtor_id = $_POST['debtor_id'];

    try {
        // Get debtor information
        $stmt = $pdo->prepare("SELECT * FROM debtors WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $debtor_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $debtor = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$debtor) {
            throw new Exception("Debtor not found");
        }

        // Check if debtor has any transactions
        $stmt = $pdo->prepare("SELECT COUNT(*) as transaction_count FROM debtor_transactions WHERE debtor_id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $debtor_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $transaction_count = $stmt->fetch(PDO::FETCH_ASSOC)['transaction_count'];

        if ($transaction_count > 0) {
            // Return error response if transactions exist
            echo json_encode([
                'success' => false,
                'message' => 'Cannot delete debtor. Please delete all transactions for this debtor first, then come back and delete the debtor.'
            ]);
            exit();
        }

        // If no transactions, proceed with deletion
        $pdo->beginTransaction();

        // Delete the debtor
        $stmt = $pdo->prepare("DELETE FROM debtors WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $debtor_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();

        $pdo->commit();

        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'Debtor deleted successfully!'
        ]);

    } catch (Exception $e) {
        $pdo->rollback();

        // Return error response
        echo json_encode([
            'success' => false,
            'message' => 'Error deleting debtor: ' . $e->getMessage()
        ]);
    }
    exit();
}
?>