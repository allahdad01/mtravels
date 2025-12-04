    <!-- Add Ticket weight Modal -->
    <div class="modal fade" id="addTransactionModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="feather icon-plus mr-2"></i><?= __('add_weight') ?>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <form id="addTransactionForm">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="searchPNR"><?= __('search_by_pnr') ?></label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="searchPNR" placeholder="<?= __('enter_pnr') ?>">
                                        <div class="input-group-append">
                                            <button class="btn btn-primary" type="button" id="searchPNRBtn">
                                                <i class="feather icon-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="searchPassenger"><?= __('search_by_passenger') ?></label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="searchPassenger" placeholder="<?= __('enter_passenger_name') ?>">
                                        <div class="input-group-append">
                                            <button class="btn btn-primary" type="button" id="searchPassengerBtn">
                                                <i class="feather icon-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive mt-3" id="searchResultsContainer" style="display: none;">
                            <table class="table table-hover" id="searchResultsTable">
                                <thead>
                                    <tr>
                                        <th><?= __('passenger') ?></th>
                                        <th><?= __('pnr') ?></th>
                                        <th><?= __('flight_details') ?></th>
                                        <th><?= __('date') ?></th>
                                        <th><?= __('action') ?></th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>

                        <div id="weightDetailsContainer" style="display: none;">
                            <hr>
                            <h6 class="mb-3"><?= __('weight_details') ?></h6>
                            
                            <input type="hidden" id="selectedTicketId" name="ticket_id">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="weight"><?= __('weight_kg') ?> <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" id="weight" name="weight" step="0.01" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="basePrice"><?= __('base_price') ?> <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" id="basePrice" name="base_price" step="0.01" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="soldPrice"><?= __('sold_price') ?> <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" id="soldPrice" name="sold_price" step="0.01" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="profit"><?= __('profit') ?></label>
                                        <input type="number" class="form-control" id="profit" readonly>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="remarks"><?= __('remarks') ?></label>
                                <textarea class="form-control" id="remarks" name="remarks" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="feather icon-x mr-2"></i><?= __('cancel') ?>
                        </button>
                        <button type="submit" class="btn btn-primary" id="saveTransactionBtn" style="display: none;">
                            <i class="feather icon-save mr-2"></i><?= __('save_transaction') ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>