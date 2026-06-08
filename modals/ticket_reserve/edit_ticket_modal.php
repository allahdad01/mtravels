
                                <!-- Edit ticket tab -->
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
                                                    <div class="modal-body">
                                                        <input type="hidden" id="editTicketId" name="id">
                                                       <div class="form-row">
                                                        <div class="form-group col-md-3">
                                                            <label for="supplier"><?= __('supplier') ?></label>
                                                            <select class="form-control" id="editSupplier" name="supplier" required readonly>
                                                                <option value=""><?= __('select_supplier') ?></option>
                                                                <?php foreach ($suppliers as $supplier): ?>
                                                                <option value="<?= $supplier['id'] ?>"><?= $supplier['name'] ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="editSoldTo"><?= __('sold_to') ?></label>
                                                            <select class="form-control" id="editSoldTo" name="soldTo" required readonly>
                                                                <option value=""><?= __('select_client') ?></option>
                                                                <?php
                                                                $clientStmt = $pdo->prepare("SELECT id, name, usd_balance, afs_balance FROM clients WHERE status = 'active' AND tenant_id = ? AND branch_id = ?");
                                                                $clientStmt->execute([$tenant_id, $branch_id]);
                                                                $clients = $clientStmt->fetchAll(PDO::FETCH_ASSOC);
                                                                foreach ($clients as $row) {
                                                                    echo "<option value='{$row['id']}'>
                                                                            {$row['name']}
                                                                          </option>";
                                                                }
                                                                ?>
                                                            </select>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="editTripType"><?= __('trip_type') ?></label>
                                                            <select class="form-control" id="editTripType" name="tripType" required>
                                                                <option value="one_way"><?= __('one_way') ?></option>
                                                                <option value="round_trip"><?= __('round_trip') ?></option>
                                                            </select>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="editTitle"><?= __('title') ?></label>
                                                            <select class="form-control" id="editTitle" name="title" required>
                                                                <option value="Mr"><?= __('mr') ?></option>
                                                                <option value="Mrs"><?= __('mrs') ?></option>
                                                                <option value="Child"><?= __('child') ?></option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="form-row">
                                                        <div class="form-group col-md-3">
                                                            <label for="editGender"><?= __('gender') ?></label>
                                                            <select class="form-control" id="editGender" name="gender" required>
                                                                <option value="Male"><?= __('male') ?></option>
                                                                <option value="Female"><?= __('female') ?></option>
                                                            </select>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="editPassengerName"><?= __('passenger_name') ?></label>
                                                            <input type="text" class="form-control" id="editPassengerName" name="passengerName" required>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="editPhone"><?= __('phone') ?></label>
                                                            <input type="text" class="form-control" id="editPhone" name="phone" required>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="editPnr"><?= __('pnr') ?></label>
                                                            <input type="text" class="form-control" id="editPnr" name="pnr" required>
                                                        </div>
                                                    </div>

                                                    <div class="form-row">
                                                        <div class="form-group col-md-3">
                                                            <label for="editOrigin"><?= __('from') ?></label>
                                                            <input type="text" class="form-control" id="editOrigin" name="origin" required>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="editDestination"><?= __('to') ?></label>
                                                            <input type="text" class="form-control" id="editDestination" name="destination" required>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="editAirline"><?= __('airline') ?></label>
                                                            <select class="form-control selectpicker" id="editAirline" name="airline" required 
                                                                data-live-search="true" data-style="btn-light">
                                                                <!-- Airline options go here -->
                                                                
                                                            </select>
                                                        </div>
                                                        <div id="editReturnJourneyFields" style="display: none;">
                                                            <div class="form-group col-md-8">
                                                                <label for="editReturnDestination"><?= __('return_to') ?></label>
                                                                <input type="text" class="form-control" id="editReturnDestination" name="returnDestination">
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="form-row">
                                                        <div class="form-group col-md-3">
                                                            <label for="editIssueDate"><?= __('issue_date') ?></label>
                                                            <input type="date" class="form-control" id="editIssueDate" name="issueDate" required>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="editDepartureDate"><?= __('departure_date') ?></label>
                                                            <input type="date" class="form-control" id="editDepartureDate" name="departureDate" required>
                                                        </div>
                                                        <div id="editReturnDateField" class="form-group col-md-3" style="display: none;">
                                                            <label for="editReturnDate"><?= __('return_date') ?></label>
                                                            <input type="date" class="form-control" id="editReturnDate" name="returnDate">
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="editBase"><?= __('base') ?></label>
                                                            <input type="number" class="form-control" id="editBase" name="base" step="any" required>
                                                        </div>
                                                    </div>

                                                    <div class="form-row">
                                                        <div class="form-group col-md-3">
                                                            <label for="editSold"><?= __('sold') ?></label>
                                                            <input type="number" class="form-control" id="editSold" name="sold" step="any" required>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="editPro"><?= __('profit') ?></label>
                                                            <input type="number" class="form-control" id="editPro" name="pro" step="any" required readonly>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="editCurr"><?= __('currency') ?></label>
                                                            <input class="form-control" id="editCurr" name="curr" required readonly>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="editPaidTo"><?= __('paid_to') ?></label>
                                                            <select class="form-control" id="editPaidTo" name="paidTo" required readonly>
                                                                <option value=""><?= __('select_main_account') ?></option>
                                                                <?php
                                                                $accountStmt = $pdo->prepare("SELECT id, name, usd_balance, afs_balance FROM main_account WHERE status = 'active' AND tenant_id = ? AND branch_id = ?");
                                                                $accountStmt->execute([$tenant_id, $branch_id]);
                                                                $accounts = $accountStmt->fetchAll(PDO::FETCH_ASSOC);
                                                                foreach ($accounts as $row) {
                                                                    echo "<option value='{$row['id']}'>
                                                                            {$row['name']}
                                                                          </option>";
                                                                }
                                                                ?>
                                                            </select>
                                                        </div>
                                                    </div>

                                                    <div class="form-row">
                                                        <div class="form-group col-md-12">
                                                            <label for="editDescription"><?= __('description') ?></label>
                                                            <input type="text" class="form-control" id="editDescription" name="description" required>
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