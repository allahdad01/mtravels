<!-- Move Family to Another Group Modal -->
<div class="modal fade" id="moveFamilyModal" tabindex="-1" aria-labelledby="moveFamilyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="moveFamilyModalLabel"><i class="fas fa-exchange-alt mr-2"></i>Move Family to Another Group</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="moveFamilyInfo" class="alert alert-info" style="display:none;"></div>

                <!-- Current Group Display -->
                <div class="form-group">
                    <label><strong>Current Group:</strong></label>
                    <p id="moveFamilyCurrentGroup" class="text-muted mb-2"></p>
                </div>

                <!-- Family Info -->
                <div class="form-group">
                    <label><strong>Family:</strong></label>
                    <p id="moveFamilyName" class="text-muted mb-2"></p>
                </div>

                <!-- Target Group -->
                <div class="form-group">
                    <label for="moveFamilyGroupSelect">Target Group</label>
                    <select class="form-control" id="moveFamilyGroupSelect">
                        <option value="">-- Select Group --</option>
                    </select>
                    <small class="form-text text-muted" id="moveFamilyHint">Select the group to move this family to.</small>
                </div>

                <!-- Hidden fields -->
                <input type="hidden" id="moveFamilyId">
                <input type="hidden" id="moveFamilySourceGroupId">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="moveFamilyConfirmBtn" onclick="confirmMoveFamily()" disabled>
                    <i class="fas fa-exchange-alt mr-1"></i>Move Family
                </button>
            </div>
        </div>
    </div>
</div>
