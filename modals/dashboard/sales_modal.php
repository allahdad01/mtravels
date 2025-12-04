
                                <!-- Sales Details Modal -->
                                <div class="modal fade" id="salesDetailsModal" tabindex="-1" role="dialog" aria-labelledby="salesDetailsModalLabel" aria-hidden="true">
                                    <div class="modal-dialog modal-lg" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row mb-3">
                                                    <div class="col-md-12">
                                                        <h4 id="salesPeriod"></h4>
                                                        <div class="table-responsive">
                                                            <table class="table table-bordered">
                                                                <tr>
                                                                    <th><?= __('currency') ?></th>
                                                                    <th><?= __('total_amount') ?></th>
                                                                </tr>
                                                                <tr>
                                                                    <td><?= __('usd') ?></td>
                                                                    <td id="salesUsd"></td>
                                                                </tr>
                                                                <tr>
                                                                    <td><?= __('afs') ?></td>
                                                                    <td id="salesAfs"></td>
                                                                </tr>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <h5><?= __('profit_sources') ?></h5>
                                                <div class="table-responsive">
                                                    <table class="table table-hover" id="transactionTable">
                                                        <thead>
                                                            <tr>
                                                                <th><?= __('source') ?></th>
                                                                <th><?= __('usd_profit') ?></th>
                                                                <th><?= __('afs_profit') ?></th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="transactionTableBody">
                                                        </tbody>
                                                    </table>
                                                </div>
                                                
                                                <!-- Transaction Details Section (Initially Hidden) -->
                                                <div id="transactionDetailsSection" class="mt-4" style="display: none;">
                                                    <h5 class="border-top pt-3"><span id="detailsSectionTitle"><?= __('transaction_details') ?></span></h5>
                                                    <div class="table-responsive">
                                                        <table class="table table-striped table-hover">
                                                            <thead id="transactionDetailsHeader">
                                                                <!-- Header will be dynamically generated -->
                                                            </thead>
                                                            <tbody id="transactionDetailsBody">
                                                                <tr>
                                                                    <td colspan="5" class="text-center"><?= __('loading_details') ?></td>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                            <button type="button" class="btn btn-primary" id="printProfitDetails"><i class="feather icon-printer mr-1"></i><?= __('print') ?></button>
                                                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>