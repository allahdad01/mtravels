<!-- Cancellation Details Modal -->
<div class="modal fade" id="cancellationDetailsModal" tabindex="-1" role="dialog" aria-labelledby="cancellationDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="cancellationDetailsModalLabel">
                    <i class="feather icon-x-circle mr-2"></i><?= __('umrah_cancellation_details') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="cancellationDetailsForm">
                    <!-- CSRF Protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                    <input type="hidden" id="cancellationBookingId" name="booking_id">
                    
                    <div class="alert alert-warning">
                        <?= __('please_specify_the_cancellation_details_and_fees') ?>
                    </div>

                    <div class="section-header"><?= __('document_return') ?></div>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th width="30%"><?= __('document_type') ?></th>
                                    <th width="20%"><?= __('returned') ?></th>
                                    <th width="20%"><?= __('condition') ?></th>
                                    <th width="30%"><?= __('notes') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><?= __('passport') ?></td>
                                    <td class="text-center">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" 
                                                   id="return_passport" name="returned_items[passport]" value="1">
                                            <label class="custom-control-label" for="return_passport"></label>
                                        </div>
                                    </td>
                                    <td>
                                        <select class="form-control form-control-sm" name="item_condition[passport]" 
                                                id="condition_passport">
                                            <option value=""><?= __('select_condition') ?></option>
                                            <option value="good"><?= __('good') ?></option>
                                            <option value="fair"><?= __('fair') ?></option>
                                            <option value="poor"><?= __('poor') ?></option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control form-control-sm" 
                                               name="item_notes[passport]">
                                    </td>
                                </tr>
                                <tr>
                                    <td><?= __('id_card') ?></td>
                                    <td class="text-center">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" 
                                                   id="return_id_card" name="returned_items[id_card]" value="1">
                                            <label class="custom-control-label" for="return_id_card"></label>
                                        </div>
                                    </td>
                                    <td>
                                        <select class="form-control form-control-sm" name="item_condition[id_card]" 
                                                id="condition_id_card">
                                            <option value=""><?= __('select_condition') ?></option>
                                            <option value="good"><?= __('good') ?></option>
                                            <option value="fair"><?= __('fair') ?></option>
                                            <option value="poor"><?= __('poor') ?></option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control form-control-sm" 
                                               name="item_notes[id_card]">
                                    </td>
                                </tr>
                                <tr>
                                    <td><?= __('photos') ?></td>
                                    <td class="text-center">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" 
                                                   id="return_photos" name="returned_items[photos]" value="1">
                                            <label class="custom-control-label" for="return_photos"></label>
                                        </div>
                                    </td>
                                    <td>
                                        <select class="form-control form-control-sm" name="item_condition[photos]" 
                                                id="condition_photos">
                                            <option value=""><?= __('select_condition') ?></option>
                                            <option value="good"><?= __('good') ?></option>
                                            <option value="fair"><?= __('fair') ?></option>
                                            <option value="poor"><?= __('poor') ?></option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control form-control-sm" 
                                               name="item_notes[photos]">
                                    </td>
                                </tr>
                                <tr>
                                    <td><?= __('other_documents') ?></td>
                                    <td class="text-center">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" 
                                                   id="return_other_docs" name="returned_items[other_docs]" value="1">
                                            <label class="custom-control-label" for="return_other_docs"></label>
                                        </div>
                                    </td>
                                    <td>
                                        <select class="form-control form-control-sm" name="item_condition[other_docs]" 
                                                id="condition_other_docs">
                                            <option value=""><?= __('select_condition') ?></option>
                                            <option value="good"><?= __('good') ?></option>
                                            <option value="fair"><?= __('fair') ?></option>
                                            <option value="poor"><?= __('poor') ?></option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control form-control-sm" 
                                               name="item_notes[other_docs]" placeholder="<?= __('specify_documents') ?>">
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="form-group">
                        <label for="cancellationReason"><?= __('reason_for_cancellation') ?></label>
                        <textarea class="form-control" id="cancellationReason" name="cancellation_reason" 
                                  rows="3" required></textarea>
                    </div>

                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="cancellationConfirmation" name="confirmation" required>
                            <label class="custom-control-label" for="cancellationConfirmation">
                                <?= __('i_confirm_all_cancellation_details_are_correct') ?>
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                <button type="button" class="btn btn-danger" id="generateCancellationFormBtn">
                    <i class="feather icon-file-text mr-2"></i><?= __('generate_cancellation_form') ?>
                </button>
            </div>
        </div>
    </div>
</div>