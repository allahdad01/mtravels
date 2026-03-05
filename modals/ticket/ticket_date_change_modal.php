
        <!-- Date Change Modal -->
        <div class="modal fade" id="dateChangeModal" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><?= __('date_change') ?></h5>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <form id="dateChangeForm">
                    <!-- CSRF Protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                        <div class="modal-body">
                            <input type="hidden" id="dateChangeTicketId" name="ticketId">
                            <input type="hidden" name="status" value="Date Changed">

                            <div class="form-group">
                                <label for="dateChangeSold"><?= __('sold_price') ?></label>
                                <input type="number" step="any" class="form-control" id="dateChangeSold" name="sold" required>
                            </div>

                            <div class="form-group">
                                <label for="dateChangeBase"><?= __('base_price') ?></label>
                                <input type="number" step="any" class="form-control" id="dateChangeBase" name="base" required>
                            </div>
                            <div class="form-group">
                                <label for="supplierPenalty"><?= __('supplier_penalty') ?></label>
                                <input type="number" step="any" class="form-control" id="supplierPenalty" name="supplier_penalty" required>
                                <small class="form-text text-muted">
                                    <?= __('penalty_charged_by_the_supplier_deducted_from_the_base_price') ?>
                                </small>
                            </div>

                            <div class="form-group">
                                <label for="servicePenalty"><?= __('our_service_penalty') ?></label>
                                <input type="number" step="any" class="form-control" id="servicePenalty" name="service_penalty" required>
                                <small class="form-text text-muted">
                                    <?= __('penalty_charged_by_us_independent_of_supplier_penalties') ?>
                                </small>
                            </div>


                            <div class="form-group" id="dateTypeSelectionGroup" style="display: none;">
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

                            <div class="form-group" id="departureGroup">
                                 <label for="dateChangeDepartureDate"><?= __('new_departure_date') ?></label>
                                 <input type="date" class="form-control" id="dateChangeDepartureDate" name="departureDate">
                             </div>

                             <div class="form-group" id="returnDateGroup" style="display: none;">
                                 <label for="dateChangeReturnDate"><?= __('new_return_date') ?></label>
                                 <input type="date" class="form-control" id="dateChangeReturnDate" name="returnDate">
                             </div>
                             
                             <div class="form-group">
                                 <label for="dateChangeDescription"><?= __('description') ?></label>
                                 <textarea class="form-control" id="dateChangeDescription" name="description" rows="3" required></textarea>
                             </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
                            <button type="submit" class="btn btn-primary"><?= __('submit') ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>