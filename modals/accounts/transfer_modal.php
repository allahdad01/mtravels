<!-- Transfer Modal -->
<div class="modal fade modern-modal" id="transferModal" tabindex="-1" role="dialog" aria-labelledby="transferModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="transferModalLabel">
                    <i class="feather icon-exchange mr-2"></i><?= __('transfer_balance') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="transferForm">
                    <!-- CSRF Protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                    
                    <div class="row">
                        <div class="col-md-6">
                    <div class="form-group">
                        <label for="fromAccount"><?= __('from_account') ?></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-light"><i class="feather icon-credit-card"></i></span>
                                    </div>
                        <select class="form-control" id="fromAccount" name="fromAccount" required>
                            <option value=""><?= __('select_account') ?></option>
                            <?php foreach ($mainAccounts as $account): ?>
                            <option value="<?= $account['id'] ?>"><?= htmlspecialchars($account['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                    <div class="form-group">
                        <label for="fromCurrency"><?= __('from_currency') ?></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-light"><i class="feather icon-dollar-sign"></i></span>
                                    </div>
                        <select class="form-control" id="fromCurrency" name="fromCurrency" required>
                            <option value=""><?= __('select_currency') ?></option>
                            <option value="USD"><?= __('usd') ?></option>
                            <option value="AFS"><?= __('afs') ?></option>
                            <option value="EUR"><?= __('eur') ?></option>
                            <option value="DARHAM"><?= __('darham') ?></option>
                            <option value="SAR"><?= __('sar') ?></option>
                        </select>
                    </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="transfer-separator position-relative my-4">
                        <hr>
                        <div class="transfer-icon bg-primary text-white">
                            <i class="feather icon-arrow-down"></i>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                    <div class="form-group">
                        <label for="toAccount"><?= __('to_account') ?></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-light"><i class="feather icon-credit-card"></i></span>
                                    </div>
                        <select class="form-control" id="toAccount" name="toAccount" required>
                            <option value=""><?= __('select_account') ?></option>
                            <?php foreach ($mainAccounts as $account): ?>
                            <option value="<?= $account['id'] ?>"><?= htmlspecialchars($account['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                    <div class="form-group">
                        <label for="toCurrency"><?= __('to_currency') ?></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-light"><i class="feather icon-dollar-sign"></i></span>
                                    </div>
                        <select class="form-control" id="toCurrency" name="toCurrency" required>
                            <option value=""><?= __('select_currency') ?></option>
                            <option value="USD"><?= __('usd') ?></option>
                            <option value="AFS"><?= __('afs') ?></option>
                            <option value="EUR"><?= __('eur') ?></option>
                            <option value="DARHAM"><?= __('darham') ?></option>
                            <option value="SAR"><?= __('sar') ?></option>
                        </select>
                    </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                    <div class="form-group">
                        <label for="amount"><?= __('amount') ?></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-light"><i class="fas fa-coins"></i></span>
                                    </div>
                        <input type="number" class="form-control" id="amount" name="amount" step="0.01" required>
                    </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                    <div class="form-group">
                        <label for="exchangeRate"><?= __('exchange_rate') ?></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-light"><i class="feather icon-percent"></i></span>
                            </div>
                            <input type="number" class="form-control" id="exchangeRate" name="exchangeRate" step="0.01" required>
                            <div class="input-group-append">
                                <span class="input-group-text bg-light" id="transferFormulaBadge" style="font-weight:700;font-size:16px;">Ã—</span>
                            </div>
                        </div>
                        <small id="transferRateHelp" class="form-text text-muted" style="font-size:11px;margin-top:4px;"></small>
                    </div>
                    <div class="form-group">
                        <div class="bg-light rounded p-2 text-center" id="transferConvertedPreview" style="font-size:13px;display:none;">
                            <span id="transferConvertedText">0.00</span>
                        </div>
                    </div>
                        </div>
                    </div>
                    
                    <div class="form-group mb-0">
                        <label for="description"><?= __('description') ?></label>
                        <textarea class="form-control" id="description" name="description" rows="2" placeholder="<?= __('enter_transaction_details') ?>"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                    <i class="feather icon-x mr-1"></i><?= __('cancel') ?>
                </button>
                <button type="button" class="btn btn-primary" id="transferBtn">
                    <i class="feather icon-check mr-1"></i><?= __('transfer') ?>
                </button>
            </div>
        </div>
    </div>
</div>
