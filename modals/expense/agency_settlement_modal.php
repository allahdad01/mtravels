<!-- Agency Settlement Modal - Mark Expense for 2nd Agency Branch -->
<div class="modal fade" id="agencySettlementModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="feather icon-building"></i>
                    Link Expense to Branch
                </h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="agencySettlementForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                <div class="modal-body">
                    <input type="hidden" id="agsExpenseId" name="agsExpenseId">

                    <div class="form-group">
                        <div class="custom-control custom-switch mb-3">
                            <input type="checkbox" class="custom-control-input" id="agsEnabled" name="agsEnabled">
                            <label class="custom-control-label" for="agsEnabled">
                                <strong>This expense is for the 2nd Agency</strong>
                                <br><small class="text-muted">Toggle on if you paid this expense on behalf of another branch</small>
                            </label>
                        </div>
                    </div>

                    <div id="agsFields" style="display:none;">
                        <div class="form-group">
                            <label class="form-label">Branch / Agency Name *</label>
                            <select class="form-control" id="agsAgency" name="agsAgency">
                                <option value="">Choose branch...</option>
                                <?php
                                $currentBranchId = $_SESSION['branch_id'] ?? 0;
                                $agsBranchQuery = "SELECT id, name FROM branches WHERE tenant_id = ? AND status = 'active' AND id != ? ORDER BY name";
                                $agsBranchStmt = $pdo->prepare($agsBranchQuery);
                                $agsBranchStmt->execute([$_SESSION['tenant_id'] ?? 1, $currentBranchId]);
                                while ($b = $agsBranchStmt->fetch(PDO::FETCH_ASSOC)):
                                ?>
                                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                                <?php endwhile; ?>
                                <option value="__custom__">+ Enter Custom Name</option>
                            </select>
                        </div>
                        <div class="form-group" id="agsCustomNameGroup" style="display:none;">
                            <label class="form-label">Agency / Client Name *</label>
                            <input type="text" class="form-control" id="agsCustomName" name="agsCustomName" placeholder="e.g. ABC Travel Agency" maxlength="255">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Amount Owed *</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">$</span>
                                </div>
                                <input type="number" step="0.01" min="0.01" class="form-control" id="agsAmount" name="agsAmount" placeholder="0.00" required>
                            </div>
                            <small class="form-text text-muted">Amount the branch needs to pay you back. Cannot exceed the expense amount.</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Currency *</label>
                            <select class="form-control" id="agsCurrency" name="agsCurrency" required>
                                <option value="USD">USD</option>
                                <option value="AFS">AFS</option>
                                <option value="EUR">EUR</option>
                                <option value="DARHAM">DARHAM</option>
                                <option value="SAR">SAR</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="feather icon-x"></i> Close
                    </button>
                    <button type="submit" class="btn btn-primary" id="agsSubmitBtn">
                        <i class="feather icon-check"></i> Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('agsEnabled').addEventListener('change', function() {
    document.getElementById('agsFields').style.display = this.checked ? 'block' : 'none';
});

document.getElementById('agsAgency').addEventListener('change', function() {
    var isCustom = this.value === '__custom__';
    document.getElementById('agsCustomNameGroup').style.display = isCustom ? 'block' : 'none';
    if (!isCustom) {
        document.getElementById('agsCustomName').value = '';
    }
});

document.getElementById('agencySettlementForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var enabled = document.getElementById('agsEnabled').checked;
    if (!enabled) {
        document.getElementById('agencySettlementModal').modal('hide');
        return;
    }

    var agency = document.getElementById('agsAgency');
    var customName = document.getElementById('agsCustomName');
    var amount = document.getElementById('agsAmount');
    var valid = true;

    [agency, amount].forEach(function(el) {
        el.style.borderColor = '';
    });
    customName.style.borderColor = '';

    if (!agency.value) {
        agency.style.borderColor = '#dc2626';
        valid = false;
    }
    if (agency.value === '__custom__' && !customName.value.trim()) {
        customName.style.borderColor = '#dc2626';
        valid = false;
    }
    if (!amount.value || parseFloat(amount.value) <= 0) {
        amount.style.borderColor = '#dc2626';
        valid = false;
    }

    if (!valid) {
        alert('Please fill in all required fields');
        return;
    }

    // Submit via AJAX (handled in event_handlers.js)
    this.dispatchEvent(new Event('agsValidSubmit'));
});
</script>
