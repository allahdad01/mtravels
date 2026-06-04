    <!-- Hotel Refund Transaction Modal -->
    <div class="modal fade" id="refundTransactionModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="feather icon-credit-card mr-2"></i><?= __('manage_transactions') ?>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" style="max-height: 75vh; overflow-y: auto;">
                    <!-- Hotel Refund Info Card -->
                    <div class="card mb-4 border-primary">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-muted mb-2"><?= __('hotel_refund_details') ?></h6>
                                    <p class="mb-1"><strong><?= __('guest_name') ?>:</strong> <span id="trans-guest-name"></span></p>
                                    <p class="mb-1"><strong><?= __('booking_id') ?>:</strong> <span id="trans-order-id"></span></p>
                                    <p class="mb-1"><strong><?= __('refund_type') ?>:</strong> <span id="refundType"></span></p>
                                    <p class="mb-1"><strong><?= __('hotel_name') ?>:</strong> <span id="refundHotel"></span></p>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-muted mb-2"><?= __('financial_summary') ?></h6>
                                    <p class="mb-1"><strong><?= __('total_amount') ?>:</strong> <span id="totalAmount"></span></p>
                                    <p class="mb-1"><strong><?= __('exchange_rate') ?>:</strong> <span id="exchangeRateDisplay"></span></p>
                                    <p class="mb-1"><strong><?= __('exchanged_amount') ?>:</strong> <span id="exchangedAmount"></span></p>
                                    <div id="usdSection" style="display: none;">
                                        <p class="mb-1"><strong><?= __('paid_amount_usd') ?>:</strong> <span id="paidAmountUSD" class="text-success">USD 0.00</span></p>
                                        <p class="mb-1"><strong><?= __('remaining_amount_usd') ?>:</strong> <span id="remainingAmountUSD" class="text-danger">USD 0.00</span></p>
                                    </div>
                                    <div id="afsSection" style="display: none;">
                                        <p class="mb-1"><strong><?= __('paid_amount_afs') ?>:</strong> <span id="paidAmountAFS" class="text-success">AFS 0.00</span></p>
                                        <p class="mb-1"><strong><?= __('remaining_amount_afs') ?>:</strong> <span id="remainingAmountAFS" class="text-danger">AFS 0.00</span></p>
                                    </div>
                                    <div id="eurSection" style="display: none;">
                                        <p class="mb-1"><strong><?= __('paid_amount_eur') ?>:</strong> <span id="paidAmountEUR" class="text-success">EUR 0.00</span></p>
                                        <p class="mb-1"><strong><?= __('remaining_amount_eur') ?>:</strong> <span id="remainingAmountEUR" class="text-danger">EUR 0.00</span></p>
                                    </div>
                                    <div id="aedSection" style="display: none;">
                                        <p class="mb-1"><strong><?= __('paid_amount_aed') ?>:</strong> <span id="paidAmountAED" class="text-success">AED 0.00</span></p>
                                        <p class="mb-1"><strong><?= __('remaining_amount_aed') ?>:</strong> <span id="remainingAmountAED" class="text-danger">AED 0.00</span></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Transactions Table -->
                    <div class="card mb-4">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><?= __('transaction_history') ?></h6>
                            <button type="button" class="btn btn-sm btn-primary" data-toggle="collapse" data-target="#addTransactionForm">
                                <i class="feather icon-plus"></i> <?= __('new_transaction') ?>
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th><?= __('date') ?></th>
                                            <th><?= __('description') ?></th>
                                            <th><?= __('receipt') ?></th>
                                            <th><?= __('amount') ?></th>
                                            <th><?= __('exchange_rate') ?></th>
                                            <th class="text-center"><?= __('actions') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody id="transactionTableBody">
                                        <!-- Transactions will be loaded here -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Add Transaction Form -->
                    <div id="addTransactionForm" class="collapse">
                        <div class="card border-primary">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0"><?= __('add_new_transaction') ?></h6>
                            </div>
                            <div class="card-body">
                                <form id="hotelTransactionForm">
                    <!-- CSRF Protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                                    <input type="hidden" id="refund_id" name="refund_id">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="paymentDate">
                                                    <i class="feather icon-calendar mr-1"></i><?= __('payment_date') ?>
                                                </label>
                                                <input type="date" class="form-control" id="paymentDate" name="payment_date" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="paymentTime">
                                                    <i class="feather icon-clock mr-1"></i><?= __('payment_time') ?>
                                                </label>
                                                <input type="time" class="form-control" id="paymentTime" name="payment_time" step="1" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="paymentAmount">
                                                    <i class="feather icon-dollar-sign mr-1"></i><?= __('amount') ?>
                                                </label>
                                                <input type="number" class="form-control" id="paymentAmount" 
                                                       name="payment_amount" step="0.01" min="0.01" required 
                                                       placeholder="<?= __('enter_amount') ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="paymentCurrency">
                                                    <i class="feather icon-dollar-sign mr-1"></i><?= __('currency') ?>
                                                </label>
                                                <select class="form-control" id="paymentCurrency" name="payment_currency" required>
                                                    <option value=""><?= __('select_currency') ?></option>
                                                    <option value="USD"><?= __('usd') ?></option>
                                                    <option value="AFS"><?= __('afs') ?></option>
                                                    <option value="EUR"><?= __('eur') ?></option>
                                                    <option value="DARHAM"><?= __('darham') ?></option>
                                                </select>
                                            </div>
                                        </div>
                                        
                                    </div>

                                    <div class="form-group" id="exchangeRateField" style="display: none;">
                                         <label id="exchangeRateLabel" for="transactionExchangeRate">
                                             <i class="feather icon-refresh-cw mr-1"></i><?= __('exchange_rate') ?>
                                         </label>
                                         <input type="number" class="form-control" id="transactionExchangeRate"
                                                name="exchange_rate" step="0.01" placeholder="0.00">
                                         <small class="form-text text-muted d-block mt-1">
                                             Enter how many <span id="exchangeRateTarget"></span> equals 1 <span id="exchangeRateBase"></span>
                                             <span id="exchangeRateExample" class="d-block mt-1" style="color: #666;"></span>
                                         </small>
                                     </div>
                                    <div class="form-group">
                                        <label for="mainAccountId">
                                            <i class="feather icon-briefcase mr-1"></i><?= __('main_account') ?>
                                        </label>
                                        <select class="form-control" id="mainAccountId" name="main_account_id" required>
                                            <option value=""><?= __('select_main_account') ?></option>
                                            <?php
                                            try {
                                                $accountsQuery = "SELECT id, name FROM main_account WHERE status = 'active' AND tenant_id = :tenant_id AND branch_id = :branch_id";
                                                $stmt = $pdo->prepare($accountsQuery);
                                                $stmt->bindParam(':tenant_id', $tenant_id, PDO::PARAM_INT);
                                                $stmt->bindParam(':branch_id', $branch_id, PDO::PARAM_INT);
                                                $stmt->execute();
                                                $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                                foreach ($accounts as $account) {
                                                    echo '<option value="' . $account['id'] . '">' . htmlspecialchars($account['name']) . '</option>';
                                                }
                                            } catch (PDOException $e) {
                                                error_log("Error fetching main accounts: " . $e->getMessage());
                                                echo '<option value="">' . __('error_loading_accounts') . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="paymentDescription">
                                            <i class="feather icon-file-text mr-1"></i><?= __('description') ?>
                                        </label>
                                        <textarea class="form-control" id="paymentDescription" 
                                                  name="payment_description" rows="2" required
                                                  placeholder="<?= __('enter_payment_description') ?>"></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label for="receiptNumber">
                                            <i class="feather icon-hash mr-1"></i><?= __('receipt_number') ?>
                                        </label>
                                        <input type="text" class="form-control" id="receiptNumber"
                                               name="receipt_number" placeholder="<?= __('enter_receipt_number') ?>">
                                    </div>

                                    <div class="text-right mt-3">
                                        <button type="button" class="btn btn-secondary" data-toggle="collapse" 
                                                data-target="#addTransactionForm">
                                            <i class="feather icon-x mr-1"></i><?= __('cancel') ?>
                                        </button>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="feather icon-check mr-1"></i><?= __('add_transaction') ?>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="feather icon-x mr-1"></i><?= __('close') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
