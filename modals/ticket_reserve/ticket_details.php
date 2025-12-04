                             <!-- Ticket details Modal -->
                             <div class="modal fade" id="detailsModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white border-0">
                <h5 class="modal-title">
                        <i class="feather icon-clipboard mr-2"></i><?= __('ticket_details') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0">
                <!-- Top Summary Card -->
                <div class="bg-light p-4 border-bottom">
                    <div class="row">
                        <div class="col-md-4 text-center">
                            <div class="small text-muted mb-1"><?= __('sold_price') ?></div>
                            <h4 class="mb-0 text-primary" id="sold-price">-</h4>
                        </div>
                        <div class="col-md-4 text-center">
                            <div class="small text-muted mb-1"><?= __('base_price') ?></div>
                            <h4 class="mb-0 text-info" id="base-price">-</h4>
                        </div>
                        <div class="col-md-4 text-center">
                            <div class="small text-muted mb-1"><?= __('profit') ?></div>
                            <h4 class="mb-0 text-success" id="profit">-</h4>
                        </div>
                     
                    </div>
                </div>

                <!-- Tabs Navigation -->
                <ul class="nav nav-pills nav-fill p-3" id="detailsTab" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="details-summary-tab" data-toggle="tab" href="#details-summary" role="tab">
                            <i class="feather icon-info mr-2"></i><?= __('summary') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="details-description-tab" data-toggle="tab" href="#details-description" role="tab">
                            <i class="feather icon-file-text mr-2"></i><?= __('description') ?>
                        </a>
                    </li>
                    
                </ul>

                <!-- Tab Content -->
                <div class="tab-content p-4">
                    <!-- Summary Tab -->
                    <div class="tab-pane fade show active" id="details-summary" role="tabpanel">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card border-0 shadow-sm mb-3">
                                    <div class="card-body">
                                        <h6 class="card-subtitle mb-3 text-muted"><?= __('client_information') ?></h6>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted"><?= __('passenger_name') ?></span>
                                            <strong id="passenger-name">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted"><?= __('pnr') ?></span>
                                            <strong id="pnr">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted"><?= __('supplier') ?></span>
                                            <strong id="supplier-name">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted"><?= __('sold_to') ?></span>
                                            <strong id="sold-to">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span class="text-muted"><?= __('paid_to') ?></span>
                                            <strong id="paid-to">-</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card border-0 shadow-sm mb-3">
                                    <div class="card-body">
                                        <h6 class="card-subtitle mb-3 text-muted"><?= __('additional_details') ?></h6>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted"><?= __('currency') ?></span>
                                            <strong id="currency">-</strong>
                                        </div>
                                       
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted"><?= __('phone') ?></span>
                                            <strong id="phone">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span class="text-muted"><?= __('gender') ?></span>
                                            <strong id="gender">-</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Description Tab -->
                    <div class="tab-pane fade" id="details-description" role="tabpanel">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <p id="description" class="mb-0">-</p>
                            </div>
                        </div>
                    </div>

                   
                </div>
            </div>
            <div class="modal-footer border-0 bg-light">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="feather icon-x mr-2"></i><?= __('close') ?>
                </button>
                
            </div>
        </div>
    </div>
</div>