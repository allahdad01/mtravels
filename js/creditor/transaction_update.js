// Function to update creditor transaction
function updateCreditorTransaction(transactionId) {
    // Get form data
    const form = document.getElementById('editTransactionForm_' + transactionId);
    const formData = new FormData(form);

    // Show loading indicator
    const saveButton = event.target;
    const originalText = saveButton.innerHTML;
    saveButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Updating...';
    saveButton.disabled = true;

    // Send AJAX request
    fetch('../api/creditor/update_creditor_transaction.php', {
        method: 'POST',
        body: formData,
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message
            alert('<?= __("transaction_updated_successfully") ?>');
            // Reload the page to show updated data
            window.location.reload();
        } else {
            // Show error message
            alert('<?= __("error") ?>: ' + data.message);
            // Reset button
            saveButton.innerHTML = originalText;
            saveButton.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('<?= __("error_occurred_during_update") ?>');
        // Reset button
        saveButton.innerHTML = originalText;
        saveButton.disabled = false;
    });
}

// Simple timestamp-based double-click prevention
document.addEventListener('DOMContentLoaded', function() {

    // Global timestamp for all button clicks
    let lastClickTime = 0;
    const CLICK_DELAY = 30000; // 3 seconds

    // Simple double-click prevention
    document.addEventListener('click', function(e) {
        if (e.target.matches('button[type="submit"]')) {
            const now = Date.now();

            // If clicked too recently, prevent this click
            if (now - lastClickTime < CLICK_DELAY) {
                e.preventDefault();
                return false;
            }

            // Update last click time
            lastClickTime = now;

            // Show loading state
            const btn = e.target;
            const originalText = btn.innerHTML;
            let loadingText = 'Processing...';

            if (btn.name === 'add_creditor') {
                loadingText = '<i class="feather icon-check-circle mr-2"></i>Adding Creditor...';
            } else if (btn.name === 'pay') {
                loadingText = '<i class="feather icon-credit-card mr-2"></i>Processing Payment...';
            } else if (btn.name === 'edit_creditor') {
                loadingText = '<i class="feather icon-edit-2 mr-2"></i>Saving Changes...';
            } else if (btn.name === 'delete_transaction') {
                loadingText = '<i class="feather icon-trash-2 mr-2"></i>Deleting...';
            } else if (btn.name === 'delete_creditor') {
                loadingText = '<i class="feather icon-trash-2 mr-2"></i>Deleting Creditor...';
            }

            btn.innerHTML = loadingText;

            // Reset after delay
            setTimeout(() => {
                if (Date.now() - lastClickTime >= CLICK_DELAY) {
                    btn.innerHTML = originalText;
                }
            }, 5000);
        }
    });

    // Handle transaction edit button (AJAX)
    document.addEventListener('click', function(e) {
        if (e.target.matches('button[onclick*="updateCreditorTransaction"]')) {
            const now = Date.now();

            if (now - lastClickTime < CLICK_DELAY) {
                e.preventDefault();
                return false;
            }

            lastClickTime = now;

            const btn = e.target;
            btn.innerHTML = '<i class="feather icon-edit-2 mr-2"></i>Saving Changes...';

            setTimeout(() => {
                if (Date.now() - lastClickTime >= CLICK_DELAY) {
                    btn.innerHTML = btn.innerHTML.replace('Saving Changes...', 'Save Changes');
                }
            }, 30000);
        }
    });
});