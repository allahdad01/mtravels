<!-- Umrah Presidency Letter Language Selection Modal -->
<div class="modal fade" id="umrahPresidencyModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= __("umrah_presedency_lettter") ?></h5>
            </div>
            <div class="modal-body">
                <form id="umrahPresidencyForm" onsubmit="generateUmrah(event)">
                    <!-- CSRF Protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                    
                    <!-- Family Head Info -->
                    <h6 class="mb-3 mt-2 text-primary"><?= __("family_information") ?></h6>
                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label for="family_head_father_name"><?= __("family_head_father_name") ?></label>
                            <input type="text" class="form-control" id="family_head_father_name" placeholder="<?= __("family_head_father_name") ?>">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="family_head_id_number"><?= __("family_head_id_number") ?></label>
                            <input type="text" class="form-control" id="family_head_id_number" placeholder="<?= __("family_head_id_number") ?>">
                        </div>
                    </div>

                    <!-- Visa & Ticket -->
                    <h6 class="mb-3 mt-4 text-primary"><?= __("visa_ticket_information") ?></h6>
                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label for="umrah_visa_amount"><?= __("umrah_visa_amount") ?></label>
                            <input type="text" class="form-control" id="umrah_visa_amount" placeholder="<?= __("umrah_visa_amount") ?>">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="ticket_amount"><?= __("ticket_amount") ?></label>
                            <input type="text" class="form-control" id="ticket_amount" placeholder="<?= __("ticket_amount") ?>">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="airline_name"><?= __("airline_name") ?></label>
                            <input type="text" class="form-control" id="airline_name" placeholder="<?= __("airline_name") ?>">
                        </div>
                    </div>

                    <!-- Duration -->
                    <h6 class="mb-3 mt-4 text-primary"><?= __("stay_duration") ?></h6>
                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label for="makkah_day_number"><?= __("makkah_day_number") ?></label>
                            <input type="text" class="form-control" id="makkah_day_number" placeholder="<?= __("makkah_day_number") ?>">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="makkah_night_number"><?= __("makkah_night_number") ?></label>
                            <input type="text" class="form-control" id="makkah_night_number" placeholder="<?= __("makkah_night_number") ?>">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="madina_day_number"><?= __("madina_day_number") ?></label>
                            <input type="text" class="form-control" id="madina_day_number" placeholder="<?= __("madina_day_number") ?>">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="madina_night_number"><?= __("madina_night_number") ?></label>
                            <input type="text" class="form-control" id="madina_night_number" placeholder="<?= __("madina_night_number") ?>">
                        </div>
                    </div>

                    <!-- Transport -->
                    <h6 class="mb-3 mt-4 text-primary"><?= __("transport_services") ?></h6>
                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label for="amount_airport_hotel"><?= __("amount_airport_hotel") ?></label>
                            <input type="text" class="form-control" id="amount_airport_hotel" placeholder="<?= __("amount_airport_hotel") ?>">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="amount_hotel_airport"><?= __("amount_hotel_airport") ?></label>
                            <input type="text" class="form-control" id="amount_hotel_airport" placeholder="<?= __("amount_hotel_airport") ?>">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="visiting_ziarats_amount"><?= __("visiting_ziarats_amount") ?></label>
                            <input type="text" class="form-control" id="visiting_ziarats_amount" placeholder="<?= __("visiting_ziarats_amount") ?>">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="halaqat_darsi_amount"><?= __("halaqat_darsi_amount") ?></label>
                            <input type="text" class="form-control" id="halaqat_darsi_amount" placeholder="<?= __("halaqat_darsi_amount") ?>">
                        </div>
                    </div>

                    <!-- Hotels -->
                    <h6 class="mb-3 mt-4 text-primary"><?= __("hotel_information") ?></h6>
                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label for="makkah_hotel_name"><?= __("makkah_hotel_name") ?></label>
                            <input type="text" class="form-control" id="makkah_hotel_name" placeholder="<?= __("makkah_hotel_name") ?>">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="makkah_hotel_degree"><?= __("makkah_hotel_degree") ?></label>
                            <input type="text" class="form-control" id="makkah_hotel_degree" placeholder="<?= __("makkah_hotel_degree") ?>">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="makkah_hotel_distance"><?= __("makkah_hotel_distance") ?></label>
                            <input type="text" class="form-control" id="makkah_hotel_distance" placeholder="<?= __("makkah_hotel_distance") ?>">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="makkah_hotel_amount"><?= __("makkah_hotel_amount") ?></label>
                            <input type="text" class="form-control" id="makkah_hotel_amount" placeholder="<?= __("makkah_hotel_amount") ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label for="madina_hotel_name"><?= __("madina_hotel_name") ?></label>
                            <input type="text" class="form-control" id="madina_hotel_name" placeholder="<?= __("madina_hotel_name") ?>">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="madina_hotel_degree"><?= __("madina_hotel_degree") ?></label>
                            <input type="text" class="form-control" id="madina_hotel_degree" placeholder="<?= __("madina_hotel_degree") ?>">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="madina_hotel_distance"><?= __("madina_hotel_distance") ?></label>
                            <input type="text" class="form-control" id="madina_hotel_distance" placeholder="<?= __("madina_hotel_distance") ?>">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="madina_hotel_amount"><?= __("madina_hotel_amount") ?></label>
                            <input type="text" class="form-control" id="madina_hotel_amount" placeholder="<?= __("madina_hotel_amount") ?>">
                        </div>
                    </div>

                    <!-- Financial -->
                    <h6 class="mb-3 mt-4 text-primary"><?= __("financials") ?></h6>
                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label for="commission_amount"><?= __("commission_amount") ?></label>
                            <input type="text" class="form-control" id="commission_amount" placeholder="<?= __("commission_amount") ?>">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="child_services_amount"><?= __("child_services_amount") ?></label>
                            <input type="text" class="form-control" id="child_services_amount" placeholder="<?= __("child_services_amount") ?>">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="child_commission_amount"><?= __("child_commission_amount") ?></label>
                            <input type="text" class="form-control" id="child_commission_amount" placeholder="<?= __("child_commission_amount") ?>">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="total_amount"><?= __("total_amount") ?></label>
                            <input type="text" class="form-control" id="total_amount" placeholder="<?= __("total_amount") ?>">
                        </div>
                    </div>
                </form>

                <!-- Language Selection -->
                <div class="list-group mt-3">
                    <a href="#" class="list-group-item list-group-item-action" onclick="generateUmrah(event, 'fa')">
                        <i class="feather icon-globe mr-2"></i> Dari
                    </a>
                    <a href="#" class="list-group-item list-group-item-action" onclick="generateUmrah(event, 'ps')">
                        <i class="feather icon-globe mr-2"></i> Pashto
                    </a>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <?= __("cancel") ?>
                </button>
            </div>
        </div>
    </div>
</div>
</div>