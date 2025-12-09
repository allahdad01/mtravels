    <!-- Edit Weight Modal -->
    <div class="modal fade" id="editWeightModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">
                        <i class="feather icon-edit-2 mr-2"></i><?= __('edit_weight') ?>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <form id="editWeightForm">
                    <!-- CSRF Protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                    <div class="modal-body">
                        <input type="hidden" id="editWeightId" name="weight_id">
                        
                        <div class="form-group">
                            <label for="editWeight"><?= __('weight_kg') ?></label>
                            <input type="number" class="form-control" id="editWeight" name="weight" step="0.01" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="editBasePrice"><?= __('base_price') ?></label>
                                    <input type="number" class="form-control" id="editBasePrice" name="base_price" step="0.01" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="editSoldPrice"><?= __('sold_price') ?></label>
                                    <input type="number" class="form-control" id="editSoldPrice" name="sold_price" step="0.01" required>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="editProfit"><?= __('profit') ?></label>
                            <input type="number" class="form-control" id="editProfit" readonly>
                        </div>

                        <div class="form-group">
                            <label for="editRemarks"><?= __('remarks') ?></label>
                            <textarea class="form-control" id="editRemarks" name="remarks" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="feather icon-x mr-2"></i><?= __('cancel') ?>
                        </button>
                        <button type="submit" class="btn btn-info">
                            <i class="feather icon-save mr-2"></i><?= __('save_changes') ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
