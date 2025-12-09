    <!-- Bank Recipt Language Selection Modal -->
    <div class="modal fade" id="bankReciptModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= __("select_bank_recipt_language") ?></h5>
                </div>
                <div class="modal-body">
                    <form id="bankReciptForm" onsubmit="generateBankRecipt(event)">
                    <!-- CSRF Protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                    <div class="form-group">
                    <label for="bank_name"><?= __("bank_name") ?></label>
                    <input type="text" class="form-control" id="bank_name" placeholder="<?= __("bank_name") ?>">
                    </div>
                    <div class="form-group">
                    <label for="bank_account_number"><?= __("bank_account_number") ?></label>
                    <input type="text" class="form-control" id="bank_account_number" placeholder="<?= __("bank_account_number") ?>">
                    </div>
                    <div class="form-group">
                    <label for="account_name"><?= __("account_name") ?></label>
                    <input type="text" class="form-control" id="account_name" placeholder="<?= __("account_name") ?>">
                    </div>
                    <div class="form-group">
                    <label for="payment"><?= __("payment") ?></label>
                    <input type="text" class="form-control" id="payment" placeholder="<?= __("payment") ?>">
                    </div>
                    </form>
                    <div class="mt-3">
                        <label class="font-weight-bold mb-2"><?= __("select_members_to_include") ?></label>
                        <div id="bankReciptMembers" class="border rounded p-2" style="max-height: 220px; overflow:auto;">
                            <div class="text-muted small"><?= __("members_will_load_here") ?></div>
                        </div>
                    </div>
                    <div class="list-group">
                        <a href="#" class="list-group-item list-group-item-action" onclick="generateBankRecipt(event, 'fa')">
                            <i class="feather icon-globe mr-2"></i> Dari
                        </a>
                        <a href="#" class="list-group-item list-group-item-action" onclick="generateBankRecipt(event, 'ps')">
                            <i class="feather icon-globe mr-2"></i> Pashto
                        </a>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <?= __("cancel") ?>
                    </button>
                </div>
            </div>
        </div>
    </div>