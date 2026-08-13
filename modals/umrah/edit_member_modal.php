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
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="feather icon-package mr-2"></i><?= __('services') ?></h6>
                            <small class="text-muted d-block">Package services are listed here; supplier &amp; pricing are assigned at fulfillment.</small>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="form-group col-md-6">
                                    <label for="editExchangeRate"><?= __('exchange_rate') ?></label>
                                    <input type="number" class="form-control" id="editExchangeRate" name="exchange_rate" value="1" min="0" step="0.0001">
                                    <small class="text-muted d-block"><?= __('exchange_rate_hint') ?></small>
                                </div>
                                <div class="form-group col-md-6">
                                    <label>&nbsp;</label>
                                    <small class="text-muted d-block"><?= __('exchange_rate_note') ?></small>
                                </div>
                            </div>
                            <div class="edit-services-grid-wrapper">
                                <div class="edit-services-grid-header" style="display:flex; flex-wrap:wrap; align-items:flex-end; gap:16px; border-bottom:1px solid #dee2e6;">
                                    <div class="edit-header-item" style="align-self:center; padding-bottom:10px;"><?= __('service_info') ?></div>
                                    <div style="margin-left:auto; min-width:260px; max-width:320px; padding-bottom:10px;">
                                        <label class="d-block text-muted" style="margin:0 0 4px; font-size:0.75rem; font-weight:400;"><?= __('sale_currency') ?> (<?= __('sale_currency_hint') ?>)</label>
                                        <select class="form-control form-control-sm" id="editSaleCurrency" name="sale_currency">
                                            <option value="USD" selected>USD</option>
                                            <option value="AFS">AFS</option>
                                        </select>
                                    </div>
                                </div>
                                <div id="editServicesTableBody" class="edit-services-grid-body">
                                    <!-- Service rows will be added here -->
                                </div>
                                <div id="editEmptyServicesNote" class="d-none text-muted small px-2 py-2">
                                    No package services are priced yet — the agreed package price (Sold Price) is recorded on the booking, and supplier &amp; pricing are assigned later at fulfillment.
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
                                                <input type="number" class="form-control form-control-sm" id="editTotalSoldPrice" name="grand_sold_price" value="0" min="0" step="0.01">
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
                            <input type="date" class="form-control" id="editDob" name="dob">
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
                            <input type="text" class="form-control" id="editFather_name" name="father_name">
                        </div>
                        <div class="form-group col-md-4">
                            <label for="editRoom_type"><?= __('room_type') ?></label>
                            <select class="form-control" id="editRoom_type" name="room_type" required>
                                <option value=""><?= __('select_room_type') ?></option>
                                <option value="1 Bed"><?= __('1_bed') ?></option>
                                <option value="2 Beds"><?= __('2_beds') ?></option>
                                <option value="3 Beds"><?= __('3_beds') ?></option>
                                <option value="4 Beds"><?= __('4_beds') ?></option>
                                <option value="5 Beds"><?= __('5_beds') ?></option>
                                <option value="6 Beds"><?= __('6_beds') ?></option>
                                <option value="Shared"><?= __('shared') ?></option>
                                <option value="No Room"><?= __('no_room') ?></option>
                            </select>
                        </div>
                    </div>

                    <!-- Third Row: Passport Number, ID Type, Flight Date -->
                    <div class="row">
                        <div class="form-group col-md-4">
                            <label for="editPassport_number"><?= __('passport_number') ?></label>
                            <input type="text" class="form-control" id="editPassport_number" name="passport_number">
                        </div>
                        <div class="form-group col-md-4">
                            <label for="editPassport_expiry"><?= __('passport_expiry') ?></label>
                            <input type="date" class="form-control" id="editPassport_expiry" name="passport_expiry">
                        </div>
                        <div class="form-group col-md-4">
                            <label for="editId_type"><?= __('id_type') ?></label>
                            <select class="form-control" id="editId_type" name="id_type">
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
                                <option value="45 Days"><?= __('45_days') ?></option>
                                <option value="60 Days"><?= __('60_days') ?></option>
                                <option value="90 Days"><?= __('90_days') ?></option>
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