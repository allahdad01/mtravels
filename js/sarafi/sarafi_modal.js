/* Sarafi operations AJAX submit + live preview (embedded by Payments Journal) */

function sarafiSubmit(formId, modalId, typeLabel) {
  const form = document.getElementById(formId);
  if (!form) return;
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    const btn = form.querySelector('button[type="submit"]');
    const original = btn ? btn.innerHTML : '';
    if (btn) btn.disabled = true;

    fetch('sarafi.php', {
      method: 'POST',
      body: new FormData(form),
      credentials: 'include',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    })
      .then(r => r.json())
      .then(res => {
        if (btn) { btn.disabled = false; btn.innerHTML = original; }
        if (res && res.success) {
          const $modal = $('#' + modalId);
          $modal.modal('hide');
          pjlToast(res.message || typeLabel + ' ok', 'success');
          loadJournal();
        } else {
          pjlToast((res && res.message) || 'Error', 'error');
        }
      })
      .catch(err => {
        if (btn) { btn.disabled = false; btn.innerHTML = original; }
        pjlToast('Network error: ' + err.message, 'error');
      });
  });
}

document.addEventListener('DOMContentLoaded', function () {
  sarafiSubmit('sarafiDepositForm', 'sarafiDepositModal', 'Deposit');
  sarafiSubmit('sarafiWithdrawForm', 'sarafiWithdrawModal', 'Withdrawal');
  sarafiSubmit('sarafiHawalaForm', 'sarafiHawalaModal', 'Hawala');
  sarafiSubmit('sarafiExchangeForm', 'sarafiExchangeModal', 'Exchange');

  /* Hawala live breakdown */
  function updateHawalaBreakdown() {
    const container = $('#sarafiHawalaModal');
    if (!container.length) return;
    const sendAmt = parseFloat(container.find('input[name="send_amount"]').val()) || 0;
    const commission = parseFloat(container.find('input[name="commission_amount"]').val()) || 0;
    const net = Math.max(0, sendAmt - commission);
    container.find('#hawalaBreakdownSend').text(sendAmt.toFixed(2));
    container.find('#hawalaBreakdownCommission').html('− ' + commission.toFixed(2));
    container.find('#hawalaBreakdownNet').text(net.toFixed(2));
  }
  $(document).on('input', '#sarafiHawalaModal input[name="send_amount"], #sarafiHawalaModal input[name="commission_amount"]', updateHawalaBreakdown);
  $('#sarafiHawalaModal').on('shown.bs.modal', updateHawalaBreakdown);

  /* Exchange auto-calculate */
  function exchangeFormula(from, to) {
    const divide = ['AFS->USD', 'AFS->EUR', 'AFS->AED', 'AED->USD', 'AED->EUR', 'AFS->SAR', 'SAR->USD', 'SAR->EUR'];
    return divide.includes(from + '->' + to) ? 'divide' : 'multiply';
  }
  function updateExchangeFormulaBadge() {
    $('#exchangeFormulaBadge').text(exchangeFormula($('#exchangeFromCurrency').val(), $('#exchangeToCurrency').val()) === 'divide' ? '÷' : '×');
  }
  $(document).on('input', '#exchangeFromAmount, #exchangeRate', function () {
    const fromAmt = parseFloat($('#exchangeFromAmount').val()) || 0;
    const rate = parseFloat($('#exchangeRate').val()) || 0;
    const toAmt = exchangeFormula($('#exchangeFromCurrency').val(), $('#exchangeToCurrency').val()) === 'divide' ? fromAmt / rate : fromAmt * rate;
    $('#exchangeToAmount').val(toAmt.toFixed(2));
  });
  const sampleRates = {
    'USD->EUR': 0.92, 'EUR->USD': 1.09,
    'USD->AFS': 72.5, 'AFS->USD': 72.5,
    'USD->AED': 3.67, 'AED->USD': 3.67,
    'EUR->AFS': 78.8, 'AFS->EUR': 78.8,
    'EUR->AED': 3.99, 'AED->EUR': 3.99,
    'AED->AFS': 19.75, 'AFS->AED': 19.75,
    'USD->SAR': 3.75, 'SAR->USD': 3.75,
    'EUR->SAR': 4.07, 'SAR->EUR': 4.07,
    'AED->SAR': 1.02, 'SAR->AED': 1.02,
    'AFS->SAR': 18.67, 'SAR->AFS': 18.67,
  };
  function updateExchangeRateHelp() {
    const fromCur = $('#exchangeFromCurrency').val();
    const toCur = $('#exchangeToCurrency').val();
    if (!fromCur || !toCur) { $('#exchangeRateHelp').text(''); return; }
    const dividePairs = ['AFS->USD', 'AFS->EUR', 'AFS->AED', 'AED->USD', 'AED->EUR', 'AFS->SAR', 'SAR->USD', 'SAR->EUR'];
    const isDivide = dividePairs.includes(fromCur + '->' + toCur);
    const rate = sampleRates[fromCur + '->' + toCur];
    if (!rate) { $('#exchangeRateHelp').text(''); return; }
    if (isDivide) {
      $('#exchangeRateHelp').text('e.g. 1 ' + toCur + ' = ' + rate.toFixed(2) + ' ' + fromCur + ' → enter ' + rate.toFixed(2));
    } else {
      $('#exchangeRateHelp').text('e.g. 1 ' + fromCur + ' = ' + rate.toFixed(2) + ' ' + toCur + ' → enter ' + rate.toFixed(2));
    }
  }
  $(document).on('change', '#exchangeFromCurrency, #exchangeToCurrency', function () {
    updateExchangeFormulaBadge();
    updateExchangeRateHelp();
    $('#exchangeFromAmount').trigger('input');
  });
  $('#sarafiExchangeModal').on('shown.bs.modal', function () {
    updateExchangeRateHelp();
  });
});
