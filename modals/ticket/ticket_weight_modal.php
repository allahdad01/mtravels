    <!-- Add Weight Modal -->
    <div class="modal fade" id="addWeightModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-info text-white border-0">
                    <h5 class="modal-title">
                        <i class="feather icon-package mr-2"></i><?= __('add_weight') ?>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="addWeightForm">
                    <div class="modal-body">
                        <!-- Passenger Information (Read-only) -->
                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-body bg-light">
                                <h6 class="card-subtitle mb-3 text-muted"><?= __('passenger_information') ?></h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="small text-muted"><?= __('passenger_name') ?></label>
                                            <p class="mb-0" id="weight-passenger-name">-</p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="small text-muted"><?= __('pnr') ?></label>
                                            <p class="mb-0" id="weight-pnr">-</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Weight Details -->
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-3 text-muted"><?= __('weight_details') ?></h6>
                                <input type="hidden" name="ticket_id" id="weight-ticket-id">
                                
                                <div class="form-group">
                                    <label for="weight"><?= __('weight_kg') ?> <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="weight" name="weight" required step="0.01">
                                </div>

                                <div class="form-group">
                                    <label for="base-weight-price"><?= __('base_price') ?> <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="base-weight-price" name="base_price" required step="0.01">
                                </div>

                                <div class="form-group">
                                    <label for="sold-weight-price"><?= __('sold_price') ?> <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="sold-weight-price" name="sold_price" required step="0.01">
                                </div>

                                <div class="form-group">
                                    <label for="weight-profit"><?= __('profit') ?></label>
                                    <input type="number" class="form-control" id="weight-profit" readonly>
                                </div>

                                <div class="form-group mb-0">
                                    <label for="weight-remarks"><?= __('remarks') ?></label>
                                    <textarea class="form-control" id="weight-remarks" name="remarks" rows="3"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="feather icon-x mr-2"></i><?= __('cancel') ?>
                        </button>
                        <button type="submit" class="btn btn-info">
                            <i class="feather icon-save mr-2"></i><?= __('save') ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>