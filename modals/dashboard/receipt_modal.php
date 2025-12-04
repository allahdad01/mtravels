                                <!-- Modal Structure -->
                                <div class="modal fade" id="receiptModal" tabindex="-1" aria-labelledby="receiptModalLabel" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="receiptModalLabel"><?= __('enter_receipt_details') ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <form id="receiptForm">
                                                    <div class="mb-3">
                                                        <label for="receiptNumber" class="form-label"><?= __('receipt_number') ?></label>
                                                        <input type="text" class="form-control" id="receiptNumber" name="receipt_number" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="remarks" class="form-label"><?= __('remarks') ?></label>
                                                        <input type="text" class="form-control" id="remarks" name="remarks" required>
                                                    </div>
                                                    <input type="hidden" id="hiddenNotificationId" name="notification_id">
                                                </form>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('close') ?></button>
                                                <button type="button" id="submitReceipt" class="btn btn-success"><?= __('submit') ?></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>