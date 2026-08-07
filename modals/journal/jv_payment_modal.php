<?php
/**
 * Payments Journal — JV Payment (Client → Supplier) modal.
 * Served by api/journal/modal_render.php?modal=jv_payment.
 *
 * Reuses the journal's own .pjl-* modal chrome (custom backdrop, not Bootstrap)
 * with Bootstrap form controls. Submits via fetch to process_client_supplier_jv.php
 * (which responds with JSON when ajax=1 is posted).
 *
 * Requires: $clients, $suppliers (tenant/branch scoped), __(), h().
 */
?>
<style>
.jvp-field { margin-bottom: 14px; }
.jvp-field label { display: block; font-size: 12px; font-weight: 600; color: var(--text-muted); margin-bottom: 5px; }
.jvp-section-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--text-dim); margin: 18px 0 10px; }
.jvp-field-hint { font-size: 11.5px; color: var(--text-dim); margin: 4px 0 0; }
</style>

<div class="pjl-backdrop" id="jvAddModal" style="display:none" onclick="pjlBackdropClick(event,'jvAddModal')">
  <div class="pjl-modal pjl-modal-lg">
    <div class="pjl-modal-head">
      <div>
        <h2><?php echo __('add_client_to_supplier_payment'); ?></h2>
        <p><?php echo __('create_a_direct_payment_between_client_and_supplier'); ?></p>
      </div>
      <button type="button" class="pjl-btn-icon" onclick="pjlCloseModal('jvAddModal')">
        <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M11 3L3 11M3 3l8 8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
      </button>
    </div>

    <form id="jvForm">
      <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
      <input type="hidden" name="jv_name" value="Client-Supplier Payment">
      <div class="pjl-modal-body">

        <div class="jvp-field">
          <label><?php echo __('transaction_parties'); ?> — <?php echo __('client'); ?></label>
          <select class="form-control" name="client_id" id="jvClientId" required>
            <option value=""><?php echo __('select_client'); ?></option>
            <?php foreach ($clients as $c): ?>
              <option value="<?php echo (int) $c['id']; ?>"
                      data-usd="<?php echo (float) $c['usd_balance']; ?>"
                      data-afs="<?php echo (float) $c['afs_balance']; ?>">
                <?php echo h($c['name']); ?>
                (USD: <?php echo number_format($c['usd_balance'], 0); ?> / AFS: <?php echo number_format($c['afs_balance'], 0); ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="jvp-field">
          <label><?php echo __('supplier'); ?></label>
          <select class="form-control" name="supplier_id" id="jvSupplierId" required>
            <option value=""><?php echo __('select_supplier'); ?></option>
            <?php foreach ($suppliers as $s): ?>
              <option value="<?php echo (int) $s['id']; ?>"
                      data-currency="<?php echo h($s['currency']); ?>"
                      data-balance="<?php echo (float) $s['balance']; ?>">
                <?php echo h($s['name']); ?>
                (<?php echo number_format($s['balance'], 0); ?> <?php echo h($s['currency']); ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="jvp-section-title"><?php echo __('amount_currency'); ?></div>
        <div class="row">
          <div class="col-md-4">
            <div class="jvp-field">
              <label><?php echo __('currency'); ?></label>
              <select class="form-control" name="currency" id="jvCurrency">
                <option value="USD">USD – US Dollar</option>
                <option value="AFS">AFS – Afghani</option>
              </select>
            </div>
          </div>
          <div class="col-md-4">
            <div class="jvp-field">
              <label><?php echo __('amount'); ?></label>
              <input type="number" step="0.01" class="form-control" name="total_amount" id="jvTotalAmount" required placeholder="0.00">
            </div>
          </div>
          <div class="col-md-4" id="jvExchangeRateField" style="display:none">
            <div class="jvp-field">
              <label><?php echo __('exchange_rate'); ?></label>
              <input type="number" step="0.00001" class="form-control" name="exchange_rate" id="jvExchangeRate" placeholder="1.00000">
              <p class="jvp-field-hint"><?php echo __('required_if_currencies_differ'); ?></p>
            </div>
          </div>
        </div>

        <div class="jvp-section-title"><?php echo __('additional_details'); ?></div>
        <div class="row">
          <div class="col-md-5">
            <div class="jvp-field">
              <label><?php echo __('receipt_number'); ?></label>
              <input type="text" class="form-control" name="receipt" id="jvReceipt" required placeholder="RCP-XXXXX">
            </div>
          </div>
          <div class="col-md-7">
            <div class="jvp-field">
              <label><?php echo __('remarks'); ?></label>
              <textarea class="form-control" name="remarks" id="jvRemarks" rows="1" placeholder="<?php echo __('optional_notes'); ?>…"></textarea>
            </div>
          </div>
        </div>

      </div>
      <div class="pjl-modal-foot">
        <button type="button" class="pjl-btn pjl-btn-ghost" onclick="pjlCloseModal('jvAddModal')"><?php echo __('cancel'); ?></button>
        <button type="submit" class="pjl-btn pjl-btn-primary" id="jvSubmitBtn">
          <svg width="13" height="13" viewBox="0 0 16 16" fill="none"><path d="M13 4L6.5 11 3 7.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
          <?php echo __('process_payment'); ?>
        </button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  const form = document.getElementById('jvForm');
  const rateField = document.getElementById('jvExchangeRateField');
  const rateInput = document.getElementById('jvExchangeRate');
  const submitBtn = document.getElementById('jvSubmitBtn');
  const origBtnHtml = submitBtn.innerHTML;

  function updateExchangeRateField() {
    const currency = document.getElementById('jvCurrency').value;
    const supplierOpt = document.getElementById('jvSupplierId').selectedOptions[0];
    const supplierCurr = supplierOpt ? supplierOpt.dataset.currency : '';
    if (supplierCurr && supplierCurr !== currency) {
      rateField.style.display = '';
      rateInput.setAttribute('required', 'required');
    } else {
      rateField.style.display = 'none';
      rateInput.removeAttribute('required');
    }
  }

  document.getElementById('jvCurrency').addEventListener('change', updateExchangeRateField);
  document.getElementById('jvSupplierId').addEventListener('change', updateExchangeRateField);

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    const clientId = document.getElementById('jvClientId').value;
    const supplierId = document.getElementById('jvSupplierId').value;
    const amount = parseFloat(document.getElementById('jvTotalAmount').value);
    const currency = document.getElementById('jvCurrency').value;
    const exchangeRate = parseFloat(rateInput.value);
    const supplierOpt = document.getElementById('jvSupplierId').selectedOptions[0];
    const supplierCurr = supplierOpt ? supplierOpt.dataset.currency : '';

    if (!clientId || !supplierId) { pjlToast('Please select both client and supplier', 'error'); return; }
    if (isNaN(amount) || amount <= 0) { pjlToast('Please enter a valid amount greater than zero', 'error'); return; }
    if (supplierCurr && supplierCurr !== currency && (isNaN(exchangeRate) || exchangeRate <= 0)) {
      pjlToast('Please enter a valid exchange rate for currency conversion', 'error'); return;
    }

    submitBtn.disabled = true;
    submitBtn.innerHTML = 'Processing…';

    const fd = new FormData(form);
    fd.set('ajax', '1');
    fd.set('jv_name', 'Client-Supplier Payment');

    fetch('process_client_supplier_jv.php', {
      method: 'POST',
      credentials: 'include',
      body: fd
    })
      .then(r => r.json().catch(() => ({ success: false, message: 'Unexpected server response' })))
      .then(data => {
        if (data && data.success) {
          pjlCloseModal('jvAddModal');
          pjlCleanupEmbed();
          loadJournal();
          pjlToast(data.message || 'JV payment processed successfully', 'success');
        } else {
          pjlToast((data && data.message) || 'JV payment failed', 'error');
        }
      })
      .catch(() => pjlToast('An error occurred while processing the payment', 'error'))
      .then(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = origBtnHtml;
      });
  });
})();
</script>
