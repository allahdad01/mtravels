<!-- Penalty Input Modal -->
<div class="modal fade" id="penaltyModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="feather icon-dollar-sign mr-2"></i>Enter Penalty Amounts
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Please enter the penalty amounts for this date change:</p>
                <form id="penaltyForm">
                    <input type="hidden" id="penaltyRequestId" value="">
                    <div class="form-group">
                        <label for="modal_supplier_penalty">Supplier Penalty ($)</label>
                        <input type="number" class="form-control" id="modal_supplier_penalty" min="0" step="0.01" placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label for="modal_service_penalty">Service Penalty ($)</label>
                        <input type="number" class="form-control" id="modal_service_penalty" min="0" step="0.01" placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label for="modal_penalty_remarks">Penalty Remarks (Optional)</label>
                        <textarea class="form-control" id="modal_penalty_remarks" rows="2" placeholder="Reason for penalties..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="feather icon-x mr-2"></i>Cancel
                </button>
                <button type="button" class="btn btn-primary" onclick="submitPenaltyApproval()">
                    <i class="feather icon-check mr-2"></i>Approve with Penalties
                </button>
            </div>
        </div>
    </div>
</div>