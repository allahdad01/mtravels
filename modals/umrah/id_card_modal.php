<!-- ID Card Generation Modal -->
<div class="modal fade" id="idCardModal" tabindex="-1" role="dialog" aria-labelledby="idCardModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title" id="idCardModalLabel">
                    <i class="feather icon-credit-card mr-2"></i><?= __('generate_id_cards') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="feather icon-info mr-2"></i><?= __('select_up_to_8_pilgrims_for_id_cards') ?>
                </div>
                
                <div class="selected-pilgrims mb-3">
                    <h6><?= __('selected_pilgrims') ?>: <span id="selectedCount">0</span>/8</h6>
                    <div id="selectedPilgrimsList" class="row">
                        <!-- Selected pilgrims will be displayed here -->
                    </div>
                </div>
                
                <form id="idCardForm" action="generate_id_cards.php" method="post" target="_blank" enctype="multipart/form-data">
                    <!-- CSRF Protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                    <input type="hidden" name="selected_pilgrims" id="selectedPilgrimsInput">
                    
                    <div class="form-group">
                        <label for="idCardTitle"><?= __('id_card_title') ?></label>
                        <input type="text" class="form-control" id="idCardTitle" name="id_card_title" 
                               value="<?= htmlspecialchars($settings['agency_name']) ?> - Umrah Pilgrim ID" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="idCardValidityDays"><?= __('id_card_validity_days') ?></label>
                        <input type="number" class="form-control" id="idCardValidityDays" name="id_card_validity_days" 
                               value="45" min="1" max="90" required>
                        <small class="form-text text-muted"><?= __('number_of_days_id_card_is_valid_from_today') ?></small>
                    </div>
                    
                    <div class="form-group">
                        <label for="idCardColor"><?= __('id_card_color') ?></label>
                        <select class="form-control" id="idCardColor" name="id_card_color">
                            <option value="primary"><?= __('blue') ?></option>
                            <option value="success"><?= __('green') ?></option>
                            <option value="danger"><?= __('red') ?></option>
                            <option value="warning"><?= __('yellow') ?></option>
                            <option value="info"><?= __('light_blue') ?></option>
                            <option value="dark"><?= __('black') ?></option>
                        </select>
                    </div>
                    
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="feather icon-phone mr-2"></i><?= __('guide_contact_information') ?></h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="guideMakkahName"><?= __('guide_makkah_name') ?></label>
                                        <input type="text" class="form-control" id="guideMakkahName" name="guide_makkah_name" placeholder="<?= __('enter_guide_name') ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="guideMakkahPhone"><?= __('guide_makkah_phone_number') ?></label>
                                        <input type="text" class="form-control" id="guideMakkahPhone" name="guide_makkah_phone" placeholder="<?= __('enter_guide_phone_number') ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="groupName"><?= __('group_name') ?></label>
                                <input type="text" class="form-control" id="groupName" name="group_name" placeholder="<?= __('enter_group_name') ?>">
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="guideMadinaName"><?= __('guide_madina_name') ?></label>
                                        <input type="text" class="form-control" id="guideMadinaName" name="guide_madina_name" placeholder="<?= __('enter_guide_madina_name') ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="guideMadinaPhone"><?= __('guide_madina_phone_number') ?></label>
                                        <input type="text" class="form-control" id="guideMadinaPhone" name="guide_madina_phone" placeholder="<?= __('enter_guide_madina_phone_number') ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="photo-upload-section mt-4">
                        <h5 class="mb-3"><?= __('pilgrim_photos') ?></h5>
                        <div class="alert alert-info">
                            <i class="feather icon-camera mr-2"></i> <?= __('upload_photos_for_id_cards') ?>
                            <ul class="mb-0 mt-2">
                                <li><?= __('passport_style_photos_recommended') ?></li>
                                <li><?= __('square_photos_work_best') ?></li>
                                <li><?= __('photos_will_be_cropped_to_fit') ?></li>
                            </ul>
                        </div>
                        
                        <div id="photoUploadContainer" class="row">
                            <!-- Photo upload fields will be added here dynamically -->
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="feather icon-x mr-2"></i><?= __('cancel') ?>
                </button>
                <button type="button" class="btn btn-primary" id="generateIdCardsBtn" disabled>
                    <i class="feather icon-printer mr-2"></i><?= __('generate_id_cards') ?>
                </button>
            </div>
        </div>
    </div>
</div>