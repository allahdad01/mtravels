                                <!-- Visa Details Modal -->
                                <div class="modal fade" id="detailsModal" tabindex="-1" role="dialog">
                                    <div class="modal-dialog modal-lg" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header bg-primary text-white">
                                                <h5 class="modal-title">
                                                    <i class="feather icon-file-text mr-2"></i><?= __('visa_details') ?>
                                                </h5>
                                                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                                <ul class="nav nav-pills nav-fill mb-3" id="detailsTab" role="tablist">
                                                    <li class="nav-item">
                                                        <a class="nav-link active" id="details-summary-tab" data-toggle="tab" href="#details-summary">
                                                            <i class="feather icon-info mr-1"></i><?= __('summary') ?>
                                                        </a>
                                                    </li>
                                                    <li class="nav-item">
                                                        <a class="nav-link" id="details-description-tab" data-toggle="tab" href="#details-description">
                                                            <i class="feather icon-file-text mr-1"></i><?= __('description') ?>
                                                        </a>
                                                    </li>
                                                </ul>
                                                <div class="tab-content p-3 border rounded">
                                                    <div class="tab-pane fade show active" id="details-summary">
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <div class="card border-primary mb-3">
                                                                    <div class="card-header bg-primary text-white">
                                                                        <i class="feather icon-user mr-1"></i><?= __('personal_details') ?>
                                                                    </div>
                                                                    <div class="card-body">
                                                                        <p class="mb-2"><strong><?= __('paid_to') ?>:</strong> <span id="paid-to"></span></p>
                                                                        <p class="mb-2"><strong><?= __('country') ?>:</strong> <span id="country"></span></p>
                                                                        <p class="mb-2"><strong><?= __('visa_type') ?>:</strong> <span id="visa-type"></span></p>
                                                                        <p class="mb-2"><strong><?= __('created_by') ?>:</strong> <span id="created-by"></span></p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="card border-success mb-3">
                                                                    <div class="card-header bg-success text-white">
                                                                        <i class="feather icon-dollar-sign mr-1"></i><?= __('financial_details') ?>
                                                                    </div>
                                                                    <div class="card-body">
                                                                        <p class="mb-2"><strong><?= __('currency') ?>:</strong> <span id="currency"></span></p>
                                                                        <p class="mb-2"><strong><?= __('base_price') ?>:</strong> <span id="base-price"></span></p>
                                                                        <p class="mb-2"><strong><?= __('sold_price') ?>:</strong> <span id="sold-price"></span></p>
                                                                        <p class="mb-2"><strong><?= __('profit') ?>:</strong> <span id="profit" class="text-success"></span></p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="card border-info">
                                                            <div class="card-header bg-info text-white">
                                                                <i class="feather icon-calendar mr-1"></i><?= __('dates') ?>
                                                            </div>
                                                            <div class="card-body">
                                                                <div class="row">
                                                                    <div class="col-md-4">
                                                                        <p class="mb-2"><strong><?= __('receive_date') ?>:</strong> <span id="receive-date"></span></p>
                                                                    </div>
                                                                    <div class="col-md-4">
                                                                        <p class="mb-2"><strong><?= __('applied_date') ?>:</strong> <span id="applied-date"></span></p>
                                                                    </div>
                                                                    <div class="col-md-4">
                                                                        <p class="mb-2"><strong><?= __('issued_date') ?>:</strong> <span id="issued-date"></span></p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="tab-pane fade" id="details-description">
                                                        <div class="card">
                                                            <div class="card-body">
                                                                <p id="description" class="mb-0"></p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer bg-light">
                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                                                    <i class="feather icon-x mr-1"></i><?= __('close') ?>
                                                </button>
                                                <button type="button" id="approveVisaBtn" class="btn btn-success" style="display: none;">
                                                    <i class="feather icon-check mr-1"></i><?= __('approve') ?>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>