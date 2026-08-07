<!-- Add Supplier Modal -->
<div class="modal fade" id="addSupplierModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form id="addSupplierForm">
                    <!-- CSRF Protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-light border-0">
                    <h5 class="modal-title">
                        <i class="feather icon-plus-circle text-primary mr-2"></i>
                        <?= __('add_new_supplier') ?>
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label class="form-label"><?= __('name') ?></label>
                        <input type="text" class="form-control" id="supplierName" name="name" required>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label"><?= __('contact_person') ?></label>
                        <input type="text" class="form-control" id="contactPerson" name="contact_person">
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="form-label"><?= __('phone') ?></label>
                                <input type="text" class="form-control" id="supplierPhone" name="phone" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="form-label"><?= __('email') ?></label>
                                <input type="email" class="form-control" id="supplierEmail" name="email">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="form-label"><?= __('currency') ?></label>
                                <select class="form-control" id="currency" name="currency" required>
                                    <option value="AFS"><?= __('afs') ?></option>
                                    <option value="USD"><?= __('usd') ?></option>
                                    <option value="EUR"><?= __('eur') ?></option>
                                    <option value="DARHAM"><?= __('darham') ?></option>
                                    <option value="SAR"><?= __('sar') ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="form-label"><?= __('balance') ?></label>
                                <input type="number" step="0.01" class="form-control" id="supplierBalance" 
                                       name="balance" value="0.00" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="form-label"><?= __('supplier_type') ?></label>
                                <select class="form-control" id="supplierType" name="supplier_type" required>
                                    <option value="" disabled selected><?= __('select_supplier_type') ?></option>
                                    <option value="Internal"><?= __('internal') ?></option>
                                    <option value="External"><?= __('external') ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="form-label"><?= __('category') ?? 'Category' ?></label>
                                <select class="form-control" id="supplierCategory" name="category" required>
                                    <option value="all"><?= __('all') ?? 'All' ?></option>
                                    <option value="ticket"><?= __('ticket') ?? 'Ticket' ?></option>
                                    <option value="visa"><?= __('visa') ?? 'Visa' ?></option>
                                    <option value="umrah"><?= __('umrah') ?? 'Umrah' ?></option>
                                    <option value="hotel"><?= __('hotel') ?? 'Hotel' ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label"><?= __('address') ?></label>
                        <textarea class="form-control" id="supplierAddress" name="address" rows="3"></textarea>
                    </div>
                    <div class="custom-control custom-checkbox mb-2">
                        <input type="checkbox" class="custom-control-input" id="routePaymentToMainAccount" name="route_payment_to_main_account" value="1">
                        <label class="custom-control-label" for="routePaymentToMainAccount">
                            <?= __('route_payments_to_main_account') ?>
                        </label>
                        <small class="form-text text-muted"><?= __('route_payments_to_main_account_hint') ?></small>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-save mr-2"></i><?= __('add_supplier') ?>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
