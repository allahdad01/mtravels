<!-- Modal for Bulk Update Flight and Return Dates -->
<div class="modal fade" id="bulkFlightDatesModal" tabindex="-1" aria-labelledby="bulkFlightDatesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="bulkFlightDatesModalLabel">
                    <i class="fas fa-plane mr-2"></i><?= __('bulk_update_flight_dates') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="bulkFlightDatesForm">
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                    
                    <!-- Family Selection -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-users mr-2"></i>Select Families</h6>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="bulkFamilyIds">Families * (Select one or more)</label>
                                <select class="form-control" id="bulkFamilyIds" name="family_ids" multiple required style="height: 150px;">
                                    <!-- Options will be populated by JavaScript -->
                                </select>
                                <small class="form-text text-muted mt-2">
                                    <i class="fas fa-info-circle"></i> Hold Ctrl (Cmd on Mac) to select multiple families
                                </small>
                            </div>
                            <div class="alert alert-info mt-2">
                                <strong id="bulkFamilyCount">0</strong> families selected
                                (<strong id="bulkTotalMembersCount">0</strong> total members)
                            </div>
                        </div>
                    </div>

                    <!-- Date Fields -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-calendar-alt mr-2"></i>Update Dates</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="form-group col-md-6">
                                    <label for="bulkFlightDate">Flight Date *</label>
                                    <input type="date" class="form-control" id="bulkFlightDate" name="flight_date" required>
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="bulkReturnDate">Return Date *</label>
                                    <input type="date" class="form-control" id="bulkReturnDate" name="return_date" required>
                                </div>
                            </div>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle mr-2"></i>
                                These dates will be applied to all members in the selected family.
                            </div>
                        </div>
                    </div>

                    <!-- Member Preview -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-list mr-2"></i>Members to Update</h6>
                        </div>
                        <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                            <p id="bulkNoMembersMsg" class="text-muted text-center py-3" style="display: block;">
                                <i class="fas fa-inbox"></i> Select families to see members
                            </p>
                            <div id="bulkMembersPreview" class="table-responsive" style="display: none;">
                                <table class="table table-sm table-hover mb-0">
                                    <thead>
                                        <tr class="bg-light sticky-top">
                                            <th style="width: 50%;"><input type="checkbox" id="bulkSelectAll" class="mr-2"> Member Name</th>
                                            <th style="width: 25%;">Passport</th>
                                            <th style="width: 25%;">Flight Date</th>
                                        </tr>
                                    </thead>
                                    <tbody id="bulkMembersTableBody">
                                        <!-- Members will be populated here -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Options -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-cogs mr-2"></i>Options</h6>
                        </div>
                        <div class="card-body">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="bulkSkipValidation" name="skip_validation">
                                <label class="custom-control-label" for="bulkSkipValidation">
                                    Skip date validation (allow past dates)
                                </label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="bulkUpdateBtn">
                    <i class="fas fa-check-circle mr-2"></i>Update All Members
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.bulk-member-row {
    transition: background-color 0.2s;
}
.bulk-member-row.selected {
    background-color: #e3f2fd;
}
.bulk-member-row:hover {
    background-color: #f5f5f5;
}
</style>
