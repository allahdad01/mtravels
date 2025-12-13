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
                                                                                        <select class="form-control bootstrap-select" id="paidto" name="paidto" required>
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
                                                                                        <select class="form-control bootstrap-select" id="country" name="country" required>
                                                                                            <option value=""><?= __('select_country') ?></option>
                                                                                            <?php foreach ($all_countries as $country): ?>
                                                                                            <option value="<?= $country ?>"><?= $country ?></option>
                                                                                            <?php endforeach; ?>
                                                                                        </select>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-6">
                                                                                    <div class="form-group">
                                                                                        <label for="visaType"><?= __('visa_type') ?></label>
                                                                                        <select class="form-control bootstrap-select" id="visaType" name="visaType" required>
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