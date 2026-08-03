<!-- Bootstrap Modal to Create a New Family -->
<div class="modal umrah-modal fade" id="createFamilyModal" tabindex="-1" aria-labelledby="createFamilyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createFamilyModalLabel"><?= __('create_new_family') ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="createFamilyForm" method="POST" onsubmit="return submitCreateFamilyForm();">
                        <!-- CSRF Protection -->
                        <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                        
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="head_of_family"><?= __('family_head') ?></label>
                                <input type="text" class="form-control" id="head_of_family" name="head_of_family" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="contact"><?= __('contact_number') ?></label>
                                <input type="text" class="form-control" id="contact" inputmode="tel" name="contact" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="address"><?= __('address') ?></label>
                                <input type="text" class="form-control" id="address" name="address" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="package_type"><?= __('package_type') ?></label>
                                <select class="form-control" id="package_type" name="package_type" required>
                                    <option value="Full Package"><?= __('full_package') ?></option>
                                    <option value="Visa"><?= __('visa') ?></option>
                                    <option value="Services"><?= __('services') ?></option>
                                    <option value="Ticket+Visa"><?= __('ticket_visa') ?></option>
                                    <option value="Visa+Services"><?= __('visa_services') ?></option>
                                    <option value="Visa+Transport"><?= __('visa_transport') ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="location"><?= __('location') ?></label>
                                <input type="text" class="form-control" id="location" name="location" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                    <label for="tazmin"><?= __('tazmin') ?></label>
                                <select class="form-control" id="tazmin" name="tazmin" required>
                                    <option value="Done"><?= __('done') ?></option>
                                    <option value="Not Done"><?= __('not_done') ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                    <label for="visa_status"><?= __('visa_status') ?></label>
                                    <select class="form-control" id="visa_status" name="visa_status" required>
                                        <option value="Not Applied"><?= __('not_applied') ?></option>
                                        <option value="Applied"><?= __('applied') ?></option>
                                        <option value="Issued"><?= __('issued') ?></option>
                                    </select>
                                </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="province"><?= __('province') ?></label>
                                <input type="text" class="form-control" id="province" name="province" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="district"><?= __('district') ?></label>
                                <input type="text" class="form-control" id="district" name="district" required>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn umrah-btn umrah-btn-primary"><?= __('create_family') ?></button>
                </form>
            </div>
        </div>
    </div>
</div>