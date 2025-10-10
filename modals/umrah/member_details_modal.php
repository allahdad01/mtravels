<!-- Member Details Modal -->
<div class="modal fade" id="memberDetailsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="feather icon-user mr-2"></i><?= __('member_details') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <!-- Personal Information -->
                    <div class="col-md-6">
                        <div class="card border-primary mb-3">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0"><i class="feather icon-user mr-2"></i><?= __('personal_information') ?></h6>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td class="text-muted"><?= __('name') ?>:</td>
                                        <td class="font-weight-bold" id="memberName"></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted"><?= __('gender') ?>:</td>
                                        <td id="memberGender"></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted"><?= __('date_of_birth') ?>:</td>
                                        <td id="memberDob"></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted"><?= __('passport_number') ?>:</td>
                                        <td id="memberPassport"></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted"><?= __('passport_expiry') ?>:</td>
                                        <td id="memberPassportExpiry"></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted"><?= __('id_type') ?>:</td>
                                        <td id="memberId"></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted"><?= __('remarks') ?>:</td>
                                        <td id="memberRemarks"></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Travel Information -->
                    <div class="col-md-6">
                        <div class="card border-info mb-3">
                            <div class="card-header bg-info text-white">
                                <h6 class="mb-0"><i class="feather icon-map mr-2"></i><?= __('travel_information') ?></h6>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td class="text-muted"><?= __('entry_date') ?>:</td>
                                        <td id="memberEntryDate"></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted"><?= __('flight_date') ?>:</td>
                                        <td id="memberFlightDate"></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted"><?= __('return_date') ?>:</td>
                                        <td id="memberReturnDate"></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted"><?= __('duration') ?>:</td>
                                        <td id="memberDuration"></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted"><?= __('room_type') ?>:</td>
                                        <td id="memberRoomType"></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Financial Information -->
                    <div class="col-md-12">
                        <div class="card border-success">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0"><i class="feather icon-dollar-sign mr-2"></i><?= __('financial_information') ?></h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <table class="table table-sm table-borderless">
                                            <tr>
                                                <td class="text-muted"><?= __('base') ?>:</td>
                                                <td class="font-weight-bold" id="memberPrice"></td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted"><?= __('sold_price') ?>:</td>
                                                <td class="font-weight-bold" id="memberSoldPrice"></td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted"><?= __('discount') ?>:</td>
                                                <td class="font-weight-bold" id="memberDiscount"></td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted"><?= __('profit') ?>:</td>
                                                <td class="text-success font-weight-bold" id="memberProfit"></td>
                                            </tr>
                                           
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <table class="table table-sm table-borderless">
                                            <tr>
                                                <td class="text-muted"><?= __('paid') ?>:</td>
                                                <td class="text-success" id="memberPaid"></td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted"><?= __('bank_payment') ?>:</td>
                                                <td id="memberBankPayment"></td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted"><?= __('receipt_number') ?>:</td>
                                                <td id="memberReceiptNumber"></td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted"><?= __('due') ?>:</td>
                                                <td class="text-danger" id="memberDue"></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Date Change History -->
                    <div class="col-md-12" id="dateChangeHistorySection" style="display: none;">
                        <div class="card border-info">
                            <div class="card-header bg-info text-white">
                                <h6 class="mb-0"><i class="feather icon-calendar mr-2"></i><?= __('date_change_history') ?></h6>
                            </div>
                            <div class="card-body">
                                <div id="dateChangeHistoryContent">
                                    <!-- History will be loaded here -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="feather icon-x mr-2"></i><?= __('close') ?>
                </button>
            </div>
        </div>
    </div>
</div>