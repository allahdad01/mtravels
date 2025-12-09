<!-- Edit Booking Modal -->
<div class="modal fade" id="editBookingModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="feather icon-edit-2 mr-2"></i><?= __('edit_booking') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="editBookingForm">
                    <!-- CSRF Protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                    <input type="hidden" id="edit_booking_id" name="booking_id">
                    
                    <!-- Personal Information -->
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label><?= __('title') ?></label>
                                <select class="form-control" id="title" name="title" required>
                                    <option value="Mr"><?= __('mr') ?></option>
                                    <option value="Mrs"><?= __('mrs') ?></option>
                                    <option value="Ms"><?= __('ms') ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label><?= __('first_name') ?></label>
                                <input type="text" class="form-control" id="first_name" name="first_name" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label><?= __('last_name') ?></label>
                                <input type="text" class="form-control" id="last_name" name="last_name" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label><?= __('gender') ?></label>
                                <select class="form-control" id="gender" name="gender" required>
                                    <option value="Male"><?= __('male') ?></option>
                                    <option value="Female"><?= __('female') ?></option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Booking Details -->
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label><?= __('order_id') ?></label>
                                <input type="text" class="form-control" id="order_id" name="order_id" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label><?= __('issue_date') ?></label>
                                <input type="date" class="form-control" id="issue_date" name="issue_date" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label><?= __('contact_number') ?></label>
                                <input type="text" class="form-control" id="contact_no" name="contact_no" required>
                            </div>
                        </div>
                    </div>

                    <!-- Stay Details -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><?= __('check_in_date') ?></label>
                                <input type="date" class="form-control" id="check_in_date" name="check_in_date" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><?= __('check_out_date') ?></label>
                                <input type="date" class="form-control" id="check_out_date" name="check_out_date" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label><?= __('accommodation_details') ?></label>
                        <textarea class="form-control" id="accommodation_details" name="accommodation_details" rows="3" required></textarea>
                    </div>

                    <!-- Financial Details -->
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label><?= __('base_amount') ?></label>
                                <input type="number" class="form-control" id="base_amount" name="base_amount" step="0.01" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label><?= __('sold_amount') ?></label>
                                <input type="number" class="form-control" id="sold_amount" name="sold_amount" step="0.01" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label><?= __('profit') ?></label>
                                <input type="number" class="form-control" id="profit" name="profit" step="0.01" readonly>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Details -->
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label><?= __('supplier') ?></label>
                                <select class="form-control" id="supplier_id" name="supplier_id" required>
                                    <!-- Will be populated dynamically -->
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label><?= __('sold_to') ?></label>
                                <select class="form-control" id="sold_to" name="sold_to" required>
                                    <!-- Will be populated dynamically -->
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label><?= __('paid_to') ?></label>
                                <select class="form-control" id="paid_to" name="paid_to" required>
                                    <!-- Will be populated dynamically -->
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><?= __('currency') ?></label>
                                <select class="form-control" id="edit_currency" name="currency" required>
                                    <option value="USD"><?= __('usd') ?></option>
                                    <option value="AFS"><?= __('afs') ?></option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                            <label><?= __('remarks') ?></label>
                        <textarea class="form-control" id="remarks" name="remarks" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                <button type="button" class="btn btn-primary" onclick="submitEditForm()"><?= __('save_changes') ?></button>
            </div>
        </div>
    </div>
</div>