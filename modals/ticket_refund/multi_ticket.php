
<!-- Multiple Ticket Invoice Modal -->
<div class="modal fade" id="multiTicketInvoiceModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="feather icon-file-text mr-2"></i><?= __('generate_combined_invoice') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info mb-3">
                    <i class="feather icon-info mr-2"></i><?= __('select_multiple_tickets_to_generate_a_combined_invoice') ?>
                </div>

                <form id="multiTicketInvoiceForm">
                    <!-- CSRF Protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                    <!-- Client Filter Section -->
                    <div class="form-group">
                        <label for="clientFilter"><?= __('filter_by_client') ?></label>
                        <select class="form-control" id="clientFilter" name="clientFilter">
                            <option value=""><?= __('all_clients') ?></option>
                            <?php
                            foreach ($clients as $client) {
                                echo "<option value='" . htmlspecialchars($client['id']) . "'>" .
                                     htmlspecialchars($client['name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="clientForInvoice"><?= __('invoice_client') ?></label>
                        <select class="form-control" id="clientForInvoice" name="clientForInvoice" required>
                            <option value=""><?= __('select_client') ?></option>
                            <?php
                            foreach ($clients as $client) {
                                echo "<option value='" . htmlspecialchars($client['id']) . "'>" .
                                     htmlspecialchars($client['name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="invoiceComment"><?= __('comments_notes') ?></label>
                        <textarea class="form-control" id="invoiceComment" name="invoiceComment" rows="2"></textarea>
                    </div>

                    <!-- Include Charges Toggle -->
                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="includeCharges" name="includeCharges">
                            <label class="custom-control-label" for="includeCharges"><?= __('include_charges_in_invoice') ?></label>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered" id="ticketSelectionTable">
                            <thead class="thead-light">
                                <tr>
                                    <th width="40">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" id="selectAllTickets">
                                            <label class="custom-control-label" for="selectAllTickets"></label>
                                        </div>
                                    </th>
                                    <th><?= __('passenger') ?></th>
                                    <th><?= __('pnr') ?></th>
                                    <th><?= __('sector') ?></th>
                                    <th><?= __('flight') ?></th>
                                    <th><?= __('date') ?></th>
                                    <th><?= __('charges') ?></th>
                                    <th><?= __('refund_amount') ?></th>
                                </tr>
                            </thead>
                            <tbody id="ticketsForInvoiceBody">
                                <!-- Tickets will be loaded here dynamically -->
                            </tbody>
                            <tfoot>
                                <tr class="table-primary">
                                    <td colspan="6" class="text-right font-weight-bold"><?= __('total') ?>:</td>
                                    <td id="invoiceTotal" class="font-weight-bold">0.00</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    
                    <div class="form-group mt-3">
                        <label for="invoiceCurrency"><?= __('currency') ?></label>
                        <select class="form-control" id="invoiceCurrency" name="invoiceCurrency" required>
                            <option value=""><?= __('select_currency') ?></option>
                            <option value="USD"><?= __('usd') ?></option>
                            <option value="AFS"><?= __('afs') ?></option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
                <button type="button" class="btn btn-primary" id="generateCombinedInvoice">
                    <i class="feather icon-file-text mr-2"></i><?= __('generate_invoice') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add a floating action button for launching the multi-ticket invoice modal -->
<div id="floatingActionButton" class="position-fixed" style="bottom: 80px; z-index: 1050;">    <button type="button" class="btn btn-primary btn-lg shadow" id="launchMultiTicketInvoice" title="<?= __('generate_multi_ticket_invoice') ?>">
        <i class="feather icon-file-text"></i>
    </button>
</div>