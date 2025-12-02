<!-- Edit Payment Modal -->
<div class="modal fade" id="editPaymentModal" tabindex="-1" role="dialog" aria-labelledby="editPaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editPaymentModalLabel"><?= __('edit_payment') ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="editPaymentForm" method="POST" action="additional_payments.php">
                    <!-- CSRF Protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                    <input type="hidden" id="edit_id" name="id">
                    <input type="hidden" name="action" value="edit">
                    
                    <!-- Form fields -->
                    <div class="form-group">
                        <label for="edit_payment_type"><?= __('payment_type') ?></label>
                        <input type="text" class="form-control" id="edit_payment_type" name="payment_type" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_main_account_id"><?= __('main_account') ?></label>
                        <select class="form-control" id="edit_main_account_id" name="main_account_id" required>
                            <option value=""><?= __('select_main_account') ?></option>
                            <?php foreach ($mainAccounts as $account): ?>
                                <option value="<?= $account['id'] ?>">
                                    <?= htmlspecialchars($account['name']) ?> 
                                   
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_description"><?= __('description') ?></label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="edit_base_amount"><?= __('base_amount') ?></label>
                        <input type="number" class="form-control" id="edit_base_amount" name="base_amount" required step="0.01" onchange="calculateEditProfit()">
                    </div>
                    <div class="form-group">
                        <label for="edit_sold_amount"><?= __('sold_amount') ?></label>
                        <input type="number" class="form-control" id="edit_sold_amount" name="sold_amount" required step="0.01" onchange="calculateEditProfit()">
                    </div>
                    <div class="form-group">
                        <label for="edit_profit"><?= __('profit') ?></label>
                        <input type="number" class="form-control" id="edit_profit" name="profit" required step="0.01" readonly>
                    </div>
                    <div class="form-group">
                        <label for="edit_currency"><?= __('currency') ?></label>
                        <select class="form-control" id="edit_currency" name="currency" required>
                            <option value="USD"><?= __('usd') ?></option>
                            <option value="AFS"><?= __('afs') ?></option>
                            <option value="EUR"><?= __('eur') ?></option>
                            <option value="DARHAM"><?= __('darham') ?></option>
                        </select>
                    </div>
                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="edit_is_from_supplier" name="is_from_supplier">
                            <label class="custom-control-label" for="edit_is_from_supplier"><?= __('bought_from_supplier') ?></label>
                        </div>
                    </div>
                    <div class="form-group supplier-group" style="display: none;">
                        <label for="edit_supplier_id"><?= __('supplier') ?></label>
                        <select class="form-control" id="edit_supplier_id" name="supplier_id">
                            <option value=""><?= __('select_supplier') ?></option>
                            <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?= $supplier['id'] ?>">
                                    <?= htmlspecialchars($supplier['name']) ?> 
                                  
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="edit_is_for_client" name="is_for_client">
                            <label class="custom-control-label" for="edit_is_for_client"><?= __('sold_to_client') ?></label>
                        </div>
                    </div>
                    <div class="form-group client-group" style="display: none;">
                        <label for="edit_client_id"><?= __('client') ?></label>
                        <select class="form-control" id="edit_client_id" name="client_id">
                            <option value=""><?= __('select_client') ?></option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?= $client['id'] ?>">
                                    <?= htmlspecialchars($client['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
                <button type="button" class="btn btn-primary" id="updatePayment"><?= __('update_payment') ?></button>
            </div>
        </div>
    </div>
</div>
