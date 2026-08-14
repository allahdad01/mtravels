<!-- Fulfillment Modal — Phase 19-21: supplier assignment, actual cost, status -->
<div class="modal fade" id="fulfillmentModal" tabindex="-1" role="dialog" aria-labelledby="fulfillmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header" style="border-bottom: 1px solid #eef2f7;">
                <h5 class="modal-title" id="fulfillmentModalLabel">
                    <i class="feather icon-truck mr-2" style="color: #0e7490;"></i><?= __('fulfill_services') ?>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
    <div class="modal-body" style="max-height: 70vh; overflow-y: auto; background: #f8fafc;">
        <div id="fulfillmentMemberInfo" class="mb-3"></div>
        <div id="fulfillmentServicesContainer"></div>
        <div id="fulfillmentEmptyState" class="text-center py-5 d-none">
            <i class="feather icon-inbox" style="font-size: 2.5rem; color: #adb5bd;"></i>
            <div class="text-muted mt-2"><?= __('no_services_to_fulfill') ?></div>
        </div>
    </div>
    <div class="modal-footer" style="border-top: 1px solid #eef2f7;">
        <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
    </div>
</div>
</div>
<script>
window.fulfillmentLabels = {
    hotel: <?= json_encode(__('hotel')) ?>,
    room_type: <?= json_encode(__('room_type')) ?>,
    floor: <?= json_encode(__('floor')) ?>,
    assign_rooms: 'Assign rooms:',
    room_split_family: 'Per family',
    room_split_member: 'Per member',
    room_split_duration: 'Per duration',
    room_split_gender: 'Per gender',
    gender_split_hint: 'Shared rooms are auto-assigned: same-gender members share a room (males together, females together) and the male/female rooms are placed nearest to each other — regardless of family.',
    add_more_rooms: 'please add more rooms',
    extra_bed: 'Extra bed',
    nights: <?= json_encode(__('nights')) ?>,
    check_in: <?= json_encode(__('check_in')) ?>,
    check_out: <?= json_encode(__('check_out')) ?>,
    nightly_rate: <?= json_encode(__('nightly_rate')) ?>,
    hotel_stays: <?= json_encode(__('hotel_stays')) ?>,
    add_stay: <?= json_encode(__('add_stay')) ?>,
    remove_stay: <?= json_encode(__('remove_stay')) ?>,
    ticket_number: <?= json_encode(__('ticket_number')) ?>,
    airline: <?= json_encode(__('airline')) ?>,
    flight_number: <?= json_encode(__('flight_number')) ?>,
    return_flight: <?= json_encode(__('return_flight')) ?>,
    flight_type: <?= json_encode(__('flight_type')) ?>,
    direct_flight: <?= json_encode(__('direct_flight')) ?>,
    connecting_flight: <?= json_encode(__('connecting_flight')) ?>,
    outbound_journey: <?= json_encode(__('outbound_journey')) ?>,
    return_journey: <?= json_encode(__('return_journey')) ?>,
    first_leg: <?= json_encode(__('first_leg')) ?>,
    second_leg: <?= json_encode(__('second_leg')) ?>,
    return_first_leg: <?= json_encode(__('return_first_leg')) ?>,
    return_second_leg: <?= json_encode(__('return_second_leg')) ?>,
    departure_city: <?= json_encode(__('departure_city')) ?>,
    arrival_city: <?= json_encode(__('arrival_city')) ?>,
    departure_date: <?= json_encode(__('departure_date')) ?>,
    departure_time: <?= json_encode(__('departure_time')) ?>,
    arrival_date: <?= json_encode(__('arrival_date')) ?>,
    arrival_time: <?= json_encode(__('arrival_time')) ?>,
    stopover_city: <?= json_encode(__('stopover_city')) ?>,
    final_destination: <?= json_encode(__('final_destination')) ?>,
    stopover_duration: <?= json_encode(__('stopover_duration')) ?>,
    calculating: <?= json_encode(__('calculating')) ?>,
    print_ticket: <?= json_encode(__('print_ticket')) ?>,
    outbound_flight_number: <?= json_encode(__('outbound_flight_number')) ?>,
    return_flight_number: <?= json_encode(__('return_flight_number')) ?>,
    same_departure: <?= json_encode(__('same_departure')) ?>,
    same_pnr: <?= json_encode(__('same_pnr')) ?>,
    same_flight_hint: <?= json_encode(__('same_flight_hint')) ?>,
    grouped_hotel_hint: <?= json_encode(__('grouped_hotel_hint')) ?>,
    return_for_duration: <?= json_encode(__('return_for_duration')) ?>,
    return_unspecified_duration: <?= json_encode(__('return_unspecified_duration')) ?>,
    departure: <?= json_encode(__('departure')) ?>,
    arrival: <?= json_encode(__('arrival')) ?>,
    supplier: <?= json_encode(__('supplier')) ?>,
    select_supplier: <?= json_encode(__('select_supplier')) ?>,
    status: <?= json_encode(__('status')) ?>,
    supplier_currency: <?= json_encode(__('supplier_currency')) ?>,
    supplier_cost: <?= json_encode(__('supplier_cost')) ?>,
    exchange_rate: <?= json_encode(__('exchange_rate')) ?>,
    cost_in_sale: <?= json_encode(__('cost_in_sale')) ?>,
    planned_date: <?= json_encode(__('planned_date')) ?>,
    completed_date: <?= json_encode(__('completed_date')) ?>,
    notes: <?= json_encode(__('notes')) ?>,
    save: <?= json_encode(__('save')) ?>,
    contract: <?= json_encode(__('contract')) ?>,
    contract_type_period: <?= json_encode(__('contract_type_period')) ?>,
    per_trip: <?= json_encode(__('per_trip')) ?>,
    vehicle: <?= json_encode(__('vehicle')) ?>,
    vehicle_placeholder: <?= json_encode(__('vehicle_placeholder')) ?>,
    trip_date: <?= json_encode(__('trip_date')) ?>,
    trip_members: <?= json_encode(__('trip_members')) ?>,
    booking_reference_number: <?= json_encode(__('booking_reference_number')) ?>,
    enter_rate_per_member: <?= json_encode(__('enter_rate_per_member')) ?>,
    remove_brn_hint: <?= json_encode(__('remove_brn_hint')) ?>,
    apply_to: <?= json_encode(__('apply_to')) ?>,
    all_family_members: <?= json_encode(__('all_family_members')) ?>,
    entire_group: <?= json_encode(__('entire_group')) ?>,
    applying_to: <?= json_encode(__('applying_to')) ?>,
    show_hide_brn_fields: <?= json_encode(__('show_hide_brn_fields')) ?>
};
</script>
</div>
