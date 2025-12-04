

    <!-- Add New Booking Modal -->
    <div class="modal fade" id="addBookingModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title d-flex align-items-center">
                        <i class="feather icon-plus-circle mr-2"></i><?= __('add_new_hotel_booking') ?>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="addHotelBookingForm" class="needs-validation" novalidate>
                        <!-- Form Sections -->
                        <div class="form-sections">
                            <!-- Guest Information Section -->
                            <div class="form-section mb-4">
                                <h6 class="text-primary mb-3">
                                    <i class="feather icon-user mr-2"></i><?= __('guest_information') ?>
                                </h6>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="form-label"><?= __('title') ?></label>
                                            <select class="form-control custom-select" name="title" required>
                                                <option value=""><?= __('select_title') ?></option>
                                                <option value="Mr"><?= __('mr') ?></option>
                                                <option value="Mrs"><?= __('mrs') ?></option>
                                                <option value="Ms"><?= __('ms') ?></option>
                                            </select>
                                            <div class="invalid-feedback">
                                                <?= __('please_select_title') ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="form-label"><?= __('first_name') ?></label>
                                            <input type="text" class="form-control" name="first_name" required>
                                            <div class="invalid-feedback">
                                                <?= __('please_enter_first_name') ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="form-label"><?= __('last_name') ?></label>
                                            <input type="text" class="form-control" name="last_name" required>
                                            <div class="invalid-feedback">
                                                <?= __('please_enter_last_name') ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="form-label"><?= __('gender') ?></label>
                                            <select class="form-control custom-select" name="gender" required>
                                                <option value=""><?= __('select_gender') ?></option>
                                                <option value="Male"><?= __('male') ?></option>
                                                <option value="Female"><?= __('female') ?></option>
                                            </select>
                                            <div class="invalid-feedback">
                                                <?= __('please_select_gender') ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Booking Details Section -->
                            <div class="form-section mb-4">
                                <h6 class="text-primary mb-3">
                                    <i class="feather icon-file-text mr-2"></i><?= __('booking_details') ?>
                                </h6>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label"><?= __('order_id') ?></label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text">#</span>
                                                </div>
                                                <input type="text" class="form-control" name="order_id" required>
                                                <div class="invalid-feedback">
                                                    <?= __('please_enter_order_id') ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label"><?= __('issue_date') ?></label>
                                            <input type="date" class="form-control" name="issue_date" 
                                                   value="<?php echo date('Y-m-d'); ?>" required>
                                            <div class="invalid-feedback">
                                                <?= __('please_select_issue_date') ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label"><?= __('contact_number') ?></label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text">
                                                        <i class="feather icon-phone"></i>
                                                    </span>
                                                </div>
                                                <input type="text" class="form-control" name="contact_no" required>
                                                <div class="invalid-feedback">
                                                    <?= __('please_enter_contact_number') ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Stay Details Section -->
                            <div class="form-section mb-4">
                                <h6 class="text-primary mb-3">
                                    <i class="feather icon-calendar mr-2"></i><?= __('stay_details') ?>
                                </h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label"><?= __('check_in_date') ?></label>
                                            <input type="date" class="form-control" name="check_in_date" required>
                                            <div class="invalid-feedback">
                                                <?= __('please_select_check_in_date') ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label"><?= __('check_out_date') ?></label>
                                            <input type="date" class="form-control" name="check_out_date" required>
                                            <div class="invalid-feedback">
                                                <?= __('please_select_check_out_date') ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label"><?= __('accommodation_details') ?></label>
                                    <textarea class="form-control" name="accommodation_details" rows="3" required></textarea>
                                    <div class="invalid-feedback">
                                        <?= __('please_enter_accommodation_details') ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Financial Details Section -->
                            <div class="form-section mb-4">
                                <h6 class="text-primary mb-3">
                                    <i class="feather icon-dollar-sign mr-2"></i><?= __('financial_details') ?>
                                </h6>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label"><?= __('base_amount') ?></label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text">$</span>
                                                </div>
                                                <input type="number" class="form-control" name="base_amount" 
                                                       step="0.01" required onchange="calculateProfit()">
                                                <div class="invalid-feedback">
                                                    <?= __('please_enter_base_amount') ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label"><?= __('sold_amount') ?></label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text">$</span>
                                                </div>
                                                <input type="number" class="form-control" name="sold_amount" 
                                                       step="0.01" required onchange="calculateProfit()">
                                                <div class="invalid-feedback">
                                                    <?= __('please_enter_sold_amount') ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label"><?= __('profit') ?></label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text">$</span>
                                                </div>
                                                <input type="number" class="form-control bg-light" name="profit" 
                                                       step="0.01" readonly>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Additional Details Section -->
                            <div class="form-section">
                                <h6 class="text-primary mb-3">
                                    <i class="feather icon-info mr-2"></i><?= __('additional_details') ?>
                                </h6>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label"><?= __('supplier') ?></label>
                                            <select class="form-control select2" name="supplier_id" id="supplier" required>
                                                <option value=""><?= __('select_supplier') ?></option>
                                            </select>
                                            <div class="invalid-feedback">
                                                <?= __('please_select_supplier') ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label"><?= __('sold_to') ?></label>
                                            <select class="form-control select2" name="sold_to" id="soldTo" required>
                                                <option value=""><?= __('select_client') ?></option>
                                            </select>
                                            <div class="invalid-feedback">
                                                <?= __('please_select_client') ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label"><?= __('paid_to') ?></label>
                                            <select class="form-control select2" name="paid_to" required>
                                                <option value=""><?= __('select_account') ?></option>
                                            </select>
                                            <div class="invalid-feedback">
                                                <?= __('please_select_account') ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label"><?= __('currency') ?></label>
                                            <input type="text" class="form-control" name="currency" id="currency" readonly required>
                                            <div class="invalid-feedback">
                                                <?= __('please_select_currency') ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group mb-0">
                                    <label class="form-label"><?= __('remarks') ?></label>
                                    <textarea class="form-control" name="remarks" rows="2" 
                                              placeholder="<?= __('enter_any_additional_notes') ?>"></textarea>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="feather icon-x mr-2"></i><?= __('cancel') ?>
                    </button>
                    <button type="button" class="btn btn-primary" data-submit onclick="addHotelBookingForm()">
                        <i class="feather icon-check mr-2"></i><?= __('add_booking') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>