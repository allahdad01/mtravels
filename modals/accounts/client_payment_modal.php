<!-- ═══════════════════════════════════════════════
     PARTIAL PAYMENT MODAL
     Flow: Balance Currency → Payment Currency
           → [Exchange Rate if mismatch]
           → Amount → Main Account → Receipt → Remarks
     ═══════════════════════════════════════════════ -->

<div class="modal fade" id="partialPaymentModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content pm-card">

            <!-- Header -->
            <div class="pm-header">
                <div class="pm-header-left">
                    <div class="pm-header-icon"><i class="feather icon-credit-card"></i></div>
                    <div>
                        <div class="pm-title">Make Payment</div>
                        <div class="pm-subtitle" id="pmClientNameDisplay">—</div>
                    </div>
                </div>
                <button type="button" class="pm-close" data-dismiss="modal">
                    <i class="feather icon-x"></i>
                </button>
            </div>

            <!-- Current Balances -->
            <div class="pm-balances">
                <div class="pm-bal-item">
                    <span class="pm-bal-label">USD Balance</span>
                    <span class="pm-bal-value usd" id="pmUsdBalance">$0.00</span>
                </div>
                <div class="pm-bal-divider"></div>
                <div class="pm-bal-item">
                    <span class="pm-bal-label">AFS Balance</span>
                    <span class="pm-bal-value afs" id="pmAfsBalance">؋0.00</span>
                </div>
            </div>

            <!-- Form -->
            <form id="partialPaymentForm" class="pm-body">
                <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                <input type="hidden" id="pmClientId"   name="client_id">
                <input type="hidden" id="pmClientName" name="client_name">
                <input type="hidden" name="usd_amount" id="hiddenUsdAmount" value="0">
                <input type="hidden" name="afs_amount" id="hiddenAfsAmount" value="0">

                <!-- ① Balance Currency -->
                <div class="pm-field" id="fieldBalanceCurrency">
                    <label class="pm-label">
                        <span class="pm-step">1</span>
                        Which balance are we updating?
                    </label>
                    <div class="pm-toggle-group">
                        <button type="button" class="pm-toggle" data-value="USD" data-target="balanceCurrency">
                            <i class="fas fa-dollar-sign"></i> USD Balance
                        </button>
                        <button type="button" class="pm-toggle" data-value="AFS" data-target="balanceCurrency">
                            <i class="fas fa-money-bill-wave"></i> AFS Balance
                        </button>
                    </div>
                    <input type="hidden" id="balanceCurrency" name="payment_currency">
                </div>

                <!-- ② Payment Currency -->
                <div class="pm-field pm-hidden" id="fieldPaymentCurrency">
                    <label class="pm-label">
                        <span class="pm-step">2</span>
                        What currency is the client paying in?
                    </label>
                    <div class="pm-toggle-group">
                        <button type="button" class="pm-toggle" data-value="USD" data-target="paymentCurrency">
                            <i class="fas fa-dollar-sign"></i> Paying in USD
                        </button>
                        <button type="button" class="pm-toggle" data-value="AFS" data-target="paymentCurrency">
                            <i class="fas fa-money-bill-wave"></i> Paying in AFS
                        </button>
                        <button type="button" class="pm-toggle" data-value="EUR" data-target="paymentCurrency">
                            <i class="fas fa-euro-sign"></i> Paying in EUR
                        </button>
                        <button type="button" class="pm-toggle" data-value="DARHAM" data-target="paymentCurrency">
                            <i class="fas fa-coins"></i> Paying in AED
                        </button>
                        <button type="button" class="pm-toggle" data-value="SAR" data-target="paymentCurrency">
                            <i class="fas fa-money-bill-wave"></i> Paying in SAR
                        </button>
                    </div>
                    <input type="hidden" id="paymentCurrency" name="payment_currency_actual">
                </div>

                <!-- ③ Exchange Rate (only when currencies differ) -->
                <div class="pm-field pm-hidden" id="fieldExchangeRate">
                    <label class="pm-label">
                        <span class="pm-step">3</span>
                        <span id="rateFieldLabel">USD to AFS Exchange Rate</span>
                    </label>
                    <div class="pm-input-wrap">
                        <span class="pm-input-pre" id="rateLabel">1 USD =</span>
                        <input type="number" class="pm-input" id="pmExchangeRate" name="exchange_rate"
                               step="0.01" min="0.01" placeholder="0.00">
                        <span class="pm-input-suf" id="rateSuffix">AFS</span>
                    </div>
                    <small class="pm-rate-hint" id="rateInstruction">Enter how many AFS equals 1 USD</small>
                    <small class="pm-rate-hint pm-rate-example" id="rateExample">Example: 1 USD = 88 AFS, enter 88</small>
                </div>

                <!-- ④ Amount -->
                <div class="pm-field pm-hidden" id="fieldAmount">
                    <label class="pm-label">
                        <span class="pm-step" id="stepAmount">3</span>
                        Amount Paid
                        <span class="pm-label-hint" id="amountHint">in USD</span>
                    </label>
                    <div class="pm-input-wrap">
                        <span class="pm-input-pre" id="amountSymbol">$</span>
                        <input type="number" class="pm-input" id="totalAmount" name="total_amount"
                               step="0.01" min="0.01" placeholder="0.00">
                    </div>
                    <div class="pm-conversion" id="conversionPreview"></div>
                </div>

                <!-- ⑤ Main Account -->
                <div class="pm-field pm-hidden" id="fieldMainAccount">
                    <label class="pm-label">
                        <span class="pm-step" id="stepAccount">4</span>
                        Main Account
                    </label>
                    <div class="pm-input-wrap">
                        <span class="pm-input-pre"><i class="feather icon-briefcase"></i></span>
                        <select class="pm-input pm-select" id="clientMainAccount" name="main_account">
                            <option value="">Select account…</option>
                            <?php foreach ($mainAccounts as $account): ?>
                                <option value="<?= $account['id'] ?>"><?= htmlspecialchars($account['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- ⑥ Receipt -->
                <div class="pm-field pm-hidden" id="fieldReceipt">
                    <label class="pm-label">
                        <span class="pm-step" id="stepReceipt">5</span>
                        Receipt Number
                    </label>
                    <div class="pm-input-wrap">
                        <span class="pm-input-pre"><i class="feather icon-hash"></i></span>
                        <input type="text" class="pm-input" id="receiptNumber" name="receipt_number"
                               placeholder="Enter receipt number">
                    </div>
                </div>

                <!-- ⑦ Remarks -->
                <div class="pm-field pm-hidden" id="fieldRemarks">
                    <label class="pm-label">
                        <span class="pm-step" id="stepRemarks">6</span>
                        Remarks
                        <span class="pm-optional">optional</span>
                    </label>
                    <div class="pm-input-wrap pm-textarea-wrap">
                        <span class="pm-input-pre"><i class="feather icon-message-square"></i></span>
                        <textarea class="pm-input pm-textarea" id="remarks" name="remarks"
                                  rows="2" placeholder="Enter payment details…"></textarea>
                    </div>
                </div>

            </form>

            <!-- Footer -->
            <div class="pm-footer">
                <button type="button" class="pm-btn-cancel" data-dismiss="modal">
                    <i class="feather icon-x"></i> Cancel
                </button>
                <button type="button" class="pm-btn-submit" id="processPaymentBtn" disabled>
                    <i class="feather icon-check-circle"></i> Process Payment
                </button>
            </div>

        </div>
    </div>
</div>


<style>
.pm-card {
    border: none;
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 24px 64px rgba(0,0,0,.16);
}
/* Header */
.pm-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.25rem;
    background: #0f172a;
    color: #fff;
}
.pm-header-left { display: flex; align-items: center; gap: .75rem; }
.pm-header-icon {
    width: 36px; height: 36px;
    background: rgba(255,255,255,.1);
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem; color: #fff;
}
.pm-title   { font-size: .95rem; font-weight: 600; color: #fff; margin: 0; }
.pm-subtitle { font-size: .75rem; color: #94a3b8; margin-top: 1px; }
.pm-close {
    background: none; border: none;
    color: #94a3b8; cursor: pointer;
    padding: .25rem; line-height: 1;
    transition: color .15s;
}
.pm-close:hover { color: #fff; }

/* Balances */
.pm-balances {
    display: flex;
    align-items: center;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    padding: .65rem 1.25rem;
    gap: 1rem;
}
.pm-bal-item { display: flex; flex-direction: column; gap: 1px; }
.pm-bal-label { font-size: .62rem; text-transform: uppercase; letter-spacing: .06em; color: #94a3b8; font-weight: 600; }
.pm-bal-value { font-size: .88rem; font-weight: 700; }
.pm-bal-value.usd { color: #16a34a; }
.pm-bal-value.afs { color: #2563eb; }
.pm-bal-divider { width: 1px; height: 24px; background: #e2e8f0; }

/* Form body */
.pm-body { padding: 0; }

/* Fields */
.pm-field {
    padding: .85rem 1.25rem;
    border-bottom: 1px solid #f1f5f9;
}
.pm-field:last-child { border-bottom: none; }
.pm-hidden { display: none !important; }
.pm-reveal  { animation: pmIn .2s ease both; }
@keyframes pmIn {
    from { opacity: 0; transform: translateY(-5px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* Labels */
.pm-label {
    display: flex;
    align-items: center;
    gap: .45rem;
    font-size: .7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .07em;
    color: #64748b;
    margin-bottom: .55rem;
}
.pm-step {
    width: 18px; height: 18px;
    background: #0f172a; color: #fff;
    border-radius: 50%;
    font-size: .58rem; font-weight: 800;
    display: inline-flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.pm-label-hint, .pm-optional {
    font-weight: 400; text-transform: none;
    letter-spacing: 0; color: #94a3b8; font-size: .68rem;
}
.pm-label-hint { margin-left: auto; }

/* Toggles */
.pm-toggle-group { display: flex; gap: .5rem; }
.pm-toggle {
    flex: 1; padding: .5rem .75rem;
    border: 1.5px solid #e2e8f0;
    border-radius: 8px; background: #fff;
    color: #475569; font-size: .82rem; font-weight: 500;
    cursor: pointer; transition: all .15s;
    display: flex; align-items: center; justify-content: center; gap: .4rem;
}
.pm-toggle:hover { border-color: #94a3b8; background: #f8fafc; }
.pm-toggle.pm-active { 
    border-color: #16a34a !important; 
    background: #16a34a !important; 
    color: #fff !important;
    box-shadow: 0 0 0 3px rgba(22, 163, 74, .15) !important;
}

/* Inputs */
.pm-input-wrap {
    display: flex; align-items: center;
    border: 1.5px solid #e2e8f0;
    border-radius: 8px; overflow: hidden;
    background: #fff; transition: border-color .15s, box-shadow .15s;
}
.pm-input-wrap:focus-within {
    border-color: #0f172a;
    box-shadow: 0 0 0 3px rgba(15,23,42,.07);
}
.pm-input-pre, .pm-input-suf {
    padding: .5rem .7rem;
    background: #f8fafc; color: #64748b;
    font-size: .8rem; font-weight: 500;
    border-right: 1px solid #e2e8f0;
    white-space: nowrap; flex-shrink: 0;
}
.pm-input-suf { border-right: none; border-left: 1px solid #e2e8f0; }
.pm-input {
    flex: 1; border: none; outline: none;
    padding: .5rem .75rem;
    font-size: .88rem; color: #0f172a;
    background: transparent; min-width: 0;
    width: 100%;
}
.pm-select { cursor: pointer; }
.pm-textarea-wrap { align-items: flex-start; }
.pm-textarea-wrap .pm-input-pre { padding-top: .55rem; }
.pm-textarea { resize: none; }

/* Conversion preview */
.pm-conversion {
    margin-top: .4rem;
    font-size: .78rem; color: #2563eb; font-weight: 500;
    min-height: 1rem;
}

/* Exchange rate helper text */
.pm-rate-hint {
    display: block;
    margin-top: .4rem;
    font-size: .74rem; color: #64748b;
}
.pm-rate-example { color: #94a3b8; }

/* Footer */
.pm-footer {
    display: flex; align-items: center; justify-content: flex-end;
    gap: .6rem; padding: .85rem 1.25rem;
    background: #f8fafc; border-top: 1px solid #e2e8f0;
}
.pm-btn-cancel {
    padding: .48rem 1rem;
    border: 1.5px solid #e2e8f0; border-radius: 8px;
    background: #fff; color: #64748b;
    font-size: .83rem; font-weight: 500; cursor: pointer;
    display: flex; align-items: center; gap: .35rem;
    transition: all .15s;
}
.pm-btn-cancel:hover { border-color: #94a3b8; color: #0f172a; }
.pm-btn-submit {
    padding: .48rem 1.2rem;
    border: none; border-radius: 8px;
    background: #0f172a; color: #fff;
    font-size: .83rem; font-weight: 600; cursor: pointer;
    display: flex; align-items: center; gap: .4rem;
    transition: background .15s;
}
.pm-btn-submit:hover:not(:disabled) { background: #1e293b; }
.pm-btn-submit:disabled { background: #cbd5e1; color: #94a3b8; cursor: not-allowed; }
</style>


<script>
(function () {

    // ── State ──────────────────────────────────────────────────────────────────
    const s = { balance: null, payment: null };

    // ── Currency display info ──────────────────────────────────────────────────
    const CUR_INFO = {
        USD:    { sym: '$',   name: 'USD' },
        AFS:    { sym: '؋',   name: 'AFS' },
        EUR:    { sym: '€',   name: 'EUR' },
        DARHAM: { sym: 'د.إ', name: 'AED' },
        SAR:    { sym: '﷼',   name: 'SAR' },
    };

    // Reference examples per balance/payment pair, same orientation as the rate label
    const RATE_EXAMPLES = {
        'USD-AFS':    'Example: 1 USD = 88 AFS, enter 88',
        'USD-EUR':    'Example: 1 USD = 0.95 EUR, enter 0.95',
        'USD-DARHAM': 'Example: 1 USD = 3.67 AED, enter 3.67',
        'USD-SAR':    'Example: 1 USD = 3.75 SAR, enter 3.75',
        'AFS-USD':    'Example: 1 USD = 88 AFS, enter 88',
        'AFS-EUR':    'Example: 1 AFS = 0.0108 EUR, enter 0.0108',
        'AFS-DARHAM': 'Example: 1 AFS = 0.0417 AED, enter 0.0417',
        'AFS-SAR':    'Example: 1 AFS = 0.0426 SAR, enter 0.0426'
    };

    // Rate orientation: which currency is the "1" of the rate
    function getRateOrientation() {
        // AFS balance + USD payment keeps the market-standard "1 USD = X AFS"
        if (s.balance === 'AFS' && s.payment === 'USD') return { base: 'USD', target: 'AFS' };
        return { base: s.balance, target: s.payment };
    }

    // ── Refs ───────────────────────────────────────────────────────────────────
    const $ = id => document.getElementById(id);

    let fields = {
        paymentCurrency: null,
        exchangeRate:    null,
        amount:          null,
        mainAccount:     null,
        receipt:         null,
        remarks:         null,
    };

    // Initialize fields references
    function initializeFields() {
        fields = {
            paymentCurrency: $('fieldPaymentCurrency'),
            exchangeRate:    $('fieldExchangeRate'),
            amount:          $('fieldAmount'),
            mainAccount:     $('fieldMainAccount'),
            receipt:         $('fieldReceipt'),
            remarks:         $('fieldRemarks'),
        };
    }

    // ── Utility ────────────────────────────────────────────────────────────────
    function reveal(el) {
        el.classList.remove('pm-hidden');
        el.classList.add('pm-reveal');
    }
    function hide(el) {
        el.classList.add('pm-hidden');
        el.classList.remove('pm-reveal');
    }
    function clearField(el) {
        // Only clear visible inputs, not hidden ones
        el.querySelectorAll('input[type=text], input[type=number], select, textarea')
          .forEach(i => { i.value = ''; });
        // deactivate any toggles inside
        el.querySelectorAll('.pm-toggle').forEach(b => b.classList.remove('pm-active'));
    }

    // Hide all fields from a given key onward and clear their values
    const fieldOrder = ['paymentCurrency','exchangeRate','amount','mainAccount','receipt','remarks'];
    function resetFrom(key) {
        const idx = fieldOrder.indexOf(key);
        fieldOrder.slice(idx).forEach(k => {
            hide(fields[k]);
            clearField(fields[k]);
        });
        $('conversionPreview').textContent = '';
        $('processPaymentBtn').disabled = true;
    }

    // Recompute step numbers depending on whether exchange rate field is visible
    function updateSteps() {
        const hasRate = !fields.exchangeRate.classList.contains('pm-hidden');
        $('stepAmount').textContent  = hasRate ? '4' : '3';
        $('stepAccount').textContent = hasRate ? '5' : '4';
        $('stepReceipt').textContent = hasRate ? '6' : '5';
        $('stepRemarks').textContent = hasRate ? '7' : '6';
    }

    // Update amount field symbol/hint to reflect payment currency
    function configureAmountField() {
        if (!s.payment) return;
        const info = CUR_INFO[s.payment] || { sym: s.payment, name: s.payment };
        $('amountSymbol').textContent = info.sym;
        $('amountHint').textContent   = info.name + ' cash received';
    }

    // Update exchange rate label/helper text to match the balance/payment pair
    function updateRateLabels() {
        if (!s.balance || !s.payment) return;
        const { base, target } = getRateOrientation();
        const baseInfo   = CUR_INFO[base]   || { name: base };
        const targetInfo = CUR_INFO[target] || { name: target };

        // Prefix/suffix of the input itself
        $('rateLabel').textContent  = `1 ${baseInfo.name} =`;
        $('rateSuffix').textContent = targetInfo.name;

        // Field label, instruction and example (same convention as transaction managers)
        $('rateFieldLabel').textContent = `${baseInfo.name} to ${targetInfo.name} Exchange Rate`;
        $('rateInstruction').textContent = `Enter how many ${targetInfo.name} equals 1 ${baseInfo.name}`;
        $('rateExample').textContent = RATE_EXAMPLES[`${s.balance}-${s.payment}`]
            || `Example: 1 ${baseInfo.name} = X ${targetInfo.name}, enter X`;

        // Placeholder derived from the example value (e.g. "e.g. 0.95")
        const example = $('rateExample').textContent;
        const m = example.match(/= ([\d.]+)/);
        $('pmExchangeRate').placeholder = m ? `e.g. ${m[1]}` : '0.00';
    }

    // Amount actually credited to the selected balance, per current pair rules
    function computeCredit(amount, rate) {
        if (s.balance === s.payment) return amount;
        if (s.balance === 'USD') return amount / rate;          // 1 USD = X <payment>
        if (s.payment === 'USD') return amount * rate;          // 1 USD = X AFS
        return amount / rate;                                   // 1 AFS = X <payment>
    }

    // Live conversion preview under amount field
    function updateConversion() {
        const rate   = parseFloat($('pmExchangeRate').value) || 0;
        const amount = parseFloat($('totalAmount').value)  || 0;
        const prev   = $('conversionPreview');

        // Only show preview when currencies differ AND both values are filled
        if (!s.balance || !s.payment || s.balance === s.payment || !amount || !rate) {
            prev.textContent = '';
            return;
        }

        const bal  = CUR_INFO[s.balance]  || { sym: s.balance, name: s.balance };
        const pay  = CUR_INFO[s.payment]  || { sym: s.payment, name: s.payment };
        const credit = computeCredit(amount, rate);

        if (s.balance === 'USD') {
            prev.textContent = `${pay.sym}${amount.toLocaleString()} ÷ ${rate} = $${credit.toFixed(2)} credited to USD balance`;
        } else if (s.payment === 'USD') {
            prev.textContent = `$${amount.toLocaleString()} × ${rate} = ؋${credit.toFixed(2)} credited to AFS balance`;
        } else {
            prev.textContent = `${pay.sym}${amount.toLocaleString()} ÷ ${rate} = ؋${credit.toFixed(2)} credited to AFS balance`;
        }
    }

    // Set hidden usd_amount / afs_amount for backend
    function syncHiddenAmounts() {
        const amount = parseFloat($('totalAmount').value) || 0;
        if (s.payment === 'USD') {
            $('hiddenUsdAmount').value = amount;
            $('hiddenAfsAmount').value = 0;
        } else {
            $('hiddenUsdAmount').value = 0;
            $('hiddenAfsAmount').value = amount;
        }
    }

    // Enable submit only when all required fields are valid
    function checkSubmit() {
        const rateOk   = s.balance === s.payment || parseFloat($('pmExchangeRate').value) > 0;
        const amountOk = parseFloat($('totalAmount').value) > 0;
        const accountOk = $('clientMainAccount').value !== '';
        const receiptOk = $('receiptNumber').value.trim() !== '';

        const ready = s.balance && s.payment && rateOk && amountOk && accountOk && receiptOk;
        $('processPaymentBtn').disabled = !ready;
        if (ready) syncHiddenAmounts();
    }

    // ── Initialize event listeners ────────────────────────────────────────────
    function attachEventListeners() {
        // ── Toggle button handler ──────────────────────────────────────────────────
        document.querySelectorAll('.pm-toggle').forEach(btn => {
        btn.addEventListener('click', function () {
            const target = this.dataset.target;
            const value  = this.dataset.value;

            // Mark active within this group
            document.querySelectorAll(`.pm-toggle[data-target="${target}"]`)
                    .forEach(b => b.classList.remove('pm-active'));
            this.classList.add('pm-active');
            $(target).value = value;
            console.log(`Toggle clicked: target=${target}, value=${value}, element=${$(target) ? 'found' : 'NOT FOUND'}, stored value=${$(target)?.value}`);

            if (target === 'balanceCurrency') {
                s.balance = value;
                resetFrom('paymentCurrency');
                // Re-highlight the selected balance currency button
                this.classList.add('pm-active');
                reveal(fields.paymentCurrency);

            } else if (target === 'paymentCurrency') {
                s.payment = value;
                resetFrom('exchangeRate');

                if (s.balance !== s.payment) {
                    // Currencies differ → show exchange rate field
                    reveal(fields.exchangeRate);
                    updateRateLabels();
                    configureAmountField();
                    reveal(fields.amount);
                    updateSteps();
                } else {
                    // Same currency → skip exchange rate
                    $('pmExchangeRate').value = '1';
                    configureAmountField();
                    updateSteps();
                    reveal(fields.amount);
                }
            }
        });
    });

    // ── Exchange rate → reveal amount ──────────────────────────────────────────
    const pmExchangeRateInput = $('pmExchangeRate');
    if (pmExchangeRateInput) {
        pmExchangeRateInput.addEventListener('input', function () {
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

    // ── Amount → reveal main account ──────────────────────────────────────────
    $('totalAmount').addEventListener('input', function () {
        updateConversion();
        if (parseFloat(this.value) > 0) {
            if (fields.mainAccount && fields.mainAccount.classList.contains('pm-hidden')) {
                reveal(fields.mainAccount);
            }
        }
        checkSubmit();
    });

    // ── Account → reveal receipt ───────────────────────────────────────────────
    $('clientMainAccount').addEventListener('change', function () {
        if (this.value) {
            if (fields.receipt && fields.receipt.classList.contains('pm-hidden')) {
                reveal(fields.receipt);
            }
        }
        checkSubmit();
    });

    // ── Receipt → reveal remarks ───────────────────────────────────────────────
    $('receiptNumber').addEventListener('input', function () {
        if (this.value.trim()) {
            if (fields.remarks && fields.remarks.classList.contains('pm-hidden')) {
                reveal(fields.remarks);
            }
        }
        checkSubmit();
    });

    $('remarks').addEventListener('input', checkSubmit);

    // ── Process Payment button ─────────────────────────────────────────
    const processPaymentBtn = $('processPaymentBtn');
    if (processPaymentBtn) {
        processPaymentBtn.addEventListener('click', function () {
            const form = document.getElementById('partialPaymentForm');
            if (!form) {
                alert('Payment form not found');
                return;
            }

            var originalHtml = processPaymentBtn.innerHTML;
            processPaymentBtn.disabled = true;
            processPaymentBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Processing...';

            // Sync state to hidden inputs before submission
            // Get the actual INPUT elements, not the field containers
            const balEl = document.querySelector('input[name="payment_currency"]');
            const payEl = document.querySelector('input[name="payment_currency_actual"]');
            console.log('Elements found:', { balEl: balEl ? balEl.id : 'NO', payEl: payEl ? payEl.id : 'NO' });
            console.log('Element details:', { 
                balEl_tag: balEl?.tagName, 
                payEl_tag: payEl?.tagName,
                balEl_name: balEl?.name,
                payEl_name: payEl?.name
            });
            
            if (balEl) balEl.value = s.balance;
            if (payEl) payEl.value = s.payment;
            
            console.log('State synced:', { 
                balance: s.balance, 
                payment: s.payment, 
                balEl_value: balEl?.value,
                payEl_value: payEl?.value
            });

            // Collect form data - use fresh FormData to ensure we get latest values
            const formData = new FormData(form);
            
            // Verify the payment currency is actually in FormData
            console.log('Direct element check:');
            console.log('  paymentCurrency element value:', form.elements['payment_currency_actual']?.value);
            console.log('  balanceCurrency element value:', form.elements['payment_currency']?.value);
            
            // Log what we're sending
            console.log('Form data being sent:');
            for (let [key, value] of formData.entries()) {
                console.log(`  ${key}: ${value}`);
            }

            // Submit to backend
            fetch('../api/accounts/fundClient.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Payment processed successfully');
                    // Close modal
                    jQuery('#partialPaymentModal').modal('hide');
                    // Refresh page or update balances
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Unknown error'));
                }
                processPaymentBtn.disabled = false;
                processPaymentBtn.innerHTML = originalHtml;
            })
            .catch(error => {
                console.error('Error:', error);
                processPaymentBtn.disabled = false;
                processPaymentBtn.innerHTML = originalHtml;
                alert('Failed to process payment');
            });
        });
    }
    } // end attachEventListeners()

    // ── Public API ─────────────────────────────────────────────────────────────
    window.openPaymentModal = function (clientId, name, usdBal, afsBal) {
        // Initialize fields on first call if not already done
        if (!fields.paymentCurrency) {
            initializeFields();
        }
        // Full reset
        const form = document.getElementById('partialPaymentForm');
        if (!form) {
            console.error('Payment modal form not found');
            return;
        }
        
        form.reset();
        document.querySelectorAll('.pm-toggle').forEach(b => b.classList.remove('pm-active'));
        Object.values(fields).forEach(f => { hide(f); clearField(f); });
        s.balance = null; s.payment = null;
        $('conversionPreview').textContent = '';
        $('processPaymentBtn').disabled = true;

        // Populate client data
        $('pmClientId').value            = clientId;
        $('pmClientName').value          = name;
        $('pmClientNameDisplay').textContent = name;
        $('pmUsdBalance').textContent    = '$' + parseFloat(usdBal).toFixed(2);
        $('pmAfsBalance').textContent    = '؋' + parseFloat(afsBal).toFixed(2);

        // Show modal using jQuery if available
        try {
            const modalEl = document.getElementById('partialPaymentModal');
            if (modalEl && typeof jQuery !== 'undefined') {
                jQuery(modalEl).modal('show');
            } else {
                console.error('Payment modal not found or jQuery not available');
            }
        } catch (e) {
            console.error('Error showing payment modal:', e);
        }
    };

    // Initialize when DOM is ready
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
