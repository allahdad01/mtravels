<?php
// Include database connection if not already included
if (!isset($conn)) {
    require_once __DIR__ . '/../../includes/conn.php';
}

// Include language helper
require_once __DIR__ . '/../../includes/language_helpers.php';
$tenant_id = $_SESSION['tenant_id'];
// Fetch customers if not already fetched
if (!isset($customers)) {
    $stmt = $conn->prepare("SELECT * FROM customers WHERE status = 'active' AND tenant_id = ? ORDER BY name ASC");
    $stmt->execute([$tenant_id]);
    $customers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Fetch main accounts
$stmt = $conn->prepare("SELECT * FROM main_account WHERE status = 'active' AND tenant_id = ? ORDER BY name ASC");
$stmt->execute([$tenant_id]);
$main_accounts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!-- Add Select2 CSS and JS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<style>
/* Select2 Z-index fix */
.select2-container--open {
    z-index: 9999;
}

/* Ensure Select2 matches Bootstrap styling */
.select2-container--bootstrap-5 .select2-selection {
    min-height: calc(1.5em + 0.75rem + 2px);
}

/* Fix Select2 in modals */
.modal-body .select2-container {
    width: 100% !important;
}
</style>

<!-- Customer Modal -->
<div class="modal fade" id="customerModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= __('new_customer') ?></h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form method="POST" action="handlers/create_customer.php">
                <div class="modal-body">
                    <div class="form-group">
                        <label><?= __('full_name') ?></label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label><?= __('email') ?></label>
                        <input type="email" class="form-control" name="email">
                        <small class="form-text text-muted"><?= __('optional') ?></small>
                    </div>
                    
                    <div class="form-group">
                        <label><?= __('phone') ?></label>
                        <input type="tel" class="form-control" name="phone" required>
                    </div>
                    
                    <div class="form-group">
                        <label><?= __('address') ?></label>
                        <textarea class="form-control" name="address" rows="2"></textarea>
                        <small class="form-text text-muted"><?= __('optional') ?></small>
                    </div>
                    
                    <div class="form-group">
                        <label><?= __('initial_balance') ?></label>
                        <div class="row">
                            <div class="col-md-6">
                                <input type="number" class="form-control" name="initial_balance" step="0.01" value="0" readonly>
                            </div>
                            <div class="col-md-6">
                                <select class="form-control" name="initial_currency">
                                    <option value="USD"><?= __('usd') ?></option>
                                    <option value="AFS"><?= __('afs') ?></option>
                                    <option value="EUR"><?= __('eur') ?></option>
                                    <option value="DARHAM"><?= __('darham') ?></option>
                                </select>
                            </div>
                        </div>
                        <small class="form-text text-muted"><?= __('optional') ?> - <?= __('set_an_initial_balance_for_the_customer') ?></small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                    <button type="submit" class="btn btn-success"><?= __('create_customer') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Deposit Modal -->
<div class="modal fade" id="depositModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= __('new_deposit') ?></h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data" action="sarafi.php">
                <div class="modal-body">
                    <div class="form-group">
                            <label><?= __('customer') ?></label>
                        <select class="form-control" name="customer_id" required>
                            <option value=""><?= __('select_customer') ?></option>
                            <?php foreach ($customers as $customer): ?>
                            <option value="<?= $customer['id'] ?>"><?= htmlspecialchars($customer['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><?= __('main_account') ?></label>
                        <select class="form-control" name="main_account_id" required>
                            <option value=""><?= __('select_main_account') ?></option>
                            <?php foreach ($main_accounts as $account): ?>
                            <option value="<?= $account['id'] ?>"><?= htmlspecialchars($account['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><?= __('amount') ?></label>
                        <input type="number" step="0.01" class="form-control" name="amount" required>
                    </div>
                    <div class="form-group">
                        <label><?= __('currency') ?></label>
                        <select class="form-control" name="currency" required>
                            <option value="USD"><?= __('usd') ?></option>
                            <option value="EUR"><?= __('eur') ?></option>
                            <option value="AFS"><?= __('afs') ?></option>
                            <option value="DARHAM"><?= __('darham') ?></option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><?= __('reference_number') ?></label>
                        <input type="text" class="form-control" name="reference" value="<?= uniqid('DEP') ?>" required>
                    </div>
                    <div class="form-group">
                        <label><?= __('notes') ?></label>
                        <textarea class="form-control" name="notes" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label><?= __('receipt_optional') ?></label>
                        <input type="file" class="form-control" name="receipt" accept="image/*,.pdf">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
                    <button type="submit" name="add_deposit" class="btn btn-primary"><?= __('submit') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Withdrawal Modal -->
<div class="modal fade" id="withdrawalModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= __('new_withdrawal') ?></h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data" action="sarafi.php">
                <div class="modal-body">
                    <div class="form-group">
                        <label><?= __('customer') ?></label>
                        <select class="form-control" name="customer_id" required>
                            <option value=""><?= __('select_customer') ?></option>
                            <?php foreach ($customers as $customer): ?>
                            <option value="<?= $customer['id'] ?>"><?= htmlspecialchars($customer['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><?= __('main_account') ?></label>
                        <select class="form-control" name="main_account_id" required>
                            <option value=""><?= __('select_main_account') ?></option>
                            <?php foreach ($main_accounts as $account): ?>
                            <option value="<?= $account['id'] ?>"><?= htmlspecialchars($account['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><?= __('amount') ?></label>
                        <input type="number" step="0.01" class="form-control" name="amount" required>
                    </div>
                    <div class="form-group">
                        <label><?= __('currency') ?></label>
                        <select class="form-control" name="currency" required>
                            <option value="USD"><?= __('usd') ?></option>
                            <option value="EUR"><?= __('eur') ?></option>
                            <option value="AFS"><?= __('afs') ?></option>
                            <option value="DARHAM"><?= __('darham') ?></option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><?= __('reference_number') ?></label>
                        <input type="text" class="form-control" name="reference" value="<?= uniqid('WDR') ?>" required>
                    </div>
                    <div class="form-group">
                        <label><?= __('notes') ?></label>
                        <textarea class="form-control" name="notes" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label><?= __('receipt_optional') ?></label>
                        <input type="file" class="form-control" name="receipt" accept="image/*,.pdf">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
                    <button type="submit" name="add_withdrawal" class="btn btn-primary"><?= __('submit') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Hawala Modal -->
<div class="modal fade" id="hawalaModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= __('new_hawala_transfer') ?></h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label><?= __('sender') ?></label>
                        <select class="form-control" name="sender_id" required>
                            <option value=""><?= __('select_sender') ?></option>
                            <?php foreach ($customers as $customer): ?>
                            <option value="<?= $customer['id'] ?>"><?= htmlspecialchars($customer['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><?= __('main_account') ?></label>
                        <select class="form-control" name="main_account_id" required>
                            <option value=""><?= __('select_main_account') ?></option>
                            <?php foreach ($main_accounts as $account): ?>
                            <option value="<?= $account['id'] ?>"><?= htmlspecialchars($account['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><?= __('amount_to_send') ?></label>
                        <input type="number" step="0.01" class="form-control" name="send_amount" required>
                    </div>
                    <div class="form-group">
                        <label><?= __('currency') ?></label>
                        <select class="form-control" name="send_currency" required>
                            <option value="USD"><?= __('usd') ?></option>
                            <option value="EUR"><?= __('eur') ?></option>
                            <option value="AFS"><?= __('afs') ?></option>
                            <option value="DARHAM"><?= __('darham') ?></option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><?= __('secret_code') ?></label>
                        <input type="text" class="form-control" name="secret_code" required>
                        <small class="text-muted"><?= __('this_code_will_be_used_by_the_receiver_to_claim_the_transfer') ?></small>
                    </div>
                    <div class="form-group">
                        <label><?= __('commission_amount') ?></label>
                        <input type="number" step="0.01" class="form-control" name="commission_amount" required>
                    </div>
                    <div class="form-group">
                        <label><?= __('commission_currency') ?></label>
                        <select class="form-control" name="commission_currency" required>
                            <option value="USD"><?= __('usd') ?></option>
                            <option value="EUR"><?= __('eur') ?></option>
                            <option value="AFS"><?= __('afs') ?></option>
                            <option value="DARHAM"><?= __('darham') ?></option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><?= __('notes') ?></label>
                        <textarea class="form-control" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
                    <button type="submit" name="add_hawala" class="btn btn-primary"><?= __('submit') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Exchange Modal -->
<div class="modal fade" id="exchangeModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= __('currency_exchange') ?></h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label><?= __('customer') ?></label>
                        <select class="form-control" name="customer_id" required>
                            <option value=""><?= __('select_customer') ?></option>
                            <?php foreach ($customers as $customer): ?>
                            <option value="<?= $customer['id'] ?>"><?= htmlspecialchars($customer['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><?= __('from_amount') ?></label>
                        <input type="number" step="0.01" class="form-control" name="from_amount" required>
                    </div>
                    <div class="form-group">
                        <label><?= __('from_currency') ?></label>
                        <select class="form-control" name="from_currency" required>
                            <option value="USD"><?= __('usd') ?></option>
                            <option value="EUR"><?= __('eur') ?></option>
                            <option value="AFS"><?= __('afs') ?></option>
                            <option value="DARHAM"><?= __('darham') ?></option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><?= __('to_amount') ?></label>
                        <input type="number" step="0.01" class="form-control" name="to_amount" required>
                    </div>
                    <div class="form-group">
                        <label><?= __('to_currency') ?></label>
                        <select class="form-control" name="to_currency" required>
                            <option value="USD"><?= __('usd') ?></option>
                            <option value="EUR"><?= __('eur') ?></option>
                            <option value="AFS"><?= __('afs') ?></option>
                            <option value="DARHAM"><?= __('darham') ?></option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><?= __('exchange_rate') ?></label>
                        <input type="number" step="0.0001" class="form-control" name="rate" required>
                    </div>
                    <div class="form-group">
                        <label><?= __('notes') ?></label>
                        <textarea class="form-control" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
                    <button type="submit" name="add_exchange" class="btn btn-primary"><?= __('submit') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Function to load customer balance
function loadCustomerBalance(customerId) {
    if (!customerId) {
        document.getElementById('customerBalance').innerHTML = '';
        return;
    }
    
    fetch('ajax/get_customer_balance.php?customer_id=' + customerId)
        .then(response => response.json())
        .then(data => {
            let balanceHtml = '';
            if (Object.keys(data).length > 0) {
                for (let currency in data) {
                    balanceHtml += `
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">${currency}</h6>
                            <h5 class="mb-0">${parseFloat(data[currency]).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</h5>
                        </div>`;
                }
            } else {
                balanceHtml = '<p class="text-muted"><?= __('no_active_wallets') ?></p>';
            }
            document.getElementById('customerBalance').innerHTML = balanceHtml;
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('customerBalance').innerHTML = 
                '<div class="alert alert-danger"><?= __('error_loading_balance') ?></div>';
        });
}

// Function to calculate exchange amount
function calculateExchange() {
    const fromAmount = parseFloat(document.querySelector('[name="from_amount"]').value) || 0;
    const rate = parseFloat(document.querySelector('[name="rate"]').value) || 0;
    const toAmount = fromAmount * rate;
    document.querySelector('[name="to_amount"]').value = toAmount.toFixed(2);
}

// Add event listeners
document.querySelector('[name="from_amount"]').addEventListener('input', calculateExchange);
document.querySelector('[name="rate"]').addEventListener('input', calculateExchange);

// Initialize Select2 for customer and main account dropdowns
function initializeSelect2() {
    // Initialize Select2 for all customer dropdowns
    $('select[name="customer_id"], select[name="sender_id"]').each(function() {
        $(this).select2({
            theme: 'bootstrap-5',
            width: '100%',
            dropdownParent: $(this).closest('.modal-body'),
            placeholder: '<?= __("select_customer") ?>',
            allowClear: true
        });
    });

    // Initialize Select2 for all main account dropdowns
    $('select[name="main_account_id"]').each(function() {
        $(this).select2({
            theme: 'bootstrap-5',
            width: '100%',
            dropdownParent: $(this).closest('.modal-body'),
            placeholder: '<?= __("select_main_account") ?>',
            allowClear: true
        });
    });
}

// Initialize Select2 when document is ready
$(document).ready(function() {
    initializeSelect2();
});

// Reinitialize Select2 when any modal is shown
$('.modal').on('shown.bs.modal', function() {
    initializeSelect2();
});
</script> 