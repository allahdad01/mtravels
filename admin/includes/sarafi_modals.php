<?php
// Include database connection
require_once __DIR__ . '/../../includes/db.php';

// Include language helper
require_once __DIR__ . '/../../includes/language_helpers.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Fetch customers if not already fetched
if (!isset($customers)) {
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE status = 'active' AND tenant_id = ? And branch_id = ? ORDER BY name ASC");
    $stmt->execute([$tenant_id, $branch_id]);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch main accounts
$stmt = $pdo->prepare("SELECT * FROM main_account WHERE status = 'active' AND tenant_id = ? And branch_id = ? ORDER BY name ASC");
$stmt->execute([$tenant_id, $branch_id]);
$main_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
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

/* Modal section styling */
.modal-section {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 16px;
    border: 1px solid #e9ecef;
}
.modal-section-title {
    font-size: 13px;
    font-weight: 600;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.modal-section-title i {
    font-size: 16px;
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
            <form method="POST" action="handlers/create_customer.php" id="customerForm">
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
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="feather icon-plus-circle mr-2"></i><?= __('new_deposit') ?></h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data" action="sarafi.php">
                <div class="modal-body">
                    <div class="modal-section">
                        <div class="modal-section-title"><i class="feather icon-user"></i><?= __('customer_information') ?></div>
                        <div class="row">
                            <div class="col-md-8">
                                <div class="form-group">
                                    <label><?= __('customer') ?></label>
                                    <select class="form-control" name="customer_id" required>
                                        <option value=""><?= __('select_customer') ?></option>
                                        <?php foreach ($customers as $customer): ?>
                                        <option value="<?= $customer['id'] ?>"><?= htmlspecialchars($customer['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label><?= __('currency') ?></label>
                                    <select class="form-control" name="currency" required>
                                        <option value="USD"><?= __('usd') ?></option>
                                        <option value="EUR"><?= __('eur') ?></option>
                                        <option value="AFS"><?= __('afs') ?></option>
                                        <option value="DARHAM"><?= __('darham') ?></option>
                                        <option value="SAR"><?= __('sar') ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><?= __('main_account') ?></label>
                                    <select class="form-control" name="main_account_id" required>
                                        <option value=""><?= __('select_main_account') ?></option>
                                        <?php foreach ($main_accounts as $account): ?>
                                        <option value="<?= $account['id'] ?>"><?= htmlspecialchars($account['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><?= __('amount') ?></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="feather icon-dollar-sign"></i></span>
                                        </div>
                                        <input type="number" step="0.01" class="form-control" name="amount" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-section">
                        <div class="modal-section-title"><i class="feather icon-file-text"></i><?= __('details') ?></div>
                        <div class="form-group">
                            <label><?= __('reference_number') ?></label>
                            <input type="text" class="form-control" name="reference" value="<?= uniqid('DEP') ?>" required>
                        </div>
                        <div class="form-group">
                            <label><?= __('notes') ?></label>
                            <textarea class="form-control" name="notes" rows="2"></textarea>
                        </div>
                        <div class="form-group mb-0">
                            <label><?= __('receipt_optional') ?></label>
                            <div class="custom-file">
                                <input type="file" class="form-control" name="receipt" accept="image/*,.pdf">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
                    <button type="submit" name="add_deposit" class="btn btn-primary">
                        <i class="feather icon-check mr-1"></i><?= __('submit') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Withdrawal Modal -->
<div class="modal fade" id="withdrawalModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="feather icon-minus-circle mr-2"></i><?= __('new_withdrawal') ?></h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data" action="sarafi.php">
                <div class="modal-body">
                    <div class="modal-section">
                        <div class="modal-section-title"><i class="feather icon-user"></i><?= __('customer_information') ?></div>
                        <div class="row">
                            <div class="col-md-8">
                                <div class="form-group">
                                    <label><?= __('customer') ?></label>
                                    <select class="form-control" name="customer_id" required>
                                        <option value=""><?= __('select_customer') ?></option>
                                        <?php foreach ($customers as $customer): ?>
                                        <option value="<?= $customer['id'] ?>"><?= htmlspecialchars($customer['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label><?= __('currency') ?></label>
                                    <select class="form-control" name="currency" required>
                                        <option value="USD"><?= __('usd') ?></option>
                                        <option value="EUR"><?= __('eur') ?></option>
                                        <option value="AFS"><?= __('afs') ?></option>
                                        <option value="DARHAM"><?= __('darham') ?></option>
                                        <option value="SAR"><?= __('sar') ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><?= __('main_account') ?></label>
                                    <select class="form-control" name="main_account_id" required>
                                        <option value=""><?= __('select_main_account') ?></option>
                                        <?php foreach ($main_accounts as $account): ?>
                                        <option value="<?= $account['id'] ?>"><?= htmlspecialchars($account['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><?= __('amount') ?></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="feather icon-dollar-sign"></i></span>
                                        </div>
                                        <input type="number" step="0.01" class="form-control" name="amount" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-section">
                        <div class="modal-section-title"><i class="feather icon-file-text"></i><?= __('details') ?></div>
                        <div class="form-group">
                            <label><?= __('reference_number') ?></label>
                            <input type="text" class="form-control" name="reference" value="<?= uniqid('WDR') ?>" required>
                        </div>
                        <div class="form-group">
                            <label><?= __('notes') ?></label>
                            <textarea class="form-control" name="notes" rows="2"></textarea>
                        </div>
                        <div class="form-group mb-0">
                            <label><?= __('receipt_optional') ?></label>
                            <div class="custom-file">
                                <input type="file" class="form-control" name="receipt" accept="image/*,.pdf">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
                    <button type="submit" name="add_withdrawal" class="btn btn-primary">
                        <i class="feather icon-check mr-1"></i><?= __('submit') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Hawala Modal -->
<div class="modal fade" id="hawalaModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="feather icon-send mr-2"></i><?= __('new_hawala_transfer') ?></h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="modal-section">
                        <div class="modal-section-title"><i class="feather icon-user"></i><?= __('sender_information') ?></div>
                        <div class="row">
                            <div class="col-md-8">
                                <div class="form-group">
                                    <label><?= __('sender') ?></label>
                                    <select class="form-control" name="sender_id" required>
                                        <option value=""><?= __('select_sender') ?></option>
                                        <?php foreach ($customers as $customer): ?>
                                        <option value="<?= $customer['id'] ?>"><?= htmlspecialchars($customer['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label><?= __('send_currency') ?></label>
                                    <select class="form-control" name="send_currency" required>
                                        <option value="USD"><?= __('usd') ?></option>
                                        <option value="EUR"><?= __('eur') ?></option>
                                        <option value="AFS"><?= __('afs') ?></option>
                                        <option value="DARHAM"><?= __('darham') ?></option>
                                        <option value="SAR"><?= __('sar') ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><?= __('main_account') ?></label>
                                    <select class="form-control" name="main_account_id" required>
                                        <option value=""><?= __('select_main_account') ?></option>
                                        <?php foreach ($main_accounts as $account): ?>
                                        <option value="<?= $account['id'] ?>"><?= htmlspecialchars($account['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label><?= __('amount_to_send') ?></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="feather icon-dollar-sign"></i></span>
                                        </div>
                                        <input type="number" step="0.01" class="form-control" name="send_amount" required>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label><?= __('secret_code') ?></label>
                                    <input type="text" class="form-control" name="secret_code" required>
                                </div>
                            </div>
                        </div>
                        <small class="text-muted d-block"><?= __('this_code_will_be_used_by_the_receiver_to_claim_the_transfer') ?></small>
                    </div>
                    <div class="modal-section">
                        <div class="modal-section-title"><i class="feather icon-percent"></i><?= __('commission') ?></div>
                        <div class="form-group mb-0">
                            <label><?= __('commission_amount') ?> <small class="text-muted">(<?= __('in_same_currency_as_send') ?>)</small></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="feather icon-dollar-sign"></i></span>
                                </div>
                                <input type="number" step="0.01" class="form-control" id="hawalaCommissionAmount" name="commission_amount" required>
                            </div>
                        </div>
                    </div>
                    <!-- Live breakdown summary -->
                    <div class="modal-section" style="background:#e8f5e9;border-color:#c8e6c9;">
                        <div class="modal-section-title" style="color:#2e7d32;"><i class="feather icon-info"></i><?= __('summary') ?></div>
                        <div class="d-flex justify-content-between align-items-center mb-1" style="font-size:14px;">
                            <span><?= __('customer_pays') ?></span>
                            <span id="hawalaBreakdownSend" class="font-weight-bold">0.00</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-1" style="font-size:14px;color:#e65100;">
                            <span><?= __('commission_deducted') ?></span>
                            <span id="hawalaBreakdownCommission" class="font-weight-bold">− 0.00</span>
                        </div>
                        <hr style="margin:6px 0;border-color:#c8e6c9;">
                        <div class="d-flex justify-content-between align-items-center" style="font-size:15px;">
                            <span class="font-weight-bold"><?= __('receiver_gets') ?></span>
                            <span id="hawalaBreakdownNet" class="font-weight-bold" style="color:#2e7d32;font-size:16px;">0.00</span>
                        </div>
                    </div>
                    <div class="modal-section">
                        <div class="modal-section-title"><i class="feather icon-file-text"></i><?= __('notes') ?></div>
                        <div class="form-group mb-0">
                            <textarea class="form-control" name="notes" rows="2" placeholder="<?= __('optional_notes') ?>"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
                    <button type="submit" name="add_hawala" class="btn btn-primary">
                        <i class="feather icon-check mr-1"></i><?= __('submit') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Exchange Modal -->
<div class="modal fade" id="exchangeModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="feather icon-repeat mr-2"></i><?= __('currency_exchange') ?></h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="modal-section">
                        <div class="modal-section-title"><i class="feather icon-user"></i><?= __('customer') ?></div>
                        <div class="form-group mb-0">
                            <select class="form-control" name="customer_id" required>
                                <option value=""><?= __('select_customer') ?></option>
                                <?php foreach ($customers as $customer): ?>
                                <option value="<?= $customer['id'] ?>"><?= htmlspecialchars($customer['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row align-items-center">
                        <div class="col-md-5">
                            <div class="modal-section">
                                <div class="modal-section-title"><i class="feather icon-arrow-left"></i><?= __('from') ?></div>
                                <div class="form-group">
                                    <label><?= __('currency') ?></label>
                                    <select class="form-control" id="exchangeFromCurrency" name="from_currency" required>
                                        <option value="USD"><?= __('usd') ?></option>
                                        <option value="EUR"><?= __('eur') ?></option>
                                        <option value="AFS"><?= __('afs') ?></option>
                                        <option value="DARHAM"><?= __('darham') ?></option>
                                        <option value="SAR"><?= __('sar') ?></option>
                                    </select>
                                </div>
                                <div class="form-group mb-0">
                                    <label><?= __('amount') ?></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="feather icon-dollar-sign"></i></span>
                                        </div>
                                        <input type="number" step="0.01" class="form-control" id="exchangeFromAmount" name="from_amount" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 text-center">
                            <div id="exchangeFormulaBadge" style="font-size:28px;font-weight:700;color:#6c757d;">×</div>
                            <div style="font-size:11px;color:#6c757d;margin-top:-4px;"><?= __('formula') ?></div>
                            <div style="margin-top:12px;">
                                <input type="number" step="0.0001" class="form-control text-center" id="exchangeRate" name="rate" required placeholder="<?= __('rate') ?>" style="font-size:14px;">
                                <small id="exchangeRateHelp" class="form-text text-muted" style="font-size:11px;margin-top:4px;display:block;">1 USD = 0.92 EUR</small>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="modal-section">
                                <div class="modal-section-title"><i class="feather icon-arrow-right"></i><?= __('to') ?></div>
                                <div class="form-group">
                                    <label><?= __('currency') ?></label>
                                    <select class="form-control" id="exchangeToCurrency" name="to_currency" required>
                                        <option value="USD"><?= __('usd') ?></option>
                                        <option value="EUR"><?= __('eur') ?></option>
                                        <option value="AFS"><?= __('afs') ?></option>
                                        <option value="DARHAM"><?= __('darham') ?></option>
                                        <option value="SAR"><?= __('sar') ?></option>
                                    </select>
                                </div>
                                <div class="form-group mb-0">
                                    <label><?= __('amount') ?></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="feather icon-dollar-sign"></i></span>
                                        </div>
                                        <input type="number" step="0.01" class="form-control" id="exchangeToAmount" name="to_amount" readonly style="background:#e9ecef;">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group mb-0">
                        <label><?= __('notes') ?> <small class="text-muted">(<?= __('optional') ?>)</small></label>
                        <textarea class="form-control" name="notes" rows="1" placeholder="<?= __('optional_notes') ?>"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
                    <button type="submit" name="add_exchange" class="btn btn-primary">
                        <i class="feather icon-check mr-1"></i><?= __('submit') ?>
                    </button>
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

// Exchange auto-calculate with multiply/divide detection
function exchangeFormula(from, to) {
    const divide = ['AFS->USD', 'AFS->EUR', 'AFS->AED', 'AED->USD', 'AED->EUR', 'AFS->SAR', 'SAR->USD', 'SAR->EUR'];
    return divide.includes(from + '->' + to) ? 'divide' : 'multiply';
}
function updateExchangeFormulaBadge() {
    const fromCur = $('#exchangeFromCurrency').val();
    const toCur = $('#exchangeToCurrency').val();
    const formula = exchangeFormula(fromCur, toCur);
    $('#exchangeFormulaBadge').text(formula === 'divide' ? '÷' : '×');
}
$(document).on('input', '#exchangeFromAmount, #exchangeRate', function() {
    const fromAmt = parseFloat($('#exchangeFromAmount').val()) || 0;
    const rate = parseFloat($('#exchangeRate').val()) || 0;
    const fromCur = $('#exchangeFromCurrency').val();
    const toCur = $('#exchangeToCurrency').val();
    const formula = exchangeFormula(fromCur, toCur);
    const toAmt = formula === 'divide' ? fromAmt / rate : fromAmt * rate;
    $('#exchangeToAmount').val(toAmt.toFixed(2));
});
const sampleRates = {
    'USD->EUR': 0.92, 'EUR->USD': 1.09,
    'USD->AFS': 72.5, 'AFS->USD': 72.5,
    'USD->AED': 3.67, 'AED->USD': 3.67,
    'EUR->AFS': 78.8, 'AFS->EUR': 78.8,
    'EUR->AED': 3.99, 'AED->EUR': 3.99,
    'AED->AFS': 19.75, 'AFS->AED': 19.75,
    'USD->SAR': 3.75, 'SAR->USD': 3.75,
    'EUR->SAR': 4.07, 'SAR->EUR': 4.07,
    'AED->SAR': 1.02, 'SAR->AED': 1.02,
    'AFS->SAR': 18.67, 'SAR->AFS': 18.67,
};
function updateExchangeRateHelp() {
    const fromCur = $('#exchangeFromCurrency').val();
    const toCur = $('#exchangeToCurrency').val();
    if (!fromCur || !toCur) { $('#exchangeRateHelp').text(''); return; }
    const dividePairs = ['AFS->USD', 'AFS->EUR', 'AFS->AED', 'AED->USD', 'AED->EUR', 'AFS->SAR', 'SAR->USD', 'SAR->EUR'];
    const isDivide = dividePairs.includes(fromCur + '->' + toCur);
    const rate = sampleRates[fromCur + '->' + toCur];
    if (!rate) { $('#exchangeRateHelp').text(''); return; }
    if (isDivide) {
        $('#exchangeRateHelp').text('e.g. 1 ' + toCur + ' = ' + rate.toFixed(2) + ' ' + fromCur + ' → enter ' + rate.toFixed(2));
    } else {
        $('#exchangeRateHelp').text('e.g. 1 ' + fromCur + ' = ' + rate.toFixed(2) + ' ' + toCur + ' → enter ' + rate.toFixed(2));
    }
}
$(document).on('change', '#exchangeFromCurrency, #exchangeToCurrency', function() {
    updateExchangeFormulaBadge();
    updateExchangeRateHelp();
    $('#exchangeFromAmount').trigger('input');
});
$(document).ready(function() {
    updateExchangeRateHelp();
});

// Hawala live breakdown
function updateHawalaBreakdown() {
    const container = $('#hawalaModal');
    const sendAmt = parseFloat(container.find('input[name="send_amount"]').val()) || 0;
    const commission = parseFloat(container.find('input[name="commission_amount"]').val()) || 0;
    const net = Math.max(0, sendAmt - commission);
    container.find('#hawalaBreakdownSend').text(sendAmt.toFixed(2));
    container.find('#hawalaBreakdownCommission').html('− ' + commission.toFixed(2));
    container.find('#hawalaBreakdownNet').text(net.toFixed(2));
}
$(document).on('input', '#hawalaModal input[name="send_amount"], #hawalaModal input[name="commission_amount"]', updateHawalaBreakdown);
$('#hawalaModal').on('shown.bs.modal', updateHawalaBreakdown);

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