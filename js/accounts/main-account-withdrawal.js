function setupMainWithdrawModal(accountId, accountName) {
    document.getElementById('withdrawMainAccountId').value = accountId;
    document.getElementById('withdrawMainAccountName').value = accountName;
    $('#withdrawMainModal').modal('show');
}

document.getElementById('withdrawMainForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const btn = document.getElementById('withdrawMainBtn');
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Withdrawing...';

    const formData = new FormData(this);
    const data = Object.fromEntries(formData.entries());

    if (!data.main_account_id || !data.currency || !data.amount || !data.receipt_number) {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
        showWarningToast('Please fill in all required fields');
        return;
    }

    const amount = parseFloat(data.amount);
    if (isNaN(amount) || amount <= 0) {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
        showWarningToast('Please enter a valid amount');
        return;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    fetch('../api/accounts/withdraw_main_fund.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ...data, csrf_token: csrfToken })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccessToast('Withdrawal successful');
            setTimeout(() => { location.reload(); }, 1000);
        } else {
            showErrorToast('Withdrawal failed: ' + data.message);
        }
    })
    .catch(error => {
        showErrorToast('An error occurred during withdrawal');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    });
});
