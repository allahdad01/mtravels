<!-- Main Account Transaction History Modal -->
<div class="modal fade modern-modal" id="transactionHistoryModal" tabindex="-1" aria-labelledby="transactionHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="transactionHistoryModalLabel">
                    <i class="feather icon-list mr-2"></i><?= __('account_transactions') ?>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    <h5 id="accountNameDisplay" class="font-weight-bold text-primary mb-0"></h5>
                    <p class="text-muted small"><?= __('transaction_history') ?></p>
                    </div>
                    
                <!-- Currency Filter -->
                <div class="row mb-4 no-gutters">
                    <div class="col-md-3 pr-md-2 mb-2 mb-md-0">
                        <div class="form-group mb-0">
                            <label for="mainAccountCurrencyFilter" class="small font-weight-bold"><?= __('filter_by_currency') ?></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-light"><i class="feather icon-filter"></i></span>
                                </div>
                                <select id="mainAccountCurrencyFilter" class="form-control">
                                    <option value="all"><?= __('all_currencies') ?></option>
                                    <option value="USD"><?= __('usd') ?> ($)</option>
                                    <option value="AFS"><?= __('afs') ?> (؋)</option>
                                    <option value="EUR"><?= __('eur') ?> (€)</option>
                                    <option value="DARHAM"><?= __('darham') ?> (AED)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 px-md-2 mb-2 mb-md-0">
                        <div class="form-group mb-0">
                            <label for="receiptSearch" class="small font-weight-bold"><?= __('search_by_receipt') ?></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-light"><i class="feather icon-search"></i></span>
                                </div>
                                <input type="text" id="receiptSearch" class="form-control" placeholder="<?= __('enter_receipt_number') ?>">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 px-md-2 mb-2 mb-md-0">
                        <div class="form-group mb-0">
                            <label for="dateRangeFilter" class="small font-weight-bold"><?= __('date_range') ?></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-light"><i class="feather icon-calendar"></i></span>
                                </div>
                                <input type="text" id="dateRangeFilter" class="form-control" placeholder="<?= __('select_date_range') ?>">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 pl-md-2">
                        <div class="form-group mb-0 d-flex flex-column h-100">
                            <label class="small font-weight-bold d-block">&nbsp;</label>
                            <div class="d-flex align-items-center mt-auto">
                                <button type="button" class="btn btn-primary btn-sm w-100" id="printTransactionsBtn">
                                    <i class="feather icon-printer mr-1"></i><?= __('export_pdf') ?>
                                </button>
                            </div>
                        </div>
                    </div>
                    </div>

                <div class="table-responsive rounded" style="max-height: calc(100vh - 280px); overflow-y: auto; overflow-x: auto; border: 1px solid #ddd; border-radius: 4px;">
                    <table class="table table-sm table-hover table-striped mb-0" id="transactionsTable" style="min-width: 100%; font-size: 0.75rem;">
                        <thead class="bg-light" style="position: sticky; top: 0; z-index: 10;">
                            <tr style="font-size: 0.7rem; padding: 2px;">
                                <th style="white-space: nowrap; padding: 4px 2px;">#</th>
                                <th style="white-space: nowrap; min-width: 120px; padding: 4px 2px;"><?= __('date') ?></th>
                                <th style="min-width: 200px; padding: 4px 2px;"><?= __('description') ?></th>
                                <th style="white-space: nowrap; min-width: 100px; padding: 4px 2px;"><?= __('receipt') ?></th>
                                <th style="min-width: 150px; padding: 4px 2px;"><?= __('reference') ?></th>
                                <th style="white-space: nowrap; min-width: 90px; padding: 4px 2px;"><?= __('debit') ?></th>
                                <th style="white-space: nowrap; min-width: 90px; padding: 4px 2px;"><?= __('credit') ?></th>
                                <th style="white-space: nowrap; min-width: 100px; padding: 4px 2px;"><?= __('balance') ?></th>
                                <th style="white-space: nowrap; min-width: 70px; padding: 4px 2px;"><?= __('currency') ?></th>
                                <th class="text-center" style="white-space: nowrap; min-width: 80px; padding: 4px 2px;"><?= __('actions') ?></th>
                            </tr>
                        </thead>
                        <tbody id="transactionsTableBody" style="font-size: 0.75rem;">
                            <!-- Transactions will be loaded here -->
                        </tbody>
                    </table>
                </div>
                <div id="transactionsPagination" class="d-flex justify-content-center align-items-center mt-3 d-none">
                    <nav aria-label="Transaction pagination">
                        <ul class="pagination mb-0" id="mainTransactionsPaginationList">
                            <!-- Pagination buttons will be inserted here -->
                        </ul>
                    </nav>
                </div>
                            <div id="noTransactionsMessage" class="text-center py-5 d-none">
                                <div class="empty-state">
                                    <i class="feather icon-inbox text-muted mb-3" style="font-size: 3rem;"></i>
                                    <h6 class="mt-3"><?= __('no_transactions_found') ?></h6>
                                    <p class="text-muted small"><?= __('no_transactions_found_for_this_account') ?></p>
                                </div>
                            </div>
                            <div id="transactionsLoader" class="text-center py-5">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden"><?= __('loading') ?>...</span>
                                </div>
                                <p class="mt-3"><?= __('loading_transactions') ?>...</p>
                            </div>
                         <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
                    </div>
                </div>
            </div>
        </div>
    </div>