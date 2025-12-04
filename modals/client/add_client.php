    <!-- Add Client Modal -->
    <div class="modal fade" id="addClientModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><?= __('add_new_client') ?></h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="addClientForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><?= __('name') ?></label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= __('email') ?></label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= __('phone') ?></label>
                        <input type="tel" class="form-control" name="phone">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= __('password') ?></label>
                        <input type="password" class="form-control" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= __('address') ?></label>
                        <textarea class="form-control" name="address" rows="2"></textarea>
                    </div>
                 
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><?= __('usd_balance') ?></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">$</span>
                                </div>
                                <input type="number" step="0.01" class="form-control" name="usd_balance" value="0.00">
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><?= __('afs_balance') ?></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">₳</span>
                                </div>
                                <input type="number" step="0.01" class="form-control" name="afs_balance" value="0.00">
                            </div>
                    </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= __('client_type') ?></label>
                        <select class="form-control" name="client_type" required>
                            <option value="regular"><?= __('regular') ?></option>
                            <option value="agency"><?= __('agency') ?></option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= __('status') ?></label>
                        <select class="form-control" name="status" required>
                            <option value="active"><?= __('active') ?></option>
                            <option value="inactive"><?= __('inactive') ?></option>
                        </select>
                    </div>
                  
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-dismiss="modal"><?= __('cancel') ?></button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus mr-2"></i><?= __('add_client') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>