    <!-- Add Date Change Modal -->
    <div class="modal fade" id="addDateChangeModal" tabindex="-1" role="dialog" aria-labelledby="addDateChangeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="addDateChangeModalLabel">
                        <i class="feather icon-plus mr-2"></i><?= __('add_date_change') ?>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="addDateChangeForm" class="needs-validation" novalidate>
                    <div class="modal-body">
                        <!-- Alert Container for Messages -->
                        <div id="modalAlertContainer"></div>
                        
                        <!-- Search Section -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="searchPNR"><?= __('search_by_pnr') ?></label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="searchPNR" 
                                               placeholder="<?= __('enter_pnr') ?>"
                                               pattern="[A-Z0-9]{6}"
                                               title="<?= __('pnr_format_hint') ?>">
                                        <div class="input-group-append">
                                            <button class="btn btn-primary" type="button" id="searchPNRBtn">
                                                <i class="feather icon-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="invalid-feedback">
                                        <?= __('please_enter_valid_pnr') ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="searchPassenger"><?= __('search_by_passenger') ?></label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="searchPassenger" 
                                               placeholder="<?= __('enter_passenger_name') ?>"
                                               minlength="3">
                                        <div class="input-group-append">
                                            <button class="btn btn-primary" type="button" id="searchPassengerBtn">
                                                <i class="feather icon-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="invalid-feedback">
                                        <?= __('name_min_length') ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Search Results Section -->
                        <div id="searchResultsContainer" class="mt-4" style="display: none;">
                            <div class="table-responsive">
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
                        </div>

                        <!-- Date Change Details Section -->
                        <div id="dateChangeDetailsContainer" class="mt-4" style="display: none;">
                            <input type="hidden" id="selectedTicketId" name="ticketId">
                            <input type="hidden" id="ticketTripType" name="ticketTripType">
                            <input type="hidden" name="status" value="Date Changed">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            
                            <!-- Date Type Selection (for round-trip) -->
                            <div id="dateTypeSelectionGroup" class="row mb-3" style="display: none;">
                                <div class="col-md-12">
                                    <label><?= __('select_date_to_change') ?></label>
                                    <div class="custom-control custom-radio">
                                        <input type="radio" class="custom-control-input" id="changeDepartureOnly" name="dateType" value="departure" checked>
                                        <label class="custom-control-label" for="changeDepartureOnly">
                                            <?= __('departure_date_only') ?>
                                        </label>
                                    </div>
                                    <div class="custom-control custom-radio">
                                        <input type="radio" class="custom-control-input" id="changeReturnOnly" name="dateType" value="return">
                                        <label class="custom-control-label" for="changeReturnOnly">
                                            <?= __('return_date_only') ?>
                                        </label>
                                    </div>
                                    <div class="custom-control custom-radio">
                                        <input type="radio" class="custom-control-input" id="changeBothDates" name="dateType" value="both">
                                        <label class="custom-control-label" for="changeBothDates">
                                            <?= __('both_dates') ?>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group" id="departureGroup">
                                        <label for="departureDate"><?= __('new_departure_date') ?></label>
                                        <input type="date" class="form-control" id="departureDate" 
                                               name="departureDate"
                                               min="<?= date('Y-m-d') ?>">
                                        <div class="invalid-feedback">
                                            <?= __('please_select_future_date') ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row" id="returnGroup" style="display: none;">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="returnDate"><?= __('new_return_date') ?></label>
                                        <input type="date" class="form-control" id="returnDate" 
                                               name="returnDate"
                                               min="<?= date('Y-m-d') ?>">
                                        <div class="invalid-feedback">
                                            <?= __('please_select_future_date') ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="supplier_penalty"><?= __('supplier_penalty') ?></label>
                                        <input type="number" step="0.01" class="form-control" 
                                               id="supplier_penalty" name="supplier_penalty" required
                                               min="0">
                                        <div class="invalid-feedback">
                                            <?= __('please_enter_valid_amount') ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="service_penalty"><?= __('service_penalty') ?></label>
                                        <input type="number" step="0.01" class="form-control" 
                                               id="service_penalty" name="service_penalty" required
                                               min="0">
                                        <div class="invalid-feedback">
                                            <?= __('please_enter_valid_amount') ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="base"><?= __('base_price') ?></label>
                                        <input type="number" step="0.01" class="form-control" 
                                               id="base" name="base" required
                                               min="0">
                                        <div class="invalid-feedback">
                                            <?= __('please_enter_valid_amount') ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="sold"><?= __('sold_price') ?></label>
                                        <input type="number" step="0.01" class="form-control" 
                                               id="sold" name="sold" required
                                               min="0">
                                        <div class="invalid-feedback">
                                            <?= __('please_enter_valid_amount') ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="description"><?= __('description') ?></label>
                                <textarea class="form-control" id="description" 
                                          name="description" rows="3" required
                                          minlength="10"></textarea>
                                <div class="invalid-feedback">
                                    <?= __('description_min_length') ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="feather icon-x mr-2"></i><?= __('cancel') ?>
                        </button>
                        <button type="submit" class="btn btn-primary" id="saveDateChangeBtn" style="display: none;">
                            <i class="feather icon-save mr-2"></i><?= __('save_date_change') ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>