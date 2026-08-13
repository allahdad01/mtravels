<!-- Add Client Modal -->
<div class="modal fade" id="addClientModal" tabindex="-1" role="dialog" aria-labelledby="addClientModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow">
            <form id="addClientForm">
                <!-- CSRF Protection -->
                <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                <div class="modal-header bg-light border-0">
                    <h5 class="modal-title">
                        <i class="feather icon-user-plus text-primary mr-2"></i>
                        <?= __('add_new_client') ?>
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group mb-2">
                        <label class="small font-weight-bold mb-1"><?= __('name') ?></label>
                        <input type="text" class="form-control form-control-sm" name="name" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-2">
                                <label class="small font-weight-bold mb-1"><?= __('email') ?> <span class="text-muted font-weight-normal">(<?= __('optional') ?>)</span></label>
                                <input type="email" class="form-control form-control-sm" name="email">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-2">
                                <label class="small font-weight-bold mb-1"><?= __('phone') ?></label>
                                <input type="tel" class="form-control form-control-sm" name="phone">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-2">
                                <label class="small font-weight-bold mb-1"><?= __('password') ?> <span class="text-muted font-weight-normal">(<?= __('optional') ?>)</span></label>
                                <input type="password" class="form-control form-control-sm" name="password" autocomplete="new-password">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-2">
                                <label class="small font-weight-bold mb-1"><?= __('client_type') ?></label>
                                <select class="form-control form-control-sm" name="client_type" required>
                                    <option value="" disabled selected><?= __('select') ?> <?= __('client_type') ?></option>
                                    <option value="regular"><?= __('regular') ?></option>
                                    <option value="agency"><?= __('agency') ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-2">
                                <label class="small font-weight-bold mb-1"><?= __('usd_balance') ?></label>
                                <div class="input-group input-group-sm">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">$</span>
                                    </div>
                                    <input type="number" step="0.01" class="form-control" name="usd_balance" value="0.00">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-2">
                                <label class="small font-weight-bold mb-1"><?= __('afs_balance') ?></label>
                                <div class="input-group input-group-sm">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">؋</span>
                                    </div>
                                    <input type="number" step="0.01" class="form-control" name="afs_balance" value="0.00">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-2">
                                <label class="small font-weight-bold mb-1"><?= __('status') ?></label>
                                <select class="form-control form-control-sm" name="status" required>
                                    <option value="active"><?= __('active') ?></option>
                                    <option value="inactive"><?= __('inactive') ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-2">
                                <label class="small font-weight-bold mb-1"><?= __('address') ?></label>
                                <input type="text" class="form-control form-control-sm" name="address">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-save mr-2"></i><?= __('add_client') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>