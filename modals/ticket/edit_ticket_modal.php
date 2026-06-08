    <!-- Edit ticket modal -->
    <div class="modal fade" id="editTicketModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div id="editLoader" style="display: none; text-align: center;">
                <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading...
            </div>

            <form id="editTicketForm">
                    <!-- CSRF Protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><?= __('edit_ticket') ?></h5>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body" style="overflow-y: auto; overflow-x: hidden;">
                         <input type="hidden" id="editTicketId" name="id">
                         
                         <!-- Client and Trip Information -->
                         <div class="card" style="position: relative; overflow: visible !important; z-index: 100;">
                             <div class="card-header bg-light">
                                 <h6 class="mb-0"><?= __('booking_details') ?></h6>
                             </div>
                             <div class="card-body" style="overflow: visible !important; padding-bottom: 400px;">
                                 <div class="form-row">
                                    <div class="form-group col-md-4">
                                        <label for="editSupplier">
                                            <i class="feather icon-user mr-1"></i><?= __('supplier') ?>
                                        </label>
                                        <select class="form-control selectpicker" id="editSupplier" name="supplier" required readonly
                                                data-live-search="true" data-style="btn-light">
                                            <option value=""><?= __('select_supplier') ?></option>
                                            <?php foreach ($suppliers as $supplier): ?>
                                                <option value="<?= $supplier['id'] ?>" data-tokens="<?= $supplier['name'] ?>"><?= $supplier['name'] ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group col-md-4">
                                        <label for="editSoldTo">
                                            <i class="feather icon-users mr-1"></i><?= __('sold_to') ?>
                                        </label>
                                        <select class="form-control selectpicker" id="editSoldTo" name="soldTo" required readonly
                                                data-live-search="true" data-style="btn-light">
                                            <option value=""><?= __('select_client') ?></option>
                                            <?php
                                            try {
                                                $stmt = $pdo->prepare("SELECT id, name, usd_balance, afs_balance FROM clients WHERE status = 'active' AND tenant_id = ? AND branch_id = ?");
                                                $stmt->execute([$tenant_id, $branch_id]);
                                                $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                                foreach ($clients as $row) {
                                                    echo "<option value='{$row['id']}' data-tokens='{$row['name']}'>{$row['name']}</option>";
                                                }
                                            } catch (PDOException $e) {
                                                echo "<option value=''>Database connection failed</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group col-md-4">
                                        <label for="editTripType">
                                            <i class="feather icon-repeat mr-1"></i><?= __('trip_type') ?>
                                        </label>
                                        <select class="form-control selectpicker" id="editTripType" name="tripType" required
                                                data-style="btn-light">
                                            <option value="one_way"><?= __('one_way') ?></option>
                                            <option value="round_trip"><?= __('round_trip') ?></option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Passenger Information Section -->
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><?= __('passenger_information') ?></h6>
                            </div>
                            <div class="card-body">
                                <div class="form-row">
                                    <div class="form-group col-md-2">
                                        <label for="editTitle">
                                            <i class="feather icon-user mr-1"></i><?= __('title') ?>
                                        </label>
                                        <select class="form-control select2" id="editTitle" name="title" required>
                                            <option value="Mr"><?= __('mr') ?></option>
                                            <option value="Mrs"><?= __('mrs') ?></option>
                                            <option value="Child"><?= __('child') ?></option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-2">
                                        <label for="editGender">
                                            <i class="feather icon-user mr-1"></i><?= __('gender') ?>
                                        </label>
                                        <select class="form-control select2" id="editGender" name="gender" required>
                                            <option value="Male"><?= __('male') ?></option>
                                            <option value="Female"><?= __('female') ?></option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="editPassengerName">
                                            <i class="feather icon-user mr-1"></i><?= __('passenger_name') ?>
                                        </label>
                                        <input type="text" class="form-control" id="editPassengerName" name="passengerName" required>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="editPhone">
                                            <i class="feather icon-phone mr-1"></i><?= __('phone') ?>
                                        </label>
                                        <input type="text" class="form-control" id="editPhone" name="phone" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Flight Details -->
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><?= __('flight_details') ?></h6>
                            </div>
                            <div class="card-body">
                                <div class="form-row">
                                    <div class="form-group col-md-3">
                                        <label for="editPnr">
                                            <i class="feather icon-hash mr-1"></i><?= __('pnr') ?>
                                        </label>
                                        <input type="text" class="form-control" id="editPnr" name="pnr" required>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="editOrigin">
                                            <i class="feather icon-map-pin mr-1"></i><?= __('from') ?>
                                        </label>
                                        <input type="text" class="form-control" id="editOrigin" name="origin" required>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="editDestination">
                                            <i class="feather icon-map-pin mr-1"></i><?= __('to') ?>
                                        </label>
                                        <input type="text" class="form-control" id="editDestination" name="destination" required>
                                    </div>
                                    <div id="editReturnJourneyFields" class="form-group col-md-3" style="display: none;">
                                        <label for="editReturnDestination">
                                            <i class="feather icon-map-pin mr-1"></i><?= __('return_to') ?>
                                        </label>
                                        <input type="text" class="form-control" id="editReturnDestination" name="returnDestination">
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group col-md-12">
                                        <label for="editAirline">
                                            <i class="feather icon-plane mr-1"></i><?= __('airline') ?>
                                        </label>
                                        <select class="form-control select2" id="editAirline" name="airline" required>
                                            <!-- Airlines will be populated by JavaScript -->
                                        </select>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group col-md-4">
                                        <label for="editIssueDate">
                                            <i class="feather icon-calendar mr-1"></i><?= __('issue_date') ?>
                                        </label>
                                        <input type="date" class="form-control" id="editIssueDate" name="issueDate" required>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="editDepartureDate">
                                            <i class="feather icon-calendar mr-1"></i><?= __('departure_date') ?>
                                        </label>
                                        <input type="date" class="form-control" id="editDepartureDate" name="departureDate" required>
                                        <label for="editDepartureTime">
                                            <i class="feather icon-clock mr-1"></i><?= __('departure_time') ?>
                                        </label>
                                        <input type="time" class="form-control" id="editDepartureTime" name="departureTime" required>
                                    </div>
                                    <div id="editReturnDateField" class="form-group col-md-4" style="display: none;">
                                        <label for="editReturnDate">
                                            <i class="feather icon-calendar mr-1"></i><?= __('return_date') ?>
                                        </label>
                                        <input type="date" class="form-control" id="editReturnDate" name="returnDate">
                                        <label for="editReturnDepartureTime">
                                            <i class="feather icon-clock mr-1"></i><?= __('return_departure_time') ?>
                                        </label>
                                        <input type="time" class="form-control" id="editReturnDepartureTime" name="returnDepartureTime">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Information -->
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><?= __('payment_information') ?></h6>
                            </div>
                            <div class="card-body">
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="editCurr">
                                            <i class="feather icon-dollar-sign mr-1"></i><?= __('currency') ?>
                                        </label>
                                        <input class="form-control" id="editCurr" name="curr" required readonly>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="editPaidTo">
                                            <i class="feather icon-credit-card mr-1"></i><?= __('paid_to') ?>
                                        </label>
                                        <select class="form-control select2" id="editPaidTo" name="paidTo" required readonly>
                                            <option value=""><?= __('select_main_account') ?></option>
                                            <?php
                                            try {
                                                $stmt = $pdo->prepare("SELECT id, name, usd_balance, afs_balance FROM main_account WHERE status = 'active' AND tenant_id = ? AND branch_id = ?");
                                                $stmt->execute([$tenant_id, $branch_id]);
                                                $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                                foreach ($accounts as $row) {
                                                    echo "<option value='{$row['id']}'>{$row['name']}</option>";
                                                }
                                            } catch (PDOException $e) {
                                                echo "<option value=''>Database connection failed</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            
                                <!-- Payment Totals Section -->
                                <div class="form-row">
                                    <div class="form-group col-md-3">
                                        <label for="editBase">
                                            <i class="feather icon-dollar-sign mr-1"></i><?= __('base') ?>
                                        </label>
                                        <input type="number" class="form-control" id="editBase" name="base" step="any" required>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="editSold">
                                            <i class="feather icon-dollar-sign mr-1"></i><?= __('sold') ?>
                                        </label>
                                        <input type="number" class="form-control" id="editSold" name="sold" step="any" required>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="editDiscount">
                                            <i class="feather icon-minus-circle mr-1"></i><?= __('discount') ?>
                                        </label>
                                        <input type="number" class="form-control" id="editDiscount" name="discount" value="0" step="any">
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="editPro">
                                            <i class="feather icon-plus-circle mr-1"></i><?= __('profit') ?>
                                        </label>
                                        <input type="number" class="form-control" id="editPro" name="pro" readonly>
                                    </div>
                                </div>
                            
                                <div class="form-group">
                                    <label for="editDescription">
                                        <i class="feather icon-file-text mr-1"></i><?= __('description') ?>
                                    </label>
                                    <textarea class="form-control" id="editDescription" name="description" rows="3" placeholder="<?= __('enter_description') ?>"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
                    <button type="submit" class="btn btn-primary"><?= __('save_changes') ?></button>
                </div>
                </div>
            </form>
        </div>
    </div>