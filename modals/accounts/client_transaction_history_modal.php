                <!-- Client Transaction History Modal -->
                <div class="modal fade modern-modal" id="clientTransactionHistoryModal" tabindex="-1" aria-labelledby="clientTransactionHistoryModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content">
                            <div class="modal-header bg-success text-white">
                                <h5 class="modal-title" id="clientTransactionHistoryModalLabel">
                                    <i class="feather icon-list mr-2"></i><?= __('client_transactions') ?>
                                </h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <div class="text-center mb-4">
                                    <h5 id="clientNameDisplay" class="font-weight-bold text-success mb-0"></h5>
                                    <p class="text-muted small"><?= __('transaction_history') ?></p>
                                </div>
                                
                                                                <!-- Currency and Search Filters -->
                                <div class="row mb-4">
                                    <div class="col-md-3">
                                        <div class="form-group mb-0">
                                            <label for="clientCurrencyFilter" class="small font-weight-bold"><?= __('filter_by_currency') ?></label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text bg-light"><i class="feather icon-filter"></i></span>
                                                </div>
                                                <select id="clientCurrencyFilter" class="form-control">
                                                <option value="all"><?= __('all_currencies') ?></option>
                                                    <option value="USD"><?= __('usd') ?> ($)</option>
                                                    <option value="AFS"><?= __('afs') ?> (؋)</option>
                                            </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group mb-0">
                                                <label for="clientReceiptSearch" class="small font-weight-bold"><?= __('search_by_receipt') ?></label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text bg-light"><i class="feather icon-search"></i></span>
                                        </div>
                                                <input type="text" id="clientReceiptSearch" class="form-control" placeholder="<?= __('enter_receipt_number') ?>">
                                    </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group mb-0">
                                            <label for="clientDateRangeFilter" class="small font-weight-bold"><?= __('date_range') ?></label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text bg-light"><i class="feather icon-calendar"></i></span>
                                                </div>
                                                <input type="text" id="clientDateRangeFilter" class="form-control" placeholder="<?= __('select_date_range') ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group mb-0 d-flex flex-column h-100">
                                            <label class="small font-weight-bold d-block">&nbsp;</label>
                                            <div class="d-flex align-items-center mt-auto">
                                                <button type="button" class="btn btn-success btn-sm w-100" id="printClientTransactionsBtn">
                                                    <i class="feather icon-printer mr-1"></i><?= __('export_pdf') ?>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                </div>

                                <div class="table-responsive rounded" style="max-height: 650px; overflow-y: auto; overflow-x: auto; border: 1px solid #ddd; border-radius: 4px;">
                                    <table class="table table-sm table-hover table-striped mb-0" id="clientTransactionsTable" style="min-width: 100%; font-size: 0.75rem;">
                                        <thead class="bg-light" style="position: sticky; top: 0; z-index: 10;">
                                            <tr style="font-size: 0.7rem; padding: 2px;">
                                                <th style="white-space: nowrap; padding: 4px 2px;">#</th>
                                                <th style="white-space: nowrap; min-width: 120px; padding: 4px 2px;"><?= __('date') ?></th>
                                                <th style="min-width: 200px; padding: 4px 2px;"><?= __('description') ?></th>
                                                <th style="white-space: nowrap; min-width: 100px; padding: 4px 2px;"><?= __('receipt') ?></th>
                                                <th style="white-space: nowrap; min-width: 110px; padding: 4px 2px;"><?= __('category') ?></th>
                                                <th style="min-width: 150px; padding: 4px 2px;"><?= __('reference') ?></th>
                                                <th style="white-space: nowrap; min-width: 90px; padding: 4px 2px;"><?= __('debit') ?></th>
                                                <th style="white-space: nowrap; min-width: 90px; padding: 4px 2px;"><?= __('credit') ?></th>
                                                <th style="white-space: nowrap; min-width: 100px; padding: 4px 2px;"><?= __('balance') ?></th>
                                                <th style="white-space: nowrap; min-width: 70px; padding: 4px 2px;"><?= __('currency') ?></th>
                                                <th class="text-center" style="white-space: nowrap; min-width: 80px; padding: 4px 2px;"><?= __('actions') ?></th>
                                            </tr>
                                        </thead>
                                        <tbody id="clientTransactionsTableBody" style="font-size: 0.75rem;">
                                            <!-- Transactions will be loaded here -->
                                        </tbody>
                                    </table>
                                </div>
                                <div id="clientTransactionsPagination" class="d-flex justify-content-center align-items-center mt-3 d-none">
                                    <nav aria-label="Client transaction pagination">
                                        <ul class="pagination mb-0" id="clientTransactionsPaginationList">
                                            <!-- Pagination buttons will be inserted here -->
                                        </ul>
                                    </nav>
                                </div>
                                <div id="noClientTransactionsMessage" class="text-center py-5 d-none">
                                    <div class="empty-state">
                                        <i class="feather icon-inbox text-muted mb-3" style="font-size: 3rem;"></i>
                                        <h6 class="mt-3"><?= __('no_transactions_found') ?></h6>
                                        <p class="text-muted small"><?= __('no_transactions_found_for_this_client') ?></p>
                                </div>
                                </div>
                                <div id="clientTransactionsLoader" class="text-center py-5">
                                    <div class="spinner-border text-success" role="status">
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