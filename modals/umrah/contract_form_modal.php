<!-- Contract Modal — Phase 24: contract + inventory + rates -->
<div class="modal fade" id="contractFormModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header" style="border-bottom: 1px solid #eef2f7;">
                <h5 class="modal-title"><i class="feather icon-file-text mr-2" style="color: #0e7490;"></i><?= __('contract') ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <form id="contractForm">
                <div class="modal-body" style="background: #f8fafc; max-height: 72vh; overflow-y: auto;">
                    <input type="hidden" name="id" id="cfContractId">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group"><label><?= __('hotel') ?> *</label>
                                <select class="form-control" name="hotel_ids[]" id="cfHotels" multiple size="5" required></select>
                                <small class="text-muted"><?= __('select_multiple_hotels') ?></small></div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group"><label><?= __('contract_number') ?> *</label>
                                <input type="text" class="form-control" name="contract_number" id="cfNumber" required></div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group"><label><?= __('supplier') ?></label>
                                <select class="form-control" name="supplier_id" id="cfSupplier"></select></div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group"><label><?= __('scope') ?></label>
                                <select class="form-control" name="scope" id="cfScope">
                                    <option value="specific_rooms"><?= __('specific_rooms') ?></option>
                                    <option value="floor"><?= __('floor') ?></option>
                                    <option value="entire_hotel"><?= __('entire_hotel') ?></option>
                                </select></div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group"><label><?= __('contract_type') ?></label>
                                <select class="form-control" name="contract_type" id="cfType">
                                    <option value="period"><?= __('contract_type_period') ?></option>
                                    <option value="per_trip"><?= __('contract_type_per_trip') ?></option>
                                </select>
                                <small class="text-muted" id="cfTypeHelp"><?= __('contract_type_period_help') ?></small></div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group"><label><?= __('valid_from') ?></label>
                                <input type="date" class="form-control" name="valid_from" id="cfValidFrom"></div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group"><label><?= __('valid_to') ?></label>
                                <input type="date" class="form-control" name="valid_to" id="cfValidTo"></div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group"><label><?= __('status') ?></label>
                                <select class="form-control" name="status" id="cfStatus">
                                    <option value="active"><?= __('active') ?></option>
                                    <option value="inactive"><?= __('inactive') ?></option>
                                    <option value="expired"><?= __('expired') ?></option>
                                </select></div>
                        </div>
                        <div class="col-12 row" id="cfAmountWrap" style="display:none;">
                            <div class="col-md-4">
                                <div class="form-group"><label><?= __('contract_amount') ?> *</label>
                                    <input type="number" step="0.001" min="0" class="form-control" name="contract_amount" id="cfAmount"></div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group"><label><?= __('currency') ?></label>
                                    <select class="form-control" name="contract_currency" id="cfAmountCurrency">
                                        <option value="USD">USD</option>
                                        <option value="AFS">AFS</option>
                                        <option value="EUR">EUR</option>
                                        <option value="DARHAM">DARHAM</option>
                                        <option value="SAR">SAR</option>
                                    </select></div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group"><label><?= __('payment_terms') ?></label>
                                <textarea class="form-control" name="payment_terms" id="cfPaymentTerms" rows="2"></textarea></div>
                        </div>
                        <div class="col-12">
                            <div class="form-group"><label><?= __('notes') ?></label>
                                <textarea class="form-control" name="notes" id="cfNotes" rows="2"></textarea></div>
                        </div>
                    </div>

                    <div id="cfRatesSection">
                        <h6 class="mb-2" style="font-size: 0.9rem;"><i class="feather icon-dollar-sign mr-1" style="color: #0e7490;"></i><?= __('rates') ?></h6>
                        <div id="cfRatesWrap"></div>
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
