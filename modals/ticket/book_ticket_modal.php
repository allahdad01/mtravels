    <!-- Book Ticket Modal -->
    <div class="modal fade" id="bookTicketModal" tabindex="-1" role="dialog" data-backdrop="static">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="feather icon-plus-circle mr-2"></i><?= __('book_a_ticket') ?>
                    </h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <form id="bookTicketForm" enctype="multipart/form-data">
                    <!-- CSRF Protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                    <input type="file" id="ticketPdfFile" accept=".pdf" style="display:none;" />
                    
                    <div class="modal-body" style="overflow-y: auto; overflow-x: hidden;">
                         <!-- PDF Upload Section (Auto-Fill Feature) -->
                         <div class="card mb-3" style="background: linear-gradient(135deg, #f0f9ff 0%, #f5f3ff 100%); border: none;">
                             <div class="card-header" style="background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%); color: white; border-radius: 8px 8px 0 0;">
                                 <div class="d-flex align-items-center justify-content-between">
                                     <h6 class="mb-0">
                                         <i class="feather icon-upload-cloud mr-2"></i><?= __('quick_ticket_import') ?>
                                     </h6>
                                     <small style="opacity: 0.9;"><?= __('saves_90_percent_data_entry') ?></small>
                                 </div>
                             </div>
                             <div class="card-body" style="padding: 15px;">
                                 <div id="pdfUploadZone" style="min-height: 120px; display: flex; align-items: center; justify-content: center;">
                                     <div style="text-align: center; padding: 40px 20px;">
                                         <i class="feather icon-upload-cloud" style="font-size: 48px; color: #999; margin-bottom: 15px;"></i>
                                         <h6><?= __('drop_airline_ticket_here') ?></h6>
                                         <p style="color: #999; margin: 10px 0;"><?= __('or_click_to_browse') ?></p>
                                         <small style="color: #ccc;">
                                             <?= __('supports') ?>: TBO, Sirena, Amadeus, FlyDubai, <?= __('and_all_airlines') ?> (Max 10MB)
                                         </small>
                                     </div>
                                 </div>
                                 <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">
                                     <small style="color: #666;">
                                         <i class="feather icon-info" style="color: #f59e0b;"></i>
                                         <?= __('auto_fill_notice') ?>
                                     </small>
                                 </div>
                             </div>
                         </div>

                         <!-- Client and Trip Information -->
                         <div class="card" style="position: relative; overflow: visible !important; z-index: 100;">
                             <div class="card-header bg-light">
                                 <h6 class="mb-0"><?= __('booking_details') ?></h6>
                             </div>
                             <div class="card-body" style="overflow: visible !important; padding-bottom: 400px;">
                                  <div class="form-row">
                                     <div class="form-group col-md-6">
                                         <label for="supplier">
                                             <i class="feather icon-user mr-1"></i><?= __('supplier') ?>
                                         </label>
                                         <select class="form-control selectpicker" id="supplier" name="supplier" required 
                                                 data-live-search="true" data-style="btn-light">
                                             <option value=""><?= __('select_supplier') ?></option>
                                             <?php foreach ($suppliers as $supplier): ?>
                                                 <option value="<?= $supplier['id'] ?>" data-tokens="<?= $supplier['name'] ?>"><?= $supplier['name'] ?></option>
                                             <?php endforeach; ?>
                                         </select>
                                     </div>
                                     
                                     <div class="form-group col-md-6">
                                         <label for="soldTo">
                                             <i class="feather icon-users mr-1"></i><?= __('sold_to') ?>
                                         </label>
                                         <select class="form-control selectpicker" id="soldTo" name="soldTo" required 
                                                 data-live-search="true" data-style="btn-light">
                                             <option value=""><?= __('select_client') ?></option>
                                             <?php
                                             $stmt = $pdo->prepare("SELECT id, name, usd_balance, afs_balance FROM clients WHERE status = 'active' AND tenant_id = ? AND branch_id = ?");
                                             $stmt->execute([$tenant_id, $branch_id]);
                                             $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                             foreach ($clients as $row) {
                                                 echo "<option value='{$row['id']}' data-tokens='{$row['name']}'>{$row['name']}</option>";
                                             }
                                             ?>
                                         </select>
                                     </div>
                                 </div>

                                <div class="form-row">
                                    <div class="col-md-12">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="adultCount">
                                                        <i class="feather icon-user mr-1"></i><?= __('adults') ?> (12+ <?= __('years') ?>)
                                                    </label>
                                                    <select class="form-control select2 passenger-count" id="adultCount" name="adultCount" required>
                                                        <?php for($i = 1; $i <= 50; $i++): ?>
                                                            <option value="<?= $i ?>"><?= $i ?></option>
                                                        <?php endfor; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="childCount">
                                                        <i class="feather icon-user mr-1"></i><?= __('children') ?> (2-11 <?= __('years') ?>)
                                                    </label>
                                                    <select class="form-control select2 passenger-count" id="childCount" name="childCount">
                                                        <?php for($i = 0; $i <= 20; $i++): ?>
                                                            <option value="<?= $i ?>"><?= $i ?></option>
                                                        <?php endfor; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="infantCount">
                                                        <i class="feather icon-user mr-1"></i><?= __('infants') ?> (0-2 <?= __('years') ?>)
                                                    </label>
                                                    <select class="form-control select2 passenger-count" id="infantCount" name="infantCount">
                                                        <?php for($i = 0; $i <= 10; $i++): ?>
                                                            <option value="<?= $i ?>"><?= $i ?></option>
                                                        <?php endfor; ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Passenger Information Section -->
                         <div class="card">
                             <div class="card-header bg-light">
                                 <h6 class="mb-0"><?= __('passenger_information') ?></h6>
                             </div>
                             <div class="card-body" id="passengersContainer">
                                 <!-- Passenger details will be dynamically added here -->
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
                                        <label for="issueDate">
                                            <i class="feather icon-calendar mr-1"></i><?= __('issue_date') ?>
                                        </label>
                                        <input type="date" class="form-control" id="issueDate" name="issueDate" required>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="pnr">
                                            <i class="feather icon-hash mr-1"></i><?= __('pnr') ?>
                                        </label>
                                        <input type="text" class="form-control" id="pnr" name="pnr" placeholder="e.g., ABC123" required>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="tripType">
                                            <i class="feather icon-repeat mr-1"></i><?= __('trip_type') ?>
                                        </label>
                                        <select class="form-control selectpicker" id="tripType" name="tripType" required
                                                data-style="btn-light">
                                            <option value="one_way"><?= __('one_way') ?></option>
                                            <option value="round_trip"><?= __('round_trip') ?></option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="phone">
                                            <i class="feather icon-phone mr-1"></i><?= __('phone_number') ?>
                                        </label>
                                        <input type="text" class="form-control" id="phone" name="phone" required>
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
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="addFlightLegBtn">
                                        <i class="feather icon-plus-circle mr-1"></i><?= __('add_leg') ?? 'Add Leg' ?>
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <p class="text-muted small mb-3">
                                    <?= __('journey_segments_hint') ?? 'Add the flight legs of this journey. Origin, destination and departure/arrival times are filled automatically from the first and last segment.' ?>
                                </p>
                                <div id="flightLegsContainer">
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
                                                <input type="text" class="form-control leg-origin" id="origin" name="origin" placeholder="e.g., KBL" required>
                                            </div>
                                            <div class="form-group col-md-3 mb-2">
                                                <label class="leg-label">To *</label>
                                                <input type="text" class="form-control leg-destination" id="destination" name="destination" placeholder="e.g., DXB" required>
                                            </div>
                                            <div class="form-group col-md-3 mb-2">
                                                <label class="leg-label">Airline</label>
                                                <input type="text" class="form-control leg-airline" id="airline" name="airline" placeholder="e.g., FlyDubai">
                                            </div>
                                            <div class="form-group col-md-3 mb-2">
                                                <label class="leg-label">Flight Number</label>
                                                <input type="text" class="form-control leg-flight-number" id="flightNumber" placeholder="e.g., FZ302">
                                            </div>
                                        </div>
                                        <div class="form-row">
                                            <div class="form-group col-md-3 mb-2">
                                                <label class="leg-label">Departure Date</label>
                                                <input type="date" class="form-control leg-date" id="departureDate" name="departureDate" required>
                                            </div>
                                            <div class="form-group col-md-3 mb-2">
                                                <label class="leg-label">Departure Time</label>
                                                <input type="time" class="form-control leg-time" id="departureTime" name="departureTime" required>
                                            </div>
                                            <div class="form-group col-md-3 mb-2">
                                                <label class="leg-label">Arrival Date</label>
                                                <input type="date" class="form-control leg-arrival-date" id="arrivalDate">
                                            </div>
                                            <div class="form-group col-md-3 mb-2">
                                                <label class="leg-label">Arrival Time</label>
                                                <input type="time" class="form-control leg-arrival-time" id="arrivalTime">
                                            </div>
                                        </div>
                                        <div class="form-row">
                                            <div class="form-group col-md-4 mb-2">
                                                <label class="leg-label">Duration</label>
                                                <input type="text" class="form-control leg-duration" id="duration" placeholder="e.g., 2h 30m" readonly>
                                            </div>
                                            <div class="form-group col-md-4 mb-2 leg-stopover-wrap" style="display: none;">
                                                <label class="leg-label">Stopover</label>
                                                <input type="text" class="form-control leg-stopover" id="stopover" placeholder="e.g., 3h 25m">
                                            </div>
                                        </div>
                                        <small class="form-text text-muted">Origin/Destination auto-filled from segments</small>
                                    </div>
                                </div>
                                <div id="flightRoutePreview" class="form-text text-info mb-2" style="font-size:12px;"></div>
                                <div id="flightStops" class="form-text text-muted" style="font-size:12px; display:none;"></div>

                                <!-- Return Flight Segments (round trip) -->
                                <div id="returnFlightSegmentsGroup" style="display: none;">
                                    <hr class="my-3">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <h6 class="mb-0">
                                            <i class="feather icon-corner-up-left mr-1"></i>Return Flight Segments
                                        </h6>
                                        <button type="button" class="btn btn-sm btn-outline-primary" id="addReturnFlightLegBtn">
                                            <i class="feather icon-plus-circle mr-1"></i><?= __('add_leg') ?? 'Add Leg' ?>
                                        </button>
                                    </div>
                                    <p class="text-muted small mb-3">
                                        Add the return flight legs of this journey. Origin, destination and departure/arrival times are filled automatically from the first and last segment.
                                    </p>
                                    <div id="returnFlightLegsContainer">
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
                                    <div id="returnFlightRoutePreview" class="form-text text-info mb-2" style="font-size:12px;"></div>
                                    <div id="returnFlightStops" class="form-text text-muted" style="font-size:12px; display:none;"></div>
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
                                        <label for="curr">
                                            <i class="feather icon-dollar-sign mr-1"></i><?= __('currency') ?>
                                        </label>
                                        <input class="form-control" id="curr" name="curr" required readonly>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="paidTo">
                                            <i class="feather icon-credit-card mr-1"></i><?= __('paid_to') ?>
                                        </label>
                                        <select class="form-control select2" id="paidTo" name="paidTo" required>
                                            <option value=""><?= __('select_main_account') ?></option>
                                            <?php
                                            $stmt = $pdo->prepare("SELECT id, name, usd_balance, afs_balance FROM main_account WHERE status = 'active' AND tenant_id = ? AND branch_id = ?");
                                            $stmt->execute([$tenant_id, $branch_id]);
                                            $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                            foreach ($accounts as $row) {
                                                echo "<option value='{$row['id']}'>{$row['name']}</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            
                                <!-- Payment Totals Section -->
                                <div class="form-row">
                                    <div class="form-group col-md-3">
                                        <label for="base">
                                            <i class="feather icon-dollar-sign mr-1"></i><?= __('base') ?>
                                        </label>
                                        <input type="number" class="form-control" id="base" name="base" step="any" readonly>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="sold">
                                            <i class="feather icon-dollar-sign mr-1"></i><?= __('sold') ?>
                                        </label>
                                        <input type="number" class="form-control" id="sold" name="sold" step="any" readonly>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="discount">
                                            <i class="feather icon-minus-circle mr-1"></i><?= __('discount') ?>
                                        </label>
                                        <input type="number" class="form-control" id="discount" name="discount" value="0" step="any" readonly>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="pro">
                                            <i class="feather icon-plus-circle mr-1"></i><?= __('profit') ?>
                                        </label>
                                        <input type="number" class="form-control" id="pro" name="pro" readonly>
                                    </div>
                                </div>
                            
                                <div class="form-group">
                                    <label for="description">
                                        <i class="feather icon-file-text mr-1"></i><?= __('description') ?>
                                    </label>
                                    <textarea class="form-control" id="description" name="description" rows="3" placeholder="<?= __('enter_description') ?>"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="feather icon-x mr-2"></i><?= __('close') ?>
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-check mr-2"></i><?= __('book') ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>