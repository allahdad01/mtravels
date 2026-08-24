<div class="modal fade" id="adjustBalanceModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content ab-card">

            <div class="ab-header">
                <div class="ab-header-left">
                    <div class="ab-header-icon"><i class="feather icon-sliders"></i></div>
                    <div>
                        <div class="ab-title">Adjust Balance</div>
                        <div class="ab-subtitle" id="abClientNameDisplay">—</div>
                    </div>
                </div>
                <button type="button" class="ab-close" data-dismiss="modal">
                    <i class="feather icon-x"></i>
                </button>
            </div>

            <div class="ab-balances">
                <div class="ab-bal-item">
                    <span class="ab-bal-label">USD Balance</span>
                    <span class="ab-bal-value usd" id="abUsdBalance">$0.00</span>
                </div>
                <div class="ab-bal-divider"></div>
                <div class="ab-bal-item">
                    <span class="ab-bal-label">AFS Balance</span>
                    <span class="ab-bal-value afs" id="abAfsBalance">؋0.00</span>
                </div>
            </div>

            <form id="adjustBalanceForm" class="ab-body">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                <input type="hidden" id="abClientId" name="client_id">
                <input type="hidden" id="abClientName" name="client_name">

                <div class="ab-field" id="abFieldCurrency">
                    <label class="ab-label">
                        <span class="ab-step">1</span>
                        Which balance to adjust?
                    </label>
                    <div class="ab-toggle-group">
                        <button type="button" class="ab-toggle" data-value="USD" data-target="abBalanceCurrency">
                            <i class="fas fa-dollar-sign"></i> USD
                        </button>
                        <button type="button" class="ab-toggle" data-value="AFS" data-target="abBalanceCurrency">
                            <i class="fas fa-money-bill-wave"></i> AFS
                        </button>
                    </div>
                    <input type="hidden" id="abBalanceCurrency" name="balance_currency">
                </div>

                <div class="ab-field ab-hidden" id="abFieldType">
                    <label class="ab-label">
                        <span class="ab-step">2</span>
                        Adjustment type
                    </label>
                    <div class="ab-toggle-group">
                        <button type="button" class="ab-toggle ab-toggle-credit" data-value="credit" data-target="abAdjustmentType">
                            <i class="fas fa-arrow-up"></i> Credit (+)
                        </button>
                        <button type="button" class="ab-toggle ab-toggle-debit" data-value="debit" data-target="abAdjustmentType">
                            <i class="fas fa-arrow-down"></i> Debit (−)
                        </button>
                    </div>
                    <input type="hidden" id="abAdjustmentType" name="adjustment_type">
                </div>

                <div class="ab-field ab-hidden" id="abFieldAmount">
                    <label class="ab-label">
                        <span class="ab-step">3</span>
                        Amount
                        <span class="ab-label-hint" id="abAmountHint">in USD</span>
                    </label>
                    <div class="ab-input-wrap">
                        <span class="ab-input-pre" id="abAmountSymbol">$</span>
                        <input type="number" class="ab-input" id="abAmount" name="amount"
                               step="0.01" min="0.01" placeholder="0.00">
                    </div>
                </div>

                <div class="ab-field ab-hidden" id="abFieldReceipt">
                    <label class="ab-label">
                        <span class="ab-step">4</span>
                        Receipt Number
                        <span class="ab-optional">optional</span>
                    </label>
                    <div class="ab-input-wrap">
                        <span class="ab-input-pre"><i class="feather icon-hash"></i></span>
                        <input type="text" class="ab-input" id="abReceiptNumber" name="receipt_number"
                               placeholder="Enter receipt number (optional)">
                    </div>
                </div>

                <div class="ab-field ab-hidden" id="abFieldRemarks">
                    <label class="ab-label">
                        <span class="ab-step">5</span>
                        Remarks
                        <span class="ab-optional">optional</span>
                    </label>
                    <div class="ab-input-wrap ab-textarea-wrap">
                        <span class="ab-input-pre"><i class="feather icon-message-square"></i></span>
                        <textarea class="ab-input ab-textarea" id="abRemarks" name="remarks"
                                  rows="2" placeholder="e.g. Old debt from June 2025…"></textarea>
                    </div>
                </div>

            </form>

            <div class="ab-footer">
                <button type="button" class="ab-btn-cancel" data-dismiss="modal">
                    <i class="feather icon-x"></i> Cancel
                </button>
                <button type="button" class="ab-btn-submit" id="abProcessBtn" disabled>
                    <i class="feather icon-check-circle"></i> Apply Adjustment
                </button>
            </div>

        </div>
    </div>
</div>

<style>
.ab-card {
    border: none; border-radius: 14px; overflow: hidden;
    box-shadow: 0 24px 64px rgba(0,0,0,.16);
}
.ab-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 1rem 1.25rem; background: #0f172a; color: #fff;
}
.ab-header-left { display: flex; align-items: center; gap: .75rem; }
.ab-header-icon {
    width: 36px; height: 36px; background: rgba(255,255,255,.1);
    border-radius: 8px; display: flex; align-items: center; justify-content: center;
    font-size: 1rem; color: #fff;
}
.ab-title { font-size: .95rem; font-weight: 600; color: #fff; margin: 0; }
.ab-subtitle { font-size: .75rem; color: #94a3b8; margin-top: 1px; }
.ab-close {
    background: none; border: none; color: #94a3b8; cursor: pointer;
    padding: .25rem; line-height: 1; transition: color .15s;
}
.ab-close:hover { color: #fff; }

.ab-balances {
    display: flex; align-items: center; background: #f8fafc;
    border-bottom: 1px solid #e2e8f0; padding: .65rem 1.25rem; gap: 1rem;
}
.ab-bal-item { display: flex; flex-direction: column; gap: 1px; }
.ab-bal-label { font-size: .62rem; text-transform: uppercase; letter-spacing: .06em; color: #94a3b8; font-weight: 600; }
.ab-bal-value { font-size: .88rem; font-weight: 700; }
.ab-bal-value.usd { color: #16a34a; }
.ab-bal-value.afs { color: #2563eb; }
.ab-bal-divider { width: 1px; height: 24px; background: #e2e8f0; }

.ab-body { padding: 0; }

.ab-field { padding: .85rem 1.25rem; border-bottom: 1px solid #f1f5f9; }
.ab-field:last-child { border-bottom: none; }
.ab-hidden { display: none !important; }
.ab-reveal { animation: abIn .2s ease both; }
@keyframes abIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }

.ab-label {
    display: flex; align-items: center; gap: .45rem;
    font-size: .7rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .07em; color: #64748b; margin-bottom: .55rem;
}
.ab-step {
    width: 18px; height: 18px; background: #0f172a; color: #fff;
    border-radius: 50%; font-size: .58rem; font-weight: 800;
    display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.ab-label-hint, .ab-optional { font-weight: 400; text-transform: none; letter-spacing: 0; color: #94a3b8; font-size: .68rem; }
.ab-label-hint { margin-left: auto; }

.ab-toggle-group { display: flex; gap: .5rem; }
.ab-toggle {
    flex: 1; padding: .5rem .75rem; border: 1.5px solid #e2e8f0;
    border-radius: 8px; background: #fff; color: #475569;
    font-size: .82rem; font-weight: 500; cursor: pointer; transition: all .15s;
    display: flex; align-items: center; justify-content: center; gap: .4rem;
}
.ab-toggle:hover { border-color: #94a3b8; background: #f8fafc; }
.ab-toggle.pm-active { border-color: #16a34a !important; background: #16a34a !important; color: #fff !important; box-shadow: 0 0 0 3px rgba(22,163,74,.15) !important; }
.ab-toggle-credit.pm-active { border-color: #16a34a !important; background: #16a34a !important; color: #fff !important; }
.ab-toggle-debit.pm-active { border-color: #e11d48 !important; background: #e11d48 !important; color: #fff !important; }

.ab-input-wrap {
    display: flex; align-items: center; border: 1.5px solid #e2e8f0;
    border-radius: 8px; overflow: hidden; background: #fff;
    transition: border-color .15s, box-shadow .15s;
}
.ab-input-wrap:focus-within { border-color: #0f172a; box-shadow: 0 0 0 3px rgba(15,23,42,.07); }
.ab-input-pre, .ab-input-suf {
    padding: .5rem .7rem; background: #f8fafc; color: #64748b;
    font-size: .8rem; font-weight: 500; border-right: 1px solid #e2e8f0;
    white-space: nowrap; flex-shrink: 0;
}
.ab-input-suf { border-right: none; border-left: 1px solid #e2e8f0; }
.ab-input {
    flex: 1; border: none; outline: none; padding: .5rem .75rem;
    font-size: .88rem; color: #0f172a; background: transparent; min-width: 0; width: 100%;
}
.ab-textarea-wrap { align-items: flex-start; }
.ab-textarea-wrap .ab-input-pre { padding-top: .55rem; }
.ab-textarea { resize: none; }

.ab-footer {
    display: flex; align-items: center; justify-content: flex-end;
    gap: .6rem; padding: .85rem 1.25rem; background: #f8fafc; border-top: 1px solid #e2e8f0;
}
.ab-btn-cancel {
    padding: .48rem 1rem; border: 1.5px solid #e2e8f0; border-radius: 8px;
    background: #fff; color: #64748b; font-size: .83rem; font-weight: 500;
    cursor: pointer; display: flex; align-items: center; gap: .35rem; transition: all .15s;
}
.ab-btn-cancel:hover { border-color: #94a3b8; color: #0f172a; }
.ab-btn-submit {
    padding: .48rem 1.2rem; border: none; border-radius: 8px;
    background: #0f172a; color: #fff; font-size: .83rem; font-weight: 600;
    cursor: pointer; display: flex; align-items: center; gap: .4rem; transition: background .15s;
}
.ab-btn-submit:hover:not(:disabled) { background: #1e293b; }
.ab-btn-submit:disabled { background: #cbd5e1; color: #94a3b8; cursor: not-allowed; }
</style>

<script>
(function () {
    const $ = id => document.getElementById(id);
    const s = { currency: null, type: null };

    let fields = { type: null, amount: null, receipt: null, remarks: null };

    function initFields() {
        fields = { type: $('abFieldType'), amount: $('abFieldAmount'), receipt: $('abFieldReceipt'), remarks: $('abFieldRemarks') };
    }

    function reveal(el) { el.classList.remove('ab-hidden'); el.classList.add('ab-reveal'); }
    function hide(el) { el.classList.add('ab-hidden'); el.classList.remove('ab-reveal'); }
    function clearField(el) {
        el.querySelectorAll('input[type=text], input[type=number], textarea').forEach(i => { i.value = ''; });
        el.querySelectorAll('.ab-toggle').forEach(b => b.classList.remove('pm-active'));
    }

    const fieldOrder = ['type','amount','receipt','remarks'];
    function resetFrom(key) {
        const idx = fieldOrder.indexOf(key);
        fieldOrder.slice(idx).forEach(k => { hide(fields[k]); clearField(fields[k]); });
        $('abProcessBtn').disabled = true;
    }

    function configureAmountField() {
        if (!s.currency) return;
        $('abAmountSymbol').textContent = s.currency === 'USD' ? '$' : '؋';
        $('abAmountHint').textContent = s.currency === 'USD' ? 'in USD' : 'in AFS';
    }

    function checkSubmit() {
        const typeOk = s.type && (s.type === 'credit' || s.type === 'debit');
        const amountOk = parseFloat($('abAmount').value) > 0;
        $('abProcessBtn').disabled = !(s.currency && typeOk && amountOk);
    }

    document.querySelectorAll('#adjustBalanceModal .ab-toggle').forEach(btn => {
        btn.addEventListener('click', function () {
            const target = this.dataset.target;
            const value = this.dataset.value;
            document.querySelectorAll(`#adjustBalanceModal .ab-toggle[data-target="${target}"]`).forEach(b => b.classList.remove('pm-active'));
            this.classList.add('pm-active');
            $(target).value = value;

            if (target === 'abBalanceCurrency') {
                s.currency = value;
                resetFrom('type');
                configureAmountField();
                reveal(fields.type);
            } else if (target === 'abAdjustmentType') {
                s.type = value;
                resetFrom('amount');
                configureAmountField();
                reveal(fields.amount);
            }
        });
    });

    $('abAmount').addEventListener('input', function () {
        if (parseFloat(this.value) > 0) {
            if (fields.receipt.classList.contains('ab-hidden')) reveal(fields.receipt);
            if (fields.remarks.classList.contains('ab-hidden')) reveal(fields.remarks);
        }
        checkSubmit();
    });

    $('abRemarks').addEventListener('input', checkSubmit);

    $('abProcessBtn').addEventListener('click', function () {
        const form = $('adjustBalanceForm');
        if (!form) return;

        var originalHtml = this.innerHTML;
        this.disabled = true;
        this.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Processing...';

        const btn = this;
        const formData = new FormData(form);

        fetch('../api/accounts/adjust_client_balance.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Balance adjusted successfully');
                    jQuery('#adjustBalanceModal').modal('hide');
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Unknown error'));
                }
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
                alert('Failed to adjust balance');
            });
    });

    window.openAdjustBalanceModal = function (clientId, name, usdBal, afsBal) {
        if (!fields.type) initFields();
        const form = $('adjustBalanceForm');
        if (!form) return;

        form.reset();
        document.querySelectorAll('#adjustBalanceModal .ab-toggle').forEach(b => b.classList.remove('pm-active'));
        Object.values(fields).forEach(f => { hide(f); clearField(f); });
        s.currency = null; s.type = null;
        $('abProcessBtn').disabled = true;

        $('abClientId').value = clientId;
        $('abClientName').value = name;
        $('abClientNameDisplay').textContent = name;
        $('abUsdBalance').textContent = '$' + parseFloat(usdBal).toFixed(2);
        $('abAfsBalance').textContent = '؋' + parseFloat(afsBal).toFixed(2);

        try {
            const modalEl = $('adjustBalanceModal');
            if (modalEl && typeof jQuery !== 'undefined') {
                jQuery(modalEl).modal('show');
            }
        } catch (e) {
            console.error('Error showing adjust balance modal:', e);
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initFields);
    } else {
        initFields();
    }
})();
</script>
