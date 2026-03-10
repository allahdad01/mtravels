<?php 
if (!function_exists('h')) {
    function h($string) {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}
?>

<!-- Custom Modal Styles -->
<style>
  @import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600&display=swap');

  #receiptModal .modal-dialog {
    max-width: 480px;
  }

  #receiptModal .modal-content {
    border: none;
    border-radius: 0;
    box-shadow: 0 8px 40px rgba(0, 0, 0, 0.18);
    font-family: 'IBM Plex Sans', sans-serif;
    overflow: hidden;
  }

  #receiptModal .modal-header {
    background-color: #0f1923;
    color: #fff;
    padding: 20px 28px;
    border-bottom: 3px solid #1a6fc4;
    border-radius: 0;
    position: relative;
  }

  #receiptModal .modal-header::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: #1a6fc4;
  }

  #receiptModal .modal-title {
    font-size: 0.95rem;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #fff;
  }

  #receiptModal .btn-close {
    filter: invert(1);
    opacity: 0.7;
  }

  #receiptModal .btn-close:hover {
    opacity: 1;
  }

  #receiptModal .modal-body {
    background-color: #f8f9fb;
    padding: 28px 28px 20px;
  }

  #receiptModal .form-label {
    font-size: 0.72rem;
    font-weight: 600;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: #6b7280;
    margin-bottom: 6px;
  }

  #receiptModal .form-control {
    border-radius: 0;
    border: 1px solid #d1d5db;
    border-left: 3px solid #1a6fc4;
    background-color: #fff;
    font-size: 0.9rem;
    color: #0f1923;
    padding: 10px 14px;
    transition: border-color 0.15s, box-shadow 0.15s;
  }

  #receiptModal .form-control:focus {
    border-color: #1a6fc4;
    border-left-color: #1a6fc4;
    box-shadow: 0 0 0 3px rgba(26, 111, 196, 0.12);
    outline: none;
  }

  #receiptModal .form-control::placeholder {
    color: #b0b7c3;
    font-size: 0.85rem;
  }

  #receiptModal .modal-footer {
    background-color: #fff;
    padding: 16px 28px;
    border-top: 1px solid #e5e7eb;
    gap: 10px;
  }

  #receiptModal #submitReceipt {
    border-radius: 0;
    background-color: #1a6fc4;
    border: none;
    font-size: 0.8rem;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    padding: 10px 24px;
    transition: background-color 0.15s;
  }

  #receiptModal #submitReceipt:hover {
    background-color: #155da0;
  }

  #receiptModal .btn-secondary {
    border-radius: 0;
    background-color: transparent;
    border: 1px solid #d1d5db;
    color: #6b7280;
    font-size: 0.8rem;
    font-weight: 500;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    padding: 10px 20px;
    transition: all 0.15s;
  }

  #receiptModal .btn-secondary:hover {
    background-color: #f3f4f6;
    border-color: #9ca3af;
    color: #374151;
  }

  /* Field row divider */
  #receiptModal .mb-3 + .mb-3 {
    padding-top: 4px;
  }
</style>

<!-- Modal Structure -->
<div class="modal fade" id="receiptModal" tabindex="-1" aria-labelledby="receiptModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
         <h5 class="modal-title" id="receiptModalLabel"><?= __('enter_receipt_details') ?></h5>
         <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
       </div>

      <div class="modal-body">
        <form id="receiptForm" novalidate>
          <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
          <input type="hidden" id="hiddenNotificationId" name="notification_id">

          <div class="mb-3">
            <label for="receiptNumber" class="form-label"><?= __('receipt_number') ?></label>
            <input
              type="text"
              class="form-control"
              id="receiptNumber"
              name="receipt_number"
              placeholder="e.g. RCP-2024-00123"
              required
            >
          </div>

          <div class="mb-3">
            <label for="remarks" class="form-label"><?= __('remarks') ?></label>
            <input
              type="text"
              class="form-control"
              id="remarks"
              name="remarks"
              placeholder="<?= __('enter_remarks') ?>"
              required
            >
          </div>
        </form>
      </div>

      <div class="modal-footer justify-content-end">
         <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
         <button type="button" id="submitReceipt" class="btn btn-success"><?= __('submit') ?></button>
       </div>

    </div>
    </div>
    </div>

    <script>
    // Reset form when modal is closed
    document.getElementById('receiptModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('receiptForm').reset();
    document.getElementById('hiddenNotificationId').value = '';
    });
    </script>