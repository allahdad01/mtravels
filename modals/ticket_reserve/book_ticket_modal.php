
                                <!-- Book Ticket Modal -->
                                <div class="modal fade" id="bookTicketModal" tabindex="-1" role="dialog">
                                    <div class="modal-dialog modal-lg" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5><?= __('reserve_ticket') ?></h5>
                                                <button type="button" class="close" data-dismiss="modal">&times;</button>
                                            </div>
                                            <form id="bookTicketForm">
                                                <!-- CSRF Protection -->
                                                <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                                                
                                                <div class="modal-body">
                                                    <div class="form-row">
                                                        <div class="form-group col-md-3">
                                                            <label for="supplier"><?= __('supplier') ?></label>
                                                            <select class="form-control selectpicker" id="supplier" name="supplier" required 
                                                                data-live-search="true" data-style="btn-light">
                                                                <option value=""><?= __('select_supplier') ?></option>
                                                                <?php foreach ($suppliers as $supplier): ?>
                                                                <option value="<?= $supplier['id'] ?>"><?= $supplier['name'] ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                         
                                                        <div class="form-group col-md-3">
                                                            <label for="soldTo"><?= __('sold_to') ?></label>
                                                            <select class="form-control selectpicker" id="soldTo" name="soldTo" required 
                                                                data-live-search="true" data-style="btn-light">
                                                                <option value=""><?= __('select_client') ?></option>
                                                                <?php 
                                                                if ($conn->connect_error) {
                                                                    echo "<option value=''>Database connection failed</option>";
                                                                } else {
                                                                    $result = $conn->query("SELECT id, name, usd_balance, afs_balance FROM clients where status = 'active' AND tenant_id = $tenant_id AND branch_id = $branch_id");
                                                                    while ($row = $result->fetch_assoc()) {
                                                                        echo "<option value='{$row['id']}'>
                                                                                {$row['name']}
                                                                              </option>";
                                                                    }
                                                                }
                                                                ?>
                                                            </select>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="tripType"><?= __('trip_type') ?></label>
                                                            <select class="form-control selectpicker" id="tripType" name="tripType" required 
                                                                data-live-search="true" data-style="btn-light">
                                                                <option value="one_way"><?= __('one_way') ?></option>
                                                                <option value="round_trip"><?= __('round_trip') ?></option>
                                                            </select>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="title"><?= __('title') ?></label>
                                                            <select class="form-control" id="title" name="title" required>
                                                                <option value="Mr"><?= __('mr') ?></option>
                                                                <option value="Mrs"><?= __('mrs') ?></option>
                                                                <option value="Child"><?= __('child') ?></option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="form-row">
                                                        <div class="form-group col-md-3">
                                                            <label for="gender"><?= __('gender') ?></label>
                                                            <select class="form-control" id="gender" name="gender" required>
                                                                <option value="Male"><?= __('male') ?></option>
                                                                <option value="Female"><?= __('female') ?></option>
                                                            </select>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="passengerName"><?= __('passenger_name') ?></label>
                                                            <input type="text" class="form-control" id="passengerName" name="passengerName" required>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="pnr"><?= __('pnr') ?></label>
                                                            <input type="text" class="form-control" id="pnr" name="pnr" required>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="phone"><?= __('phone') ?></label>
                                                            <input type="text" class="form-control" id="phone" name="phone" required>
                                                        </div>
                                                    </div>

                                                    <div class="form-row">
                                                        <div class="form-group col-md-3">
                                                            <label for="origin"><?= __('from') ?></label>
                                                            <input type="text" class="form-control" id="origin" name="origin" required>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="destination"><?= __('to') ?></label>
                                                            <input type="text" class="form-control" id="destination" name="destination" required>
                                                        </div>
                                                        <div id="returnJourneyFields" class="form-group col-md-3" style="display: none;">
                                                            <label for="returnDestination"><?= __('return_to') ?></label>
                                                            <input type="text" class="form-control" id="returnDestination" name="returnDestination">
                                                        </div>
                                                        
                                                            <div class="form-group col-md-3">
                                                                <label for="airline">
                                                                    <i class="feather icon-plane mr-1"></i><?= __('airline') ?>
                                                                </label>
                                                                <select class="form-control selectpicker" id="airline" name="airline" required 
                                                                    data-live-search="true" data-style="btn-light">
                                                                    <!-- Airlines will be populated by JavaScript -->
                                                                </select>
                                                            </div>
                                                        </div>

                                                    <div class="form-row">
                                                        <div class="form-group col-md-3">
                                                            <label for="issueDate"><?= __('issue_date') ?></label>
                                                            <input type="date" class="form-control" id="issueDate" name="issueDate" required>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="departureDate"><?= __('departure_date') ?></label>
                                                            <input type="date" class="form-control" id="departureDate" name="departureDate" required>
                                                        </div>
                                                        <div id="returnDateField" class="form-group col-md-3" style="display: none;">
                                                            <label for="returnDate"><?= __('return_date') ?></label>
                                                            <input type="date" class="form-control" id="returnDate" name="returnDate">
                                                        </div>
                                                        <div class="form-group col-md-3" id="baseFieldContainer">
                                                            <label for="base"><?= __('base') ?></label>
                                                            <input type="number" class="form-control" id="base" name="base" step="any" required>
                                                        </div>
                                                    </div>

                                                    <div class="form-row">
                                                        <div class="form-group col-md-3">
                                                            <label for="sold"><?= __('sold') ?></label>
                                                            <input type="number" class="form-control" id="sold" name="sold" step="any" required>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="profit"><?= __('profit') ?></label>
                                                            <input type="number" class="form-control" id="pro" name="pro" step="any" required readonly>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="curr"><?= __('currency') ?></label>
                                                            <input class="form-control" id="curr" name="curr" required readonly>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="description"><?= __('description') ?></label>
                                                            <input type="text" class="form-control" id="description" name="description" required>
                                                        </div>
                                                    </div>
                                                    
                                                                                   
                                                    <div class="form-row">
                                                        <div class="form-group col-md-4">
                                                            <label for="paidTo"><?= __('paid_to') ?></label>
                                                            <select class="form-control" id="paidTo" name="paidTo" required>
                                                                <option value=""><?= __('select_main_account') ?></option>
                                                                <?php 
                                                                if ($conn->connect_error) {
                                                                    echo "<option value=''>Database connection failed</option>";
                                                                } else {
                                                                    $result = $conn->query("SELECT id, name, usd_balance, afs_balance FROM main_account where status = 'active' AND tenant_id = $tenant_id AND branch_id = $branch_id");
                                                                    while ($row = $result->fetch_assoc()) {
                                                                        echo "<option value='{$row['id']}'>
                                                                                {$row['name']}
                                                                              </option>";
                                                                    }
                                                                }
                                                                ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
                                                    <button type="submit" class="btn btn-primary"><?= __('book') ?></button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>   