<!-- Member Details Modal - Modern Enhanced Style -->
<div class="modal fade" id="memberDetailsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius: 0.75rem; border: none; box-shadow: 0 10px 40px rgba(0,0,0,0.1);">
            <div class="modal-header" style="background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%); border: none; padding: 1.5rem;">
                <h5 class="modal-title text-white" style="font-weight: 700; font-size: 1.5rem;">
                    <i class="fas fa-user-circle mr-2" style="font-size: 1.75rem;"></i><?= __('member_details') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="font-size: 1.5rem; opacity: 0.8;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="padding: 2rem; background: #f9fafb;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                    
                    <!-- Personal Information -->
                    <div style="background: white; border-radius: 0.75rem; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-left: 4px solid #2563eb;">
                        <h6 style="font-size: 1rem; font-weight: 700; color: #1f2937; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-user" style="color: #2563eb;"></i><?= __('personal_information') ?>
                        </h6>
                        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                            <div style="display: flex; justify-content: space-between; padding-bottom: 0.75rem; border-bottom: 1px solid #e5e7eb;">
                                <span style="color: #6b7280; font-weight: 500;"><?= __('name') ?>:</span>
                                <span style="color: #1f2937; font-weight: 600;" id="memberName"></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding-bottom: 0.75rem; border-bottom: 1px solid #e5e7eb;">
                                <span style="color: #6b7280; font-weight: 500;"><?= __('gender') ?>:</span>
                                <span style="color: #1f2937;" id="memberGender"></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding-bottom: 0.75rem; border-bottom: 1px solid #e5e7eb;">
                                <span style="color: #6b7280; font-weight: 500;">Passenger Type:</span>
                                <span style="color: #1f2937; font-weight: 600;" id="memberPassengerType"></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding-bottom: 0.75rem; border-bottom: 1px solid #e5e7eb;">
                                <span style="color: #6b7280; font-weight: 500;"><?= __('date_of_birth') ?>:</span>
                                <span style="color: #1f2937;" id="memberDob"></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding-bottom: 0.75rem; border-bottom: 1px solid #e5e7eb;">
                                <span style="color: #6b7280; font-weight: 500;"><?= __('passport_number') ?>:</span>
                                <span style="color: #1f2937;" id="memberPassport"></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding-bottom: 0.75rem; border-bottom: 1px solid #e5e7eb;">
                                <span style="color: #6b7280; font-weight: 500;"><?= __('passport_expiry') ?>:</span>
                                <span style="color: #1f2937;" id="memberPassportExpiry"></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding-bottom: 0.75rem; border-bottom: 1px solid #e5e7eb;">
                                <span style="color: #6b7280; font-weight: 500;"><?= __('id_type') ?>:</span>
                                <span style="color: #1f2937;" id="memberId"></span>
                            </div>
                            <div style="display: flex; justify-content: space-between;">
                                <span style="color: #6b7280; font-weight: 500;"><?= __('remarks') ?>:</span>
                                <span style="color: #1f2937;" id="memberRemarks"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Member Photo -->
                    <div style="background: white; border-radius: 0.75rem; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-left: 4px solid #ec4899; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                        <h6 style="font-size: 1rem; font-weight: 700; color: #1f2937; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; width: 100%;">
                            <i class="fas fa-camera" style="color: #ec4899;"></i>Member Photo
                        </h6>
                        <div id="memberPhotoDisplay" style="width: 100%; text-align: center;">
                            <div style="width: 150px; height: 200px; background: #f3f4f6; border: 2px dashed #d1d5db; border-radius: 0.5rem; display: flex; align-items: center; justify-content: center; margin: 0 auto; color: #9ca3af;">
                                <i class="fas fa-image" style="font-size: 2rem;"></i>
                            </div>
                            <p style="color: #9ca3af; font-size: 0.875rem; margin-top: 0.5rem;">No photo available</p>
                        </div>
                    </div>

                    <!-- Travel Information -->
                    <div style="background: white; border-radius: 0.75rem; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-left: 4px solid #3b82f6;">
                        <h6 style="font-size: 1rem; font-weight: 700; color: #1f2937; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-plane" style="color: #3b82f6;"></i><?= __('travel_information') ?>
                        </h6>
                        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                            <div style="display: flex; justify-content: space-between; padding-bottom: 0.75rem; border-bottom: 1px solid #e5e7eb;">
                                <span style="color: #6b7280; font-weight: 500;"><?= __('entry_date') ?>:</span>
                                <span style="color: #1f2937;" id="memberEntryDate"></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding-bottom: 0.75rem; border-bottom: 1px solid #e5e7eb;">
                                <span style="color: #6b7280; font-weight: 500;"><?= __('flight_date') ?>:</span>
                                <span style="color: #1f2937;" id="memberFlightDate"></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding-bottom: 0.75rem; border-bottom: 1px solid #e5e7eb;">
                                <span style="color: #6b7280; font-weight: 500;"><?= __('return_date') ?>:</span>
                                <span style="color: #1f2937;" id="memberReturnDate"></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding-bottom: 0.75rem; border-bottom: 1px solid #e5e7eb;">
                                <span style="color: #6b7280; font-weight: 500;"><?= __('duration') ?>:</span>
                                <span style="color: #1f2937;" id="memberDuration"></span>
                            </div>
                            <div style="display: flex; justify-content: space-between;">
                                <span style="color: #6b7280; font-weight: 500;"><?= __('room_type') ?>:</span>
                                <span style="color: #1f2937;" id="memberRoomType"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Account Information -->
                    <div style="background: white; border-radius: 0.75rem; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-left: 4px solid #8b5cf6;">
                        <h6 style="font-size: 1rem; font-weight: 700; color: #1f2937; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-building" style="color: #8b5cf6;"></i><?= __('account_info') ?>
                        </h6>
                        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                            <div style="display: flex; justify-content: space-between; padding-bottom: 0.75rem; border-bottom: 1px solid #e5e7eb;">
                                <span style="color: #6b7280; font-weight: 500;"><?= __('sold_to') ?>:</span>
                                <span style="color: #1f2937;" id="memberSoldTo"></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding-bottom: 0.75rem; border-bottom: 1px solid #e5e7eb;">
                                <span style="color: #6b7280; font-weight: 500;"><?= __('paid_to') ?>:</span>
                                <span style="color: #1f2937;" id="memberPaidTo"></span>
                            </div>
                            <div style="display: flex; justify-content: space-between;">
                                <span style="color: #6b7280; font-weight: 500;"><?= __('created_by') ?>:</span>
                                <span style="color: #1f2937;" id="memberCreatedBy"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Services Information -->
                    <div style="background: white; border-radius: 0.75rem; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-left: 4px solid #06b6d4;">
                        <h6 style="font-size: 1rem; font-weight: 700; color: #1f2937; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-briefcase" style="color: #06b6d4;"></i>Services
                        </h6>
                        <div id="memberServices" style="display: flex; flex-direction: column; gap: 0.75rem;">
                            <div style="padding: 0.75rem; background: #f0f9ff; border-radius: 0.375rem; border-left: 3px solid #06b6d4; color: #6b7280; font-size: 0.85rem;">
                                No services assigned
                            </div>
                        </div>
                    </div>

                    <!-- Financial Information -->
                    <div style="background: white; border-radius: 0.75rem; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-left: 4px solid #10b981; grid-column: 1 / -1;">
                        <h6 style="font-size: 1rem; font-weight: 700; color: #1f2937; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-dollar-sign" style="color: #10b981;"></i><?= __('financial_information') ?>
                        </h6>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 2rem;">
                            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                                <div style="display: flex; justify-content: space-between; padding-bottom: 0.75rem; border-bottom: 1px solid #e5e7eb;">
                                    <span style="color: #6b7280; font-weight: 500;"><?= __('base') ?>:</span>
                                    <span style="color: #1f2937; font-weight: 600;" id="memberPrice"></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; padding-bottom: 0.75rem; border-bottom: 1px solid #e5e7eb;">
                                    <span style="color: #6b7280; font-weight: 500;"><?= __('sold_price') ?>:</span>
                                    <span style="color: #1f2937; font-weight: 600;" id="memberSoldPrice"></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; padding-bottom: 0.75rem; border-bottom: 1px solid #e5e7eb;">
                                    <span style="color: #6b7280; font-weight: 500;"><?= __('discount') ?>:</span>
                                    <span style="color: #1f2937; font-weight: 600;" id="memberDiscount"></span>
                                </div>
                                <div style="display: flex; justify-content: space-between;">
                                    <span style="color: #6b7280; font-weight: 500;"><?= __('profit') ?>:</span>
                                    <span style="color: #10b981; font-weight: 600;" id="memberProfit"></span>
                                </div>
                            </div>
                            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                                <div style="display: flex; justify-content: space-between; padding-bottom: 0.75rem; border-bottom: 1px solid #e5e7eb;">
                                    <span style="color: #6b7280; font-weight: 500;"><?= __('paid') ?>:</span>
                                    <span style="color: #10b981; font-weight: 600;" id="memberPaid"></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; padding-bottom: 0.75rem; border-bottom: 1px solid #e5e7eb;">
                                    <span style="color: #6b7280; font-weight: 500;"><?= __('bank_payment') ?>:</span>
                                    <span style="color: #1f2937;" id="memberBankPayment"></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; padding-bottom: 0.75rem; border-bottom: 1px solid #e5e7eb;">
                                    <span style="color: #6b7280; font-weight: 500;"><?= __('receipt_number') ?>:</span>
                                    <span style="color: #1f2937;" id="memberReceiptNumber"></span>
                                </div>
                                <div style="display: flex; justify-content: space-between;">
                                    <span style="color: #6b7280; font-weight: 500;"><?= __('due') ?>:</span>
                                    <span style="color: #ef4444; font-weight: 600;" id="memberDue"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Date Change History -->
                    <div id="dateChangeHistorySection" style="display: none; grid-column: 1 / -1;">
                        <div style="background: white; border-radius: 0.75rem; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-left: 4px solid #f59e0b;">
                            <h6 style="font-size: 1rem; font-weight: 700; color: #1f2937; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fas fa-calendar" style="color: #f59e0b;"></i><?= __('date_change_history') ?>
                            </h6>
                            <div id="dateChangeHistoryContent">
                                <!-- History will be loaded here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="padding: 1.5rem; background: #f9fafb; border-top: 1px solid #e5e7eb;">
                <button type="button" class="btn btn-secondary" data-dismiss="modal" style="padding: 0.5rem 1.5rem;">
                    <i class="fas fa-times mr-2"></i><?= __('close') ?>
                </button>
            </div>
        </div>
    </div>
</div>