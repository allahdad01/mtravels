                                       <!-- Add Visa Modal -->
                                       <div class="modal fade" id="addVisaModal" tabindex="-1" role="dialog">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5><?= __('add_visa') ?></h5>
                                                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                                                    </div>
                                                    <form id="addVisaForm">
                                                         <!-- CSRF Protection -->
                                                         <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                                                         
                                                         <div class="modal-body">
                                                            <div class="row">
                                                                <div class="col-md-12">
                                                                    <h5 class="mb-4 text-primary">
                                                                        <i class="feather icon-file-text mr-2"></i><?= __('visa_application_details') ?>
                                                                    </h5>
                                                                </div>
                                                            </div>

                                                            <div class="row">
                                                                <div class="col-md-5">
                                                                    <div class="card mb-4 border-primary">
                                                                        <div class="card-header bg-primary text-white">
                                                                            <?= __('supplier_and_client_info') ?>
                                                                        </div>
                                                                        <div class="card-body">
                                                                            <div class="form-group">
                                                                                <label for="supplier"><?= __('supplier') ?></label>
                                                                                <select class="form-control bootstrap-select" id="supplier" name="supplier" required>
                                                                                    <option value=""><?= __('select_supplier') ?></option>
                                                                                    <?php foreach ($suppliers as $supplier): ?>
                                                                                    <option value="<?= $supplier['id'] ?>"><?= $supplier['name'] ?></option>
                                                                                    <?php endforeach; ?>
                                                                                </select>
                                                                            </div>
                                                                            <div class="form-group">
                                                                                <label for="soldto"><?= __('sold_to') ?></label>
                                                                                <select class="form-control bootstrap-select" id="soldTo" name="soldto" required>
                                                                                    <option value=""><?= __('select_client') ?></option>
                                                                                    <?php foreach ($clients as $client): ?>
                                                                                    <option value="<?= $client['id'] ?>"><?= $client['name'] ?></option>
                                                                                    <?php endforeach; ?>
                                                                                </select>
                                                                            </div>
                                                                            <div class="form-group">
                                                                                    <div class="form-group">
                                                                                        <label for="paidto"><?= __('paid_via') ?></label>
                                                                                        <select class="form-control" id="paidto" name="paidto" required>
                                                                                            <option value=""><?= __('select_main_account') ?></option>
                                                                                            <?php foreach ($internal as $int): ?>
                                                                                            <option value="<?= $int['id'] ?>"><?= $int['name'] ?></option>
                                                                                            <?php endforeach; ?>
                                                                                        </select>
                                                                                    </div>
                                                                                </div>
                                                                            <div class="form-group">
                                                                                <label for="phone"><?= __('phone') ?></label>
                                                                                <input type="text" class="form-control" id="phone" name="phone" required>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <div class="col-md-7">
                                                                    <div class="card mb-4 border-info">
                                                                        <div class="card-header bg-info text-white">
                                                                            <?= __('applicant_details') ?>
                                                                        </div>
                                                                        <div class="card-body">
                                                                            <div class="row">
                                                                                <div class="col-md-6">
                                                                                    <div class="form-group">
                                                                                        <label for="title"><?= __('title') ?></label>
                                                                                        <select class="form-control" id="title" name="title" required>
                                                                                            <option value="Mr">Mr</option>
                                                                                            <option value="Mrs">Mrs</option>
                                                                                            <option value="Child">Child</option>
                                                                                        </select>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-6">
                                                                                    <div class="form-group">
                                                                                        <label for="gender"><?= __('gender') ?></label>
                                                                                        <select class="form-control" id="gender" name="gender" required>
                                                                                            <option value="Male"><?= __('male') ?></option>
                                                                                            <option value="Female"><?= __('female') ?></option>
                                                                                        </select>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-6">
                                                                                    <div class="form-group">
                                                                                        <label for="passengerName"><?= __('passenger_name') ?></label>
                                                                                        <input type="text" class="form-control" id="passengerName" name="passengerName" required>
                                                                                    </div>
                                                                                </div>
                                                                            
                                                                            
                                                                                <div class="col-md-6">
                                                                                    <div class="form-group">
                                                                                        <label for="passNum"><?= __('passport_number') ?></label>
                                                                                        <input type="text" class="form-control" id="passNum" name="passNum" required>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-6">
                                                                                    <div class="form-group">
                                                                                        <label for="country"><?= __('country') ?></label>
                                                                                        <select class="form-control" id="country" name="country" required>
                                                                                            <option value=""><?= __('select_country') ?></option>
                                                                                            <option value="Pakistan">Pakistan</option>
                                                                                            <option value="India">India</option>
                                                                                            <option value="Turkey">Turkey</option>
                                                                                            <option value="Iran">Iran</option>
                                                                                            <option value="Saudi Arabia">Saudi Arabia</option>
                                                                                            <option value="United Arab Emirates">United Arab Emirates</option>
                                                                                            <option value="Uzbekistan">Uzbekistan</option>
                                                                                            <option value="Kazakhstan">Kazakhstan</option>
                                                                                            <option value="Qatar">Qatar</option>
                                                                                            <option value="Kuwait">Kuwait</option>
                                                                                        </select>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-6">
                                                                                    <div class="form-group">
                                                                                        <label for="visaType"><?= __('visa_type') ?></label>
                                                                                        <select class="form-control" id="visaType" name="visaType" required>
                                                                                            <option value=""><?= __('select_visa_type') ?></option>
                                                                                            <option value="Tourist">Tourist</option>
                                                                                            <option value="Business">Business</option>
                                                                                            <option value="Work">Work</option>
                                                                                            <option value="Study">Study</option>
                                                                                            <option value="Family">Family</option>
                                                                                            <option value="Medical">Medical</option>
                                                                                            <option value="Religious">Religious</option>
                                                                                            <option value="Transit">Transit</option>
                                                                                            <option value="Diplomatic">Diplomatic</option>
                                                                                        </select>
                                                                                    </div>
                                                                                </div>
                                                                             </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="row">
                                                                <div class="col-md-5">
                                                                    <div class="card mb-4 border-success">
                                                                        <div class="card-header bg-success text-white">
                                                                            <?= __('dates') ?>
                                                                        </div>
                                                                        <div class="card-body">
                                                                            <div class="row">
                                                                                <div class="col-md-12">
                                                                                    <div class="form-group">
                                                                                        <label for="receiveDate"><?= __('received_date') ?></label>
                                                                                        <input type="date" class="form-control" id="receiveDate" name="receiveDate" required>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-12">
                                                                                    <div class="form-group">
                                                                                        <label for="appliedDate"><?= __('applied_date') ?></label>
                                                                                        <input type="date" class="form-control" id="appliedDate" name="appliedDate" required>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-12">
                                                                                    <div class="form-group">
                                                                                        <label for="issuedDate"><?= __('issued_date') ?></label>
                                                                                        <input type="date" class="form-control" id="issuedDate" name="issuedDate">
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <div class="col-md-7">
                                                                    <div class="card mb-4 border-warning">
                                                                        <div class="card-header bg-warning text-white">
                                                                            <?= __('financial_details') ?>
                                                                        </div>
                                                                        <div class="card-body">
                                                                            <div class="row">
                                                                                <div class="col-md-6">
                                                                                    <div class="form-group">
                                                                                        <label for="base"><?= __('base') ?></label>
                                                                                        <input type="number" step="0.01" class="form-control" id="base" name="base" required>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-6">
                                                                                    <div class="form-group">
                                                                                        <label for="sold"><?= __('sold') ?></label>
                                                                                        <input type="number" step="0.01" class="form-control" id="sold" name="sold" required>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-12">
                                                                                    <div class="form-group">
                                                                                        <label for="pro"><?= __('profit') ?></label>
                                                                                        <input type="number" step="0.01" class="form-control" id="pro" name="pro" required readonly>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="row">
                                                                               
                                                                                <div class="col-md-6">
                                                                                    <div class="form-group">
                                                                                        <label for="curr"><?= __('currency') ?></label>
                                                                                        <input type="text" class="form-control" id="curr" name="curr" required readonly>
                                                                                    </div>
                                                                                </div>
                                                                                
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="row">
                                                                <div class="col-md-12">
                                                                    <div class="form-group">
                                                                        <label for="description"><?= __('description') ?></label>
                                                                        <input type="text" class="form-control" id="description" name="description" required>
                                                                    </div>
                                                                </div>
                                                              </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
                                                            <button type="submit" class="btn btn-primary" data-no-protection><?= __('add_visa') ?></button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>