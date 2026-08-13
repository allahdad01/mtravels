<!-- Member Dashboard Modal — Phase 23: the operational center of a member.
     Package + financial summary + sold services with fulfillment state + payments. -->
<div class="modal fade" id="memberDashboardModal" tabindex="-1" role="dialog" aria-labelledby="memberDashboardModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header" style="border-bottom: 1px solid #eef2f7; background: linear-gradient(135deg, #0e7490 0%, #155e75 100%);">
                <h5 class="modal-title text-white" id="memberDashboardModalLabel">
                    <i class="feather icon-layout mr-2"></i><?= __('member_dashboard') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="max-height: 75vh; overflow-y: auto; background: #f8fafc;">

                <div id="dashboardMemberInfo" class="mb-3"></div>

                <div id="dashboardFinancial" class="row mb-3"></div>

                <div class="d-flex align-items-center mb-2">
                    <h6 class="mb-0 mr-2"><i class="feather icon-grid mr-2" style="color: #0e7490;"></i><?= __('services') ?></h6>
                    <span class="status-pill status-pill-secondary" id="dashboardServiceCount">0</span>
                </div>
                <div id="dashboardServices" class="mb-3"></div>

                <h6 class="mb-2"><i class="feather icon-credit-card mr-2" style="color: #0e7490;"></i><?= __('recent_payments') ?></h6>
                <div id="dashboardPayments"></div>

            </div>
            <div class="modal-footer" style="border-top: 1px solid #eef2f7;">
                <button type="button" class="btn btn-outline-secondary" id="btnDashboardFulfill" style="border-color: #0e7490; color: #0e7490;">
                    <i class="feather icon-truck mr-2"></i><?= __('fulfill_services') ?>
                </button>
                <button type="button" class="btn btn-outline-secondary" id="btnDashboardPayment" style="border-color: #0e7490; color: #0e7490;">
                    <i class="feather icon-credit-card mr-2"></i><?= __('record_payment') ?>
                </button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
            </div>
        </div>
    </div>
</div>
<script>
window.dashboardLabels = {
    package: <?= json_encode(__('package')) ?>,
    selling_price: <?= json_encode(__('selling_price')) ?>,
    paid: <?= json_encode(__('paid')) ?>,
    due: <?= json_encode(__('due')) ?>,
    profit: <?= json_encode(__('profit')) ?>,
    supplier: <?= json_encode(__('supplier')) ?>,
    status: <?= json_encode(__('status')) ?>,
    supplier_cost: <?= json_encode(__('supplier_cost')) ?>,
    exchange_rate: <?= json_encode(__('exchange_rate')) ?>,
    cost_in_sale: <?= json_encode(__('cost_in_sale')) ?>,
    requested_date: <?= json_encode(__('requested_date')) ?>,
    planned_date: <?= json_encode(__('planned_date')) ?>,
    completed_date: <?= json_encode(__('completed_date')) ?>,
    hotel: <?= json_encode(__('hotel')) ?>,
    room_type: <?= json_encode(__('room_type')) ?>,
    nights: <?= json_encode(__('nights')) ?>,
    check_in: <?= json_encode(__('check_in')) ?>,
    check_out: <?= json_encode(__('check_out')) ?>,
    nightly_rate: <?= json_encode(__('nightly_rate')) ?>,
    ticket_number: <?= json_encode(__('ticket_number')) ?>,
    airline: <?= json_encode(__('airline')) ?>,
    flight_number: <?= json_encode(__('flight_number')) ?>,
    return_flight: <?= json_encode(__('return_flight')) ?>,
    departure: <?= json_encode(__('departure')) ?>,
    arrival: <?= json_encode(__('arrival')) ?>,
    pnr: <?= json_encode(__('pnr')) ?>,
    quantity: <?= json_encode(__('quantity')) ?>,
    optional: <?= json_encode(__('optional')) ?>,
    not_fulfilled: <?= json_encode(__('not_fulfilled')) ?>,
    fulfillment_details: <?= json_encode(__('fulfillment_details')) ?>,
    no_payments_yet: <?= json_encode(__('no_payments_yet')) ?>,
    flight_date: <?= json_encode(__('flight_date')) ?>,
    return_date: <?= json_encode(__('return_date')) ?>,
    payment_date: <?= json_encode(__('payment_date')) ?>,
    payment_description: <?= json_encode(__('payment_description')) ?>,
    receipt: <?= json_encode(__('receipt')) ?>,
    total: <?= json_encode(__('total')) ?>,
    discount: <?= json_encode(__('discount')) ?>
};
</script>
