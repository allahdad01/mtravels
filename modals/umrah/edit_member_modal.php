<!-- Bootstrap Modal for Editing Umrah Booking -->
<div class="modal fade" id="editMemberModal" tabindex="-1" aria-labelledby="editMemberModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editMemberModalLabel"><?= __('edit_member') ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="max-height: 75vh; overflow-y: auto;">
                <form id="editMemberForm" method="POST">
                    <!-- CSRF Protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">

                    <input type="hidden" name="booking_id" id="editBookingId">

                    <!-- Common Fields: Sold To, Paid To -->
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="feather icon-settings mr-2"></i><?= __('common_information') ?></h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="form-group col-md-6">
                                    <label for="editSoldTo"><?= __('sold_to') ?></label>
                                    <select class="form-control" id="editSoldTo" name="soldTo" required>
                                        <option value=""><?= __('select_client') ?></option>
                                        <?php
                                        // Fetch clients from the database using PDO
                                        try {
                                            $stmt = $pdo->prepare("SELECT id, name, usd_balance, afs_balance FROM clients WHERE status = 'active' AND tenant_id = :tenant_id");
                                            $stmt->bindParam(':tenant_id', $tenant_id, PDO::PARAM_INT);
                                            $stmt->execute();
                                            $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                            foreach ($clients as $row) {
                                                echo "<option value='{$row['id']}' data-usd='{$row['usd_balance']}' data-afs='{$row['afs_balance']}'>
                                                        {$row['name']}
                                                      </option>";
                                            }
                                        } catch (PDOException $e) {
                                            error_log("Error fetching clients: " . $e->getMessage());
                                            echo "<option value=''>" . __('error_loading_clients') . "</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="editPaidTo"><?= __('paid_to') ?></label>
                                    <select class="form-control" id="editPaidTo" name="paidTo" required>
                                        <option value=""><?= __('select_main_account') ?></option>
                                        <?php
                                        // Fetch main accounts from the database using PDO
                                        try {
                                            $stmt = $pdo->prepare("SELECT id, name, usd_balance, afs_balance FROM main_account WHERE status = 'active' AND tenant_id = :tenant_id");
                                            $stmt->bindParam(':tenant_id', $tenant_id, PDO::PARAM_INT);
                                            $stmt->execute();
                                            $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                            foreach ($accounts as $row) {
                                                echo "<option value='{$row['id']}' data-usd='{$row['usd_balance']}' data-afs='{$row['afs_balance']}'>
                                                        {$row['name']}
                                                      </option>";
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

                    <!-- Services Section -->
                    <div class="card mb-4">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="feather icon-package mr-2"></i><?= __('services') ?></h6>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="editAddServiceBtn">
                                <i class="feather icon-plus"></i> Add Service
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="edit-services-grid-wrapper">
                                <div class="edit-services-grid-header">
                                    <div class="edit-header-item edit-header-column-1"><?= __('service_info') ?></div>
                                    <div class="edit-header-item edit-header-column-2"><?= __('pricing_info') ?></div>
                                    <div class="edit-header-item edit-header-column-3"><?= __('actions') ?></div>
                                </div>
                                <div id="editServicesTableBody" class="edit-services-grid-body">
                                    <!-- Service rows will be added here -->
                                </div>
                                <div class="edit-services-grid-footer">
                                    <div class="edit-footer-item edit-footer-column-1">
                                        <strong><?= __('total') ?>:</strong>
                                    </div>
                                    <div class="edit-footer-item edit-footer-column-2">
                                        <div class="edit-total-inputs">
                                            <div class="edit-total-input-group">
                                                <label><?= __('base_price') ?>:</label>
                                                <input type="number" class="form-control form-control-sm" id="editTotalBasePrice" readonly value="0">
                                            </div>
                                            <div class="edit-total-input-group">
                                                <label><?= __('sold_price') ?>:</label>
                                                <input type="number" class="form-control form-control-sm" id="editTotalSoldPrice" readonly value="0">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="edit-footer-item edit-footer-column-3">
                                        <div class="edit-total-input-group">
                                            <label><?= __('discount') ?>:</label>
                                            <input type="number" class="form-control form-control-sm" id="editDiscount" name="discount" value="0" min="0" step="0.01">
                                        </div>
                                        <div class="edit-total-input-group">
                                            <label><?= __('profit') ?>:</label>
                                            <input type="number" class="form-control form-control-sm" id="editTotalProfit" readonly value="0">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>


                    <!-- Second Row: Entry Date, Name, Date of Birth -->
                    <div class="row">
                        <div class="form-group col-md-4">
                            <label for="editEntry_date"><?= __('entry_date') ?></label>
                            <input type="date" class="form-control" id="editEntry_date" name="entry_date" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="editName"><?= __('name') ?></label>
                            <input type="text" class="form-control" id="editName" name="name" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="editDob"><?= __('date_of_birth') ?></label>
                            <input type="date" class="form-control" id="editDob" name="dob" required>
                        </div>
                    </div>

                    <!-- Additional Row: Gender and Nationality -->
                    <div class="row">
                        <div class="form-group col-md-4">
                            <label for="editGender"><?= __('gender') ?></label>
                            <select class="form-control" id="editGender" name="gender" required>
                                <option value="Male"><?= __('male') ?></option>
                                <option value="Female"><?= __('female') ?></option>
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="editFather_name"><?= __('father_name') ?></label>
                            <input type="text" class="form-control" id="editFather_name" name="father_name" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="editG_name"><?= __('g_name') ?></label>
                            <input type="text" class="form-control" id="editG_name" name="g_name" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="editRelation"><?= __('relation') ?></label>
                            <select class="form-control" id="editRelation" name="relation" required>
                                <option value=""><?= __('select_relation') ?></option>
                                <option value="Ownself"><?= __('ownself') ?></option>
                                <option value="Friend"><?= __('friend') ?></option>
                                <option value="Father"><?= __('father') ?></option>
                                <option value="Mother"><?= __('mother') ?></option>
                                <option value="Brother"><?= __('brother') ?></option>
                                <option value="Sister"><?= __('sister') ?></option>
                                <option value="Son"><?= __('son') ?></option>
                                <option value="Daughter"><?= __('daughter') ?></option>
                                <option value="Wife"><?= __('wife') ?></option>
                                <option value="Husband"><?= __('husband') ?></option>
                                <option value="Grandfather"><?= __('grand_father') ?></option>
                                <option value="Grandmother"><?= __('grand_mother') ?></option>
                                <option value="Uncle"><?= __('uncle') ?></option>
                                <option value="Aunt"><?= __('aunt') ?></option>
                                <option value="Cousin"><?= __('cousin') ?></option>
                                <option value="Nephew"><?= __('nephew') ?></option>
                                <option value="Niece"><?= __('niece') ?></option>
                                <option value="Son-in-law"><?= __('son_in_law') ?></option>
                                <option value="Daughter-in-law"><?= __('daughter_in_law') ?></option>
                                <option value="Brother-in-law"><?= __('brother_in_law') ?></option>
                                <option value="Sister-in-law"><?= __('sister_in_law') ?></option>
                                <option value="Grandson"><?= __('grandson') ?></option>
                                <option value="Granddaughter"><?= __('granddaughter') ?></option>
                                <option value="Father-in-law"><?= __('father_in_law') ?></option>
                                <option value="Mother-in-law"><?= __('mother_in_law') ?></option>
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="editRoom_type"><?= __('room_type') ?></label>
                            <select class="form-control" id="editRoom_type" name="room_type" required>
                                <option value=""><?= __('select_room_type') ?></option>
                                <option value="1 Bed"><?= __('1_bed') ?></option>
                                <option value="2 Beds"><?= __('2_beds') ?></option>
                                <option value="3 Beds"><?= __('3_beds') ?></option>
                                <option value="Shared"><?= __('shared') ?></option>
                                <option value="No Room"><?= __('no_room') ?></option>
                            </select>
                        </div>
                    </div>

                    <!-- Third Row: Passport Number, ID Type, Flight Date -->
                    <div class="row">
                        <div class="form-group col-md-4">
                            <label for="editPassport_number"><?= __('passport_number') ?></label>
                            <input type="text" class="form-control" id="editPassport_number" name="passport_number" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="editPassport_expiry"><?= __('passport_expiry') ?></label>
                            <input type="date" class="form-control" id="editPassport_expiry" name="passport_expiry" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="editId_type"><?= __('id_type') ?></label>
                            <select class="form-control" id="editId_type" name="id_type" required>
                            <option value="ID Original + Passport Original"><?= __('ID Original + Passport Original') ?></option>
                            <option value="ID Original + Passport Copy"><?= __('ID Original + Passport Copy') ?></option>
                            <option value="ID Copy + Passport Original"><?= __('ID Copy + Passport Original') ?></option>
                            <option value="ID Copy + Passport Copy"><?= __('ID Copy + Passport Copy') ?></option>
                            </select>
                        </div>
                    </div>

                    <!-- Fourth Row: Return Date, Duration, Room Type -->
                    <div class="row">
                        <div class="form-group col-md-4">
                            <label for="editFlight_date"><?= __('flight_date') ?></label>
                            <input type="date" class="form-control" id="editFlight_date" name="flight_date">
                        </div>
                        <div class="form-group col-md-4">
                            <label for="editReturn_date"><?= __('return_date') ?></label>
                            <input type="date" class="form-control" id="editReturn_date" name="return_date">
                        </div>
                        <div class="form-group col-md-4">
                            <label for="editDuration"><?= __('duration') ?></label>
                            <select class="form-control" id="editDuration" name="duration" required>
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
                            </select>
                        </div>
                    </div>

                    <!-- Eighth Row: Due Amount and Additional Fields -->
                    <div class="row">
                            <input type="hidden" class="form-control" id="editDue" name="due" readonly>

                        <div class="form-group col-md-12">
                            <label for="editRemarks"><?= __('remarks') ?></label>
                            <textarea class="form-control" id="editRemarks" name="remarks"></textarea>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary"><?= __('update_booking') ?></button>
                </form>
            </div>
        </div>
    </div>
</div>