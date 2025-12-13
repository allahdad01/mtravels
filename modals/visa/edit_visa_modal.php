<?php
// List of all countries
$all_countries = [
    "Afghanistan", "Albania", "Algeria", "Andorra", "Angola", "Antigua and Barbuda", "Argentina", "Armenia", "Australia", "Austria",
    "Azerbaijan", "Bahamas", "Bahrain", "Bangladesh", "Barbados", "Belarus", "Belgium", "Belize", "Benin", "Bhutan",
    "Bolivia", "Bosnia and Herzegovina", "Botswana", "Brazil", "Brunei", "Bulgaria", "Burkina Faso", "Burundi", "Cabo Verde", "Cambodia",
    "Cameroon", "Canada", "Central African Republic", "Chad", "Chile", "China", "Colombia", "Comoros", "Congo", "Costa Rica",
    "Croatia", "Cuba", "Cyprus", "Czech Republic", "Denmark", "Djibouti", "Dominica", "Dominican Republic", "Ecuador", "Egypt",
    "El Salvador", "Equatorial Guinea", "Eritrea", "Estonia", "Eswatini", "Ethiopia", "Fiji", "Finland", "France", "Gabon",
    "Gambia", "Georgia", "Germany", "Ghana", "Greece", "Grenada", "Guatemala", "Guinea", "Guinea-Bissau", "Guyana",
    "Haiti", "Holy See", "Honduras", "Hungary", "Iceland", "India", "Indonesia", "Iran", "Iraq", "Ireland",
    "Israel", "Italy", "Jamaica", "Japan", "Jordan", "Kazakhstan", "Kenya", "Kiribati", "Kuwait", "Kyrgyzstan",
    "Laos", "Latvia", "Lebanon", "Lesotho", "Liberia", "Libya", "Liechtenstein", "Lithuania", "Luxembourg", "Madagascar",
    "Malawi", "Malaysia", "Maldives", "Mali", "Malta", "Marshall Islands", "Mauritania", "Mauritius", "Mexico", "Micronesia",
    "Moldova", "Monaco", "Mongolia", "Montenegro", "Morocco", "Mozambique", "Myanmar", "Namibia", "Nauru", "Nepal",
    "Netherlands", "New Zealand", "Nicaragua", "Niger", "Nigeria", "North Korea", "North Macedonia", "Norway", "Oman", "Pakistan",
    "Palau", "Palestine", "Panama", "Papua New Guinea", "Paraguay", "Peru", "Philippines", "Poland", "Portugal", "Qatar",
    "Romania", "Russia", "Rwanda", "Saint Kitts and Nevis", "Saint Lucia", "Saint Vincent and the Grenadines", "Samoa", "San Marino", "Sao Tome and Principe", "Saudi Arabia",
    "Senegal", "Serbia", "Seychelles", "Sierra Leone", "Singapore", "Slovakia", "Slovenia", "Solomon Islands", "Somalia", "South Africa",
    "South Korea", "South Sudan", "Spain", "Sri Lanka", "Sudan", "Suriname", "Sweden", "Switzerland", "Syria", "Taiwan",
    "Tajikistan", "Tanzania", "Thailand", "Timor-Leste", "Togo", "Tonga", "Trinidad and Tobago", "Tunisia", "Turkey", "Turkmenistan",
    "Tuvalu", "Uganda", "Ukraine", "United Arab Emirates", "United Kingdom", "United States", "Uruguay", "Uzbekistan", "Vanuatu", "Vatican City",
    "Venezuela", "Vietnam", "Yemen", "Zambia", "Zimbabwe"
];

// List of all visa types
$all_visa_types = [
    "Tourist", "Business", "Work", "Study", "Family", "Medical", "Religious", "Transit", "Diplomatic", "Entry", "Exit", "Residence", "Visitor", "Student"
];
?>
                                       <!-- Edit Visa Modal -->
                                       <div class="modal fade" id="editVisaModal" tabindex="-1" role="dialog" aria-labelledby="editVisaModalLabel" aria-hidden="true">
                                            <div class="modal-dialog modal-lg" role="document">
                                                <form id="editVisaForm">
                    <!-- CSRF Protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                                                    <input type="hidden" id="editVisaId" name="visa_id">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="editVisaModalLabel"><?= __('edit_visa') ?></h5>
                                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                <span aria-hidden="true">&times;</span>
                                                            </button>
                                                        </div>
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
                                                                                <label for="editSupplier"><?= __('supplier') ?></label>
                                                                                <select class="form-control bootstrap-select" id="editSupplier" name="supplier" required>
                                                                                    <option value=""><?= __('select_supplier') ?></option>
                                                                                    <?php foreach ($suppliers as $supplier): ?>
                                                                                        <option value="<?= $supplier['id'] ?>"><?= htmlspecialchars($supplier['name']) ?></option>
                                                                                    <?php endforeach; ?>
                                                                                </select>
                                                                            </div>
                                                                            <div class="form-group">
                                                                                <label for="editSoldTo"><?= __('sold_to') ?></label>
                                                                                <select class="form-control bootstrap-select" id="editSoldTo" name="sold_to" required>
                                                                                    <option value=""><?= __('select_client') ?></option>
                                                                                    <?php foreach ($clients as $client): ?>
                                                                                        <option value="<?= $client['id'] ?>"><?= htmlspecialchars($client['name']) ?></option>
                                                                                    <?php endforeach; ?>
                                                                                </select>
                                                                            </div>
                                                                            <div class="form-group">
                                                                                <label for="editPhone"><?= __('phone') ?></label>
                                                                                <input type="text" class="form-control" id="editPhone" name="phone" required>
                                                                            </div>
                                                                            <div class="form-group">
                                                                                <label for="editPaidTo"><?= __('paid_via') ?></label>
                                                                                <select class="form-control bootstrap-select" id="editPaidTo" name="paid_to" required>
                                                                                    <?php 
                                                                                    // Fetch the current visa's paid_to value if available
                                                                                    $currentPaidTo = isset($visa['paid_to']) ? $visa['paid_to'] : null;
                                                                                    
                                                                                    foreach ($internal as $int): 
                                                                                        $selected = ($currentPaidTo == $int['id']) ? 'selected' : '';
                                                                                    ?>
                                                                                        <option value="<?= $int['id'] ?>" <?= $selected ?>><?= $int['name'] ?></option>
                                                                                    <?php endforeach; ?>
                                                                                </select>
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
                                                                                        <label for="editTitle"><?= __('title') ?></label>
                                                                                        <select class="form-control" id="editTitle" name="title" required>
                                                                                            <option value="Mr"><?= __('mr') ?></option>
                                                                                            <option value="Mrs"><?= __('mrs') ?></option>
                                                                                            <option value="Child"><?= __('child') ?></option>
                                                                                        </select>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-6">
                                                                                    <div class="form-group">
                                                                                        <label for="editGender"><?= __('gender') ?></label>
                                                                                        <select class="form-control" id="editGender" name="gender" required>
                                                                                            <option value="Male"><?= __('male') ?></option>
                                                                                            <option value="Female"><?= __('female') ?></option>
                                                                                        </select>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="row">
                                                                                <div class="col-md-6">
                                                                                    <div class="form-group">
                                                                                        <label for="editApplicantName"><?= __('applicant_name') ?></label>
                                                                                        <input type="text" class="form-control" id="editApplicantName" name="applicant_name" required>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-6">
                                                                                    <div class="form-group">
                                                                                        <label for="editPassportNumber"><?= __('passport_number') ?></label>
                                                                                        <input type="text" class="form-control" id="editPassportNumber" name="passport_number" required>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="row">
                                                                                <div class="col-md-6">
                                                                                    <div class="form-group">
                                                                                        <label for="editCountry"><?= __('country') ?></label>
                                                                                        <select class="form-control bootstrap-select" id="editCountry" name="country" required>
                                                                                            <option value=""><?= __('select_country') ?></option>
                                                                                            <?php foreach ($all_countries as $country): ?>
                                                                                            <option value="<?= $country ?>"><?= $country ?></option>
                                                                                            <?php endforeach; ?>
                                                                                        </select>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-6">
                                                                                    <div class="form-group">
                                                                                        <label for="editVisaType"><?= __('visa_type') ?></label>
                                                                                        <select class="form-control bootstrap-select" id="editVisaType" name="visa_type" required>
                                                                                            <option value=""><?= __('select_visa_type') ?></option>
                                                                                            <?php foreach ($all_visa_types as $visa_type): ?>
                                                                                            <option value="<?= $visa_type ?>"><?= $visa_type ?></option>
                                                                                            <?php endforeach; ?>
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
                                                                            <div class="form-group">
                                                                                <label for="editReceiveDate"><?= __('receive_date') ?></label>
                                                                                <input type="date" class="form-control" id="editReceiveDate" name="receive_date" required>
                                                                            </div>
                                                                            <div class="form-group">
                                                                                <label for="editAppliedDate"><?= __('applied_date') ?></label>
                                                                                <input type="date" class="form-control" id="editAppliedDate" name="applied_date" required>
                                                                            </div>
                                                                            <div class="form-group">
                                                                                <label for="editIssuedDate"><?= __('issued_date') ?></label>
                                                                                <input type="date" class="form-control" id="editIssuedDate" name="issued_date">
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
                                                                                        <label for="editBase"><?= __('base_price') ?></label>
                                                                                        <input type="number" class="form-control" id="editBase" name="base" step="0.01" required>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-6">
                                                                                    <div class="form-group">
                                                                                        <label for="editSold"><?= __('sold_price') ?></label>
                                                                                        <input type="number" class="form-control" id="editSold" name="sold" step="0.01" required>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-12">
                                                                                    <div class="form-group">
                                                                                        <label for="editPro"><?= __('profit') ?></label>
                                                                                        <input type="number" class="form-control" id="editPro" name="profit" step="0.01" readonly>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="row">
                                                                                
                                                                                <div class="col-md-6">
                                                                                    <div class="form-group">
                                                                                        <label for="editCurrency"><?= __('currency') ?></label>
                                                                                        <select class="form-control" id="editCurrency" name="currency" required>
                                                                                            <option value="USD"><?= __('usd') ?></option>
                                                                                            <option value="EUR"><?= __('eur') ?></option>
                                                                                            <option value="DARHAM"><?= __('darham') ?></option>
                                                                                            <option value="AFS"><?= __('afs') ?></option>
                                                                                        </select>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-12">
                                                                                    <div class="form-group">
                                                                                        <label for="editStatus"><?= __('status') ?></label>
                                                                                        <select class="form-control" id="editStatus" name="status">
                                                                                            <option value="Pending"><?= __('pending') ?></option>
                                                                                            <option value="Approved"><?= __('approved') ?></option>
                                                                                            <option value="Rejected"><?= __('rejected') ?></option>
                                                                                        </select>
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
                                                                        <label for="editRemarks"><?= __('remarks') ?></label>
                                                                        <input type="text" class="form-control" id="editRemarks" name="remarks">
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