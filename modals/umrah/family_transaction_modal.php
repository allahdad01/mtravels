<!-- Family Transaction Modal -->
<div class="modal fade" id="familyTransactionModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="feather icon-credit-card mr-2"></i><?= __('family_transaction_management') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="max-height: 75vh; overflow-y: auto;">
                <!-- Family Info Card -->
                <div class="card mb-4 border-primary">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-muted mb-2"><?= __('family_details') ?></h6>
                                <p class="mb-1"><strong><?= __('family_head') ?>:</strong> <span id="familyTransactionHead"></span></p>
                                <p class="mb-1"><strong><?= __('package') ?>:</strong> <span id="familyTransactionPackage"></span></p>
                                <p class="mb-1"><strong><?= __('total_members') ?>:</strong> <span id="familyTransactionMembers"></span></p>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-muted mb-2"><?= __('financial_summary') ?></h6>
                                <div class="d-flex justify-content-between mb-1">
                                    <span><?= __('total_price') ?>:</span>
                                    <strong id="familyTotalPrice"></strong>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-success"><?= __('paid') ?>:</span>
                                    <strong class="text-success" id="familyTotalPaid"></strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-danger"><?= __('due') ?>:</span>
                                    <strong class="text-danger" id="familyTotalDue"></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Transaction Form -->
                <div class="card mb-4">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><?= __('add_family_transactions') ?></h6>
                        <button type="button" class="btn btn-sm btn-primary" data-toggle="collapse" data-target="#familyTransactionForm">
                            <i class="feather icon-plus"></i> <?= __('new_transaction') ?>
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div id="familyTransactionForm" class="collapse">
                            <div class="card border-primary">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0"><?= __('transaction_details') ?></h6>
                                </div>
                                <div class="card-body">
                                    <form id="familyTransactionFormData">
                    <!-- CSRF Protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                                        <input type="hidden" id="familyTransactionFamilyId" name="family_id">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="familyPaymentDate">
                                                        <i class="feather icon-calendar mr-1"></i><?= __('payment_date') ?>
                                                    </label>
                                                    <input type="date" class="form-control" id="familyPaymentDate" name="payment_date" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="familyTransactionTo">
                                                        <i class="feather icon-user mr-1"></i><?= __('transaction_to') ?>
                                                    </label>
                                                    <select class="form-control" id="familyTransactionTo" name="transaction_to" required>
                                                        <option value="Internal Account"><?= __('internal_account') ?></option>
                                                        <option value="Bank"><?= __('bank') ?></option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="familyPaymentCurrency">
                                                        <i class="feather icon-dollar-sign mr-1"></i><?= __('currency') ?>
                                                    </label>
                                                    <select class="form-control" id="familyPaymentCurrency" name="payment_currency" required>
                                                        <option value=""><?= __('select_currency') ?></option>
                                                        <option value="USD">USD</option>
                                                        <option value="AFS">AFS</option>
                                                        <option value="EUR">EUR</option>
                                                        <option value="DARHAM">DARHAM</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                 <div class="form-group" id="familyReceiptNumberField" style="display: none;">
                                                     <label class="text-info">
                                                         <i class="feather icon-alert-circle mr-1"></i><?= __('receipt_numbers_per_member') ?? 'Receipt Numbers (per member)' ?>
                                                     </label>
                                                     <small class="d-block text-muted mb-2">Each member will have their own receipt number below</small>
                                                 </div>
                                             </div>
                                            </div>
                                            <div class="row">
                                             <div class="col-md-6">
                                                 <div class="form-group" id="familyMainAccountField" style="display: none;">
                                                     <label for="familyMainAccount">
                                                         <i class="feather icon-briefcase mr-1"></i><?= __('main_account') ?>
                                                     </label>
                                                     <select class="form-control" id="familyMainAccount" name="main_account_id">
                                                         <option value=""><?= __('select_main_account') ?></option>
                                                         <!-- Options will be loaded dynamically -->
                                                     </select>
                                                 </div>
                                             </div>
                                             <div class="col-md-6">
                                                 <div class="form-group" id="familyExchangeRateField" style="display: none;">
                                                     <label id="familyExchangeRateLabel" for="familyExchangeRate">
                                                         <i class="feather icon-refresh-cw mr-1"></i><?= __('exchange_rate') ?>
                                                     </label>
                                                     <input type="number" class="form-control" id="familyExchangeRate"
                                                            name="exchange_rate" step="0.01" min="0.01" placeholder="0.00">
                                                 </div>
                                             </div>
                                            </div>

                                        <div class="form-group">
                                            <label for="familyPaymentDescription">
                                                <i class="feather icon-file-text mr-1"></i><?= __('description') ?>
                                            </label>
                                            <textarea class="form-control" id="familyPaymentDescription"
                                                      name="payment_description" rows="2"
                                                      placeholder="Enter payment description"></textarea>
                                        </div>

                                        <!-- Member Payment Inputs -->
                                         <div class="mt-4">
                                             <h6 class="mb-3"><?= __('member_payments') ?></h6>
                                             <div class="alert alert-info" id="bankReceiptAlert" style="display: none;">
                                                 <i class="feather icon-info mr-2"></i>
                                                 <strong>Receipt Numbers:</strong> You can enter a separate receipt number for each member below.
                                             </div>
                                             <div id="familyMemberPayments" class="row">
                                                 <!-- Member payment inputs will be loaded here -->
                                             </div>
                                         </div>

                                        <div class="text-right mt-3">
                                            <button type="button" class="btn btn-secondary" data-toggle="collapse"
                                                    data-target="#familyTransactionForm">
                                                <i class="feather icon-x mr-1"></i><?= __('cancel') ?>
                                            </button>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="feather icon-check mr-1"></i><?= __('add_transactions') ?>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Family Members and Transactions Table -->
                <div class="card">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="feather icon-users mr-2"></i><?= __('family_members_transactions') ?></h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th><?= __('member') ?></th>
                                        <th><?= __('sold_price') ?></th>
                                        <th><?= __('paid') ?></th>
                                        <th><?= __('due') ?></th>
                                        <th><?= __('transactions') ?></th>
                                    </tr>
                                </thead>
                                <tbody id="familyMembersTransactionTable">
                                    <!-- Family members will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="feather icon-x mr-1"></i><?= __('close') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Show/Hide Receipt Number and Main Account fields based on Transaction To selection
$(document).ready(function() {
    $('#familyTransactionTo').change(function() {
        const transactionTo = $(this).val();

        if (transactionTo === 'Bank' || transactionTo === 'Internal Account') {
            $('#familyReceiptNumberField').slideDown();
        } else {
            $('#familyReceiptNumberField').slideUp();
        }

        if (transactionTo === 'Bank') {
            // Check if supplier is internal, then load main accounts
            checkAndLoadFamilyMainAccounts();
        } else {
            $('#familyMainAccountField').slideUp();
        }
    });
});

// Function to check supplier type and load main accounts if internal
function checkAndLoadFamilyMainAccounts() {
    const familyId = $('#familyTransactionFamilyId').val();
    if (!familyId) return;
    
    $.ajax({
        url: '../api/umrah/get_family_supplier_type.php',
        type: 'GET',
        data: { family_id: familyId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const supplierType = response.supplier_type;
                if (supplierType === 'Internal') {
                    // Load main accounts
                    loadFamilyMainAccounts();
                    $('#familyMainAccountField').slideDown();
                } else {
                    $('#familyMainAccountField').slideUp();
                }
            }
        },
        error: function() {
            $('#familyMainAccountField').slideUp();
        }
    });
}

// Function to load main accounts
function loadFamilyMainAccounts() {
    $.ajax({
        url: '../api/accounts/get_main_accounts.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const accountSelect = $('#familyMainAccount');
                accountSelect.html('<option value=""><?= __('select_main_account') ?></option>');
                
                response.accounts.forEach(function(account) {
                    accountSelect.append(`<option value="${account.id}">${account.name}</option>`);
                });
            }
        },
        error: function() {
            console.error('Failed to load main accounts');
        }
    });
}
</script>
