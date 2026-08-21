<!-- Bootstrap Modal to Edit a Group -->
<div class="modal umrah-modal fade" id="editGroupModal" tabindex="-1" aria-labelledby="editGroupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editGroupModalLabel"><?= __('edit_group') ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <!-- Group Details Form -->
                <form id="editGroupForm" method="POST" onsubmit="return submitEditGroupForm();">
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="group_id" id="editGroupId">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="editGroupNumber"><?= __('group_number') ?></label>
                                <input type="text" class="form-control" id="editGroupNumber" name="group_number" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="editGroupName"><?= __('group_name') ?></label>
                                <input type="text" class="form-control" id="editGroupName" name="group_name" required>
                            </div>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn umrah-btn umrah-btn-primary"><?= __('save_changes') ?></button>
                        </div>
                    </div>
                </form>

                <hr>

                <!-- Edit Families Toggle -->
                <div class="form-group">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="editFamiliesToggle" onchange="toggleEditFamiliesPanel()">
                        <label class="custom-control-label fw-bold" for="editFamiliesToggle">
                            <i class="fas fa-users mr-1"></i> <?= __('edit_families') ?> & <?= __('members') ?>
                        </label>
                    </div>
                </div>

                <!-- Families & Members Panel (hidden by default) -->
                <div id="editFamiliesPanel" style="display: none;">
                    <!-- Step 1: Family Selection -->
                    <div id="familySelectionSection">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="text-primary mb-0"><i class="fas fa-home mr-1"></i> <?= __('select_families_to_edit') ?> <small class="text-muted">(Step 1)</small></h6>
                            <div>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="toggleAllFamilies(true)">Select All</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAllFamilies(false)">Deselect All</button>
                            </div>
                        </div>
                        <div id="familyListContainer" class="border rounded p-2 mb-3" style="max-height: 200px; overflow-y: auto; background: #f8f9fa;">
                            <div class="text-center text-muted py-3" id="familyListLoading">
                                <i class="fas fa-spinner fa-spin"></i> Loading families...
                            </div>
                        </div>
                        <button type="button" class="btn umrah-btn umrah-btn-primary btn-sm" id="loadMembersBtn" onclick="loadSelectedFamilyMembers()" disabled>
                            <i class="fas fa-arrow-right mr-1"></i> Load Members <span id="selectedFamilyCount">(0)</span>
                        </button>
                    </div>

                    <!-- Step 2: Family & Member Editing -->
                    <div id="memberEditSection" style="display: none;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="text-success mb-0"><i class="fas fa-user-edit mr-1"></i> Edit Families & Members <small class="text-muted">(Step 2)</small></h6>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="backToFamilySelection()">
                                <i class="fas fa-arrow-left mr-1"></i> Back to Families
                            </button>
                        </div>
                        <div id="memberEditContainer"></div>
                        <div class="mt-3 text-right">
                            <button type="button" class="btn umrah-btn umrah-btn-primary" onclick="saveAllEdits()">
                                <i class="fas fa-save mr-1"></i> <?= __('save_all_changes') ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.family-select-card {
    border: 2px solid #e9ecef;
    border-radius: 8px;
    padding: 10px 14px;
    margin-bottom: 8px;
    background: #fff;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 10px;
}
.family-select-card:hover {
    border-color: #007bff;
    background: #f0f7ff;
}
.family-select-card.selected {
    border-color: #28a745;
    background: #f0fff4;
}
.family-select-card input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}
.family-select-card .family-info {
    flex: 1;
}
.family-select-card .family-name {
    font-weight: 600;
    color: #333;
}
.family-select-card .family-meta {
    font-size: 0.8rem;
    color: #6c757d;
}
.member-edit-card {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 12px;
    background: #fff;
}
.member-edit-card .card-header-edit {
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    margin: -15px -15px 12px -15px;
    padding: 8px 15px;
    border-radius: 8px 8px 0 0;
    font-weight: 600;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.member-edit-card .save-indicator {
    font-size: 0.75rem;
    color: #28a745;
    display: none;
}
.family-edit-section {
    border: 1px solid #ffc107;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
    background: #fffdf0;
}
.family-edit-section .section-title {
    font-weight: 600;
    color: #856404;
    margin-bottom: 10px;
    padding-bottom: 5px;
    border-bottom: 1px solid #ffc107;
}
/* Custom badges */
.ug-badge {
    display: inline-block;
    padding: 3px 8px;
    font-size: 0.7rem;
    font-weight: 600;
    line-height: 1;
    color: #fff;
    border-radius: 10px;
    white-space: nowrap;
}
.ug-badge-success { background: #28a745; }
.ug-badge-warning { background: #ffc107; color: #333; }
.ug-badge-info    { background: #17a2b8; }
.ug-badge-primary { background: #007bff; }
.ug-badge-danger  { background: #dc3545; }
</style>
