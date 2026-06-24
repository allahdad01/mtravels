<div class="modal fade" id="clientWithdrawModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content pm-card">

            <div class="pm-header">
                <div class="pm-header-left">
                    <div class="pm-header-icon"><i class="feather icon-arrow-down"></i></div>
                    <div>
                        <div class="pm-title">Withdraw</div>
                        <div class="pm-subtitle" id="cwClientNameDisplay">—</div>
                    </div>
                </div>
                <button type="button" class="pm-close" data-dismiss="modal">
                    <i class="feather icon-x"></i>
                </button>
            </div>

            <div class="pm-balances">
                <div class="pm-bal-item">
                    <span class="pm-bal-label">USD Balance</span>
                    <span class="pm-bal-value usd" id="cwUsdBalance">$0.00</span>
                </div>
                <div class="pm-bal-divider"></div>
                <div class="pm-bal-item">
                    <span class="pm-bal-label">AFS Balance</span>
                    <span class="pm-bal-value afs" id="cwAfsBalance">؋0.00</span>
                </div>
            </div>

            <form id="clientWithdrawForm" class="pm-body">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                <input type="hidden" id="cwClientId"   name="client_id">
                <input type="hidden" id="cwClientName" name="client_name">
                <input type="hidden" name="usd_amount" id="cwHiddenUsdAmount" value="0">
                <input type="hidden" name="afs_amount" id="cwHiddenAfsAmount" value="0">

                <div class="pm-field" id="cwFieldBalanceCurrency">
                    <label class="pm-label">
                        <span class="pm-step">1</span>
                        Which balance to deduct from?
                    </label>
                    <div class="pm-toggle-group">
                        <button type="button" class="pm-toggle" data-value="USD" data-target="cwBalanceCurrency">
                            <i class="fas fa-dollar-sign"></i> USD Balance
                        </button>
                        <button type="button" class="pm-toggle" data-value="AFS" data-target="cwBalanceCurrency">
                            <i class="fas fa-money-bill-wave"></i> AFS Balance
                        </button>
                    </div>
                    <input type="hidden" id="cwBalanceCurrency" name="payment_currency">
                </div>

                <div class="pm-field pm-hidden" id="cwFieldPaymentCurrency">
                    <label class="pm-label">
                        <span class="pm-step">2</span>
                        What currency is being withdrawn?
                    </label>
                    <div class="pm-toggle-group">
                        <button type="button" class="pm-toggle" data-value="USD" data-target="cwPaymentCurrency">
                            <i class="fas fa-dollar-sign"></i> Withdrawing USD
                        </button>
                        <button type="button" class="pm-toggle" data-value="AFS" data-target="cwPaymentCurrency">
                            <i class="fas fa-money-bill-wave"></i> Withdrawing AFS
                        </button>
                    </div>
                    <input type="hidden" id="cwPaymentCurrency" name="payment_currency_actual">
                </div>

                <div class="pm-field pm-hidden" id="cwFieldExchangeRate">
                    <label class="pm-label">
                        <span class="pm-step">3</span>
                        Exchange Rate
                    </label>
                    <div class="pm-input-wrap">
                        <span class="pm-input-pre">1 USD =</span>
                        <input type="number" class="pm-input" id="cwExchangeRate" name="exchange_rate"
                               step="0.01" min="0.01" placeholder="e.g. 70">
                        <span class="pm-input-suf">AFS</span>
                    </div>
                </div>

                <div class="pm-field pm-hidden" id="cwFieldAmount">
                    <label class="pm-label">
                        <span class="pm-step" id="cwStepAmount">3</span>
                        Amount to Withdraw
                        <span class="pm-label-hint" id="cwAmountHint">in USD</span>
                    </label>
                    <div class="pm-input-wrap">
                        <span class="pm-input-pre" id="cwAmountSymbol">$</span>
                        <input type="number" class="pm-input" id="cwTotalAmount" name="total_amount"
                               step="0.01" min="0.01" placeholder="0.00">
                    </div>
                    <div class="pm-conversion" id="cwConversionPreview"></div>
                </div>

                <div class="pm-field pm-hidden" id="cwFieldMainAccount">
                    <label class="pm-label">
                        <span class="pm-step" id="cwStepAccount">4</span>
                        Main Account
                    </label>
                    <div class="pm-input-wrap">
                        <span class="pm-input-pre"><i class="feather icon-briefcase"></i></span>
                        <select class="pm-input pm-select" id="cwMainAccount" name="main_account">
                            <option value="">Select account…</option>
                            <?php foreach ($mainAccounts as $account): ?>
                                <option value="<?= $account['id'] ?>"><?= htmlspecialchars($account['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="pm-field pm-hidden" id="cwFieldReceipt">
                    <label class="pm-label">
                        <span class="pm-step" id="cwStepReceipt">5</span>
                        Receipt Number
                    </label>
                    <div class="pm-input-wrap">
                        <span class="pm-input-pre"><i class="feather icon-hash"></i></span>
                        <input type="text" class="pm-input" id="cwReceiptNumber" name="receipt_number"
                               placeholder="Enter receipt number">
                    </div>
                </div>

                <div class="pm-field pm-hidden" id="cwFieldRemarks">
                    <label class="pm-label">
                        <span class="pm-step" id="cwStepRemarks">6</span>
                        Remarks
                        <span class="pm-optional">optional</span>
                    </label>
                    <div class="pm-input-wrap pm-textarea-wrap">
                        <span class="pm-input-pre"><i class="feather icon-message-square"></i></span>
                        <textarea class="pm-input pm-textarea" id="cwRemarks" name="remarks"
                                  rows="2" placeholder="Enter withdraw details…"></textarea>
                    </div>
                </div>

            </form>

            <div class="pm-footer">
                <button type="button" class="pm-btn-cancel" data-dismiss="modal">
                    <i class="feather icon-x"></i> Cancel
                </button>
                <button type="button" class="pm-btn-submit" id="cwProcessBtn" disabled>
                    <i class="feather icon-check-circle"></i> Process Withdrawal
                </button>
            </div>

        </div>
    </div>
</div>

<script>
(function () {

    const s = { balance: null, payment: null };

    const $ = id => document.getElementById(id);

    let fields = {
        paymentCurrency: null,
        exchangeRate:    null,
        amount:          null,
        mainAccount:     null,
        receipt:         null,
        remarks:         null,
    };

    function initializeFields() {
        fields = {
            paymentCurrency: $('cwFieldPaymentCurrency'),
            exchangeRate:    $('cwFieldExchangeRate'),
            amount:          $('cwFieldAmount'),
            mainAccount:     $('cwFieldMainAccount'),
            receipt:         $('cwFieldReceipt'),
            remarks:         $('cwFieldRemarks'),
        };
    }

    function reveal(el) {
        el.classList.remove('pm-hidden');
        el.classList.add('pm-reveal');
    }
    function hide(el) {
        el.classList.add('pm-hidden');
        el.classList.remove('pm-reveal');
    }
    function clearField(el) {
        el.querySelectorAll('input[type=text], input[type=number], select, textarea')
          .forEach(i => { i.value = ''; });
        el.querySelectorAll('.pm-toggle').forEach(b => b.classList.remove('pm-active'));
    }

    const fieldOrder = ['paymentCurrency','exchangeRate','amount','mainAccount','receipt','remarks'];
    function resetFrom(key) {
        const idx = fieldOrder.indexOf(key);
        fieldOrder.slice(idx).forEach(k => {
            hide(fields[k]);
            clearField(fields[k]);
        });
        $('cwConversionPreview').textContent = '';
        $('cwProcessBtn').disabled = true;
    }

    function updateSteps() {
        const hasRate = !fields.exchangeRate.classList.contains('pm-hidden');
        $('cwStepAmount').textContent  = hasRate ? '4' : '3';
        $('cwStepAccount').textContent = hasRate ? '5' : '4';
        $('cwStepReceipt').textContent = hasRate ? '6' : '5';
        $('cwStepRemarks').textContent = hasRate ? '7' : '6';
    }

    function configureAmountField() {
        if (!s.payment) return;
        $('cwAmountSymbol').textContent = s.payment === 'USD' ? '$' : '؋';
        $('cwAmountHint').textContent   = s.payment === 'USD' ? 'USD to withdraw' : 'AFS to withdraw';
    }

    function updateConversion() {
        const rate   = parseFloat($('cwExchangeRate').value) || 0;
        const amount = parseFloat($('cwTotalAmount').value)  || 0;
        const prev   = $('cwConversionPreview');

        if (!s.balance || !s.payment || s.balance === s.payment || !amount || !rate) {
            prev.textContent = '';
            return;
        }

        if (s.balance === 'USD' && s.payment === 'AFS') {
            const inUsd = (amount / rate).toFixed(2);
            prev.textContent = `؋${amount.toLocaleString()} ÷ ${rate} = $${inUsd} deducted from USD balance`;
        } else if (s.balance === 'AFS' && s.payment === 'USD') {
            const inAfs = (amount * rate).toFixed(2);
            prev.textContent = `$${amount.toLocaleString()} × ${rate} = ؋${inAfs} deducted from AFS balance`;
        }
    }

    function syncHiddenAmounts() {
        const amount = parseFloat($('cwTotalAmount').value) || 0;
        if (s.payment === 'USD') {
            $('cwHiddenUsdAmount').value = amount;
            $('cwHiddenAfsAmount').value = 0;
        } else {
            $('cwHiddenUsdAmount').value = 0;
            $('cwHiddenAfsAmount').value = amount;
        }
    }

    function checkSubmit() {
        const rateOk   = s.balance === s.payment || parseFloat($('cwExchangeRate').value) > 0;
        const amountOk = parseFloat($('cwTotalAmount').value) > 0;
        const accountOk = $('cwMainAccount').value !== '';
        const receiptOk = $('cwReceiptNumber').value.trim() !== '';

        const ready = s.balance && s.payment && rateOk && amountOk && accountOk && receiptOk;
        $('cwProcessBtn').disabled = !ready;
        if (ready) syncHiddenAmounts();
    }

    function attachEventListeners() {
        document.querySelectorAll('#clientWithdrawModal .pm-toggle').forEach(btn => {
        btn.addEventListener('click', function () {
            const target = this.dataset.target;
            const value  = this.dataset.value;

            document.querySelectorAll(`#clientWithdrawModal .pm-toggle[data-target="${target}"]`)
                    .forEach(b => b.classList.remove('pm-active'));
            this.classList.add('pm-active');
            $(target).value = value;

            if (target === 'cwBalanceCurrency') {
                s.balance = value;
                resetFrom('paymentCurrency');
                this.classList.add('pm-active');
                reveal(fields.paymentCurrency);

            } else if (target === 'cwPaymentCurrency') {
                s.payment = value;
                resetFrom('exchangeRate');

                if (s.balance !== s.payment) {
                    reveal(fields.exchangeRate);
                    configureAmountField();
                    reveal(fields.amount);
                    updateSteps();
                } else {
                    $('cwExchangeRate').value = '1';
                    configureAmountField();
                    updateSteps();
                    reveal(fields.amount);
                }
            }
        });
    });

    const cwExchangeRateInput = $('cwExchangeRate');
    if (cwExchangeRateInput) {
        cwExchangeRateInput.addEventListener('input', function () {
            if (parseFloat(this.value) > 0) {
                configureAmountField();
                if (fields.amount && fields.amount.classList.contains('pm-hidden')) {
                    reveal(fields.amount);
                }
                updateConversion();
            }
            checkSubmit();
        });
    }

    $('cwTotalAmount').addEventListener('input', function () {
        updateConversion();
        if (parseFloat(this.value) > 0) {
            if (fields.mainAccount && fields.mainAccount.classList.contains('pm-hidden')) {
                reveal(fields.mainAccount);
            }
        }
        checkSubmit();
    });

    $('cwMainAccount').addEventListener('change', function () {
        if (this.value) {
            if (fields.receipt && fields.receipt.classList.contains('pm-hidden')) {
                reveal(fields.receipt);
            }
        }
        checkSubmit();
    });

    $('cwReceiptNumber').addEventListener('input', function () {
        if (this.value.trim()) {
            if (fields.remarks && fields.remarks.classList.contains('pm-hidden')) {
                reveal(fields.remarks);
            }
        }
        checkSubmit();
    });

    $('cwRemarks').addEventListener('input', checkSubmit);

    const processBtn = $('cwProcessBtn');
    if (processBtn) {
        processBtn.addEventListener('click', function () {
            const form = document.getElementById('clientWithdrawForm');
            if (!form) {
                alert('Withdraw form not found');
                return;
            }

            var originalHtml = processBtn.innerHTML;
            processBtn.disabled = true;
            processBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Processing...';

            const balEl = document.querySelector('input[name="payment_currency"]');
            const payEl = document.querySelector('input[name="payment_currency_actual"]');
            if (balEl) balEl.value = s.balance;
            if (payEl) payEl.value = s.payment;

            const formData = new FormData(form);

            fetch('../api/accounts/withdraw_client.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Withdrawal processed successfully');
                    jQuery('#clientWithdrawModal').modal('hide');
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Unknown error'));
                }
                processBtn.disabled = false;
                processBtn.innerHTML = originalHtml;
            })
            .catch(error => {
                console.error('Error:', error);
                processBtn.disabled = false;
                processBtn.innerHTML = originalHtml;
                alert('Failed to process withdrawal');
            });
        });
    }
    }

    window.openClientWithdrawModal = function (clientId, name, usdBal, afsBal) {
        if (!fields.paymentCurrency) {
            initializeFields();
        }
        const form = document.getElementById('clientWithdrawForm');
        if (!form) {
            console.error('Withdraw modal form not found');
            return;
        }

        form.reset();
        document.querySelectorAll('#clientWithdrawModal .pm-toggle').forEach(b => b.classList.remove('pm-active'));
        Object.values(fields).forEach(f => { hide(f); clearField(f); });
        s.balance = null; s.payment = null;
        $('cwConversionPreview').textContent = '';
        $('cwProcessBtn').disabled = true;

        $('cwClientId').value            = clientId;
        $('cwClientName').value          = name;
        $('cwClientNameDisplay').textContent = name;
        $('cwUsdBalance').textContent    = '$' + parseFloat(usdBal).toFixed(2);
        $('cwAfsBalance').textContent    = '؋' + parseFloat(afsBal).toFixed(2);

        try {
            const modalEl = document.getElementById('clientWithdrawModal');
            if (modalEl && typeof jQuery !== 'undefined') {
                jQuery(modalEl).modal('show');
            } else {
                console.error('Withdraw modal not found or jQuery not available');
            }
        } catch (e) {
            console.error('Error showing withdraw modal:', e);
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            initializeFields();
            attachEventListeners();
        });
    } else {
        initializeFields();
        attachEventListeners();
    }

})();
</script>
