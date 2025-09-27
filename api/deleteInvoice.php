<?php
// Include necessary files and create DB connection
include_once '../includes/db.php';  // Your DB connection file

// Check if the invoice_id is provided
if (isset($_POST['invoice_id'])) {
    $invoice_id = $_POST['invoice_id'];

    try {
        // Prepare delete query
        $stmt = $pdo->prepare("DELETE FROM invoices WHERE id = :invoice_id");
        $stmt->bindParam(':invoice_id', $invoice_id, PDO::PARAM_INT);

        // Execute the query
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            // Invoice deleted successfully
            $response = [
                'status' => 'success',
                'message' => 'Invoice deleted successfully.',
            ];
        } else {
            // Invoice not found or could not be deleted
            $response = [
                'status' => 'error',
                'message' => 'Invoice not found or could not be deleted.',
            ];
        }
    } catch (PDOException $e) {
        // Error deleting the invoice
        $response = [
            'status' => 'error',
            'message' => 'Error: ' . $e->getMessage(),
        ];
    }
} else {
    // No invoice ID provided
    $response = [
        'status' => 'error',
        'message' => 'Invoice ID is missing.',
    ];
}

// Return response as JSON
echo json_encode($response);
?>
