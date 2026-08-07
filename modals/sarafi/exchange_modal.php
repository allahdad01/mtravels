<!-- Sarafi Exchange Modal (standalone, embedded by Payments Journal) -->
<div class="modal fade" id="sarafiExchangeModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="feather icon-repeat mr-2"></i><?= __('currency_exchange') ?></h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="sarafiExchangeForm">
                <input type="hidden" name="add_exchange" value="1">
                <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
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
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-check mr-1"></i><?= __('submit') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
