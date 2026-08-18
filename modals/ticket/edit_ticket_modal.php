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
                                    <div class="form-group col-md-6">
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
                                    
                                    <div class="form-group col-md-6">
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
                                    <div class="form-group col-md-8">
                                        <label for="editPassengerName">
                                            <i class="feather icon-user mr-1"></i><?= __('passenger_name') ?>
                                        </label>
                                        <input type="text" class="form-control" id="editPassengerName" name="passengerName" required>
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
                                        <label for="editIssueDate">
                                            <i class="feather icon-calendar mr-1"></i><?= __('issue_date') ?>
                                        </label>
                                        <input type="date" class="form-control" id="editIssueDate" name="issueDate" required>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="editPnr">
                                            <i class="feather icon-hash mr-1"></i><?= __('pnr') ?>
                                        </label>
                                        <input type="text" class="form-control" id="editPnr" name="pnr" placeholder="e.g., ABC123" required>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="editTripType">
                                            <i class="feather icon-repeat mr-1"></i><?= __('trip_type') ?>
                                        </label>
                                        <select class="form-control selectpicker" id="editTripType" name="tripType" required
                                                data-style="btn-light">
                                            <option value="one_way"><?= __('one_way') ?></option>
                                            <option value="round_trip"><?= __('round_trip') ?></option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="editPhone">
                                            <i class="feather icon-phone mr-1"></i><?= __('phone') ?>
                                        </label>
                                        <input type="text" class="form-control" id="editPhone" name="phone" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Journey Segments (ticket segmentation) -->
                        <div class="card">
                            <div class="card-header bg-light">
                                <div class="d-flex align-items-center justify-content-between">
                                    <h6 class="mb-0">
                                        <i class="feather icon-navigation mr-1"></i>Journey Segments
                                    </h6>
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="addEditFlightLegBtn">
                                        <i class="feather icon-plus-circle mr-1"></i><?= __('add_leg') ?? 'Add Leg' ?>
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <p class="text-muted small mb-3">
                                    <?= __('journey_segments_hint') ?? 'Add the flight legs of this journey. Origin, destination and departure/arrival times are filled automatically from the first and last segment.' ?>
                                </p>
                                <div id="editFlightLegsContainer">
                                    <!-- Leg 1 (keeps original field IDs for compatibility) -->
                                    <div class="flight-leg-row border rounded p-3 mb-3" data-leg="1">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <h6 class="mb-0"><span class="leg-number">Leg 1</span></h6>
                                            <button type="button" class="btn btn-sm btn-outline-danger remove-leg-btn" disabled
                                                    title="<?= __('first_leg_required') ?? 'First leg cannot be removed' ?>">
                                                <i class="feather icon-trash-2"></i>
                                            </button>
                                        </div>
                                        <div class="form-row">
                                            <div class="form-group col-md-3 mb-2">
                                                <label class="leg-label">From *</label>
                                                <input type="text" class="form-control leg-origin" id="editOrigin" name="origin" placeholder="e.g., KBL" required>
                                            </div>
                                            <div class="form-group col-md-3 mb-2">
                                                <label class="leg-label">To *</label>
                                                <input type="text" class="form-control leg-destination" id="editDestination" name="destination" placeholder="e.g., DXB" required>
                                            </div>
                                            <div class="form-group col-md-3 mb-2">
                                                <label class="leg-label">Airline</label>
                                                <input type="text" class="form-control leg-airline" id="editAirline" name="airline" placeholder="e.g., FlyDubai">
                                            </div>
                                            <div class="form-group col-md-3 mb-2">
                                                <label class="leg-label">Flight Number</label>
                                                <input type="text" class="form-control leg-flight-number" id="editFlightNumber" placeholder="e.g., FZ302">
                                            </div>
                                        </div>
                                        <div class="form-row">
                                            <div class="form-group col-md-3 mb-2">
                                                <label class="leg-label">Departure Date</label>
                                                <input type="date" class="form-control leg-date" id="editDepartureDate" name="departureDate" required>
                                            </div>
                                            <div class="form-group col-md-3 mb-2">
                                                <label class="leg-label">Departure Time</label>
                                                <input type="time" class="form-control leg-time" id="editDepartureTime" name="departureTime" required>
                                            </div>
                                            <div class="form-group col-md-3 mb-2">
                                                <label class="leg-label">Arrival Date</label>
                                                <input type="date" class="form-control leg-arrival-date" id="editArrivalDate">
                                            </div>
                                            <div class="form-group col-md-3 mb-2">
                                                <label class="leg-label">Arrival Time</label>
                                                <input type="time" class="form-control leg-arrival-time" id="editArrivalTime">
                                            </div>
                                        </div>
                                        <div class="form-row">
                                            <div class="form-group col-md-4 mb-2">
                                                <label class="leg-label">Duration</label>
                                                <input type="text" class="form-control leg-duration" id="editDuration" placeholder="e.g., 2h 30m" readonly>
                                            </div>
                                            <div class="form-group col-md-4 mb-2 leg-stopover-wrap" style="display: none;">
                                                <label class="leg-label">Stopover</label>
                                                <input type="text" class="form-control leg-stopover" id="editStopover" placeholder="e.g., 3h 25m">
                                            </div>
                                        </div>
                                        <small class="form-text text-muted">Origin/Destination auto-filled from segments</small>
                                    </div>
                                </div>
                                <div id="editFlightRoutePreview" class="form-text text-info mb-2" style="font-size:12px;"></div>
                                <div id="editFlightStops" class="form-text text-muted" style="font-size:12px; display:none;"></div>

                                <!-- Return Flight Segments (round trip) -->
                                <div id="editReturnFlightSegmentsGroup" style="display: none;">
                                    <hr class="my-3">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <h6 class="mb-0">
                                            <i class="feather icon-corner-up-left mr-1"></i>Return Flight Segments
                                        </h6>
                                        <button type="button" class="btn btn-sm btn-outline-primary" id="addEditReturnFlightLegBtn">
                                            <i class="feather icon-plus-circle mr-1"></i><?= __('add_leg') ?? 'Add Leg' ?>
                                        </button>
                                    </div>
                                    <p class="text-muted small mb-3">
                                        Add the return flight legs of this journey. Origin, destination and departure/arrival times are filled automatically from the first and last segment.
                                    </p>
                                    <div id="editReturnFlightLegsContainer">
                                        <!-- Return Leg 1 -->
                                        <div class="flight-leg-row border rounded p-3 mb-3" data-leg="1">
                                            <div class="d-flex align-items-center justify-content-between mb-2">
                                                <h6 class="mb-0"><span class="leg-number">Leg 1</span></h6>
                                                <button type="button" class="btn btn-sm btn-outline-danger remove-leg-btn" disabled
                                                        title="<?= __('first_leg_required') ?? 'First leg cannot be removed' ?>">
                                                    <i class="feather icon-trash-2"></i>
                                                </button>
                                            </div>
                                            <div class="form-row">
                                                <div class="form-group col-md-3 mb-2">
                                                    <label class="leg-label">From *</label>
                                                    <input type="text" class="form-control leg-origin" placeholder="e.g., DXB">
                                                </div>
                                                <div class="form-group col-md-3 mb-2">
                                                    <label class="leg-label">To *</label>
                                                    <input type="text" class="form-control leg-destination" placeholder="e.g., KBL">
                                                </div>
                                                <div class="form-group col-md-3 mb-2">
                                                    <label class="leg-label">Airline</label>
                                                    <input type="text" class="form-control leg-airline" placeholder="e.g., FlyDubai">
                                                </div>
                                                <div class="form-group col-md-3 mb-2">
                                                    <label class="leg-label">Flight Number</label>
                                                    <input type="text" class="form-control leg-flight-number" placeholder="e.g., FZ302">
                                                </div>
                                            </div>
                                            <div class="form-row">
                                                <div class="form-group col-md-3 mb-2">
                                                    <label class="leg-label">Departure Date</label>
                                                    <input type="date" class="form-control leg-date">
                                                </div>
                                                <div class="form-group col-md-3 mb-2">
                                                    <label class="leg-label">Departure Time</label>
                                                    <input type="time" class="form-control leg-time">
                                                </div>
                                                <div class="form-group col-md-3 mb-2">
                                                    <label class="leg-label">Arrival Date</label>
                                                    <input type="date" class="form-control leg-arrival-date">
                                                </div>
                                                <div class="form-group col-md-3 mb-2">
                                                    <label class="leg-label">Arrival Time</label>
                                                    <input type="time" class="form-control leg-arrival-time">
                                                </div>
                                            </div>
                                            <div class="form-row">
                                                <div class="form-group col-md-4 mb-2">
                                                    <label class="leg-label">Duration</label>
                                                    <input type="text" class="form-control leg-duration" placeholder="e.g., 2h 30m" readonly>
                                                </div>
                                                <div class="form-group col-md-4 mb-2 leg-stopover-wrap" style="display: none;">
                                                    <label class="leg-label">Stopover</label>
                                                    <input type="text" class="form-control leg-stopover" placeholder="e.g., 3h 25m">
                                                </div>
                                            </div>
                                            <small class="form-text text-muted">Origin/Destination auto-filled from segments</small>
                                        </div>
                                    </div>
                                    <div id="editReturnFlightRoutePreview" class="form-text text-info mb-2" style="font-size:12px;"></div>
                                    <div id="editReturnFlightStops" class="form-text text-muted" style="font-size:12px; display:none;"></div>
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