<!-- Edit Family Modal -->
<div class="modal fade" id="editFamilyModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= __('edit_family_details') ?></h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editFamilyForm">
                    <input type="hidden" id="editFamilyId" name="family_id">
                    <div class="form-group">
                        <label><?= __('family_head') ?></label>
                        <input type="text" class="form-control" id="editHeadOfFamily" name="head_of_family">
                    </div>
                    <div class="form-group">
                        <label><?= __('contact') ?></label>
                        <input type="text" class="form-control" id="editContact" name="contact">
                    </div>
                    <div class="form-group">
                        <label><?= __('address') ?></label>
                        <input type="text" class="form-control" id="editAddress" name="address">
                    </div>
                    <div class="form-group">
                        <label for="editPackageType">Package Type:</label>
                        <select class="form-control" id="editPackageType" name="package_type" required>
                            <option value="Full Package"><?= __('full_package') ?></option>
                            <option value="Visa"><?= __('visa') ?></option>
                            <option value="Services"><?= __('services') ?></option>
                            <option value="Ticket+Visa"><?= __('ticket_visa') ?></option>
                            <option value="Visa+Services"><?= __('visa_services') ?></option>
                            <option value="Visa+Transport"><?= __('visa_transport') ?></option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><?= __('location') ?></label>
                        <input type="text" class="form-control" id="editLocation" name="location">
                    </div>
                    <div class="form-group">
                        <label for="editTazmin"><?= __('tazmin') ?></label>
                        <select class="form-control" id="editTazmin" name="tazmin" required>
                            <option value="Done"><?= __('done') ?></option>
                            <option value="Not Done"><?= __('not_done') ?></option>
                        </select>
                    </div>
                    <div class="form-group">
                            <label for="editStatus"><?= __('visa_status') ?></label>
                            <select class="form-control" id="editStatus" name="visa_status" required>
                                <option value="Not Applied"><?= __('not_applied') ?></option>
                                <option value="Applied"><?= __('applied') ?></option>
                                <option value="Issued"><?= __('issued') ?></option>
                            </select>
                        </div>
                    <div class="form-group">
                        <label for="editProvince"><?= __('province') ?></label>
                        <input type="text" class="form-control" id="editProvince" name="province">
                    </div>
                        <div class="form-group">
                        <label for="editDistrict"><?= __('district') ?></label>
                        <input type="text" class="form-control" id="editDistrict" name="district">
                    </div>
                    <button type="submit" class="btn btn-primary"><?= __('save_changes') ?></button>
                </form>
            </div>
        </div>
    </div>
</div>