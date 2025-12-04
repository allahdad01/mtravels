                <!-- Supplier Transaction History Modal -->
                <div class="modal fade modern-modal" id="supplierTransactionHistoryModal" tabindex="-1" aria-labelledby="supplierTransactionHistoryModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content">
                            <div class="modal-header bg-info text-white">
                                <h5 class="modal-title" id="supplierTransactionHistoryModalLabel">
                                    <i class="feather icon-list mr-2"></i><?= __('supplier_transactions') ?>
                                </h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <div class="text-center mb-4">
                                    <h5 id="supplierNameDisplay" class="font-weight-bold text-info mb-0"></h5>
                                    <p class="text-muted small"><?= __('transaction_history') ?></p>
                                </div>
                                <!-- Currency and Search Filters -->
                                <div class="row mb-4">
                                    <div class="col-md-4">
                                        <div class="form-group mb-0">
                                            <label for="supplierReceiptSearch" class="small font-weight-bold"><?= __('search_by_receipt') ?></label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text bg-light"><i class="feather icon-search"></i></span>
                                        </div>
                                                <input type="text" id="supplierReceiptSearch" class="form-control" placeholder="<?= __('enter_receipt_number') ?>">
                                    </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group mb-0">
                                            <label for="supplierDateRangeFilter" class="small font-weight-bold"><?= __('date_range') ?></label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text bg-light"><i class="feather icon-calendar"></i></span>
                                        </div>
                                                <input type="text" id="supplierDateRangeFilter" class="form-control" placeholder="<?= __('select_date_range') ?>">
                                    </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group mb-0 d-flex flex-column h-100">
                                            <label class="small font-weight-bold d-block">&nbsp;</label>
                                            <div class="d-flex align-items-center mt-auto">
                                                <button type="button" class="btn btn-info btn-sm w-100" id="printSupplierTransactionsBtn">
                                                    <i class="feather icon-printer mr-1"></i><?= __('export_pdf') ?>
                                            </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                
                                <div class="table-responsive rounded">
                                    <table class="table table-hover table-striped mb-0" id="supplierTransactionsTable">
                                        <thead class="bg-light">
                                            <tr>
                                                <th>#</th>
                                                <th><?= __('date') ?></th>
                                                <th><?= __('remarks') ?></th>
                                                <th><?= __('receipt') ?></th>
                                                <th><?= __('category') ?></th>
                                                <th><?= __('reference') ?></th>
                                                <th><?= __('debit') ?></th>
                                                <th><?= __('credit') ?></th>
                                                <th><?= __('balance') ?></th>                               
                                                <th class="text-center"><?= __('actions') ?></th>
                                            </tr>
                                        </thead>
                                        <tbody id="supplierTransactionsTableBody">
                                            <!-- Transactions will be loaded here -->
                                        </tbody>
                                    </table>
                                </div>
                                <div id="noSupplierTransactionsMessage" class="text-center py-5 d-none">
                                    <div class="empty-state">
                                        <i class="feather icon-inbox text-muted mb-3" style="font-size: 3rem;"></i>
                                        <h6 class="mt-3"><?= __('no_transactions_found') ?></h6>
                                        <p class="text-muted small"><?= __('no_transactions_found_for_this_supplier') ?></p>
                                </div>
                                </div>
                                <div id="supplierTransactionsLoader" class="text-center py-5">
                                    <div class="spinner-border text-info" role="status">
                                        <span class="visually-hidden"><?= __('loading') ?>...</span>
                                    </div>
                                    <p class="mt-3"><?= __('loading_transactions') ?>...</p>
                                </div>
                            </div>
                            <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
                            </div>
                        </div>
                    </div>
                </div>