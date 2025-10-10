<!-- Hidden template for family member document section -->
<div id="memberDocumentTemplate" style="display: none;">
    <div class="member-document-section mb-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h6 class="mb-0">
                    <i class="feather icon-user mr-2"></i>
                    <span class="member-name"></span> 
                    <small class="ml-2">(<span class="member-passport"></span> - ID: <span class="member-booking-id"></span>)</small>
                </h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm">
                        <thead class="thead-light">
                            <tr>
                                <th width="30%"><?= __('document_type') ?></th>
                                <th width="15%"><?= __('returned') ?></th>
                                <th width="20%"><?= __('condition') ?></th>
                                <th width="35%"><?= __('notes') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong><?= __('passport') ?></strong></td>
                                <td class="text-center">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input member-return-checkbox" 
                                               data-member-id="" data-doc-type="passport" value="1">
                                        <label class="custom-control-label"></label>
                                    </div>
                                </td>
                                <td>
                                    <select class="form-control form-control-sm member-condition-select" 
                                            data-member-id="" data-doc-type="passport">
                                        <option value=""><?= __('select_condition') ?></option>
                                        <option value="good"><?= __('good') ?></option>
                                        <option value="fair"><?= __('fair') ?></option>
                                        <option value="damaged"><?= __('damaged') ?></option>
                                        <option value="missing"><?= __('missing') ?></option>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm member-notes-input" 
                                           data-member-id="" data-doc-type="passport" 
                                           placeholder="<?= __('passport_notes') ?>">
                                </td>
                            </tr>
                            <tr>
                                <td><strong><?= __('id_card') ?></strong></td>
                                <td class="text-center">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input member-return-checkbox" 
                                               data-member-id="" data-doc-type="id_card" value="1">
                                        <label class="custom-control-label"></label>
                                    </div>
                                </td>
                                <td>
                                    <select class="form-control form-control-sm member-condition-select" 
                                            data-member-id="" data-doc-type="id_card">
                                        <option value=""><?= __('select_condition') ?></option>
                                        <option value="good"><?= __('good') ?></option>
                                        <option value="fair"><?= __('fair') ?></option>
                                        <option value="damaged"><?= __('damaged') ?></option>
                                        <option value="missing"><?= __('missing') ?></option>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm member-notes-input" 
                                           data-member-id="" data-doc-type="id_card" 
                                           placeholder="<?= __('id_card_notes') ?>">
                                </td>
                            </tr>
                            <tr>
                                <td><strong><?= __('photos') ?></strong></td>
                                <td class="text-center">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input member-return-checkbox" 
                                               data-member-id="" data-doc-type="photos" value="1">
                                        <label class="custom-control-label"></label>
                                    </div>
                                </td>
                                <td>
                                    <select class="form-control form-control-sm member-condition-select" 
                                            data-member-id="" data-doc-type="photos">
                                        <option value=""><?= __('select_condition') ?></option>
                                        <option value="good"><?= __('good') ?></option>
                                        <option value="fair"><?= __('fair') ?></option>
                                        <option value="damaged"><?= __('damaged') ?></option>
                                        <option value="missing"><?= __('missing') ?></option>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm member-notes-input" 
                                           data-member-id="" data-doc-type="photos" 
                                           placeholder="<?= __('photos_notes') ?>">
                                </td>
                            </tr>
                            <tr>
                                <td><strong><?= __('other_documents') ?></strong></td>
                                <td class="text-center">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input member-return-checkbox" 
                                               data-member-id="" data-doc-type="other_docs" value="1">
                                        <label class="custom-control-label"></label>
                                    </div>
                                </td>
                                <td>
                                    <select class="form-control form-control-sm member-condition-select" 
                                            data-member-id="" data-doc-type="other_docs">
                                        <option value=""><?= __('select_condition') ?></option>
                                        <option value="good"><?= __('good') ?></option>
                                        <option value="fair"><?= __('fair') ?></option>
                                        <option value="damaged"><?= __('damaged') ?></option>
                                        <option value="missing"><?= __('missing') ?></option>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm member-notes-input" 
                                           data-member-id="" data-doc-type="other_docs" 
                                           placeholder="<?= __('specify_other_documents') ?>">
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>