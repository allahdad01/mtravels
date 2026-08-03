<!-- Edit Family Modal -->
<div class="modal umrah-modal fade" id="editFamilyModal" tabindex="-1" aria-labelledby="editFamilyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editFamilyModalLabel"><?= __('edit_family_details') ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="editFamilyForm" method="POST" onsubmit="return submitEditFamilyForm();">
                        <!-- CSRF Protection -->
                        <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                        <input type="hidden" id="editFamilyId" name="family_id">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="editHeadOfFamily"><?= __('family_head') ?></label>
                                <input type="text" class="form-control" id="editHeadOfFamily" name="head_of_family" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="editContact"><?= __('contact_number') ?></label>
                                <input type="text" class="form-control" id="editContact" inputmode="tel" name="contact" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="editAddress"><?= __('address') ?></label>
                                <input type="text" class="form-control" id="editAddress" name="address" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="editPackageType"><?= __('package_type') ?></label>
                                <select class="form-control" id="editPackageType" name="package_type" required>
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
                                <label for="editLocation"><?= __('location') ?></label>
                                <input type="text" class="form-control" id="editLocation" name="location" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                    <label for="editTazmin"><?= __('tazmin') ?></label>
                                <select class="form-control" id="editTazmin" name="tazmin" required>
                                    <option value="Done"><?= __('done') ?></option>
                                    <option value="Not Done"><?= __('not_done') ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                    <label for="editStatus"><?= __('visa_status') ?></label>
                                    <select class="form-control" id="editStatus" name="visa_status" required>
                                        <option value="Not Applied"><?= __('not_applied') ?></option>
                                        <option value="Applied"><?= __('applied') ?></option>
                                        <option value="Issued"><?= __('issued') ?></option>
                                    </select>
                                </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="editProvince"><?= __('province') ?></label>
                                <input type="text" class="form-control" id="editProvince" name="province" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="editDistrict"><?= __('district') ?></label>
                                <input type="text" class="form-control" id="editDistrict" name="district" required>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn umrah-btn umrah-btn-primary"><?= __('save_changes') ?></button>
                </form>
            </div>
        </div>
    </div>
</div>