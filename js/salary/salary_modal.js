/* Salary operations AJAX submit (embedded by Payments Journal) */

function salarySubmit(formId, modalId, endpoint, typeLabel) {
  const form = document.getElementById(formId);
  if (!form) return;
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    const btn = form.querySelector('button[type="submit"]');
    const original = btn ? btn.innerHTML : '';
    if (btn) btn.disabled = true;

    fetch(endpoint, {
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
  salarySubmit('salaryForm', 'salaryModal', 'salary_payment.php', 'Salary');
  salarySubmit('salaryAdvanceForm', 'salaryAdvanceModal', 'salary_advances.php', 'Advance');
  salarySubmit('salaryBonusForm', 'salaryBonusModal', 'salary_payment.php', 'Bonus');
  salarySubmit('salaryDeductionForm', 'salaryDeductionModal', 'manage_deductions.php', 'Deduction');
});
