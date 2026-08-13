<!-- Transport Contract Modal — Phase 24+: amount-based contracts
     (period | per_trip) — the contracted amount is divided among the
     trip's members at fulfillment time. -->
<div class="modal fade" id="transportContractModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header" style="border-bottom: 1px solid #eef2f7;">
                <h5 class="modal-title"><i class="feather icon-truck mr-2" style="color: #0e7490;"></i><?= __('transport_contract') ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <form id="transportContractForm">
                <div class="modal-body" style="background: #f8fafc; max-height: 72vh; overflow-y: auto;">
                    <input type="hidden" name="id" id="tcContractId">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group"><label><?= __('contract_number') ?> *</label>
                                <input type="text" class="form-control" name="contract_number" id="tcNumber" required></div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group"><label><?= __('supplier') ?></label>
                                <select class="form-control" name="supplier_id" id="tcSupplier"></select></div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group"><label><?= __('contract_type') ?></label>
                                <select class="form-control" name="contract_type" id="tcType">
                                    <option value="per_trip"><?= __('contract_type_per_trip') ?></option>
                                    <option value="period"><?= __('contract_type_period') ?></option>
                                </select>
                                <small class="text-muted" id="tcTypeHelp"><?= __('contract_type_per_trip_help') ?></small></div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group"><label><?= __('contract_amount') ?> *</label>
                                <input type="number" step="0.001" min="0" class="form-control" name="contract_amount" id="tcAmount" required></div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group"><label><?= __('currency') ?></label>
                                <select class="form-control" name="contract_currency" id="tcCurrency">
                                    <option value="USD">USD</option>
                                    <option value="AFS">AFS</option>
                                    <option value="EUR">EUR</option>
                                    <option value="DARHAM">DARHAM</option>
                                    <option value="SAR">SAR</option>
                                </select></div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group"><label><?= __('valid_from') ?></label>
                                <input type="date" class="form-control" name="valid_from" id="tcValidFrom"></div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group"><label><?= __('valid_to') ?></label>
                                <input type="date" class="form-control" name="valid_to" id="tcValidTo"></div>
                        </div>
                        <div class="col-12">
                            <div class="form-group"><label><?= __('status') ?></label>
                                <select class="form-control" name="status" id="tcStatus">
                                    <option value="active"><?= __('active') ?></option>
                                    <option value="inactive"><?= __('inactive') ?></option>
                                    <option value="expired"><?= __('expired') ?></option>
                                </select></div>
                        </div>
                        <div class="col-12">
                            <div class="form-group"><label><?= __('payment_terms') ?></label>
                                <textarea class="form-control" name="payment_terms" id="tcPaymentTerms" rows="2"></textarea></div>
                        </div>
                        <div class="col-12">
                            <div class="form-group"><label><?= __('notes') ?></label>
                                <textarea class="form-control" name="notes" id="tcNotes" rows="2"></textarea></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid #eef2f7;">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
                    <button type="submit" class="btn btn-primary"><i class="feather icon-save mr-1"></i><?= __('save') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
