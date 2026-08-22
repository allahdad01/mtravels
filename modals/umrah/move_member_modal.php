<!-- Move Member Modal -->
<div class="modal fade" id="moveMemberModal" tabindex="-1" aria-labelledby="moveMemberModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="moveMemberModalLabel"><i class="fas fa-exchange-alt mr-2"></i>Move Member to Another Family</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="moveMemberInfo" class="alert alert-info" style="display:none;"></div>

                <!-- Current Family Display -->
                <div class="form-group">
                    <label><strong>Current Family:</strong></label>
                    <p id="moveMemberCurrentFamily" class="text-muted mb-2"></p>
                </div>

                <!-- Target Group -->
                <div class="form-group">
                    <label for="moveMemberGroupSelect">Target Group</label>
                    <select class="form-control" id="moveMemberGroupSelect" onchange="moveMemberLoadFamilies()">
                        <option value="">-- Select Group --</option>
                    </select>
                </div>

                <!-- Target Family -->
                <div class="form-group">
                    <label for="moveMemberFamilySelect">Target Family</label>
                    <select class="form-control" id="moveMemberFamilySelect" disabled>
                        <option value="">-- Select a group first --</option>
                    </select>
                    <small class="form-text text-muted" id="moveMemberFamilyHint">Select a group to see available families.</small>
                </div>

                <!-- Hidden fields -->
                <input type="hidden" id="moveMemberBookingId">
                <input type="hidden" id="moveMemberSourceFamilyId">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="moveMemberConfirmBtn" onclick="confirmMoveMember()" disabled>
                    <i class="fas fa-exchange-alt mr-1"></i>Move Member
                </button>
            </div>
        </div>
    </div>
</div>
