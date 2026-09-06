<!-- Bulk Ticket Payment Modal -->
<style>
#bulkPaymentModal .table { font-size: 12px; }
#bulkPaymentModal .table td, #bulkPaymentModal .table th { padding: 4px 6px; vertical-align: middle !important; }
#bulkPaymentModal .table th { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; }
#bulkPaymentModal .custom-control { min-height: 18px; padding-left: 0; margin-bottom: 0; position: relative; }
#bulkPaymentModal .custom-control .custom-control-input { position: absolute; opacity: 0; z-index: -1; }
#bulkPaymentModal .custom-control .custom-control-label { margin: 0; display: inline-block; position: relative; min-height: 18px; line-height: 18px; padding-left: 22px; }
#bulkPaymentModal .custom-control .custom-control-label::before { position: absolute; top: 2px; left: 0; width: 14px; height: 14px; display: block; content: ""; background-color: #fff; border: 1px solid #adb5bd; border-radius: 3px; }
#bulkPaymentModal .custom-control .custom-control-label::after { position: absolute; top: 2px; left: 0; width: 14px; height: 14px; display: block; content: ""; background: no-repeat center/10px 10px; }
#bulkPaymentModal .custom-control .custom-control-input:checked ~ .custom-control-label::before { border-color: #185fa5; background-color: #185fa5; }
#bulkPaymentModal .custom-control .custom-control-input:checked ~ .custom-control-label::after { background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8' viewBox='0 0 8 8'%3e%3cpath fill='%23fff' d='M6.564.75l-3.59 3.617-1.538-1.55L0 4.26l2.974 2.99L8 2.193z'/%3e%3c/svg%3e"); }
#bulkPaymentModal label { font-size: 11px; font-weight: 600; color: #6c757d; margin-bottom: 3px; }
#bulkPaymentModal .form-control, #bulkPaymentModal .form-control-sm, #bulkPaymentModal select { font-size: 12px; padding: 4px 8px; }
#bulkPaymentModal textarea.form-control { font-size: 12px; padding: 4px 8px; }
#bulkAllocationSection .table td { white-space: nowrap; }
.bulk-alloc-input { width: 100px !important; text-align: right; font-size: 12px; padding: 3px 6px; border: 1px solid #ced4da; border-radius: 4px; display: inline-block; }
</style>
<div class="modal fade" id="bulkPaymentModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-money-bill-wave mr-2"></i><?= __('bulk_ticket_payment') ?? 'Bulk Ticket Payment' ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="max-height: 75vh; overflow-y: auto;">
                <!-- CSRF Protection -->
                <input type="hidden" id="bulkCsrfToken" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">

                <!-- Ticket Selection (loads automatically) -->
                <div class="card mb-3" id="bulkTicketSection">
                    <div class="card-header bg-light">
                        <h6 class="mb-3"><i class="feather icon-list mr-1"></i><?= __('select_tickets') ?? 'Select Tickets' ?></h6>
                        <div class="row align-items-end">
                            <div class="col-md-3 col-sm-6 mb-2">
                                <label for="bulkPnrSearch" style="font-size:11px; font-weight:600; color:#6c757d; margin-bottom:3px;"><?= __('search_pnr') ?? 'Search PNR' ?></label>
                                <div class="input-group input-group-sm">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="feather icon-search" style="font-size:12px;"></i></span>
                                    </div>
                                    <input type="text" class="form-control form-control-sm" id="bulkPnrSearch"
                                           placeholder="<?= __('search_by_pnr_or_passenger') ?? 'PNR or passenger name...' ?>">
                                </div>
                            </div>
                            <div class="col-md-2 col-sm-6 mb-2">
                                <label for="bulkDateFrom" style="font-size:11px; font-weight:600; color:#6c757d; margin-bottom:3px;"><?= __('departure_from') ?? 'Dep. From' ?></label>
                                <input type="date" class="form-control form-control-sm" id="bulkDateFrom">
                            </div>
                            <div class="col-md-2 col-sm-6 mb-2">
                                <label for="bulkDateTo" style="font-size:11px; font-weight:600; color:#6c757d; margin-bottom:3px;"><?= __('departure_to') ?? 'Dep. To' ?></label>
                                <input type="date" class="form-control form-control-sm" id="bulkDateTo">
                            </div>
                            <div class="col-md-5 col-sm-6 mb-2">
                                <div class="d-flex align-items-center" style="padding-top:18px;">
                                    <button type="button" class="btn btn-sm btn-outline-secondary mr-1" id="bulkClearFilters" title="<?= __('clear_filters') ?? 'Clear Filters' ?>">
                                        <i class="feather icon-x" style="font-size:12px;"></i> <?= __('clear') ?? 'Clear' ?>
                                    </button>
                                    <div class="ml-auto">
                                        <button type="button" class="btn btn-sm btn-outline-primary mr-1" id="bulkSelectAll">
                                            <i class="feather icon-check-square mr-1"></i><?= __('all') ?? 'All' ?>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="bulkDeselectAll">
                                            <i class="feather icon-square mr-1"></i><?= __('none') ?? 'None' ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th width="40" class="text-center align-middle">
                                            <div class="custom-control custom-checkbox">
                                                <input type="checkbox" class="custom-control-input" id="bulkSelectAllCheck">
                                                <label class="custom-control-label" for="bulkSelectAllCheck"></label>
                                            </div>
                                        </th>
                                        <th class="align-middle"><?= __('pnr') ?? 'PNR' ?></th>
                                        <th class="align-middle"><?= __('passenger') ?? 'Passenger' ?></th>
                                        <th class="align-middle"><?= __('route') ?? 'Route' ?></th>
                                        <th class="align-middle"><?= __('sold') ?? 'Sold' ?></th>
                                        <th class="align-middle"><?= __('paid') ?? 'Paid' ?></th>
                                        <th class="text-danger align-middle"><?= __('outstanding') ?? 'Outstanding' ?></th>
                                    </tr>
                                </thead>
                                <tbody id="bulkTicketTableBody">
                                    <tr>
                                        <td colspan="7" class="text-center text-muted">
                                            <?= __('select_client_to_load_tickets') ?? 'Select a client above to load their tickets' ?>
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot id="bulkTicketTableFoot" style="display:none;">
                                    <tr class="table-primary font-weight-bold">
                                        <td colspan="4" class="text-right"><?= __('total') ?? 'Total' ?>:</td>
                                        <td id="bulkTotalSold">0.00</td>
                                        <td id="bulkTotalPaid">0.00</td>
                                        <td id="bulkTotalOutstanding" class="text-danger">0.00</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Payment Details -->
                <div class="card mb-3" id="bulkPaymentSection">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="feather icon-credit-card mr-1"></i><?= __('payment_details') ?? 'Payment Details' ?></h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="bulkPaymentDate"><i class="feather icon-calendar mr-1"></i><?= __('payment_date') ?? 'Payment Date' ?> <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="bulkPaymentDate" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="bulkPaymentTime"><i class="feather icon-clock mr-1"></i><?= __('payment_time') ?? 'Payment Time' ?> <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" id="bulkPaymentTime" step="1" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="bulkPaymentCurrency"><i class="feather icon-dollar-sign mr-1"></i><?= __('currency') ?? 'Currency' ?> <span class="text-danger">*</span></label>
                                    <select class="form-control" id="bulkPaymentCurrency" required>
                                        <option value=""><?= __('select_currency') ?? 'Select Currency' ?></option>
                                        <option value="USD">USD</option>
                                        <option value="AFS">AFS</option>
                                        <option value="EUR">EUR</option>
                                        <option value="DARHAM">AED/DARHAM</option>
                                        <option value="SAR">SAR</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="bulkPaymentTotalAmount"><i class="feather icon-dollar-sign mr-1"></i><?= __('total_payment_amount') ?? 'Total Payment Amount' ?> <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="bulkPaymentTotalAmount" step="0.01" min="0.01" required placeholder="0.00">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-3" id="bulkExchangeRateField" style="display:none;">
                                <div class="form-group">
                                    <label for="bulkExchangeRate"><i class="feather icon-refresh-cw mr-1"></i><?= __('exchange_rate') ?? 'Exchange Rate' ?></label>
                                    <input type="number" class="form-control" id="bulkExchangeRate" step="0.01" placeholder="0.00">
                                    <small class="form-text text-muted" id="bulkExchangeRateHint"></small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="bulkPaymentDescription"><i class="feather icon-file-text mr-1"></i><?= __('description') ?? 'Description' ?> <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="bulkPaymentDescription" rows="2" required placeholder="<?= __('enter_description') ?? 'Enter payment description' ?>"></textarea>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="bulkReceiptNumber"><i class="feather icon-hash mr-1"></i><?= __('receipt_number') ?? 'Receipt #' ?></label>
                                    <input type="text" class="form-control" id="bulkReceiptNumber" placeholder="<?= __('receipt_number') ?? 'Receipt number' ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 4: Allocation Table -->
                <div class="card mb-3" id="bulkAllocationSection" style="display:none;">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="feather icon-table mr-1"></i><?= __('allocate_amounts') ?? 'Allocate Amounts' ?></h6>
                        <div id="bulkAllocationValidation" class="text-muted" style="font-size:13px;"></div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th><?= __('pnr') ?? 'PNR' ?></th>
                                        <th><?= __('passenger') ?? 'Passenger' ?></th>
                                        <th class="text-right"><?= __('outstanding') ?? 'Outstanding' ?></th>
                                        <th class="text-right" width="150"><?= __('allocate') ?? 'Allocate' ?></th>
                                    </tr>
                                </thead>
                                <tbody id="bulkAllocationBody">
                                </tbody>
                                <tfoot>
                                    <tr class="table-primary font-weight-bold">
                                        <td colspan="2" class="text-right"><?= __('total') ?? 'Total' ?>:</td>
                                        <td id="bulkAllocTotalOutstanding" class="text-right">0.00</td>
                                        <td id="bulkAllocTotalAllocate" class="text-right">0.00</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="feather icon-x mr-1"></i><?= __('close') ?? 'Close' ?>
                </button>
                <button type="button" class="btn btn-primary btn-lg" id="bulkSubmitPayment" disabled>
                    <i class="fas fa-money-bill-wave mr-1"></i><?= __('submit_bulk_payment') ?? 'Submit Bulk Payment' ?>
                </button>
            </div>
        </div>
    </div>
</div>
