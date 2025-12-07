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
require_once '../includes/conn.php';

// Handle debtor deletion via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_debtor'])) {
    $debtor_id = $_POST['debtor_id'];

    try {
        // Get debtor information
        $stmt = $conn->prepare("SELECT * FROM debtors WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bind_param("iii", $debtor_id, $tenant_id, $branch_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $debtor = $result->fetch_assoc();

        if (!$debtor) {
            throw new Exception("Debtor not found");
        }

        // Check if debtor has any transactions
        $stmt = $conn->prepare("SELECT COUNT(*) as transaction_count FROM debtor_transactions WHERE debtor_id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bind_param("iii", $debtor_id, $tenant_id, $branch_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $transaction_count = $result->fetch_assoc()['transaction_count'];

        if ($transaction_count > 0) {
            // Return error response if transactions exist
            echo json_encode([
                'success' => false,
                'message' => 'Cannot delete debtor. Please delete all transactions for this debtor first, then come back and delete the debtor.'
            ]);
            exit();
        }

        // If no transactions, proceed with deletion
        $conn->begin_transaction();

        // Delete the debtor
        $stmt = $conn->prepare("DELETE FROM debtors WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bind_param("iii", $debtor_id, $tenant_id, $branch_id);
        $stmt->execute();

        $conn->commit();

        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'Debtor deleted successfully!'
        ]);

    } catch (Exception $e) {
        if (isset($conn) && $conn->connect_error === null) {
            $conn->rollback();
        }

        // Return error response
        echo json_encode([
            'success' => false,
            'message' => 'Error deleting debtor: ' . $e->getMessage()
        ]);
    }
    exit();
}
?>