                                <!-- Debtors Modal -->
                                <div class="modal fade" id="debtorsModal" tabindex="-1">
                                    <div class="modal-dialog modal-lg modal-dialog-centered">
                                        <div class="modal-content border-0">
                                            <div class="modal-header bg-light border-0">
                                                <h5 class="modal-title" id="debtorsModalTitle"><?= __('debtors_list') ?></h5>
                                                <button type="button" class="close" data-dismiss="modal">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="table-responsive">
                                                    <table class="table table-hover">
                                                        <thead>
                                                            <tr>
                                                                <th><?= __('name') ?></th>
                                                                <th><?= __('pnr') ?></th>
                                                                <th><?= __('phone') ?></th>
                                                                <th><?= __('amount_due') ?></th>
                                                                <th><?= __('date') ?></th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="debtorsTableBody">
                                                            <!-- Will be populated dynamically -->
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
