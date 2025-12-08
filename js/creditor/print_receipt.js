// Initialize transaction manager when document is ready
// Print receipt function
function printReceipt(transactionId) {
    window.open(`../api/creditor/print_creditor_receipt.php?id=${transactionId}`, '_blank');
}