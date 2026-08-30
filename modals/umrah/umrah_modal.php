<!-- Bootstrap Modal for Adding Multiple Umrah Bookings -->
<div class="modal fade" id="umrahModal" tabindex="-1" aria-labelledby="umrahModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="umrahModalLabel"><?= __('add_new_members') ?></h5>
                    <small class="text-muted d-block mt-1">Add multiple members to this family</small>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="max-height: 85vh; overflow-y: auto;">
                <form id="umrahForm" method="POST">
                    <!-- CSRF Protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="family_id" id="familyId">

                    <!-- SECTION 0: FAMILY SELECTION (Add New Family / Select Existing Family)
                         Shown only when the modal is opened from a Group (no family preselected) -->
                    <div class="card mb-4" id="familyChooserCard" style="display:none; border: 2px solid #4099ff;">
                        <div class="card-header" style="background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%); color: white; border-radius: 8px 8px 0 0;">
                            <h6 class="mb-0">
                                <i class="feather icon-users mr-2"></i><?= __('family') ?>
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="btn-group w-100 mb-3" role="group" aria-label="Family mode">
                                <button type="button" class="btn btn-primary active" id="familyModeNewBtn" onclick="setUmrahFamilyMode('new')">
                                    <i class="feather icon-user-plus mr-1"></i><?= __('add_new_family') ?>
                                </button>
                                <button type="button" class="btn btn-outline-primary" id="familyModeExistingBtn" onclick="setUmrahFamilyMode('existing')">
                                    <i class="feather icon-users mr-1"></i><?= __('select_existing_family') ?>
                                </button>
                            </div>

                            <!-- New Family panel -->
                            <div id="newFamilyPanel">
                                <div class="row">
                                    <div class="form-group col-md-4">
                                        <label for="newHeadOfFamily"><?= __('family_head') ?></label>
                                        <input type="text" class="form-control" id="newHeadOfFamily" name="head_of_family">
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="newContact"><?= __('contact_number') ?></label>
                                        <input type="text" class="form-control" id="newContact" name="contact" inputmode="tel">
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="newAddress"><?= __('address') ?></label>
                                        <input type="text" class="form-control" id="newAddress" name="address">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="form-group col-md-4">
                                        <label for="newTazmin"><?= __('tazmin') ?></label>
                                        <select class="form-control" id="newTazmin" name="tazmin">
                                            <option value="Not Done"><?= __('not_done') ?></option>
                                            <option value="Done"><?= __('done') ?></option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label><?= __('group') ?></label>
                                        <select class="form-control" name="group_id">
                                            <option value=""><?= __('select_group') ?></option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Existing Family panel -->
                            <div id="existingFamilyPanel" style="display:none;">
                                <div class="form-group mb-0">
                                    <label for="existingFamilySelect"><?= __('select_existing_family') ?></label>
                                    <select class="form-control" id="existingFamilySelect" name="existing_family_id" onchange="setUmrahFamilyFromSelect(this)">
                                        <option value=""><?= __('select') ?></option>
                                        <?php
                                        $famStmt = $pdo->prepare("SELECT family_id, head_of_family, contact, group_id FROM families WHERE tenant_id = ? AND branch_id = ? ORDER BY head_of_family");
                                        $famStmt->execute([$tenant_id, $branch_id]);
                                        foreach ($famStmt->fetchAll(PDO::FETCH_ASSOC) as $famRow) {
                                            $label = $famRow['head_of_family'];
                                            if (!empty($famRow['contact'])) { $label .= ' — ' . $famRow['contact']; }
                                            echo "<option value='{$famRow['family_id']}' data-group-id='{$famRow['group_id']}'>{$label}</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 1: MULTI-FILE DOCUMENT UPLOAD -->
                    <div class="card mb-4" style="background: linear-gradient(135deg, #f0f9ff 0%, #f5f3ff 100%); border: none;">
                        <div class="card-header" style="background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%); color: white; border-radius: 8px 8px 0 0;">
                            <div class="d-flex align-items-center justify-content-between">
                                <h6 class="mb-0">
                                    <i class="feather icon-upload-cloud mr-2"></i>Quick Document Import (Multiple)
                                </h6>
                                <small style="opacity: 0.9;">Upload passports for auto-fill</small>
                            </div>
                        </div>
                        <div class="card-body" style="padding: 15px;">
                            <div class="row">
                                <div class="col-md-12">
                                    <label style="font-weight: 600; margin-bottom: 10px; display: block;">
                                        <i class="feather icon-file-text mr-1"></i>Passports (One per member)
                                    </label>
                                    <div id="passportUploadZone" style="min-height: 120px; display: flex; align-items: center; justify-content: center; border: 2px dashed #ddd; border-radius: 5px; cursor: pointer; background-color: #fafafa;">
                                        <div style="text-align: center; padding: 20px;">
                                            <i class="feather icon-upload-cloud" style="font-size: 32px; color: #999; margin-bottom: 10px;"></i>
                                            <p style="margin: 5px 0; font-size: 12px;">Drag & drop multiple files or click to select</p>
                                            <small style="color: #ccc;">PDF or Images (Max 10MB per file) - Files will auto-populate member rows</small>
                                        </div>
                                    </div>
                                    <input type="file" id="passportDocumentFile" accept=".pdf,.jpg,.jpeg,.png" multiple style="display:none;" />
                                    <!-- File list display -->
                                    <div id="uploadedFilesList" style="margin-top: 10px;"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 2: COMMON FIELDS (Sold To, Paid To, Services, Discount, Remarks) -->
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="feather icon-settings mr-2"></i><?= __('common_information') ?></h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="form-group col-md-6">
                                    <label for="soldTo"><?= __('sold_to') ?></label>
                                    <select class="form-control" id="soldTo" name="soldTo" required>
                                        <option value=""><?= __('select_client') ?></option>
                                        <?php
                                        try {
                                            $stmt = $pdo->prepare("SELECT id, name, usd_balance, afs_balance FROM clients WHERE status = 'active' AND tenant_id = :tenant_id");
                                            $stmt->bindParam(':tenant_id', $tenant_id, PDO::PARAM_INT);
                                            $stmt->execute();
                                            $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                            foreach ($clients as $row) {
                                                echo "<option value='{$row['id']}'>{$row['name']}</option>";
                                            }
                                        } catch (PDOException $e) {
                                            error_log("Error fetching clients: " . $e->getMessage());
                                            echo "<option value=''>" . __('error_loading_clients') . "</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="paidTo"><?= __('paid_to') ?></label>
                                    <select class="form-control" id="paidTo" name="paidTo" required>
                                        <option value=""><?= __('select_main_account') ?></option>
                                        <?php
                                        try {
                                            $stmt = $pdo->prepare("SELECT id, name, usd_balance, afs_balance FROM main_account WHERE status = 'active' AND tenant_id = :tenant_id");
                                            $stmt->bindParam(':tenant_id', $tenant_id, PDO::PARAM_INT);
                                            $stmt->execute();
                                            $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                            foreach ($accounts as $row) {
                                                echo "<option value='{$row['id']}'>{$row['name']}</option>";
                                            }
                                        } catch (PDOException $e) {
                                            error_log("Error fetching main accounts: " . $e->getMessage());
                                            echo "<option value=''>" . __('error_loading_accounts') . "</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 3: SHARED SERVICES -->
                    <div class="card mb-4">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap">
                            <h6 class="mb-0"><i class="feather icon-package mr-2"></i><?= __('services') ?></h6>
                            <div class="d-flex align-items-center" style="gap: 6px;">
                                <select class="form-control form-control-sm" id="packageSelect" name="package_id" style="min-width: 220px;">
                                    <option value="">-- <?= __('select_package') ?> --</option>
                                    <?php
                                    try {
                                        $stmt = $pdo->prepare("SELECT id, name, code FROM umrah_packages WHERE tenant_id = :tenant_id AND status = 'active' ORDER BY sort_order, name");
                                        $stmt->bindParam(':tenant_id', $tenant_id, PDO::PARAM_INT);
                                        $stmt->execute();
                                        $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        foreach ($packages as $packageRow) {
                                            $label = $packageRow['name'];
                                            if (!empty($packageRow['code'])) { $label .= ' (' . $packageRow['code'] . ')'; }
                                            echo "<option value='{$packageRow['id']}'>{$label}</option>";
                                        }
                                    } catch (PDOException $e) {
                                        error_log("Error fetching packages: " . $e->getMessage());
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info py-2 mb-3" style="font-size: 0.85rem;">
                                <i class="feather icon-info mr-1"></i>
                                <?= __('select_package_hint') ?> — services load from the package. Pricing is set per member below.
                            </div>
                            <div id="packageEmptyState" class="text-center py-4">
                                <i class="feather icon-package" style="font-size: 2rem; color: #adb5bd;"></i>
                                <div class="text-muted mt-2"><?= __('select_package') ?> <?= __('select_package_to_continue') ?></div>
                            </div>
                            <div id="packageServicesPanel" class="d-none">
                            <div class="services-grid-wrapper">
                                <div class="services-grid-header" style="display:flex; flex-wrap:wrap; align-items:flex-end; gap:16px; border-bottom:1px solid #dee2e6;">
                                    <div class="header-item" style="align-self:center; padding-bottom:10px;"><?= __('service_info') ?></div>
                                </div>
                                <div id="servicesTableBody" class="services-grid-body">
                                    <!-- Service rows will be added here -->
                                </div>
                            </div>
                        </div>
                        </div>
                    </div>

                    <!-- SECTION 4: MEMBERS CONTAINER -->
                    <div class="card mb-4">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0"><i class="feather icon-users mr-2"></i>Members</h6>
                                <small class="text-muted d-block">Add member details (flight dates, room type apply to all)</small>
                            </div>
                            <button type="button" class="btn btn-sm btn-success" id="addMemberBtn">
                                <i class="feather icon-user-plus"></i> Add Member
                            </button>
                        </div>
                        <div class="card-body">
                            <div id="membersContainer">
                                <!-- Member cards will be added here -->
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 5: SHARED FLIGHT & ROOM INFO -->
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="feather icon-info mr-2"></i>Flight & Accommodation (Shared for all)</h6>
                        </div>
                        <div class="card-body">
                            <!-- Common Flight Info -->
                            <div class="row">
                                <div class="form-group col-md-4">
                                    <label for="entry_date"><?= __('entry_date') ?></label>
                                    <input type="date" class="form-control" id="entry_date" name="entry_date" required>
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="room_type"><?= __('room_type') ?></label>
                                    <select class="form-control" id="room_type" name="room_type" required>
                                        <option value=""><?= __('select_room_type') ?></option>
                                        <option value="1 Bed"><?= __('1_bed') ?></option>
                                        <option value="2 Beds"><?= __('2_beds') ?></option>
                                        <option value="3 Beds"><?= __('3_beds') ?></option>
                                        <option value="4 Beds"><?= __('4_beds') ?></option>
                                        <option value="5 Beds"><?= __('5_beds') ?></option>
                                        <option value="6 Beds"><?= __('6_beds') ?></option>
                                        <option value="Shared"><?= __('shared') ?></option>
                                        <option value="Special Room"><?= __('special_room') ?></option>
                                        <option value="No Room"><?= __('no_room') ?></option>
                                    </select>
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="duration"><?= __('duration') ?></label>
                                    <select class="form-control" id="duration" name="duration" required>
                                        <option value=""><?= __('select_duration') ?></option>
                                        <option value="5 Days"><?= __('5_days') ?></option>
                                        <option value="6 Days"><?= __('6_days') ?></option>
                                        <option value="7 Days"><?= __('7_days') ?></option>
                                        <option value="8 Days"><?= __('8_days') ?></option>
                                        <option value="9 Days"><?= __('9_days') ?></option>
                                        <option value="10 Days"><?= __('10_days') ?></option>
                                        <option value="11 Days"><?= __('11_days') ?></option>
                                        <option value="12 Days"><?= __('12_days') ?></option>
                                        <option value="13 Days"><?= __('13_days') ?></option>
                                        <option value="14 Days"><?= __('14_days') ?></option>
                                        <option value="15 Days"><?= __('15_days') ?></option>
                                        <option value="16 Days"><?= __('16_days') ?></option>
                                        <option value="17 Days"><?= __('17_days') ?></option>
                                        <option value="18 Days"><?= __('18_days') ?></option>
                                        <option value="19 Days"><?= __('19_days') ?></option>
                                        <option value="20 Days"><?= __('20_days') ?></option>
                                        <option value="21 Days"><?= __('21_days') ?></option>
                                        <option value="22 Days"><?= __('22_days') ?></option>
                                        <option value="23 Days"><?= __('23_days') ?></option>
                                        <option value="24 Days"><?= __('24_days') ?></option>
                                        <option value="25 Days"><?= __('25_days') ?></option>
                                        <option value="26 Days"><?= __('26_days') ?></option>
                                        <option value="27 Days"><?= __('27_days') ?></option>
                                        <option value="28 Days"><?= __('28_days') ?></option>
                                        <option value="29 Days"><?= __('29_days') ?></option>
                                        <option value="30 Days"><?= __('30_days') ?></option>
                                        <option value="45 Days"><?= __('45_days') ?></option>
                                        <option value="60 Days"><?= __('60_days') ?></option>
                                        <option value="90 Days"><?= __('90_days') ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 6: SHARED REMARKS & HIDDEN FIELDS -->
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="feather icon-edit-2 mr-2"></i>Additional Information</h6>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="remarks"><?= __('remarks') ?></label>
                                <textarea class="form-control" id="remarks" name="remarks"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- HIDDEN FIELDS -->
                    <input type="hidden" class="form-control" id="received_bank_payment" name="received_bank_payment">
                    <input type="hidden" class="form-control" id="bank_receipt_number" name="bank_receipt_number">
                    <input type="hidden" class="form-control" id="paid" name="paid">
                    <input type="hidden" class="form-control" id="due" name="due" readonly>

                    <!-- SECTION 7: SUMMARY & ACTION BUTTONS -->
                    <div class="card mb-4 bg-light" id="membersSummaryCard" style="display:none;">
                        <div class="card-header bg-primary text-white">
                            <h6 class="mb-0">Summary</h6>
                        </div>
                        <div class="card-body">
                            <p class="mb-2"><strong>Members to add: <span id="memberCount">0</span></strong></p>
                            <div id="membersSummaryList" style="max-height: 200px; overflow-y: auto;"></div>
                        </div>
                    </div>

                    <!-- ACTION BUTTONS -->
                    <div class="modal-footer sticky-footer" style="background: white; border-top: 1px solid #e9ecef; margin-top: 20px;">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-save mr-2"></i><?= __('add_booking') ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
