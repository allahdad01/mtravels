<!-- Edit Transaction Modal -->
<div class="modal fade modern-modal" id="editTransactionModal" tabindex="-1" aria-labelledby="editTransactionModalLabel" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="editTransactionModalLabel">
                    <i class="feather icon-edit mr-2"></i><?= __('edit_fund_transaction') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="editTransactionForm">
                    <!-- CSRF Protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                    
                    <input type="hidden" id="editTransactionId" name="transaction_id">
                    <input type="hidden" id="editTransactionType" name="transaction_type">
                    <input type="hidden" id="originalAmount" name="original_amount">
                    <input type="hidden" id="originalType" name="original_type">
                    <input type="hidden" id="editTransactionCurrencyHidden" name="currency">
                    <input type="hidden" id="editTransactionTypeHidden" name="type">

                    <!-- Transaction Details Section -->
                    <div class="form-section">
                        <div class="form-section-title"><?= __('transaction_details') ?></div>

                        <div class="form-group mb-3">
                            <label for="editTransactionDate"><?= __('transaction_date') ?></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-light"><i class="feather icon-calendar"></i></span>
                                </div>
                                <input type="datetime-local" class="form-control" id="editTransactionDate" name="transaction_date" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="form-group mb-md-0">
                                    <label for="editTransactionAmount"><?= __('amount') ?></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text bg-light"><i class="fas fa-coins"></i></span>
                                        </div>
                                        <input type="number" step="0.01" class="form-control" id="editTransactionAmount" name="amount" required>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-md-0">
                                    <label for="editTransactionCurrency"><?= __('currency') ?></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text bg-light"><i class="feather icon-dollar-sign"></i></span>
                                        </div>
                                            <select class="form-control" id="editTransactionCurrency" name="currency" required disabled>
                                                <option value="USD"><?= __('usd') ?> ($)</option>
                                                <option value="AFS"><?= __('afs') ?> (Ø‹)</option>
                                                <option value="EUR"><?= __('eur') ?> (â‚¬)</option>
                                                <option value="DARHAM"><?= __('darham') ?> (AED)</option>
                                                <option value="SAR"><?= __('sar') ?></option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        <div class="form-group mb-3">
                            <label for="editTransactionTypeSelect"><?= __('type') ?></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-light"><i class="feather icon-repeat"></i></span>
                                </div>
                                <select class="form-control" id="editTransactionTypeSelect" name="type" required disabled>
                                    <option value="credit"><?= __('credit') ?> (<?= __('add_funds') ?>)</option>
                                    <option value="debit"><?= __('debit') ?> (<?= __('remove_funds') ?>)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Information Section -->
                    <div class="form-section">
                        <div class="form-section-title"><?= __('additional_information') ?></div>

                        <div class="form-group mb-3">
                            <label for="editTransactionReceipt"><?= __('receipt_number') ?></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-light"><i class="feather icon-file-text"></i></span>
                                </div>
                                <input type="text" class="form-control" id="editTransactionReceipt" name="receipt">
                          </div>
                        </div>

                        <div class="form-group mb-0">
                            <label for="editTransactionDescription"><?= __('description') ?>/<?= __('remarks') ?></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-light"><i class="feather icon-message-square"></i></span>
                                </div>
                                <textarea class="form-control" id="editTransactionDescription" name="description" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                    <i class="feather icon-x mr-1"></i><?= __('cancel') ?>
                </button>
                <button type="button" class="btn btn-primary" id="saveEditTransactionBtn">
                    <i class="feather icon-save mr-1"></i><?= __('save_changes') ?>
                </button>
            </div>
        </div>
    </div>
</div>
