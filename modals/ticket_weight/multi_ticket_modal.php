    <!-- Floating Action Button for Multi-Weight Invoice -->
    <div id="floatingActionButton" class="position-fixed" style="bottom: 80px; <?php echo is_rtl() ? 'left' : 'right'; ?>: 20px; z-index: 1050;">
        <button type="button" class="btn btn-primary btn-lg shadow" id="launchMultiWeightInvoice" title="<?= __('generate_multi_weight_invoice') ?>">
            <i class="feather icon-file-text"></i>
        </button>
    </div>

    <!-- Multiple Weight Invoice Modal -->
    <div class="modal fade" id="multiWeightInvoiceModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="feather icon-file-text mr-2"></i><?= __('generate_combined_weight_invoice') ?>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info mb-3">
                        <i class="feather icon-info mr-2"></i><?= __('select_multiple_weights_to_generate_a_combined_invoice') ?>
                    </div>

                    <form id="multiWeightInvoiceForm">
                    <!-- CSRF Protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                        <div class="form-group">
                            <label for="clientFilterWeight"><?= __('filter_by_client') ?></label>
                            <select class="form-control" id="clientFilterWeight" name="clientFilter">
                                <option value=""><?= __('all_clients') ?></option>
                                <?php
                                // Fetch clients from database using PDO
                                $clientStmt = $pdo->prepare("SELECT DISTINCT c.name FROM clients c
                                                INNER JOIN ticket_bookings t ON c.id = t.sold_to
                                                WHERE t.tenant_id = ? AND t.branch_id = ?
                                                ORDER BY c.name ASC");
                                $clientStmt->execute([$tenant_id, $branch_id]);
                                $clientResult = $clientStmt->fetchAll(PDO::FETCH_ASSOC);

                                if ($clientResult && count($clientResult) > 0) {
                                    foreach ($clientResult as $client) {
                                        echo '<option value="' . htmlspecialchars($client['name']) . '">' .
                                             htmlspecialchars($client['name']) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="clientForWeightInvoice"><?= __('client') ?></label>
                            <input type="text" class="form-control" id="clientForWeightInvoice" name="clientForInvoice" required>
                        </div>

                        <div class="form-group">
                            <label for="weightInvoiceComment"><?= __('comments_notes') ?></label>
                            <textarea class="form-control" id="weightInvoiceComment" name="invoiceComment" rows="2"></textarea>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover table-bordered" id="weightSelectionTable">
                                <thead class="thead-light">
                                    <tr>
                                        <th width="40">
                                            <div class="custom-control custom-checkbox">
                                                <input type="checkbox" class="custom-control-input" id="selectAllWeightsModal">
                                                <label class="custom-control-label" for="selectAllWeightsModal"></label>
                                            </div>
                                        </th>
                                        <th><?= __('client') ?></th>
                                        <th><?= __('passenger') ?></th>
                                        <th><?= __('pnr') ?></th>
                                        <th><?= __('weight') ?></th>
                                        <th><?= __('amount') ?></th>
                                    </tr>
                                </thead>
                                <tbody id="weightsForInvoiceBody">
                                    <!-- Weights will be loaded here dynamically -->
                                </tbody>
                                <tfoot>
                                    <tr class="table-primary">
                                        <td colspan="5" class="text-right font-weight-bold"><?= __('total') ?>:</td>
                                        <td id="weightInvoiceTotal" class="font-weight-bold">0.00</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div class="form-group mt-3">
                            <label for="weightInvoiceCurrency"><?= __('currency') ?></label>
                            <select class="form-control" id="weightInvoiceCurrency" name="invoiceCurrency" required>
                                <option value=""><?= __('select_currency') ?></option>
                                <option value="USD"><?= __('usd') ?></option>
                                <option value="AFS"><?= __('afs') ?></option>
                                <option value="EUR"><?= __('eur') ?></option>
                                <option value="DARHAM"><?= __('darham') ?></option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
                    <button type="button" class="btn btn-primary" id="generateCombinedWeightInvoice">
                        <i class="feather icon-file-text mr-2"></i><?= __('generate_invoice') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>