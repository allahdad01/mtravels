<!-- Hotel Master Modal — Phase 24: hotel / room type / room forms -->
<div class="modal fade" id="hotelFormModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header" style="border-bottom: 1px solid #eef2f7;">
                <h5 class="modal-title" id="hotelFormTitle">
                    <i class="feather icon-home mr-2" style="color: #0e7490;"></i><span id="hotelFormTitleText">Hotel</span>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <form id="hotelForm">
                <input type="hidden" name="entity" id="hfEntity">
                <input type="hidden" name="id" id="hfId">
                <div class="modal-body" style="background: #f8fafc; max-height: 70vh; overflow-y: auto;">

                    <!-- Hotel -->
                    <div id="hotelFormHotelSection" class="d-none" data-entity="hotel">
                        <input type="hidden" id="hfHotelId">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group"><label><?= __('name') ?> *</label>
                                    <input type="text" class="form-control" name="name" id="hfName" required></div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group"><label><?= __('saudi_name') ?></label>
                                    <input type="text" class="form-control" name="saudi_name" id="hfSaudiName"></div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group"><label><?= __('city') ?></label>
                                    <select class="form-control" name="city" id="hfCity" required>
                                        <option value="">--</option>
                                        <option value="Makkah"><?= __('makkah') ?></option>
                                        <option value="Madinah"><?= __('madinah') ?></option>
                                        <option value="Other"><?= __('other') ?></option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group"><label><?= __('location') ?></label>
                                    <input type="text" class="form-control" name="location" id="hfLocation"></div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group"><label><?= __('star_rating') ?></label>
                                    <select class="form-control" name="star_rating" id="hfStar">
                                        <option value="">--</option>
                                        <option>3</option><option>4</option><option>5</option>
                                    </select></div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group"><label><?= __('contact') ?></label>
                                    <input type="text" class="form-control" name="contact" id="hfContact"></div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group"><label><?= __('supplier') ?></label>
                                    <select class="form-control" name="supplier_id" id="hfSupplier"></select></div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group"><label><?= __('address') ?></label>
                                    <input type="text" class="form-control" name="address" id="hfAddress"></div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group"><label><?= __('status') ?></label>
                                    <select class="form-control" name="status" id="hfHotelStatus">
                                        <option value="active"><?= __('active') ?></option>
                                        <option value="inactive"><?= __('inactive') ?></option>
                                    </select></div>
                            </div>
                            <div class="col-12">
                                <div class="form-group"><label><?= __('notes') ?></label>
                                    <textarea class="form-control" name="notes" id="hfNotes" rows="2"></textarea></div>
                            </div>
                        </div>
                    </div>

                    <!-- Room type (global — shared by all hotels) -->
                    <div id="hotelFormRoomTypeSection" class="d-none" data-entity="room_type">
                        <input type="hidden" id="hfRoomTypeId">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group"><label><?= __('room_type_name') ?> *</label>
                                    <input type="text" class="form-control" name="name" id="hfRtName" required placeholder="<?= __('room_type_name_placeholder') ?>"></div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group"><label><?= __('max_occupancy') ?></label>
                                    <input type="number" class="form-control" name="max_occupancy" id="hfRtOccupancy" min="1" max="12" placeholder="<?= __('max_occupancy_placeholder') ?>"></div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group"><label><?= __('bed_type') ?></label>
                                    <input type="text" class="form-control" name="bed_type" id="hfRtBed" placeholder="<?= __('bed_type_placeholder') ?>"></div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group"><label><?= __('status') ?></label>
                                    <select class="form-control" name="status" id="hfRtStatus">
                                        <option value="active"><?= __('active') ?></option>
                                        <option value="inactive"><?= __('inactive') ?></option>
                                    </select></div>
                            </div>
                            <div class="col-12">
                                <div class="form-group"><label><?= __('description') ?></label>
                                    <textarea class="form-control" name="description" id="hfRtDescription" rows="2"></textarea></div>
                            </div>
                        </div>
                    </div>

                    <!-- Room -->
                    <div id="hotelFormRoomSection" class="d-none" data-entity="room">
                        <input type="hidden" id="hfRoomId">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group"><label><?= __('hotel') ?> *</label>
                                    <select class="form-control" name="hotel_id" id="hfRoomHotel" required>
                                        <option value=""><?= __('select_hotel') ?></option>
                                    </select></div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group"><label><?= __('room_type') ?> *</label>
                                    <select class="form-control" name="room_type_id" id="hfRoomType" required></select></div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group"><label><?= __('room_number') ?> *</label>
                                    <input type="text" class="form-control" name="room_number" id="hfRoomNumber" required placeholder="<?= __('room_number_placeholder') ?>"></div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group"><label><?= __('floor') ?></label>
                                    <input type="text" class="form-control" name="floor" id="hfRoomFloor" placeholder="<?= __('floor_placeholder') ?>"></div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group"><label><?= __('status') ?></label>
                                    <select class="form-control" name="status" id="hfRoomStatus">
                                        <option value="active"><?= __('active') ?></option>
                                        <option value="maintenance"><?= __('maintenance') ?></option>
                                        <option value="inactive"><?= __('inactive') ?></option>
                                    </select></div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group"><label><?= __('notes') ?></label>
                                    <input type="text" class="form-control" name="notes" id="hfRoomNotes"></div>
                            </div>
                        </div>
                    </div>

                </div>
                <div class="modal-footer" style="border-top: 1px solid #eef2f7;">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
                    <button type="submit" class="btn btn-primary"><i class="feather icon-save mr-1"></i><?= __('save') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
