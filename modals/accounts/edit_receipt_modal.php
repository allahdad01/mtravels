<!-- Edit Receipt Modal -->
<div class="modal fade modern-modal" id="editReceiptModal" tabindex="-1" aria-labelledby="editReceiptModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="editReceiptModalLabel">
                    <i class="feather icon-file-text mr-2"></i>Edit Receipt
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="editReceiptForm">
                    <input type="hidden" id="editReceiptTransactionId" name="transaction_id">
                    <input type="hidden" id="editReceiptTransactionType" name="transaction_type">
                    
                    <div class="form-group">
                        <label for="editReceiptNumber" class="font-weight-bold">Receipt Number</label>
                        <input type="text" class="form-control" id="editReceiptNumber" name="receipt" placeholder="Enter receipt number" required>
                        <small class="form-text text-muted">Enter or update the receipt number for this transaction</small>
                    </div>
                    <div id="editReceiptError" class="alert alert-danger d-none mt-2 mb-0"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-info" id="saveEditReceiptBtn">
                    <i class="feather icon-save mr-1"></i>Save Receipt
                </button>
            </div>
        </div>
    </div>
</div>
