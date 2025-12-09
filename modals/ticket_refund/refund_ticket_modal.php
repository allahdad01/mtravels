<!-- Add Refund Ticket Modal -->
<div class="modal fade" id="addRefundTicketModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="feather icon-plus mr-2"></i><?= __('add_refund_ticket') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <!-- Search Section -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="form-row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="searchPNR"><?= __('search_by_pnr') ?></label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="searchPNR" placeholder="<?= __('enter_pnr') ?>">
                                        <div class="input-group-append">
                                            <button class="btn btn-primary" type="button" onclick="searchTickets('pnr')">
                                                <i class="feather icon-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="searchName"><?= __('search_by_name') ?></label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="searchName" placeholder="<?= __('enter_passenger_name') ?>">
                                        <div class="input-group-append">
                                            <button class="btn btn-primary" type="button" onclick="searchTickets('passenger')">
                                                <i class="feather icon-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div id="searchResults" class="mt-3" style="display: none;">
                            <h6><?= __('search_results') ?></h6>
                            <div class="table-responsive">
                                <table class="table table-hover table-bordered mb-0">
                                    <thead>
                                        <tr>
                                            <th><?= __('passenger') ?></th>
                                            <th><?= __('pnr') ?></th>
                                            <th><?= __('flight_details') ?></th>
                                            <th><?= __('date') ?></th>
                                            <th><?= __('action') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody id="searchResultsBody">
                                        <!-- Search results will be populated here -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Refund Form -->
                <form id="refundTicketForm" style="display: none;">
                    <!-- CSRF Protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                    <input type="hidden" id="ticketId" name="ticketId">
                    <input type="hidden" id="status" name="status" value="pending">
                    <input type="hidden" id="exchangeRate" name="exchange_rate" value="1">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="base"><?= __('base') ?></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text" id="baseCurrency"></span>
                                    </div>
                                    <input type="number" class="form-control" id="base" name="base" 
                                           step="0.01" min="0" required readonly>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="sold"><?= __('sold') ?></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text" id="soldCurrency"></span>
                                    </div>
                                    <input type="number" class="form-control" id="sold" name="sold" 
                                           step="0.01" min="0" required readonly>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="supplier_penalty"><?= __('supplier_penalty') ?></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text" id="penaltyCurrency"></span>
                                    </div>
                                    <input type="number" class="form-control" id="supplier_penalty" name="supplier_penalty" 
                                           step="0.01" min="0" required value="0">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="service_penalty"><?= __('service_penalty') ?></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text" id="penaltyCurrency2"></span>
                                    </div>
                                    <input type="number" class="form-control" id="service_penalty" name="service_penalty" 
                                           step="0.01" min="0" required value="0">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="totalPenalty"><?= __('total_penalty') ?></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text" id="totalPenaltyCurrency"></span>
                            </div>
                            <input type="text" class="form-control" id="totalPenalty" name="total_penalty" readonly>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="calculationMethod"><?= __('calculation_method') ?></label>
                        <select class="form-control" id="calculationMethod" name="calculationMethod" onchange="calculateRefund()">
                            <option value="sold"><?= __('calculate_from_sold') ?></option>
                            <option value="base"><?= __('calculate_from_base') ?></option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="refundPassengerAmount"><?= __('refund_amount') ?></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text" id="refundCurrency">USD</span>
                            </div>
                            <input type="text" class="form-control" id="refundPassengerAmount" name="refund_amount" readonly required>
                        </div>
                        <small class="form-text text-muted" id="refundCalculationInfo">
                            <?= __('automatically_calculated_based_on_selected_method') ?>
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="description"><?= __('description') ?></label>
                        <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="feather icon-x mr-2"></i><?= __('cancel') ?>
                </button>
                <button type="button" class="btn btn-primary" onclick="saveRefundTicket()">
                    <i class="feather icon-save mr-2"></i><?= __('save') ?>
                </button>
            </div>
        </div>
    </div>
</div>