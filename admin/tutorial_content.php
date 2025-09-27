<?php
// Tutorial Content Sections
?>

<!-- Ticket Management Tutorials -->
<div id="book-tickets" class="tutorial-content">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0"><i class="fas fa-ticket-alt me-2"></i><?= __('how_to_book_new_tickets') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('book-tickets')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                <strong><?= __('complete_process') ?>:</strong> <?= __('this_tutorial_covers_the_entire_ticket_booking_process_from_opening_the_booking_form_to_adding_transactions') ?>
            </div>

            <!-- Step 1: Navigate and Open Booking Form -->
            <div class="step-item">
                <span class="step-number">1</span>
                <strong><?= __('navigate_to_ticket_management') ?></strong>
                <p><?= __('go_to') ?> <strong><?= __('bookings_tickets_book_tickets') ?></strong> <?= __('from_the_main_menu_to_access_the_ticket_management_page') ?></p>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot') ?>: <?= __('ticket_management_page_with_book_ticket_button') ?>
                    <img src="../uploads/tutorials/ticket_tutorials/book-tickets.png" alt="" class="img-fluid w-100 rounded">
                </div>
            </div>

            <!-- Step 2: Click Book Ticket Button -->
            <div class="step-item">
                <span class="step-number">2</span>
                <strong><?= __('open_booking_form') ?></strong>
                <p><?= __('click_the_blue') ?> <strong><?= __('book_ticket') ?></strong> <?= __('button_on_the_top_right_of_the_page') ?> <?= __('this_will_open_the_ticket_booking_modal_form') ?></p>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot') ?>: <?= __('clicking_the_book_ticket_button') ?>
                    <img src="../uploads/tutorials/ticket_tutorials/book-tickets.png" alt="" class="img-fluid w-100 rounded">
                </div>
            </div>

            <!-- Step 3: Fill Booking Details -->
            <div class="step-item">
                <span class="step-number">3</span>
                <strong><?= __('complete_booking_details_section') ?></strong>
                <p><?= __('fill_in_the_following_information_in_the') ?> <strong><?= __('booking_details') ?></strong> <?= __('section') ?>:</p>
                <ul class="mt-2 mb-2">
                    <li><strong><?= __('supplier') ?></strong> <?= __('select_the_ticket_supplier_from_the_dropdown') ?></li>
                    <li><strong><?= __('sold_to') ?></strong> <?= __('choose_the_client_who_is_purchasing_the_ticket') ?></li>
                    <li><strong><?= __('trip_type') ?></strong> <?= __('select_one_way_or_round_trip') ?></li>
                    <li><strong><?= __('passenger_counts') ?></strong> <?= __('specify_number_of_adults_children_and_infants') ?></li>
                </ul>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot') ?>: <?= __('booking_details_section_filled_out') ?>
                    <img src="../uploads/tutorials/ticket_tutorials/book-tickets.png" alt="" class="img-fluid w-100 rounded">
                </div>
            </div>

            <!-- Step 4: Enter Passenger Information -->
            <div class="step-item">
                <span class="step-number">4</span>
                <strong><?= __('enter_passenger_information') ?></strong>
                <p><?= __('complete_the') ?> <strong><?= __('passenger_information') ?></strong> <?= __('section_for_each_traveler') ?>:</p>
                <ul class="mt-2 mb-2">
                    <li><strong><?= __('title') ?></strong> <?= __('mr_mrs_ms_child_or_infant') ?></li>
                    <li><strong><?= __('gender') ?></strong> <?= __('male_or_female') ?></li>
                    <li><strong><?= __('passenger_name') ?></strong> <?= __('full_name_as_on_passport') ?></li>
                    <li><strong><?= __('phone') ?></strong> <?= __('contact_number') ?></li>
                    <li><strong><?= __('pricing') ?></strong> <?= __('base_amount_sold_amount_discount_if_any') ?></li>
                </ul>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong><?= __('note') ?>:</strong> <?= __('the_profit_will_be_calculated_automatically_based_on_base_and_sold_amounts') ?>
                </div>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot') ?>: <?= __('passenger_information_section_with_details_filled') ?>
                    <img src="../uploads/tutorials/ticket_tutorials/book-tickets.png" alt="" class="img-fluid w-100 rounded">
                </div>
            </div>

            <!-- Step 5: Flight Details -->
            <div class="step-item">
                <span class="step-number">5</span>
                <strong><?= __('complete_flight_details') ?></strong>
                <p><?= __('fill_in_the') ?> <strong><?= __('flight_details') ?></strong> <?= __('section') ?>:</p>
                <ul class="mt-2 mb-2">
                    <li><strong><?= __('pnr') ?></strong> <?= __('passenger_name_record_number') ?></li>
                    <li><strong><?= __('from_to') ?></strong> <?= __('origin_and_destination_cities') ?></li>
                    <li><strong><?= __('return_to') ?></strong> <?= __('only_for_round_trips') ?></li>
                    <li><strong><?= __('airline') ?></strong> <?= __('select_from_the_dropdown_list') ?></li>
                    <li><strong><?= __('issue_date') ?></strong> <?= __('date_ticket_was_issued') ?></li>
                    <li><strong><?= __('departure_date') ?></strong> <?= __('flight_departure_date') ?></li>
                    <li><strong><?= __('return_date') ?></strong> <?= __('only_for_round_trips') ?></li>
                    <li><strong><?= __('market_exchange_rate') ?></strong> <?= __('current_market_rate') ?></li>
                </ul>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot') ?>: <?= __('flight_details_section_completed') ?>
                    <img src="../uploads/tutorials/ticket_tutorials/book-tickets.png" alt="" class="img-fluid w-100 rounded">
                </div>
            </div>

            <!-- Step 6: Payment Information -->
            <div class="step-item">
                <span class="step-number">6</span>
                <strong><?= __('set_payment_information') ?></strong>
                <p><?= __('complete_the') ?> <strong><?= __('payment_information') ?></strong> <?= __('section') ?>:</p>
                <ul class="mt-2 mb-2">
                    <li><strong><?= __('exchange_rate') ?></strong> <?= __('rate_used_for_calculation') ?></li>
                    <li><strong><?= __('currency') ?></strong> <?= __('will_be_set_automatically_based_on_supplier') ?></li>
                    <li><strong><?= __('description') ?></strong> <?= __('optional_notes_about_the_booking') ?></li>
                    <li><strong><?= __('base_sold_discount_profit') ?></strong> <?= __('summary_totals_calculated_automatically') ?></li>
                    <li><strong><?= __('paid_to') ?></strong> <?= __('select_the_main_account_that_will_receive_payment') ?></li>
                </ul>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot') ?>: <?= __('payment_information_section_filled') ?>
                    <img src="../uploads/tutorials/ticket_tutorials/book-tickets.png" alt="" class="img-fluid w-100 rounded">
                </div>
            </div>

            <!-- Step 7: Save Ticket -->
            <div class="step-item">
                <span class="step-number">7</span>
                <strong><?= __('book_the_ticket') ?></strong>
                <p><?= __('click_the') ?> <strong><?= __('book') ?></strong> <?= __('button_to_save_the_ticket') ?> <?= __('the_system_will') ?>:</p>
                <ul class="mt-2 mb-2">
                    <li><?= __('create_the_ticket_record_in_the_database') ?></li>
                    <li><?= __('update_account_balances_automatically') ?></li>
                    <li><?= __('generate_a_unique_ticket_id') ?></li>
                    <li><?= __('close_the_booking_modal') ?></li>
                    <li><?= __('show_a_success_message') ?></li>
                    <li><?= __('refresh_the_ticket_list_to_show_the_new_booking') ?></li>
                </ul>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot') ?>: <?= __('success_message_after_booking') ?>
                    <img src="../uploads/tutorials/ticket_tutorials/book-tickets.png" alt="" class="img-fluid w-100 rounded">
                </div>
            </div>

            <!-- Step 8: Locate New Ticket -->
            <div class="step-item">
                <span class="step-number">8</span>
                <strong><?= __('find_your_new_ticket') ?></strong>
                <p><?= __('the_newly_created_ticket_will_appear_in_the_ticket_list') ?> <?= __('you_can_identify_it_by') ?>:</p>
                <ul class="mt-2 mb-2">
                    <li><?= __('passenger_name_and_pnr_number') ?></li>
                    <li><?= __('flight_details_origin_destination_airline') ?></li>
                    <li><?= __('booking_date_and_amount') ?></li>
                    <li><?= __('payment_status_indicator_usually_unpaid_for_new_tickets') ?></li>
                </ul>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot') ?>: <?= __('new_ticket_appearing_in_the_ticket_list') ?>
                    <img src="../uploads/tutorials/ticket_tutorials/book-tickets.png" alt="" class="img-fluid w-100 rounded">
                </div>
            </div>

            <!-- Step 9: Access Transaction Management -->
            <div class="step-item">
                <span class="step-number">9</span>
                <strong><?= __('open_transaction_management') ?></strong>
                <p><?= __('to_add_payments_for_the_ticket') ?>:</p>
                <ol class="mt-2 mb-2">
                    <li><?= __('find_your_ticket_in_the_list') ?></li>
                    <li><?= __('click_the') ?> <strong><?= __('three-dot_menu_action_column') ?></strong> <?= __('in_the_action_column') ?></li>
                    <li><?= __('select') ?> <strong><?= __('manage_transactions') ?></strong> <?= __('from_the_dropdown') ?></li>
                </ol>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot') ?>: <?= __('actions_dropdown_menu_with_manage_transactions_option') ?>
                    <img src="../uploads/tutorials/ticket_tutorials/book-tickets.png" alt="" class="img-fluid w-100 rounded">
                </div>
            </div>

            <!-- Step 10: Transaction Management Modal -->
            <div class="step-item">
                <span class="step-number">10</span>
                <strong><?= __('review_transaction_overview') ?></strong>
                <p><?= __('the_transaction_management_modal_will_open_showing') ?>:</p>
                <ul class="mt-2 mb-2">
                    <li><strong><?= __('ticket_details') ?></strong> <?= __('passenger_name_and_pnr') ?></li>
                    <li><strong><?= __('financial_summary') ?></strong> <?= __('total_amount_exchange_rate_converted_amounts') ?></li>
                    <li><strong><?= __('payment_status') ?></strong> <?= __('paid_and_remaining_amounts_in_different_currencies') ?></li>
                    <li><strong><?= __('transaction_history') ?></strong> <?= __('all_existing_payments_if_any') ?></li>
                </ul>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot') ?>: <?= __('transaction_management_modal_opened') ?>
                    <img src="../uploads/tutorials/ticket_tutorials/book-tickets.png" alt="" class="img-fluid w-100 rounded">
                </div>
            </div>

            <!-- Step 11: Add New Transaction -->
            <div class="step-item">
                <span class="step-number">11</span>
                <strong><?= __('add_a_new_transaction') ?></strong>
                <p><?= __('to_record_a_payment') ?>:</p>
                <ol class="mt-2 mb-2">
                    <li><?= __('click_the') ?> <strong><?= __('new_transaction') ?></strong> <?= __('button_in_the_transaction_history_section') ?></li>
                    <li><?= __('this_will_expand_the') ?> <strong><?= __('add_new_transaction_form_below') ?></strong></li>
                </ol>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot') ?>: <?= __('clicking_new_transaction_button') ?>
                    <img src="../uploads/tutorials/ticket_tutorials/book-tickets.png" alt="" class="img-fluid w-100 rounded">
                </div>
            </div>

            <!-- Step 12: Fill Transaction Form -->
            <div class="step-item">
                <span class="step-number">12</span>
                <strong><?= __('complete_transaction_details') ?></strong>
                <p><?= __('fill_in_the_transaction_form_with_the_following_information') ?>:</p>
                <ul class="mt-2 mb-2">
                    <li><strong><?= __('payment_date') ?></strong> <?= __('date_when_payment_was_received') ?></li>
                    <li><strong><?= __('payment_time') ?></strong> <?= __('time_of_payment_hours_minutes_seconds') ?></li>
                    <li><strong><?= __('amount') ?></strong> <?= __('payment_amount_in_the_selected_currency') ?></li>
                    <li><strong><?= __('currency') ?></strong> <?= __('select_usd_afs_eur_or_darham') ?></li>
                    <li><strong><?= __('description') ?></strong> <?= __('notes_about_the_payment_e_g_partial_payment_full_payment_cash_payment') ?></li>
                </ul>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong><?= __('tip') ?>:</strong> <?= __('the_system_will_automatically_convert_amounts_based_on_the_ticket_exchange_rate') ?>
                </div>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot') ?>: <?= __('transaction_form_filled_with_payment_details') ?>
                    <img src="../uploads/tutorials/ticket_tutorials/book-tickets.png" alt="" class="img-fluid w-100 rounded">
                </div>
            </div>

            <!-- Step 13: Save Transaction -->
            <div class="step-item">
                <span class="step-number">13</span>
                <strong><?= __('save_the_transaction') ?></strong>
                <p><?= __('click_the') ?> <strong><?= __('add_transaction') ?></strong> <?= __('button_to_save_the_payment') ?> <?= __('the_system_will') ?>:</p>
                <ul class="mt-2 mb-2">
                    <li><?= __('record_the_transaction_in_the_database') ?></li>
                    <li><?= __('update_the_payment_status_and_remaining_balance') ?></li>
                    <li><?= __('add_the_transaction_to_the_history_table') ?></li>
                    <li><?= __('update_the_ticket_payment_indicator') ?></li>
                    <li><?= __('show_a_success_confirmation') ?></li>
                </ul>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot') ?>: <?= __('transaction_saved_successfully') ?>
                    <img src="../uploads/tutorials/ticket_tutorials/book-tickets.png" alt="" class="img-fluid w-100 rounded">
                </div>
            </div>

            <!-- Step 14: Verify Updates -->
            <div class="step-item">
                <span class="step-number">14</span>
                <strong><?= __('verify_payment_status') ?></strong>
                <p><?= __('after_saving_the_transaction_verify_the_updates') ?>:</p>
                <ul class="mt-2 mb-2">
                    <li><strong><?= __('payment_summary') ?></strong> <?= __('check_updated_paid_and_remaining_amounts') ?></li>
                    <li><strong><?= __('transaction_history') ?></strong> <?= __('new_transaction_appears_in_the_table') ?></li>
                    <li><strong><?= __('payment_status') ?></strong> <?= __('color-coded_status_red_unpaid_yellow_partial_green_paid') ?></li>
                </ul>
                <p><?= __('close_the_transaction_modal_to_return_to_the_ticket_list_where_the_payment_indicator_will_be_updated') ?>.</p>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot') ?>: <?= __('updated_payment_status_and_transaction_history') ?>
                    <img src="../uploads/tutorials/ticket_tutorials/book-tickets.png" alt="" class="img-fluid w-100 rounded">
                </div>
            </div>

            <div class="alert alert-success mt-4">
                <i class="fas fa-check-circle me-2"></i>
                <strong><?= __('process_complete') ?></strong> <?= __('you_have_successfully_booked_a_ticket_and_recorded_a_payment_transaction') ?> <?= __('the_ticket_status_will_now_reflect_the_payment_received') ?>
            </div>
        </div>
    </div>
</div>

<div id="refund-tickets" class="tutorial-content">
    <div class="card">
        <div class="card-header bg-warning text-white">
            <h4 class="mb-0"><i class="fas fa-undo me-2"></i><?= __('how_to_process_ticket_refunds') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('refund-tickets')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                <strong><?= __('complete_process') ?>:</strong> <?= __('this_tutorial_covers_the_entire_ticket_refund_process_from_accessing_the_refund_page_to_managing_refund_transactions') ?>
            </div>

            <!-- Step 1: Navigate to Refund Tickets -->
            <div class="step-item">
                <span class="step-number">1</span>
                <strong><?= __('navigate_to_refund_tickets') ?></strong>
                <p><?= __('go_to') ?> <strong><?= __('bookings_tickets_refund_tickets') ?></strong> <?= __('from_the_main_menu_to_access_the_refund_tickets_management_page') ?>.</p>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot') ?>: <?= __('refund_tickets_page_with_statistics_and_add_refund_ticket_button') ?>
                    <img src="../uploads/tutorials/ticket_tutorials/book-tickets.png" alt="" class="img-fluid w-100 rounded">
                </div>
            </div>

            <!-- Step 2: Open Add Refund Modal -->
            <div class="step-item">
                <span class="step-number">2</span>
                <strong><?= __('start_refund_process') ?></strong>
                <p><?= __('click_the') ?> <strong><?= __('add_refund_ticket') ?></strong> <?= __('button_on_the_top_right_of_the_page') ?> <?= __('this_will_open_the_refund_ticket_modal_where_you_can_search_for_existing_tickets_to_refund') ?>.</p>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot') ?>: <?= __('clicking_add_refund_ticket_button') ?>
                    <img src="../uploads/tutorials/ticket_tutorials/book-tickets.png" alt="" class="img-fluid w-100 rounded">
                </div>
            </div>

            <!-- Step 3: Search for Ticket -->
            <div class="step-item">
                <span class="step-number">3</span>
                <strong><?= __('search_for_original_ticket') ?></strong>
                <p><?= __('in_the_search_section_you_can_find_the_ticket_to_refund_using_either_method') ?>:</p>
                <ul class="mt-2 mb-2">
                    <li><strong><?= __('search_by_pnr') ?></strong> <?= __('enter_the_passenger_name_record_number_and_click_the_search_button') ?></li>
                    <li><strong><?= __('search_by_name') ?></strong> <?= __('enter_the_passengers_name_and_click_the_search_button') ?></li>
                </ul>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong><?= __('note') ?>:</strong> <?= __('only_tickets_that_havent_been_refunded_previously_will_appear_in_search_results') ?>
                </div>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot') ?>: <?= __('search_section_with_pnr_and_passenger_name_fields') ?>
                    <img src="../uploads/tutorials/ticket_tutorials/book-tickets.png" alt="" class="img-fluid w-100 rounded">
                </div>
            </div>

            <!-- Step 4: Select Ticket from Results -->
            <div class="step-item">
                <span class="step-number">4</span>
                <strong><?= __('select_ticket_for_refund') ?></strong>
                <p><?= __('the_search_results_will_display_matching_tickets_in_a_table_showing') ?>:</p>
                <ul class="mt-2 mb-2">
                    <li><strong><?= __('passenger_name') ?></strong> <?= __('full_name_of_the_traveler') ?></li>
                    <li><strong><?= __('pnr') ?></strong> <?= __('booking_reference_number') ?></li>
                    <li><strong><?= __('flight_details') ?></strong> <?= __('route_airline_and_dates') ?></li>
                    <li><strong><?= __('action_button') ?></strong> <?= __('select_for_refund_button') ?></li>
                </ul>
                <p><?= __('click_the') ?> <strong><?= __('select_for_refund') ?></strong> <?= __('button_next_to_the_ticket_you_want_to_refund') ?>.</p>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot') ?>: <?= __('search_results_table_with_selectable_tickets') ?>
                    <img src="../uploads/tutorials/ticket_tutorials/book-tickets.png" alt="" class="img-fluid w-100 rounded">
                </div>
            </div>

            <!-- Step 5: Review Ticket Information -->
            <div class="step-item">
                <span class="step-number">5</span>
                <strong><?= __('review_original_ticket_details') ?></strong>
                <p><?= __('after_selecting_a_ticket_the_refund_form_will_appear_showing_the_original_ticket_information') ?>:</p>
                <ul class="mt-2 mb-2">
                    <li><strong><?= __('base_price') ?></strong> <?= __('original_cost_from_supplier_read_only') ?></li>
                    <li><strong><?= __('sold_price') ?></strong> <?= __('amount_charged_to_customer_read_only') ?></li>
                    <li><strong><?= __('currency') ?></strong> <?= __('original_ticket_currency') ?></li>
                </ul>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot') ?>: <?= __('refund_form_showing_original_ticket_prices') ?>
                    <img src="../uploads/tutorials/ticket_tutorials/book-tickets.png" alt="" class="img-fluid w-100 rounded">
                </div>
            </div>

            <!-- Step 6: Enter Penalties -->
            <div class="step-item">
                <span class="step-number">6</span>
                <strong><?= __('enter_penalty_amounts') ?></strong>
                <p><?= __('input_the_penalty_charges_that_apply_to_this_refund') ?>:</p>
                <ul class="mt-2 mb-2">
                    <li><strong><?= __('supplier_penalty') ?></strong> <?= __('cancellation_fee_charged_by_the_airline_supplier') ?></li>
                    <li><strong><?= __('service_penalty') ?></strong> <?= __('your_agencys_service_fee_for_processing_the_refund') ?></li>
                    <li><strong><?= __('total_penalty') ?></strong> <?= __('automatically_calculated_sum_of_both_penalties') ?></li>
                </ul>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong><?= __('tip') ?>:</strong> <?= __('check_with_the_airline_for_their_current_cancellation_policy_and_penalty_structure') ?>
                </div>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot') ?>: <?= __('penalty_fields_with_supplier_and_service_penalty_amounts') ?>
                    <img src="../uploads/tutorials/ticket_tutorials/book-tickets.png" alt="" class="img-fluid w-100 rounded">
                </div>
            </div>

            <!-- Step 7: Select Calculation Method -->
            <div class="step-item">
                <span class="step-number">7</span>
                <strong><?= __('choose_calculation_method') ?></strong>
                <p><?= __('select_how_the_refund_should_be_calculated_from_the_dropdown') ?>:</p>
                <ul class="mt-2 mb-2">
                    <li><strong><?= __('calculate_from_sold') ?></strong> <?= __('refund_sold_price_total_penalties') ?></li>
                    <li><strong><?= __('calculate_from_base') ?></strong> <?= __('refund_base_price_total_penalties') ?></li>
                </ul>
                <p><?= __('the_refund_amount_will_be_automatically_calculated_and_displayed_based_on_your_selection') ?>.</p>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot') ?>: <?= __('calculation_method_dropdown_with_automatic_refund_calculation') ?>
                    <img src="../uploads/tutorials/ticket_tutorials/book-tickets.png" alt="" class="img-fluid w-100 rounded">
                </div>
            </div>

            <!-- Step 8: Add Description -->
            <div class="step-item">
                <span class="step-number">8</span>
                <strong><?= __('add_refund_description') ?></strong>
                <p><?= __('enter_a_detailed_description_of_the_refund_including') ?>:</p>
                <ul class="mt-2 mb-2">
                    <li><?= __('reason_for_cancellation_refund') ?></li>
                    <li><?= __('any_special_circumstances') ?></li>
                    <li><?= __('reference_to_airline_policies_applied') ?></li>
                    <li><?= __('customer_communication_notes') ?></li>
                </ul>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot') ?>: <?= __('description_textarea_with_sample_refund_notes') ?>
                    <img src="../uploads/tutorials/ticket_tutorials/book-tickets.png" alt="" class="img-fluid w-100 rounded">
                </div>
            </div>

            <!-- Step 9: Save Refund Ticket -->
            <div class="step-item">
                <span class="step-number">9</span>
                <strong><?= __('save_the_refund') ?></strong>
                <p><?= __('click_the') ?> <strong><?= __('save') ?></strong> <?= __('button_to_process_the_refund') ?> <?= __('the_system_will') ?>:</p>
                <ul class="mt-2 mb-2">
                    <li><?= __('create_a_refund_ticket_record') ?></li>
                    <li><?= __('set_initial_status_as_pending') ?></li>
                    <li><?= __('calculate_final_refund_amount') ?></li>
                    <li><?= __('close_the_modal_and_refresh_the_refund_tickets_list') ?></li>
                    <li><?= __('show_a_success_confirmation_message') ?></li>
                </ul>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot') ?>: <?= __('success_message_after_saving_refund_ticket') ?>
                    <img src="../uploads/tutorials/ticket_tutorials/book-tickets.png" alt="" class="img-fluid w-100 rounded">
                </div>
            </div>

            <!-- Step 10: Locate Refunded Ticket -->
            <div class="step-item">
                <span class="step-number">10</span>
                <strong><?= __('find_your_refunded_ticket') ?></strong>
                <p><?= __('the_new_refunded_ticket_will_appear_in_the_main_refund_tickets_table_showing') ?>:</p>
                <ul class="mt-2 mb-2">
                    <li><strong><?= __('passenger_details') ?></strong> <?= __('name_pnr_phone_created_by') ?></li>
                    <li><strong><?= __('flight_info') ?></strong> <?= __('route_and_airline_information') ?></li>
                    <li><strong><?= __('financial_details') ?></strong> <?= __('base_and_sold_amounts') ?></li>
                    <li><strong><?= __('penalties') ?></strong> <?= __('supplier_and_service_penalty_amounts') ?></li>
                    <li><strong><?= __('refund_amount') ?></strong> <?= __('final_amount_to_be_paid_to_passenger') ?></li>
                    <li><strong><?= __('payment_status') ?></strong> <?= __('color-coded_indicator_red_unpaid_yellow_partial_green_paid') ?></li>
                </ul>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot') ?>: <?= __('new_refunded_ticket_in_the_main_list') ?>
                    <img src="../uploads/tutorials/ticket_tutorials/book-tickets.png" alt="" class="img-fluid w-100 rounded">
                </div>
            </div>

            <!-- Step 11: Manage Refund Payments -->
            <div class="step-item">
                <span class="step-number">11</span>
                <strong><?= __('process_refund_payments') ?></strong>
                <p><?= __('to_add_payment_transactions_for_the_refund') ?>:</p>
                <ol class="mt-2 mb-2">
                    <li><?= __('click_the') ?> <strong><?= __('three-dot_menu_actions_column') ?></strong> <?= __('in_the_actions_column') ?></li>
                    <li><?= __('select') ?> <strong><?= __('manage_payments') ?></strong> <?= __('from_the_dropdown') ?></li>
                    <li><?= __('this_opens_the_transaction_management_modal') ?></li>
                    <li><?= __('follow_the_same_process_as_regular_ticket_transactions_to_record_payments') ?></li>
                </ol>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot') ?>: <?= __('actions_dropdown_with_manage_payments_option') ?>
                    <img src="../uploads/tutorials/ticket_tutorials/book-tickets.png" alt="" class="img-fluid w-100 rounded">
                </div>
            </div>

            <!-- Step 12: Edit Penalties (Optional) -->
            <div class="step-item">
                <span class="step-number">12</span>
                <strong><?= __('edit_penalties_if_needed') ?></strong>
                <p><?= __('if_you_need_to_modify_penalty_amounts_after_creating_the_refund') ?>:</p>
                <ol class="mt-2 mb-2">
                    <li><?= __('click_the_actions_menu_for_the_refunded_ticket') ?></li>
                    <li><?= __('select') ?> <strong><?= __('edit_penalties') ?></strong></li>
                    <li><?= __('modify_supplier_or_service_penalty_amounts') ?></li>
                    <li><?= __('the_refund_amount_will_be_recalculated_automatically') ?></li>
                    <li><?= __('save_the_changes') ?></li>
                </ol>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong><?= __('caution') ?>:</strong> <?= __('editing_penalties_affects_the_refund_amount_and_any_existing_payment_calculations') ?>
                </div>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot') ?>: <?= __('edit_penalties_modal_with_updated_amounts') ?>
                    <img src="../uploads/tutorials/ticket_tutorials/book-tickets.png" alt="" class="img-fluid w-100 rounded">
                </div>
            </div>

            <!-- Step 13: Print Refund Agreement -->
            <div class="step-item">
                <span class="step-number">13</span>
                <strong><?= __('generate_documentation') ?></strong>
                <p><?= __('to_create_official_refund_documentation') ?>:</p>
                <ol class="mt-2 mb-2">
                    <li><?= __('click_the_actions_menu_for_the_refunded_ticket') ?></li>
                    <li><?= __('select') ?> <strong><?= __('print_refund_agreement') ?></strong></li>
                    <li><?= __('a_pdf_document_will_be_generated_with_refund_details') ?></li>
                    <li><?= __('print_or_save_the_document_for_your_records') ?></li>
                    <li><?= __('provide_a_copy_to_the_customer_as_needed') ?></li>
                </ol>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot') ?>: <?= __('generated_refund_agreement_document') ?>
                    <img src="../uploads/tutorials/ticket_tutorials/book-tickets.png" alt="" class="img-fluid w-100 rounded">
                </div>
            </div>

            <!-- Step 14: Monitor Payment Status -->
            <div class="step-item">
                <span class="step-number">14</span>
                <strong><?= __('track_refund_status') ?></strong>
                <p><?= __('monitor_the_refund_payment_status_using_the_indicators_in_the_main_table') ?>:</p>
                <ul class="mt-2 mb-2">
                    <li><strong><?= __('red_circle') ?></strong> <?= __('no_payment_made_yet') ?></li>
                    <li><strong><?= __('yellow_circle') ?></strong> <?= __('partial_payment_made') ?></li>
                    <li><strong><?= __('green_circle') ?></strong> <?= __('fully_paid_completed') ?></li>
                    <li><strong><?= __('gray_minus') ?></strong> <?= __('not_applicable_non_agency_client') ?></li>
                </ul>
                <p><?= __('the_system_tracks_all_payments_and_automatically_updates_the_status_as_transactions_are_added') ?>.</p>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot') ?>: <?= __('payment_status_indicators_in_the_refund_tickets_table') ?>
                    <img src="../uploads/tutorials/ticket_tutorials/book-tickets.png" alt="" class="img-fluid w-100 rounded">
                </div>
            </div>

            <div class="alert alert-success mt-4">
                <i class="fas fa-check-circle me-2"></i>
                <strong><?= __('refund_process_complete') ?></strong> <?= __('you_have_successfully_processed_a_ticket_refund') ?> <?= __('the_refund_record_is_now_in_the_system_and_you_can_manage_payments_and_track_the_status_until_completion') ?>
            </div>
            <div class="alert alert-info mt-3">
                <i class="fas fa-lightbulb me-2"></i>
                <strong><?= __('best_practices') ?>:</strong>
                <ul class="mb-0 mt-2">
                    <li><?= __('always_verify_airline_refund_policies_before_processing') ?></li>
                    <li><?= __('document_the_reason_for_refund_clearly') ?></li>
                    <li><?= __('keep_copies_of_all_refund_agreements') ?></li>
                    <li><?= __('communicate_with_customers_about_refund_timelines') ?></li>
                    <li><?= __('monitor_payment_status_regularly') ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Salary Management Tutorials -->
<div id="salary-management-overview" class="tutorial-content">
    <div class="card">
        <div class="card-header bg-finance text-white">
            <h4 class="mb-0"><i class="fas fa-dollar-sign me-2"></i><?= __('salary_management_overview') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('salary-management-overview')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i>
                <strong><?= __('overview') ?>:</strong> <?= __('comprehensive_guide_to_managing_employee_salaries_including_adding_editing_and_tracking_salary_records') ?>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-list me-2"></i><?= __('salary_management_dashboard') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('accessing_salary_management') ?></h6>
                    <p><?= __('navigate_to_the_salary_management_page_from_the_admin_dashboard') ?> <?= __('the_page_displays_a_comprehensive_list_of_all_employee_salary_records') ?>.</p>
                </div>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-plus me-2"></i><?= __('adding_new_salary_record') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('select_employee') ?></h6>
                    <p><?= __('click_on_the') ?> <strong><?= __('add_new_salary_record') ?></strong> <?= __('button') ?> <?= __('choose_an_employee_without_an_existing_salary_record_from_the_dropdown') ?>.</p>
                    <div class="screenshot-placeholder" data-description="<?= __('employee_selection_dropdown_in_new_salary_record_form') ?>">
                        <img src="../uploads/tutorials/ticket_tutorials/book-tickets.png" alt="" class="img-fluid w-100 rounded">
                    </div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">2</span>
                <div class="step-content">
                    <h6><?= __('enter_salary_details') ?></h6>
                    <p><?= __('fill_in_the_following_details') ?>:
                        <ul>
                            <li><?= __('base_salary') ?></li>
                            <li><?= __('currency_usd_or_afs') ?></li>
                            <li><?= __('joining_date') ?></li>
                            <li><?= __('monthly_payment_day') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="<?= __('salary_details_input_form_with_all_fields') ?>">
                        <img src="../uploads/tutorials/ticket_tutorials/book-tickets.png" alt="" class="img-fluid w-100 rounded">
                    </div>
                </div>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-edit me-2"></i><?= __('editing_salary_records') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('access_edit_modal') ?></h6>
                    <p><?= __('in_the_salary_records_table_click_the_action_dropdown_for_the_specific_employee_and_select_edit_salary') ?>.</p>
                    <div class="screenshot-placeholder" data-description="<?= __('action_dropdown_with_edit_salary_option') ?>">
                        <img src="../uploads/tutorials/ticket_tutorials/book-tickets.png" alt="" class="img-fluid w-100 rounded">
                    </div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">2</span>
                <div class="step-content">
                    <h6><?= __('modify_salary_information') ?></h6>
                    <p><?= __('in_the_edit_modal_you_can_update') ?>:
                        <ul>
                            <li><?= __('base_salary') ?></li>
                            <li><?= __('currency') ?></li>
                            <li><?= __('payment_day') ?></li>
                            <li><?= __('employment_status_active_inactive') ?></li>
                            <li><?= __('firing_status') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="<?= __('salary_edit_modal_with_all_editable_fields') ?>">
                        <img src="../uploads/tutorials/ticket_tutorials/book-tickets.png" alt="" class="img-fluid w-100 rounded">
                    </div>
                </div>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-dollar-sign me-2"></i><?= __('additional_salary_management_features') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('manage_bonuses') ?></h6>
                    <p><?= __('click_the') ?> <strong><?= __('manage_bonuses') ?></strong> <?= __('button_to_add_or_track_employee_bonuses') ?>.</p>
                    <div class="screenshot-placeholder" data-description="<?= __('bonuses_management_page') ?>">
                        <img src="../uploads/tutorials/ticket_tutorials/book-tickets.png" alt="" class="img-fluid w-100 rounded">
                    </div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">2</span>
                <div class="step-content">
                    <h6><?= __('manage_deductions') ?></h6>
                    <p><?= __('click_the') ?> <strong><?= __('manage_deductions') ?></strong> <?= __('button_to_track_and_manage_employee_salary_deductions') ?>.</p>
                    <div class="screenshot-placeholder" data-description="<?= __('deductions_management_page') ?>">
                        <img src="../uploads/tutorials/ticket_tutorials/book-tickets.png" alt="" class="img-fluid w-100 rounded">
                    </div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">3</span>
                <div class="step-content">
                    <h6><?= __('print_payroll') ?></h6>
                    <p><?= __('use_the') ?> <strong><?= __('print_group_payroll') ?></strong> <?= __('button_to_generate_a_comprehensive_payroll_report') ?>.</p>
                    <div class="screenshot-placeholder" data-description="<?= __('payroll_printing_interface') ?>">
                        <img src="../uploads/tutorials/ticket_tutorials/book-tickets.png" alt="" class="img-fluid w-100 rounded">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Expense Management Tutorials -->
<div id="expense-management-overview" class="tutorial-content">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i><?= __('expense_management') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('expense-management-overview')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i>
                <strong><?= __('overview') ?>:</strong> <?= __('comprehensive_guide_to_managing_expenses_tracking_financial_activities_and_generating_insightful_reports') ?>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-plus-circle me-2"></i><?= __('adding_expense_categories') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('create_new_expense_categories') ?></h6>
                    <p><?= __('organize_your_expenses_with_custom_categories') ?>:
                        <ul>
                            <li><?= __('click_add_category_button') ?></li>
                            <li><?= __('enter_unique_category_name') ?></li>
                            <li><?= __('save_and_manage_categories') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="<?= __('expense_category_creation_process') ?>">
                        <img src="../uploads/tutorials/ticket_tutorials/book-tickets.png" alt="" class="img-fluid w-100 rounded">
                    </div>
                </div>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-receipt me-2"></i><?= __('recording_expenses') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('expense_entry_details') ?></h6>
                    <p><?= __('comprehensive_expense_recording') ?>:
                        <ul>
                            <li><?= __('select_expense_category') ?></li>
                            <li><?= __('choose_expense_date') ?></li>
                            <li><?= __('enter_description') ?></li>
                            <li><?= __('input_amount') ?></li>
                            <li><?= __('select_currency') ?></li>
                            <li><?= __('choose_main_account') ?></li>
                            <li><?= __('optional_receipt_upload') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="<?= __('expense_entry_form_with_detailed_fields') ?>">
                        <img src="../uploads/tutorials/ticket_tutorials/book-tickets.png" alt="" class="img-fluid w-100 rounded">
                    </div>
                </div>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-filter me-2"></i><?= __('date_range_filtering') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('advanced_date_filtering') ?></h6>
                    <p><?= __('customize_your_financial_view') ?>:
                        <ul>
                            <li><?= __('select_start_and_end_dates') ?></li>
                            <li><?= __('use_quick_date_range_buttons') ?></li>
                            <li><?= __('filter_expenses_by_specific_periods') ?></li>
                            <li><?= __('reset_to_default_view') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="<?= __('date_range_filtering_interface') ?>">
                        <img src="../uploads/tutorials/ticket_tutorials/book-tickets.png" alt="" class="img-fluid w-100 rounded">
                    </div>
                </div>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-chart-bar me-2"></i><?= __('financial_reporting') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('interactive_financial_charts') ?></h6>
                    <p><?= __('visualize_your_financial_data') ?>:
                        <ul>
                            <li><?= __('income_overview_chart') ?></li>
                            <li><?= __('expense_distribution_chart') ?></li>
                            <li><?= __('profit_and_loss_analysis') ?></li>
                            <li><?= __('export_chart_as_image') ?></li>
                            <li><?= __('export_comprehensive_financial_report') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="<?= __('financial_charts_and_reporting_interface') ?>">
                        <img src="../uploads/tutorials/ticket_tutorials/book-tickets.png" alt="" class="img-fluid w-100 rounded">
                    </div>
                </div>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-file-export me-2"></i><?= __('export_and_reporting') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('multiple_export_options') ?></h6>
                    <p><?= __('export_financial_data_in_various_formats') ?>:
                        <ul>
                            <li><?= __('export_charts_as_images') ?></li>
                            <li><?= __('export_financial_data_to_excel') ?></li>
                            <li><?= __('generate_comprehensive_financial_report') ?></li>
                            <li><?= __('customize_export_date_range') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="<?= __('export_options_and_financial_report_generation') ?>">
                        <img src="../uploads/tutorials/ticket_tutorials/book-tickets.png" alt="" class="img-fluid w-100 rounded">
                    </div>
                </div>
            </div>

            <div class="alert alert-warning mt-4">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <strong><?= __('expense_management_best_practices') ?>:</strong>
                <ul>
                    <li><?= __('maintain_accurate_and_timely_expense_records') ?></li>
                    <li><?= __('categorize_expenses_consistently') ?></li>
                    <li><?= __('upload_receipts_for_documentation') ?></li>
                    <li><?= __('regularly_review_financial_reports') ?></li>
                    <li><?= __('use_budget_allocations_wisely') ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Budget Allocation Management Tutorials -->
<div id="budget-allocation-overview" class="tutorial-content">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i><?= __('budget_allocation_management') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('budget-allocation-overview')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i>
                <strong><?= __('overview') ?>:</strong> <?= __('comprehensive_guide_to_managing_budget_allocations_tracking_expenses_and_maintaining_financial_control') ?>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-chart-pie me-2"></i><?= __('budget_allocation_dashboard') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('understanding_the_budget_allocation_dashboard') ?></h6>
                    <p><?= __('navigate_and_interpret_the_budget_allocation_overview') ?>:</p>
                    <ul>
                        <li><?= __('view_total_allocated_funds_across_different_categories') ?></li>
                        <li><?= __('monitor_available_and_used_funds') ?></li>
                        <li><?= __('track_allocations_by_main_account_and_currency') ?></li>
                    </ul>
                    <div class="screenshot-placeholder" data-description="<?= __('budget_allocation_dashboard_overview') ?>">
                        <img src="../uploads/tutorials/ticket_tutorials/book-tickets.png" alt="" class="img-fluid w-100 rounded">
                    </div>
                </div>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-plus-circle me-2"></i><?= __('creating_a_new_budget_allocation') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('initiating_a_new_budget_allocation') ?></h6>
                    <p><?= __('steps_to_create_a_budget_allocation') ?>:</p>
                    <ol>
                        <li><?= __('click_the_new_allocation_button') ?></li>
                        <li><?= __('select_expense_category') ?></li>
                        <li><?= __('choose_main_account') ?></li>
                        <li><?= __('enter_allocation_amount') ?></li>
                        <li><?= __('select_currency') ?></li>
                        <li><?= __('set_allocation_date') ?></li>
                        <li><?= __('add_optional_description') ?></li>
                        <li><?= __('confirm_allocation_creation') ?></li>
                    </ol>
                    <div class="screenshot-placeholder" data-description="<?= __('creating_a_new_budget_allocation_process') ?>">
                        <img src="../uploads/tutorials/ticket_tutorials/book-tickets.png" alt="" class="img-fluid w-100 rounded">
                    </div>
                </div>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-dollar-sign me-2"></i><?= __('managing_allocation_funds') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('adding_funds_to_an_allocation') ?></h6>
                    <p><?= __('how_to_add_additional_funds_to_an_existing_budget_allocation') ?>:</p>
                    <ol>
                        <li><?= __('locate_the_specific_budget_allocation') ?></li>
                        <li><?= __('click_the_fund_button') ?></li>
                        <li><?= __('enter_additional_amount') ?></li>
                        <li><?= __('add_optional_note') ?></li>
                        <li><?= __('confirm_fund_addition') ?></li>
                    </ol>
                    <div class="screenshot-placeholder" data-description="<?= __('adding_funds_to_budget_allocation') ?>">
                        <img src="../uploads/tutorials/ticket_tutorials/book-tickets.png" alt="" class="img-fluid w-100 rounded">
                    </div>
                </div>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-eye me-2"></i><?= __('viewing_allocation_details') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('exploring_allocation_transactions') ?></h6>
                    <p><?= __('methods_to_view_allocation_details') ?>:</p>
                    <ul>
                        <li><?= __('view_fund_transactions') ?></li>
                        <li><?= __('check_expenses_for_the_allocation') ?></li>
                        <li><?= __('monitor_remaining_and_used_funds') ?></li>
                        <li><?= __('track_allocation_history') ?></li>
                    </ul>
                    <div class="screenshot-placeholder" data-description="<?= __('viewing_budget_allocation_transactions_and_details') ?>">
                        <img src="../uploads/tutorials/ticket_tutorials/book-tickets.png" alt="" class="img-fluid w-100 rounded">
                    </div>
                </div>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-trash me-2"></i><?= __('deleting_budget_allocations') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('removing_budget_allocations') ?></h6>
                    <p><?= __('guidelines_for_deleting_budget_allocations') ?>:</p>
                    <ul>
                        <li><?= __('only_delete_allocations_with_zero_used_funds') ?></li>
                        <li><?= __('remaining_funds_will_be_returned_to_the_main_account') ?></li>
                        <li><?= __('confirm_deletion_to_prevent_accidental_removal') ?></li>
                    </ul>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <strong><?= __('caution') ?>:</strong> <?= __('deleting_an_allocation_is_irreversible') ?> <?= __('ensure_you_want_to_remove_the_allocation_permanently') ?>
                    </div>
                    <div class="screenshot-placeholder" data-description="<?= __('budget_allocation_deletion_process') ?>">
                        <img src="../uploads/tutorials/ticket_tutorials/book-tickets.png" alt="" class="img-fluid w-100 rounded">
                    </div>
                </div>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-calendar-alt me-2"></i><?= __('budget_rollover') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('managing_remaining_funds') ?></h6>
                    <p><?= __('process_for_rolling_over_unused_budget_allocations') ?>:</p>
                    <ol>
                        <li><?= __('check_allocations_with_remaining_funds') ?></li>
                        <li><?= __('navigate_to_budget_rollover_page') ?></li>
                        <li><?= __('review_allocations_from_previous_months') ?></li>
                        <li><?= __('confirm_fund_transfer_or_reallocation') ?></li>
                    </ol>
                    <div class="screenshot-placeholder" data-description="<?= __('budget_allocation_rollover_process') ?>">
                        <img src="../uploads/tutorials/ticket_tutorials/book-tickets.png" alt="" class="img-fluid w-100 rounded">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Client Management Tutorials -->
<div id="client-management-overview" class="tutorial-content">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0"><i class="fas fa-users me-2"></i><?= __('client_management') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('client-management-overview')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i>
                <strong><?= __('overview') ?>:</strong> <?= __('comprehensive_guide_to_managing_clients_tracking_their_information_and_maintaining_financial_relationships') ?>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-plus-circle me-2"></i><?= __('adding_new_clients') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('basic_client_information') ?></h6>
                    <p><?= __('fill_in_essential_client_details') ?>:
                        <ul>
                            <li><?= __('client_name') ?></li>
                            <li><?= __('email_address') ?></li>
                            <li><?= __('phone_number') ?></li>
                            <li><?= __('password_setup') ?></li>
                            <li><?= __('address') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="<?= __('client_basic_information_input_form') ?>">
                        <img src="../uploads/tutorials/ticket_tutorials/book-tickets.png" alt="" class="img-fluid w-100 rounded">
                    </div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">2</span>
                <div class="step-content">
                    <h6><?= __('financial_and_classification_details') ?></h6>
                    <p><?= __('provide_financial_and_client_classification_information') ?>:
                        <ul>
                            <li><?= __('usd_balance') ?></li>
                            <li><?= __('afs_balance') ?></li>
                            <li><?= __('client_type_regular_agency') ?></li>
                            <li><?= __('initial_status_active_inactive') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="<?= __('client_financial_and_classification_details_input') ?>">
                        <img src="../uploads/tutorials/ticket_tutorials/book-tickets.png" alt="" class="img-fluid w-100 rounded">
                    </div>
                </div>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-list me-2"></i><?= __('client_tabs_and_views') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('active_and_inactive_client_management') ?></h6>
                    <p><?= __('navigate_and_manage_clients') ?>:
                        <ul>
                            <li><?= __('active_clients_tab') ?></li>
                            <li><?= __('inactive_clients_tab') ?></li>
                            <li><?= __('comprehensive_client_information_table') ?></li>
                            <li><?= __('quick_status_and_type_filtering') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="<?= __('client_tabs_and_table_view') ?>">
                        <img src="../uploads/tutorials/ticket_tutorials/book-tickets.png" alt="" class="img-fluid w-100 rounded">
                    </div>
                </div>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-search me-2"></i><?= __('advanced_client_search_and_filtering') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('powerful_search_and_filter_options') ?></h6>
                    <p><?= __('refine_client_view_with_multiple_filters') ?>:
                        <ul>
                            <li><?= __('search_by_name_email_or_phone') ?></li>
                            <li><?= __('filter_by_client_type') ?></li>
                            <li><?= __('quick_access_to_client_details') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="<?= __('client_search_and_filtering_interface') ?>"></div>
                </div>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-edit me-2"></i><?= __('editing_client_information') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('updating_client_details') ?></h6>
                    <p><?= __('modify_client_information') ?>:
                        <ul>
                            <li><?= __('edit_contact_information') ?></li>
                            <li><?= __('update_client_type') ?></li>
                            <li><?= __('change_account_status') ?></li>
                            <li><?= __('modify_address') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="<?= __('client_edit_modal_with_detailed_fields') ?>"></div>
                </div>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-chart-bar me-2"></i><?= __('client_dashboard_statistics') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('comprehensive_client_insights') ?></h6>
                    <p><?= __('understand_your_client_base') ?>:
                        <ul>
                            <li><?= __('total_clients_count') ?></li>
                            <li><?= __('number_of_agencies') ?></li>
                            <li><?= __('total_usd_balance') ?></li>
                            <li><?= __('total_afs_balance') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="<?= __('client_dashboard_statistics_overview') ?>"></div>
                </div>
            </div>

            <div class="alert alert-warning mt-4">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <strong><?= __('client_management_best_practices') ?>:</strong>
                <ul>
                    <li><?= __('maintain_accurate_and_up-to-date_client_information') ?></li>
                    <li><?= __('regularly_review_client_statuses') ?></li>
                    <li><?= __('ensure_clear_communication_channels') ?></li>
                    <li><?= __('monitor_financial_balances_carefully') ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>
<!-- File Browser Management Tutorials -->
<div id="file-browser-overview" class="tutorial-content">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0"><i class="fas fa-folder-open me-2"></i><?= __('file_browser_management') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('file-browser-overview')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i>
                <strong><?= __('overview') ?>:</strong> <?= __('comprehensive_guide_to_managing_files_folders_and_uploads_in_the_system') ?>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-list-alt me-2"></i><?= __('file_browser_dashboard') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('navigating_the_file_browser') ?></h6>
                    <p><?= __('explore_the_uploads_directory_view_files_and_folders_and_manage_your_documents') ?>.</p>
                    <div class="screenshot-placeholder" data-description="<?= __('file_browser_main_dashboard') ?>"></div>
                </div>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-upload me-2"></i><?= __('uploading_files') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('initiate_file_upload') ?></h6>
                    <p><?= __('click_the_upload_files_button_to_open_the_upload_modal') ?>.</p>
                    <div class="screenshot-placeholder" data-description="<?= __('upload_files_button_location') ?>"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">2</span>
                <div class="step-content">
                    <h6><?= __('select_or_drag_files') ?></h6>
                    <p><?= __('choose_files_to_upload') ?>:
                        <ul>
                            <li><?= __('click_to_select_files') ?></li>
                            <li><?= __('drag_and_drop_files_into_the_upload_area') ?></li>
                            <li><?= __('maximum_file_size_100mb') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="<?= __('file_upload_dropzone_interface') ?>"></div>
                </div>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-folder-plus me-2"></i><?= __('creating_folders') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('create_new_folder') ?></h6>
                    <p><?= __('click_new_folder_to_create_directories_for_organizing_your_files') ?>.</p>
                    <div class="screenshot-placeholder" data-description="<?= __('new_folder_creation_modal') ?>"></div>
                </div>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-filter me-2"></i><?= __('filtering_and_sorting') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('filter_files') ?></h6>
                    <p><?= __('use_filter_options_to_view_specific_file_types') ?>:
                        <ul>
                            <li><?= __('all_files') ?></li>
                            <li><?= __('images') ?></li>
                            <li><?= __('documents') ?></li>
                            <li><?= __('archives') ?></li>
                            <li><?= __('folders') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="<?= __('file_filtering_dropdown') ?>"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">2</span>
                <div class="step-content">
                    <h6><?= __('sort_files') ?></h6>
                    <p><?= __('organize_files_by') ?>:
                        <ul>
                            <li><?= __('name_ascending_descending') ?></li>
                            <li><?= __('date_ascending_descending') ?></li>
                            <li><?= __('size_ascending_descending') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="<?= __('file_sorting_options') ?>"></div>
                </div>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-edit me-2"></i><?= __('file_management_actions') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('file_actions') ?></h6>
                    <p><?= __('perform_actions_on_files') ?>:
                        <ul>
                            <li><?= __('preview') ?></li>
                            <li><?= __('download') ?></li>
                            <li><?= __('rename') ?></li>
                            <li><?= __('delete') ?></li>
                            <li><?= __('move_copy') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="<?= __('file_action_buttons') ?>"></div>
                </div>
            </div>

            <div class="alert alert-warning mt-4">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <strong><?= __('important_security_notes') ?>:</strong>
                <ul>
                    <li><?= __('only_upload_files_from_trusted_sources') ?></li>
                    <li><?= __('be_cautious_with_file_permissions') ?></li>
                    <li><?= __('avoid_uploading_executable_files') ?></li>
                    <li><?= __('regularly_clean_up_unnecessary_files') ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Supplier Management Tutorials -->
<div id="supplier-management-overview" class="tutorial-content">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0"><i class="fas fa-truck me-2"></i><?= __('supplier_management') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('supplier-management-overview')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i>
                <strong><?= __('overview') ?>:</strong> <?= __('comprehensive_guide_to_managing_suppliers_tracking_their_information_and_maintaining_financial_relationships') ?>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-plus-circle me-2"></i><?= __('adding_new_suppliers') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('basic_supplier_information') ?></h6>
                    <p><?= __('fill_in_essential_supplier_details') ?>:
                        <ul>
                            <li><?= __('supplier_name') ?></li>
                            <li><?= __('contact_person') ?></li>
                            <li><?= __('phone_number') ?></li>
                            <li><?= __('email_address') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="<?= __('supplier_basic_information_input_form') ?>"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">2</span>
                <div class="step-content">
                    <h6><?= __('financial_and_classification_details') ?></h6>
                    <p><?= __('provide_financial_and_supplier_classification_information') ?>:
                        <ul>
                            <li><?= __('currency') ?></li>
                            <li><?= __('initial_balance') ?></li>
                            <li><?= __('supplier_type_internal_external') ?></li>
                            <li><?= __('address') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="<?= __('supplier_financial_and_classification_details_input') ?>"></div>
                </div>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-list me-2"></i><?= __('supplier_tabs_and_views') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('active_and_inactive_supplier_management') ?></h6>
                    <p><?= __('navigate_and_manage_suppliers') ?>:
                        <ul>
                            <li><?= __('active_suppliers_tab') ?></li>
                            <li><?= __('inactive_suppliers_tab') ?></li>
                            <li><?= __('comprehensive_supplier_information_table') ?></li>
                            <li><?= __('quick_status_filtering') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="<?= __('supplier_tabs_and_table_view') ?>"></div>
                </div>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-edit me-2"></i><?= __('editing_supplier_information') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('updating_supplier_details') ?></h6>
                    <p><?= __('modify_supplier_information') ?>:
                        <ul>
                            <li><?= __('edit_contact_information') ?></li>
                            <li><?= __('update_financial_details') ?></li>
                            <li><?= __('change_supplier_type') ?></li>
                            <li><?= __('modify_address') ?></li>
                            <li><?= __('adjust_balance') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="<?= __('supplier_edit_modal_with_detailed_fields') ?>"></div>
                </div>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-exchange-alt me-2"></i><?= __('supplier_status_management') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('managing_supplier_status') ?></h6>
                    <p><?= __('control_supplier_account_status') ?>:
                        <ul>
                            <li><?= __('switch_between_active_and_inactive_status') ?></li>
                            <li><?= __('understand_status_implications') ?></li>
                            <li><?= __('maintain_accurate_supplier_records') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="<?= __('supplier_status_change_process') ?>"></div>
                </div>
            </div>

            <div class="alert alert-warning mt-4">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <strong><?= __('supplier_management_best_practices') ?>:</strong>
                <ul>
                    <li><?= __('keep_supplier_information_current') ?></li>
                    <li><?= __('maintain_accurate_financial_records') ?></li>
                    <li><?= __('regularly_review_supplier_status') ?></li>
                    <li><?= __('ensure_clear_communication_channels') ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>
<!-- Asset Management Tutorials -->
<div id="asset-management-overview" class="tutorial-content">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0"><i class="fas fa-box me-2"></i><?= __('asset_management') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('asset-management-overview')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i>
                <strong><?= __('overview') ?>:</strong> <?= __('comprehensive_guide_to_managing_company_assets_tracking_their_lifecycle_and_maintaining_accurate_records') ?>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-plus-circle me-2"></i><?= __('adding_new_assets') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('basic_asset_information') ?></h6>
                    <p><?= __('fill_in_essential_asset_details') ?>:
                        <ul>
                            <li><?= __('asset_name') ?></li>
                            <li><?= __('category_electronics_furniture_vehicle_etc') ?></li>
                            <li><?= __('purchase_date') ?></li>
                            <li><?= __('warranty_expiry_date') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="<?= __('asset_basic_information_input_form') ?>"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">2</span>
                <div class="step-content">
                    <h6><?= __('financial_and_location_details') ?></h6>
                    <p><?= __('provide_financial_and_tracking_information') ?>:
                        <ul>
                            <li><?= __('purchase_value') ?></li>
                            <li><?= __('current_value') ?></li>
                            <li><?= __('currency') ?></li>
                            <li><?= __('location') ?></li>
                            <li><?= __('serial_number') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="<?= __('asset_financial_and_location_details_input') ?>"></div>
                </div>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-eye me-2"></i><?= __('viewing_asset_details') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('comprehensive_asset_information') ?></h6>
                    <p><?= __('view_detailed_asset_information') ?>:
                        <ul>
                            <li><?= __('basic_details') ?></li>
                            <li><?= __('purchase_and_current_value') ?></li>
                            <li><?= __('status_active_inactive_maintenance') ?></li>
                            <li><?= __('condition') ?></li>
                            <li><?= __('assigned_user') ?></li>
                            <li><?= __('depreciation_information') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="<?= __('asset_details_view_modal') ?>"></div>
                </div>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-chart-pie me-2"></i><?= __('asset_analytics_and_reporting') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('asset_distribution_and_status') ?></h6>
                    <p><?= __('analyze_assets_through_interactive_charts') ?>:
                        <ul>
                            <li><?= __('category_distribution_pie_chart') ?></li>
                            <li><?= __('asset_status_bar_chart') ?></li>
                            <li><?= __('total_assets_count') ?></li>
                            <li><?= __('total_asset_value_by_currency') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="<?= __('asset_analytics_and_reporting_charts') ?>"></div>
                </div>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-filter me-2"></i><?= __('advanced_asset_filtering') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('powerful_search_and_filter_options') ?></h6>
                    <p><?= __('refine_asset_view_with_multiple_filters') ?>:
                        <ul>
                            <li><?= __('filter_by_category') ?></li>
                            <li><?= __('search_by_location') ?></li>
                            <li><?= __('date_range_selection') ?></li>
                            <li><?= __('status-based_filtering') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="<?= __('advanced_asset_filtering_interface') ?>"></div>
                </div>
            </div>

            <div class="alert alert-warning mt-4">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <strong><?= __('asset_management_best_practices') ?>:</strong>
                <ul>
                    <li><?= __('keep_asset_information_up-to-date') ?></li>
                    <li><?= __('regularly_review_asset_conditions') ?></li>
                    <li><?= __('track_depreciation_accurately') ?></li>
                    <li><?= __('maintain_proper_documentation') ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- User Management Tutorials -->
<div id="user-management-overview" class="tutorial-content">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0"><i class="fas fa-users me-2"></i><?= __('user_management_overview') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('user-management-overview')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i>
                <strong><?= __('overview') ?>:</strong> <?= __('comprehensive_guide_to_managing_users_their_roles_profiles_and_administrative_actions') ?>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-list-alt me-2"></i><?= __('user_dashboard') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('navigating_user_management') ?></h6>
                    <p><?= __('access_the_user_management_page_to_view_and_manage_all_system_users') ?>.</p>
                    <div class="screenshot-placeholder" data-description="<?= __('user_management_dashboard_main_page') ?>"></div>
                </div>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-user-plus me-2"></i><?= __('adding_new_users') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('initiate_user_creation') ?></h6>
                    <p><?= __('click_the_add_new_user_button_to_open_the_user_creation_modal') ?>.</p>
                    <div class="screenshot-placeholder" data-description="<?= __('add_new_user_button_location') ?>"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">2</span>
                <div class="step-content">
                    <h6><?= __('enter_user_details') ?></h6>
                    <p><?= __('fill_in_comprehensive_user_information') ?>:
                        <ul>
                            <li><?= __('full_name') ?></li>
                            <li><?= __('email_address') ?></li>
                            <li><?= __('password') ?></li>
                            <li><?= __('role_admin_finance_sales_umrah_staff') ?></li>
                            <li><?= __('phone_number') ?></li>
                            <li><?= __('address') ?></li>
                            <li><?= __('hire_date') ?></li>
                            <li><?= __('profile_picture') ?></li>
                            <li><?= __('additional_documents') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="<?= __('new_user_creation_form_with_all_fields') ?>"></div>
                </div>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-user-edit me-2"></i><?= __('editing_user_profiles') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('access_user_edit_modal') ?></h6>
                    <p><?= __('in_the_user_table_click_the_action_dropdown_for_the_specific_user_and_select_edit') ?>.</p>
                    <div class="screenshot-placeholder" data-description="<?= __('user_table_action_dropdown_with_edit_option') ?>"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">2</span>
                <div class="step-content">
                    <h6><?= __('modify_user_information') ?></h6>
                    <p><?= __('update_user_details') ?>:
                        <ul>
                            <li><?= __('personal_information') ?></li>
                            <li><?= __('contact_details') ?></li>
                            <li><?= __('role') ?></li>
                            <li><?= __('profile_picture') ?></li>
                            <li><?= __('optional_password_change') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="<?= __('user_edit_modal_with_editable_fields') ?>"></div>
                </div>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-user-times me-2"></i><?= __('user_status_management') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('firing_and_reactivating_users') ?></h6>
                    <p><?= __('manage_user_employment_status') ?>:
                        <ul>
                            <li><?= __('fire_an_active_employee') ?></li>
                            <li><?= __('reactivate_a_fired_employee') ?></li>
                            <li><?= __('view_fired_and_active_user_lists') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="<?= __('user_firing_and_reactivation_process') ?>"></div>
                </div>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-file-alt me-2"></i><?= __('user_documentation') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('generate_official_documents') ?></h6>
                    <p><?= __('create_various_official_documents') ?>:
                        <ul>
                            <li><?= __('employment_agreement') ?></li>
                            <li><?= __('guarantor_letter') ?></li>
                            <li><?= __('tawseah') ?></li>
                            <li><?= __('official_warning_ikhtar') ?></li>
                            <li><?= __('fine_letter') ?></li>
                            <li><?= __('termination_letter') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="<?= __('document_generation_options_in_user_actions') ?>"></div>
                </div>
            </div>

            <div class="alert alert-warning mt-4">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <strong><?= __('important_security_notes') ?>:</strong>
                <ul>
                    <li><?= __('always_use_strong_unique_passwords') ?></li>
                    <li><?= __('assign_roles_with_minimal_necessary_permissions') ?></li>
                    <li><?= __('protect_sensitive_user_information') ?></li>
                    <li><?= __('maintain_accurate_and_up-to-date_user_records') ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>
<!-- Payroll Reporting Tutorial -->
<div id="payroll-reporting" class="tutorial-content">
    <div class="card">
        <div class="card-header bg-finance text-white">
            <h4 class="mb-0"><i class="fas fa-print me-2"></i><?= __('payroll_reporting_and_management') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('payroll-reporting')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i>
                <strong><?= __('overview') ?>:</strong> <?= __('comprehensive_guide_to_generating_managing_and_analyzing_payroll_reports_for_accurate_financial_tracking') ?>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-list-alt me-2"></i><?= __('accessing_payroll_reporting') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('navigate_to_payroll_section') ?></h6>
                    <p><?= __('go_to_the_salary_management_page_and_click_on_the_print_group_payroll_button') ?>.</p>
                    <div class="screenshot-placeholder" data-description="<?= __('print_group_payroll_button_in_salary_management_page') ?>"></div>
                </div>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-filter me-2"></i><?= __('report_customization') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('select_reporting_period') ?></h6>
                    <p><?= __('choose_the_date_range_for_your_payroll_report') ?>:
                        <ul>
                            <li><?= __('monthly_reports') ?></li>
                            <li><?= __('quarterly_reports') ?></li>
                            <li><?= __('annual_reports') ?></li>
                            <li><?= __('custom_date_range') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="<?= __('date_range_selection_interface') ?>"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">2</span>
                <div class="step-content">
                    <h6><?= __('employee_filtering') ?></h6>
                    <p><?= __('customize_your_report_by_selecting') ?>:
                        <ul>
                            <li><?= __('all_employees') ?></li>
                            <li><?= __('specific_departments') ?></li>
                            <li><?= __('individual_employees') ?></li>
                            <li><?= __('employment_status_active_inactive') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="<?= __('employee_filtering_options') ?>"></div>
                </div>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-chart-bar me-2"></i><?= __('report_details') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('comprehensive_payroll_information') ?></h6>
                    <p><?= __('each_payroll_report_includes') ?>:
                        <ul>
                            <li><?= __('employee_name_and_id') ?></li>
                            <li><?= __('base_salary') ?></li>
                            <li><?= __('bonuses') ?></li>
                            <li><?= __('deductions') ?></li>
                            <li><?= __('net_salary') ?></li>
                            <li><?= __('payment_method') ?></li>
                            <li><?= __('payment_date') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="<?= __('detailed_payroll_report_preview') ?>"></div>
                </div>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-file-export me-2"></i><?= __('report_export_options') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('export_and_share_reports') ?></h6>
                    <p><?= __('multiple_export_options_available') ?>:
                        <ul>
                            <li><?= __('print_physical_copy') ?></li>
                            <li><?= __('export_to_pdf') ?></li>
                            <li><?= __('export_to_excel_xlsx') ?></li>
                            <li><?= __('export_to_csv') ?></li>
                            <li><?= __('email_report') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="<?= __('report_export_and_sharing_options') ?>"></div>
                </div>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-lock me-2"></i><?= __('report_security') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('confidentiality_and_access_control') ?></h6>
                    <p><?= __('payroll_reports_are_subject_to_strict_access_controls') ?>:
                        <ul>
                            <li><?= __('only_admin_and_authorized_personnel_can_generate_reports') ?></li>
                            <li><?= __('sensitive_information_is_masked') ?></li>
                            <li><?= __('audit_trail_maintained_for_report_generation') ?></li>
                            <li><?= __('export_and_sharing_are_logged') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="<?= __('report_access_and_security_settings') ?>"></div>
                </div>
            </div>

            <div class="alert alert-warning mt-4">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <strong><?= __('important_compliance_notes') ?>:</strong>
                <ul>
                    <li><?= __('ensure_compliance_with_local_labor_and_tax_regulations') ?></li>
                    <li><?= __('maintain_accurate_and_complete_payroll_records') ?></li>
                    <li><?= __('protect_employee_financial_information') ?></li>
                    <li><?= __('regularly_reconcile_payroll_reports') ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>
<!-- Salary Bonuses and Deductions Tutorial -->
<div id="salary-bonuses-deductions" class="tutorial-content">
    <div class="card">
        <div class="card-header bg-finance text-white">
            <h4 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i><?= __('salary_bonuses_and_deductions_management') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('salary-bonuses-deductions')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i>
                <strong><?= __('overview') ?>:</strong> <?= __('comprehensive_guide_to_managing_employee_financial_adjustments_through_bonuses_and_deductions') ?>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-plus-circle me-2"></i><?= __('managing_bonuses') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('accessing_bonus_management') ?></h6>
                    <p><?= __('navigate_to_the_salary_management_page_and_click_on_the_manage_bonuses_button') ?>.</p>
                    <div class="screenshot-placeholder" data-description="<?= __('bonus_management_button_in_salary_management_page') ?>"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">2</span>
                <div class="step-content">
                    <h6><?= __('select_employee') ?></h6>
                    <p><?= __('choose_the_specific_employee_for_whom_you_want_to_add_a_bonus') ?>.</p>
                    <div class="screenshot-placeholder" data-description="<?= __('employee_selection_dropdown_in_bonus_management') ?>"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">3</span>
                <div class="step-content">
                    <h6><?= __('enter_bonus_details') ?></h6>
                    <p><?= __('fill_in_the_bonus_information') ?>:
                        <ul>
                            <li><?= __('bonus_amount') ?></li>
                            <li><?= __('bonus_type_performance_retention_holiday_etc') ?></li>
                            <li><?= __('bonus_reason') ?></li>
                            <li><?= __('effective_date') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="<?= __('bonus_details_input_form') ?>"></div>
                </div>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-minus-circle me-2"></i><?= __('managing_deductions') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('accessing_deductions_management') ?></h6>
                    <p><?= __('navigate_to_the_salary_management_page_and_click_on_the_manage_deductions_button') ?>.</p>
                    <div class="screenshot-placeholder" data-description="<?= __('deductions_management_button_in_salary_management_page') ?>"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">2</span>
                <div class="step-content">
                    <h6><?= __('select_employee') ?></h6>
                    <p><?= __('choose_the_specific_employee_for_whom_you_want_to_add_a_deduction') ?>.</p>
                    <div class="screenshot-placeholder" data-description="<?= __('employee_selection_dropdown_in_deductions_management') ?>"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">3</span>
                <div class="step-content">
                    <h6>Enter Deduction Details</h6>
                    <p>Fill in the deduction information:
                        <ul>
                            <li><?= __('deduction_amount') ?></li>
                            <li><?= __('deduction_type_disciplinary_loan_repayment_advance_recovery_etc') ?></li>
                            <li><?= __('deduction_reason') ?></li>
                            <li><?= __('effective_date') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="<?= __('deduction_details_input_form') ?>"></div>
                </div>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-calculator me-2"></i><?= __('salary_adjustment_calculation') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('review_salary_adjustments') ?></h6>
                    <p><?= __('after_adding_bonuses_or_deductions_review_the_final_salary_calculation') ?>:
                        <ul>
                            <li><?= __('base_salary') ?></li>
                            <li><?= __('total_bonuses') ?></li>
                            <li><?= __('total_deductions') ?></li>
                            <li><?= __('net_salary') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="<?= __('salary_adjustment_calculation_preview') ?>"></div>
                </div>
            </div>

            <div class="alert alert-warning mt-4">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <strong><?= __('important_notes') ?>:</strong>
                <ul>
                    <li><?= __('ensure_all_bonus_and_deduction_entries_are_justified_and_documented') ?></li>
                    <li><?= __('maintain_transparency_in_salary_adjustments') ?></li>
                    <li><?= __('keep_accurate_records_for_payroll_and_accounting_purposes') ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>
<div id="date-change" class="tutorial-content">
    <div class="card">
        <div class="card-header bg-info text-white">
            <h4 class="mb-0"><i class="fas fa-calendar-alt me-2"></i><?= __('how_to_change_ticket_dates') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('date-change')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                <strong><?= __('complete_process') ?>:</strong> <?= __('this_tutorial_covers_the_entire_date_change_process_from_accessing_the_date_change_page_to_managing_change_fees_and_transactions') ?>
            </div>

            <!-- Step 1: Navigate to Date Change -->
            <div class="step-item">
                <span class="step-number">1</span>
                <strong><?= __('navigate_to_date_change_management') ?></strong>
                <p><?= __('go_to_bookings_date_changes_from_the_main_menu_to_access_the_date_change_management_page_with_all_existing_date_change_records') ?>.</p>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot_date_change_management_page_with_statistics_and_add_date_change_button') ?>
                </div>
            </div>

            <!-- Step 2: Open Add Date Change Modal -->
            <div class="step-item">
                <span class="step-number">2</span>
                <strong><?= __('start_date_change_process') ?></strong>
                <p><?= __('click_the_add_date_change_button_on_the_top_right_of_the_page_this_will_open_the_date_change_modal_where_you_can_search_for_existing_tickets_to_modify') ?>.</p>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot_clicking_add_date_change_button') ?>
                </div>
            </div>

            <!-- Step 3: Search for Ticket -->
            <div class="step-item">
                <span class="step-number">3</span>
                <strong><?= __('search_for_original_ticket') ?></strong>
                <p><?= __('in_the_search_section_you_can_find_the_ticket_to_change_using_either_method') ?>:</p>
                <ul class="mt-2 mb-2">
                    <li><strong><?= __('search_by_pnr') ?>:</strong> <?= __('enter_the_6_character_passenger_name_record_number_and_click_the_search_button') ?></li>
                    <li><strong><?= __('search_by_passenger') ?>:</strong> <?= __("enter_the_passenger's_name_minimum_3_characters_and_click_the_search_button") ?></li>
                </ul>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong><?= __('note') ?>:</strong> <?= __('only_active_tickets_that_havent_been_refunded_will_appear_in_search_results') ?>
                </div>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot_search_section_with_pnr_and_passenger_name_fields') ?>
                </div>
            </div>

            <!-- Step 4: Select Ticket from Results -->
            <div class="step-item">
                <span class="step-number">4</span>
                <strong><?= __('select_ticket_for_date_change') ?></strong>
                <p><?= __('the_search_results_will_display_matching_tickets_in_a_table_showing') ?>:</p>
                <ul class="mt-2 mb-2">
                    <li><strong><?= __('passenger_name') ?>:</strong> <?= __('full_name_of_the_traveler') ?></li>
                    <li><strong><?= __('pnr') ?>:</strong> <?= __('booking_reference_number') ?></li>
                    <li><strong><?= __('flight_details') ?>:</strong> <?= __('route_airline_and_current_flight_information') ?></li>
                    <li><strong><?= __('current_date') ?>:</strong> <?= __('original_departure_date') ?></li>
                    <li><strong><?= __('action_button') ?>:</strong> <?= __('select_for_date_change_button') ?></li>
                </ul>
                <p><?= __('click_the_select_for_date_change_button_next_to_the_ticket_you_want_to_modify') ?>.</p>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot_search_results_table_with_selectable_tickets') ?>
                </div>
            </div>

            <!-- Step 5: Enter New Departure Date -->
            <div class="step-item">
                <span class="step-number">5</span>
                <strong><?= __('set_new_departure_date') ?></strong>
                <p><?= __('after_selecting_a_ticket_the_date_change_form_will_appear_enter_the_new_departure_date') ?>:</p>
                <ul class="mt-2 mb-2">
                    <li><strong><?= __('new_departure_date') ?>:</strong> <?= __('select_the_new_travel_date_from_the_date_picker') ?></li>
                    <li><strong><?= __('validation') ?>:</strong> <?= __('only_future_dates_are_allowed_system_prevents_past_dates') ?></li>
                    <li><strong><?= __('date_format') ?>:</strong> <?= __('use_the_standard_date_picker_format') ?></li>
                </ul>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong><?= __('tip') ?>:</strong> <?= __('verify_the_new_date_with_the_airlines_availability_before_processing_the_change') ?>
                </div>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot_new_departure_date_field_with_date_picker') ?>
                </div>
            </div>

            <!-- Step 6: Set Exchange Rate -->
            <div class="step-item">
                <span class="step-number">6</span>
                <strong><?= __('enter_current_exchange_rate') ?></strong>
                <p><?= __('input_the_current_exchange_rate_for_currency_conversion') ?>:</p>
                <ul class="mt-2 mb-2">
                    <li><strong><?= __('exchange_rate') ?>:</strong> <?= __('enter_the_rate_with_up_to_4_decimal_places_e_g_85_2500') ?></li>
                    <li><strong><?= __('currency_base') ?>:</strong> <?= __('this_is_typically_usd_to_local_currency_conversion') ?></li>
                    <li><strong><?= __('minimum_value') ?>:</strong> <?= __('must_be_greater_than_0_0001') ?></li>
                </ul>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot_exchange_rate_field_with_current_rate') ?>
                </div>
            </div>

            <!-- Step 7: Enter Penalty Amounts -->
            <div class="step-item">
                <span class="step-number">7</span>
                <strong><?= __('enter_penalty_and_service_charges') ?></strong>
                <p><?= __('input_the_charges_associated_with_the_date_change') ?>:</p>
                <ul class="mt-2 mb-2">
                    <li><strong><?= __('supplier_penalty') ?>:</strong> <?= __('change_fee_charged_by_the_airline_supplier') ?></li>
                    <li><strong><?= __('service_penalty') ?>:</strong> <?= __('your_agency_s_service_fee_for_processing_the_date_change') ?></li>
                    <li><strong><?= __('minimum_value') ?>:</strong> <?= __('both_fields_accept_values_from_0_free_changes_upward') ?></li>
                </ul>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong><?= __('tip') ?>:</strong> <?= __('check_the_airline_s_current_change_policy_for_accurate_penalty_amounts') ?>
                </div>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot_supplier_and_service_penalty_fields') ?>
                </div>
            </div>

            <!-- Step 8: Update Pricing Information -->
            <div class="step-item">
                <span class="step-number">8</span>
                <strong><?= __('enter_updated_pricing') ?></strong>
                <p><?= __('input_the_updated_ticket_pricing_information') ?>:</p>
                <ul class="mt-2 mb-2">
                    <li><strong><?= __('base_price') ?>:</strong> <?= __('new_cost_from_the_supplier_airline') ?></li>
                    <li><strong><?= __('sold_price') ?>:</strong> <?= __('new_amount_to_be_charged_to_the_customer') ?></li>
                    <li><strong><?= __('pricing_logic') ?>:</strong> <?= __('these_may_be_the_same_as_original_or_different_based_on_date_change') ?></li>
                </ul>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot_base_price_and_sold_price_fields') ?>
                </div>
            </div>

            <!-- Step 9: Add Description -->
            <div class="step-item">
                <span class="step-number">9</span>
                <strong><?= __('add_change_description') ?></strong>
                <p><?= __('enter_a_detailed_description_of_the_date_change_minimum_10_characters') ?>:</p>
                <ul class="mt-2 mb-2">
                    <li><?= __('reason_for_date_change') ?></li>
                    <li><?= __('customer_request_details') ?></li>
                    <li><?= __('airline_policy_references') ?></li>
                    <li><?= __('any_special_circumstances') ?></li>
                    <li><?= __('communication_notes_with_customer') ?></li>
                </ul>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot_description_textarea_with_sample_change_notes') ?>
                </div>
            </div>

            <!-- Step 10: Save Date Change -->
            <div class="step-item">
                <span class="step-number">10</span>
                <strong><?= __('save_the_date_change') ?></strong>
                <p><?= __('click_the_save_date_change_button_to_process_the_modification_the_system_will') ?>:</p>
                <ul class="mt-2 mb-2">
                    <li><?= __('create_a_date_change_record') ?></li>
                    <li><?= __('set_status_as_date_changed') ?></li>
                    <li><?= __('store_the_old_and_new_departure_dates') ?></li>
                    <li><?= __('calculate_total_penalty_amounts') ?></li>
                    <li><?= __('close_the_modal_and_refresh_the_date_change_list') ?></li>
                    <li><?= __('show_a_success_confirmation_message') ?></li>
                </ul>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot_success_message_after_saving_date_change') ?>
                </div>
            </div>

            <!-- Step 11: Locate Changed Ticket -->
            <div class="step-item">
                <span class="step-number">11</span>
                <strong><?= __('find_your_date_change_record') ?></strong>
                <p><?= __('the_new_date_change_record_will_appear_in_the_main_table_showing') ?>:</p>
                <ul class="mt-2 mb-2">
                    <li><strong><?= __('passenger_details') ?>:</strong> <?= __('name_pnr_phone_created_by') ?></li>
                    <li><strong><?= __('flight_info') ?>:</strong> <?= __('route_and_destination_information') ?></li>
                    <li><strong><?= __('date_change') ?>:</strong> <?= __('old_date_vs_new_date_comparison') ?></li>
                    <li><strong><?= __('financial_details') ?>:</strong> <?= __('base_and_sold_amounts') ?></li>
                    <li><strong><?= __('penalties') ?>:</strong> <?= __('total_penalty_amount_supplier_service') ?></li>
                    <li><strong><?= __('payment_status') ?>:</strong> <?= __('color_coded_indicator_red_unpaid_yellow_partial_green_paid') ?></li>
                </ul>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot_new_date_change_record_in_the_main_list') ?>
                </div>
            </div>

            <!-- Step 12: Manage Change Payments -->
            <div class="step-item">
                <span class="step-number">12</span>
                <strong><?= __('process_change_fee_payments') ?></strong>
                <p><?= __('to_add_payment_transactions_for_the_date_change_fees') ?>:</p>
                <ol class="mt-2 mb-2">
                    <li><?= __('in_the_actions_column_click_the_credit_card_icon_manage_transactions') ?></li>
                    <li><?= __('this_opens_the_comprehensive_transaction_management_modal') ?></li>
                    <li><?= __('the_modal_shows_the_total_penalty_amount_to_be_collected') ?></li>
                    <li><?= __('use_the_detailed_transaction_form_to_record_payments') ?></li>
                    <li><?= __('support_for_multiple_currencies_usd_afs_eur_aed') ?></li>
                </ol>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot_manage_transactions_button_in_actions') ?>
                </div>
            </div>

            <!-- Step 13: Transaction Management -->
            <div class="step-item">
                <span class="step-number">13</span>
                <strong><?= __('add_change_fee_transactions') ?></strong>
                <p><?= __('in_the_transaction_modal_add_payments_for_the_date_change') ?>:</p>
                <ul class="mt-2 mb-2">
                    <li><strong><?= __('transaction_details') ?>:</strong> <?= __('date_and_time_of_payment') ?></li>
                    <li><strong><?= __('payment_information') ?>:</strong> <?= __('amount_and_currency_selection') ?></li>
                    <li><strong><?= __('description') ?>:</strong> <?= __('payment_notes_and_details') ?></li>
                    <li><strong><?= __('multi-currency_support') ?>:</strong> <?= __('usd_afs_eur_aed_with_automatic_conversion') ?></li>
                    <li><strong><?= __('payment_tracking') ?>:</strong> <?= __('running_totals_and_remaining_balances') ?></li>
                </ul>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot_transaction_management_modal_with_payment_form') ?>
                </div>
            </div>

            <!-- Step 14: Print Agreement -->
            <div class="step-item">
                <span class="step-number">14</span>
                <strong><?= __('generate_documentation') ?></strong>
                <p><?= __('to_create_official_date_change_documentation') ?>:</p>
                <ol class="mt-2 mb-2">
                    <li><?= __('in_the_actions_column_click_the_printer_icon_print_agreement') ?></li>
                    <li><?= __('a_pdf_document_will_be_generated_with_date_change_details') ?></li>
                    <li><?= __('document_includes_old_and_new_dates_penalty_information') ?></li>
                    <li><?= __('print_or_save_the_document_for_your_records') ?></li>
                    <li><?= __('provide_a_copy_to_the_customer_as_needed') ?></li>
                </ol>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot_print_agreement_button_and_generated_document') ?>
                </div>
            </div>

            <!-- Step 15: Monitor Payment Status -->
            <div class="step-item">
                <span class="step-number">15</span>
                <strong><?= __('track_change_fee_status') ?></strong>
                <p><?= __('monitor_the_payment_status_using_the_indicators_in_the_main_table') ?>:</p>
                <ul class="mt-2 mb-2">
                    <li><strong><?= __('red_circle') ?>:</strong> <?= __('no_payment_made_yet_for_change_fees') ?></li>
                    <li><strong><?= __('yellow_circle') ?>:</strong> <?= __('partial_payment_of_change_fees') ?></li>
                    <li><strong><?= __('green_circle') ?>:</strong> <?= __('fully_paid_completed') ?></li>
                    <li><strong><?= __('gray_minus') ?>:</strong> <?= __('not_applicable_non_agency_client') ?></li>
                </ul>
                <p><?= __('the_system_automatically_calculates_payment_status_based_on_the_total_penalty_amount_and_tracks_all_transactions') ?>.</p>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot_payment_status_indicators_in_the_date_change_table') ?>
                </div>
            </div>

            <div class="alert alert-success mt-4">
                <i class="fas fa-check-circle me-2"></i>
                <strong><?= __('date_change_process_complete') ?>!</strong> <?= __('you_have_successfully_processed_a_ticket_date_change_the_change_record_is_now_in_the_system_and_you_can_manage_payments_and_track_the_status_until_completion') ?>
            </div>

            <div class="alert alert-info mt-3">
                <i class="fas fa-lightbulb me-2"></i>
                <strong><?= __('best_practices') ?>:</strong>
                <ul class="mb-0 mt-2">
                    <li><?= __('always_verify_airline_change_policies_and_availability_before_processing') ?></li>
                    <li><?= __('document_the_reason_for_date_change_clearly') ?></li>
                    <li><?= __('keep_copies_of_all_change_agreements') ?></li>
                    <li><?= __('communicate_new_travel_dates_to_customers_promptly') ?></li>
                    <li><?= __('monitor_payment_status_for_change_fees_regularly') ?></li>
                    <li><?= __('update_any_connected_services_hotels_transfers_if_applicable') ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>
<div id="letter-management-overview" class="tutorial-content">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0"><i class="fas fa-file-alt me-2"></i><?= __('letter_management_maktob') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('letter-management-overview')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i>
                <strong><?= __('overview') ?>:</strong> <?= __('comprehensive_guide_to_creating_managing_and_tracking_official_letters_maktobs') ?>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-plus-circle me-2"></i><?= __('creating_new_letters') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('letter_basic_information') ?></h6>
                    <p><?= __('fill_in_essential_letter_details') ?>:
                        <ul>
                            <li><?= __('letter_number') ?></li>
                            <li><?= __('letter_date') ?></li>
                            <li><?= __('company_name') ?></li>
                            <li><?= __('language_english_dari_pashto') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="<?= __('letter_basic_information_input_form') ?>"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">2</span>
                <div class="step-content">
                    <h6><?= __('letter_content') ?></h6>
                    <p><?= __('compose_the_letter') ?>:
                        <ul>
                            <li><?= __('subject_line') ?></li>
                            <li><?= __('detailed_content') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="<?= __('letter_content_composition_textarea') ?>"></div>
                </div>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-eye me-2"></i><?= __('viewing_letter_details') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('accessing_letter_details') ?></h6>
                    <p><?= __('multiple_ways_to_view_letter_information') ?>:
                        <ul>
                            <li><?= __('click_view_button_in_recent_letters_table') ?></li>
                            <li><?= __('use_dropdown_actions_menu') ?></li>
                            <li><?= __('hover_over_letter_row_for_quick_preview') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="<?= __('letter_view_access_methods') ?>"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">2</span>
                <div class="step-content">
                    <h6><?= __('letter_information_modal') ?></h6>
                    <p><?= __('comprehensive_letter_details_include') ?>:
                        <ul>
                            <li><?= __('letter_number') ?></li>
                            <li><?= __('company_name') ?></li>
                            <li><?= __('exact_date') ?></li>
                            <li><?= __('language_english_dari_pashto') ?></li>
                            <li><?= __('current_status_draft_sent') ?></li>
                            <li><?= __('full_letter_content') ?></li>
                            <li><?= __('sender_information') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="<?= __('detailed_letter_information_modal') ?>"></div>
                </div>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-download me-2"></i><?= __('letter_export_and_download') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('initiating_letter_download') ?></h6>
                    <p><?= __('download_methods') ?>:
                        <ul>
                            <li><?= __('click_download_icon_in_actions_column') ?></li>
                            <li><?= __('use_download_pdf_button_in_view_modal') ?></li>
                            <li><?= __('keyboard_shortcut_for_quick_export') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="<?= __('letter_download_initiation_methods') ?>"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">2</span>
                <div class="step-content">
                    <h6><?= __('pdf_export_options') ?></h6>
                    <p><?= __('pdf_export_features') ?>:
                        <ul>
                            <li><?= __('preserves_original_formatting') ?></li>
                            <li><?= __('includes_all_letter_details') ?></li>
                            <li><?= __('multilingual_support') ?></li>
                            <li><?= __('professional_letterhead') ?></li>
                            <li><?= __('high-quality_resolution') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="<?= __('pdf_export_preview_and_options') ?>"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">3</span>
                <div class="step-content">
                    <h6><?= __('download_confirmation') ?></h6>
                    <p><?= __('after_downloading') ?>:
                        <ul>
                            <li><?= __('verify_file_location') ?></li>
                            <li><?= __('check_pdf_quality') ?></li>
                            <li><?= __('ensure_all_content_is_visible') ?></li>
                            <li><?= __('save_to_appropriate_folder') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="PDF download confirmation and file saving"></div>
                </div>
            </div>

            <div class="alert alert-warning mt-4">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <strong><?= __('export_best_practices') ?>:</strong>
                <ul>
                    <li><?= __('always_review_pdf_before_sharing') ?></li>
                    <li><?= __('protect_sensitive_information') ?></li>
                    <li><?= __('use_official_communication_channels') ?></li>
                    <li><?= __('maintain_document_integrity') ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>
<div id="ticket-weight" class="tutorial-content">
    <div class="card">
        <div class="card-header bg-success text-white">
            <h4 class="mb-0"><i class="fas fa-weight me-2"></i><?= __('how_to_add_weight_to_tickets') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('ticket-weight')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                <strong><?= __('complete_process') ?>:</strong> <?= __('this_tutorial_covers_the_entire_ticket_weight_management_process_from_accessing_the_weight_page_to_managing_weight_payments_and_transactions') ?>
            </div>

            <!-- Step 1: Navigate to Weight Management -->
            <div class="step-item">
                <span class="step-number">1</span>
                <strong><?= __('navigate_to_weight_management') ?></strong>
                <p><?= __('go_to_bookings_ticket_weights_from_the_main_menu_to_access_the_ticket_weights_management_page_with_all_existing_weight_records') ?>.</p>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot_ticket_weights_management_page_with_add_weight_button') ?>
                </div>
            </div>

            <!-- Step 2: Open Add Weight Modal -->
            <div class="step-item">
                <span class="step-number">2</span>
                <strong><?= __('start_weight_addition_process') ?></strong>
                <p><?= __('click_the_add_weight_button_on_the_top_right_of_the_page_this_will_open_the_weight_addition_modal_where_you_can_search_for_existing_tickets_to_add_baggage_weight') ?>.</p>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot_clicking_add_weight_button') ?>
                </div>
            </div>

            <!-- Step 3: Search for Ticket -->
            <div class="step-item">
                <span class="step-number">3</span>
                <strong><?= __('search_for_original_ticket') ?></strong>
                <p><?= __('in_the_search_section_you_can_find_the_ticket_to_add_weight_using_either_method') ?>:</p>
                <ul class="mt-2 mb-2">
                    <li><strong><?= __('search_by_pnr') ?>:</strong> <?= __('enter_the_passenger_name_record_number_and_click_the_search_button') ?></li>
                    <li><strong><?= __('search_by_passenger') ?>:</strong> <?= __('enter_the_passengers_name_and_click_the_search_button') ?></li>
                </ul>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong><?= __('note') ?>:</strong> <?= __('only_active_tickets_will_appear_in_search_results_you_can_add_multiple_weight_records_to_the_same_ticket') ?>
                </div>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot_search_section_with_pnr_and_passenger_name_fields') ?>
                </div>
            </div>

            <!-- Step 4: Select Ticket from Results -->
            <div class="step-item">
                <span class="step-number">4</span>
                <strong><?= __('select_ticket_for_weight_addition') ?></strong>
                <p><?= __('the_search_results_will_display_matching_tickets_in_a_table_showing') ?>:</p>
                <ul class="mt-2 mb-2">
                    <li><strong><?= __('passenger_name') ?>:</strong> <?= __('full_name_of_the_traveler') ?></li>
                    <li><strong><?= __('pnr') ?>:</strong> <?= __('booking_reference_number') ?></li>
                    <li><strong><?= __('flight_details') ?>:</strong> <?= __('airline_and_route_information') ?></li>
                    <li><strong><?= __('date') ?>:</strong> <?= __('departure_date') ?></li>
                    <li><strong><?= __('action_button') ?>:</strong> <?= __('select_button') ?></li>
                </ul>
                <p><?= __('click_the_select_button_next_to_the_ticket_you_want_to_add_weight_to') ?>.</p>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot_search_results_table_with_selectable_tickets') ?>
                </div>
            </div>

            <!-- Step 5: Enter Weight Details -->
            <div class="step-item">
                <span class="step-number">5</span>
                <strong><?= __('enter_weight_information') ?></strong>
                <p><?= __('after_selecting_a_ticket_the_weight_details_form_will_appear_enter_the_weight_amount') ?>:</p>
                <ul class="mt-2 mb-2">
                    <li><strong><?= __('weight_kg') ?>:</strong> <?= __('enter_the_additional_baggage_weight_in_kilograms_with_up_to_2_decimal_places') ?></li>
                    <li><strong><?= __('required_field') ?>:</strong> <?= __('this_field_is_mandatory_and_must_be_filled') ?></li>
                    <li><strong><?= __('precision') ?>:</strong> <?= __('supports_precise_weight_measurements_eg_2350_kg') ?></li>
                </ul>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong><?= __('tip') ?>:</strong> <?= __('check_the_airlines_current_baggage_weight_policies_and_charges_before_setting_prices') ?>
                </div>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot_weight_field_with_kilogram_input') ?>
                </div>
            </div>

            <!-- Step 6: Enter Pricing Information -->
            <div class="step-item">
                <span class="step-number">6</span>
                <strong><?= __('set_weight_pricing') ?></strong>
                <p><?= __('input_the_pricing_information_for_the_additional_weight') ?>:</p>
                <ul class="mt-2 mb-2">
                    <li><strong><?= __('base_price') ?>:</strong> <?= __('cost_charged_by_the_airline_supplier_for_the_additional_weight') ?></li>
                    <li><strong><?= __('sold_price') ?>:</strong> <?= __('amount_you_will_charge_to_the_customer') ?></li>
                    <li><strong><?= __('auto-calculation') ?>:</strong> <?= __('profit_is_automatically_calculated_as_sold_price_base_price') ?></li>
                </ul>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot_base_price_and_sold_price_fields_with_profit_calculation') ?>
                </div>
            </div>

            <!-- Step 7: View Profit Calculation -->
            <div class="step-item">
                <span class="step-number">7</span>
                <strong><?= __('review_profit_calculation') ?></strong>
                <p><?= __('the_system_automatically_calculates_the_profit_margin') ?>:</p>
                <ul class="mt-2 mb-2">
                    <li><strong><?= __('automatic_calculation') ?>:</strong> <?= __('profit_sold_price_base_price') ?></li>
                    <li><strong><?= __('real-time_updates') ?>:</strong> <?= __('changes_as_you_modify_base_or_sold_prices') ?></li>
                    <li><strong><?= __('read-only_field') ?>:</strong> <?= __('cannot_be_manually_edited') ?></li>
                    <li><strong><?= __('profit_visibility') ?>:</strong> <?= __('helps_you_see_your_margin_on_weight_charges') ?></li>
                </ul>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot_profit_field_showing_calculated_margin') ?>
                </div>
            </div>

            <!-- Step 8: Add Remarks -->
            <div class="step-item">
                <span class="step-number">8</span>
                <strong><?= __('add_weight_remarks_optional') ?></strong>
                <p><?= __('enter_any_additional_notes_or_remarks_about_the_weight_addition') ?>:</p>
                <ul class="mt-2 mb-2">
                    <li><?= __('special_baggage_type_sports_equipment_musical_instruments_etc') ?></li>
                    <li><?= __('customer_requests_or_special_circumstances') ?></li>
                    <li><?= __('airline_policies_applied') ?></li>
                    <li><?= __('communication_notes_with_customer') ?></li>
                    <li><?= __('any_restrictions_or_conditions') ?></li>
                </ul>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot_remarks_textarea_with_sample_weight_notes') ?>
                </div>
            </div>

            <!-- Step 9: Save Weight Record -->
            <div class="step-item">
                <span class="step-number">9</span>
                <strong><?= __('save_the_weight_addition') ?></strong>
                <p><?= __('click_the_save_transaction_button_to_process_the_weight_addition_the_system_will') ?>:</p>
                <ul class="mt-2 mb-2">
                    <li><?= __('create_a_weight_record_linked_to_the_ticket') ?></li>
                    <li><?= __('store_weight_amount_and_pricing_information') ?></li>
                    <li><?= __('calculate_and_save_profit_margin') ?></li>
                    <li><?= __('store_any_remarks_or_notes') ?></li>
                    <li><?= __('close_the_modal_and_refresh_the_weights_list') ?></li>
                    <li><?= __('show_a_success_confirmation_message') ?></li>
                </ul>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot_success_message_after_saving_weight_record') ?>
                </div>
            </div>

            <!-- Step 10: Locate Weight Record -->
            <div class="step-item">
                <span class="step-number">10</span>
                <strong><?= __('find_your_weight_record') ?></strong>
                <p><?= __('the_new_weight_record_will_appear_in_the_main_table_showing') ?>:</p>
                <ul class="mt-2 mb-2">
                    <li><strong><?= __('passenger_details') ?>:</strong> <?= __('name_pnr_phone_created_by') ?></li>
                    <li><strong><?= __('flight_details') ?>:</strong> <?= __('route_information') ?></li>
                    <li><strong><?= __('weight_details') ?>:</strong> <?= __('weight_amount_remarks_exchange_rates') ?></li>
                    <li><strong><?= __('financial_details') ?>:</strong> <?= __('base_price_sold_price_profit_amount') ?></li>
                    <li><strong><?= __('date_added') ?>:</strong> <?= __('when_the_weight_was_added_and_by_whom') ?></li>
                    <li><strong><?= __('payment_status') ?>:</strong> <?= __('color-coded_indicator_redunpaid_yellowpartial_greenpaid') ?></li>
                </ul>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot_new_weight_record_in_the_main_list') ?>
                </div>
            </div>

            <!-- Step 11: Manage Weight Payments -->
            <div class="step-item">
                <span class="step-number">11</span>
                <strong><?= __('process_weight_payments') ?></strong>
                <p><?= __('to_add_payment_transactions_for_the_weight_charges') ?>:</
                <ol class="mt-2 mb-2">
                    <li><?= __('in_the_actions_column_click_the_credit_card_icon_manage_transactions') ?></li>
                    <li><?= __('this_opens_the_comprehensive_transaction_management_modal') ?></li>
                    <li><?= __('the_modal_shows_the_weight_details_and_total_amount_to_be_collected') ?></li>
                    <li><?= __('use_the_transaction_form_to_record_payments') ?></li>
                    <li><?= __('support_for_multiple_currencies_usdafs_eur_darham') ?></li>
                </ol>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot_manage_transactions_button_in_actions') ?>
                </div>
            </div>

            <!-- Step 12: Transaction Management -->
            <div class="step-item">
                <span class="step-number">12</span>
                <strong><?= __('add_weight_fee_transactions') ?></strong>
                <p><?= __('in_the_transaction_modal_add_payments_for_the_weight_charges') ?>:</p>
                <ul class="mt-2 mb-2">
                    <li><strong><?= __('amount') ?>:</strong> <?= __('payment_amount_with_decimal_precision') ?></li>
                    <li><strong><?= __('currency') ?>:</strong> <?= __('usdafs_eur_or_darham_selection') ?></li>
                    <li><strong><?= __('date_and_time') ?>:</strong> <?= __('when_the_payment_was_received') ?></li>
                    <li><strong><?= __('remarks') ?>:</strong> <?= __('payment_notes_and_details') ?></li>
                    <li><strong><?= __('multi-currency_support') ?>:</strong> <?= __('automatic_conversion_and_tracking') ?></li>
                    <li><strong><?= __('payment_tracking') ?>:</strong> <?= __('running_totals_and_remaining_balances') ?></li>
                </ul>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot_transaction_management_modal_with_weight_payment_form') ?>
                </div>
            </div>
            <!-- Step 13: Edit Weight Details -->
            <div class="step-item">
                <span class="step-number">13</span>
                <strong><?= __('edit_weight_information_if_needed') ?></strong>
                <p><?= __('to_modify_weight_details_after_creation') ?>:</p>
                <ol class="mt-2 mb-2">
                    <li><?= __('in_the_actions_column_click_the_edit_icon_edit_weight') ?></li>
                    <li><?= __('this_opens_the_edit_weight_modal_with_current_values') ?></li>
                    <li><?= __('modify_weight_amount_base_price_sold_price_or_remarks') ?></li>
                    <li><?= __('the_profit_is_recalculated_automatically') ?></li>
                    <li>Save the changes</li>
                </ol>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong><?= __('caution') ?>:</strong> <?= __('editing_pricing_affects_the_total_amount_and_any_existing_payment_calculations') ?>
                </div>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot_edit_weight_modal_with_updated_values') ?>
                </div>
            </div>

            <!-- Step 14: Monitor Payment Status -->
            <div class="step-item">
                <span class="step-number">14</span>
                <strong><?= __('track_weight_payment_status') ?></strong>
                <p><?= __('monitor_the_payment_status_using_the_indicators_in_the_main_table') ?>:</p>
                <ul class="mt-2 mb-2">
                    <li><strong><?= __('red_circle') ?>:</strong> <?= __('no_payment_received_for_weight_charges') ?></li>
                    <li><strong><?= __('yellow_circle') ?>:</strong> <?= __('partial_payment_of_weight_charges') ?></li>
                    <li><strong><?= __('green_circle') ?>:</strong> <?= __('fully_paidcompleted') ?></li>
                    <li><strong><?= __('gray_minus') ?>:</strong> <?= __('not_applicable_non_agency_client') ?></li>
                </ul>
                <p><?= __('the_system_automatically_calculates_payment_status_based_on_the_sold_price_amount_and_tracks_all_transactions_with_proper_currency_conversions') ?>.</p>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot_payment_status_indicators_in_the_weight_table') ?>
                </div>
            </div>

            <!-- Step 15: Delete Weight Records -->
            <div class="step-item">
                <span class="step-number">15</span>
                <strong><?= __('delete_weight_records_if_necessary') ?></strong>
                <p><?= __('to_remove_a_weight_record') ?>:</p>
                <ol class="mt-2 mb-2">
                    <li><?= __('in_the_actions_column_click_the_delete_icon_delete_weight') ?></li>
                    <li><?= __('confirm_the_deletion_in_the_popup_dialog') ?></li>
                    <li><?= __('the_system_will_remove_the_weight_record_and_all_associated_transactions') ?></li>
                    <li><?= __('this_action_cannot_be_undone') ?></li>
                </ol>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong><?= __('warning') ?>:</strong> <?= __('deleting_a_weight_record_will_also_remove_all_associated_payment_transactions_this_action_is_permanent') ?>
                </div>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot_delete_confirmation_dialog') ?>
                </div>
            </div>

            <div class="alert alert-success mt-4">
                <i class="fas fa-check-circle me-2"></i>
                <strong><?= __('weight_management_complete') ?>!</strong> <?= __('you_have_successfully_added_and_managed_ticket_weight_the_weight_record_is_now_in_the_system_and_you_can_manage_payments_and_track_the_status_until_completion') ?>
            </div>

            <div class="alert alert-info mt-3">
                <i class="fas fa-lightbulb me-2"></i>
                <strong><?= __('best_practices') ?>:</strong>
                <ul class="mb-0 mt-2">
                    <li><?= __('always_verify_airline_baggage_policies_and_weight_charges_before_processing') ?></li>
                    <li><?= __('document_the_type_of_additional_baggage_clearly_in_remarks') ?></li>
                    <li><?= __('keep_records_of_all_weight_confirmations') ?></li>
                    <li><?= __('communicate_weight_charges_to_customers_promptly') ?></li>
                    <li><?= __('monitor_payment_status_for_weight_fees_regularly') ?></li>
                    <li><?= __('consider_profit_margins_when_setting_weight_prices') ?></li>
                    <li><?= __('update_any_baggage_tags_or_documentation_as_needed') ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<div id="ticket-reserve" class="tutorial-content">
    <div class="card">
        <div class="card-header bg-secondary text-white">
            <h4 class="mb-0"><i class="fas fa-bookmark me-2"></i><?= __('how_to_reserve_tickets') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('ticket-reserve')"></button>
        </div>
        <div class="card-body">
            <div class="step-item">
                <span class="step-number">1</span>
                <strong><?= __('access_reservations') ?></strong>
                <p><?= __('go_to_bookings_tickets_ticket_reservations_from_the_main_menu') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">2</span>
                <strong><?= __('enter_client_details') ?></strong>
                <p><?= __('select_client_and_enter_passenger_information_for_the_reservation') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">3</span>
                <strong><?= __('set_flight_preferences') ?></strong>
                <p><?= __('enter_preferred_travel_dates_destinations_and_flight_preferences') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">4</span>
                <strong><?= __('set_reservation_period') ?></strong>
                <p><?= __('specify_how_long_the_reservation_should_be_held_before_booking') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">5</span>
                <strong><?= __('process_deposit') ?></strong>
                <p><?= __('if_required_collect_a_reservation_deposit_from_the_client') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">6</span>
                <strong><?= __('generate_reservation') ?></strong>
                <p><?= __('save_reservation_and_provide_confirmation_with_booking_deadline') ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Visa Management Tutorials -->
<div id="add-visa" class="tutorial-content">
    <div class="card">
        <div class="card-header" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
            <h4 class="mb-0"><i class="fas fa-passport me-2"></i><?= __('how_to_add_new_visa') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('add-visa')"></button>
        </div>
        <div class="card-body">
            <div class="step-item">
                <span class="step-number">1</span>
                <strong><?= __('navigate_to_visa_section') ?></strong>
                <p><?= __('click_on_visa_from_the_main_menu_to_access_visa_management') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">2</span>
                <strong><?= __('select_client') ?></strong>
                <p><?= __('choose_the_client_from_the_dropdown_or_create_a_new_client_profile') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">3</span>
                <strong><?= __('enter_visa_details') ?></strong>
                <p><?= __('fill_in_visa_type_destination_country_application_date_and_expected_processing_time') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">4</span>
                <strong><?= __('upload_documents') ?></strong>
                <p><?= __('upload_required_documents_such_as_passport_copy_photos_and_supporting_documents') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">5</span>
                <strong><?= __('set_pricing') ?></strong>
                <p><?= __('enter_visa_processing_fees_service_charges_and_payment_terms') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">6</span>
                <strong><?= __('process_application') ?></strong>
                <p><?= __('save_the_visa_application_and_track_its_status_through_the_processing_stages') ?></p>
            </div>
        </div>
    </div>
</div>
<div id="refund-visa" class="tutorial-content">
    <div class="card">
        <div class="card-header" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
            <h4 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i><?= __('how_to_process_visa_refunds') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('refund-visa')"></button>
        </div>
        <div class="card-body">
            <div class="step-item">
                <span class="step-number">1</span>
                <strong><?= __('access_visa_refunds') ?></strong>
                <p><?= __('navigate_to_the_visa_refunds_section_from_the_visa_management_menu') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">2</span>
                <strong><?= __('find_visa_application') ?></strong>
                <p><?= __('search_for_the_visa_application_using_client_name_or_application_number') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">3</span>
                <strong><?= __('check_refund_eligibility') ?></strong>
                <p><?= __('review_the_visa_status_and_refund_policy_to_determine_eligible_refund_amount') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">4</span>
                <strong><?= __('calculate_refund') ?></strong>
                <p><?= __('deduct_processing_fees_and_calculate_the_final_refund_amount') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">5</span>
                <strong><?= __('process_refund') ?></strong>
                <p><?= __('execute_the_refund_transaction_and_update_account_balances') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">6</span>
                <strong><?= __('update_records') ?></strong>
                <p><?= __('mark_visa_as_refunded_and_generate_refund_documentation') ?></p>
            </div>
        </div>
    </div>
</div>

<div id="delete-visa" class="tutorial-content">
    <div class="card">
        <div class="card-header" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
            <h4 class="mb-0"><i class="fas fa-trash me-2"></i><?= __('how_to_delete_visa_records') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('delete-visa')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong><?= __('warning') ?>:</strong> <?= __('deleting_visa_records_is_permanent_and_should_only_be_done_in_specific_circumstances') ?>
            </div>
            <div class="step-item">
                <span class="step-number">1</span>
                <strong><?= __('verify_authorization') ?></strong>
                <p><?= __('ensure_you_have_proper_authorization_to_delete_visa_records') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">2</span>
                <strong><?= __('locate_visa_record') ?></strong>
                <p><?= __('find_the_visa_record_that_needs_to_be_deleted_using_search_functions') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">3</span>
                <strong><?= __('check_dependencies') ?></strong>
                <p><?= __('verify_that_no_active_transactions_or_payments_are_linked_to_this_visa') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">4</span>
                <strong><?= __('backup_information') ?></strong>
                <p><?= __('create_a_backup_or_export_the_visa_information_before_deletion') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">5</span>
                <strong><?= __('confirm_deletion') ?></strong>
                <p><?= __('double_check_all_details_and_confirm_the_deletion_action') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">6</span>
                <strong><?= __('log_activity') ?></strong>
                <p><?= __('document_the_deletion_reason_in_the_system_activity_log') ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Umrah Management Tutorials -->
<div id="add-umrah-family" class="tutorial-content">
    <div class="card">
        <div class="card-header" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
            <h4 class="mb-0"><i class="fas fa-users me-2"></i><?= __('how_to_add_umrah_family') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('add-umrah-family')"></button>
        </div>
        <div class="card-body">
            <div class="step-item">
                <span class="step-number">1</span>
                <strong><?= __('access_umrah_management') ?></strong>
                <p><?= __('click_on_umrah_management_from_the_main_menu') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">2</span>
                <strong><?= __('create_new_family') ?></strong>
                <p><?= __('click_add_new_family_and_enter_family_head_information') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">3</span>
                <strong><?= __('enter_package_details') ?></strong>
                <p><?= __('select_umrah_package_travel_dates_and_accommodation_preferences') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">4</span>
                <strong><?= __('set_pricing') ?></strong>
                <p><?= __('enter_package_price_payment_terms_and_any_special_discounts') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">5</span>
                <strong><?= __('process_initial_payment') ?></strong>
                <p><?= __('collect_registration_fee_or_advance_payment_for_the_umrah_package') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">6</span>
                <strong><?= __('generate_family_id') ?></strong>
                <p><?= __('save_family_information_and_generate_unique_family_identification_number') ?></p>
            </div>
        </div>
    </div>
</div>
<div id="add-umrah-member" class="tutorial-content">
    <div class="card">
        <div class="card-header" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
            <h4 class="mb-0"><i class="fas fa-user-plus me-2"></i><?= __('how_to_add_umrah_member') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('add-umrah-member')"></button>
        </div>
        <div class="card-body">
            <div class="step-item">
                <span class="step-number">1</span>
                <strong><?= __('select_family') ?></strong>
                <p><?= __('choose_the_family_to_which_you_want_to_add_a_new_member') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">2</span>
                <strong><?= __('enter_personal_details') ?></strong>
                <p><?= __('fill_in_members_name_relationship_to_family_head_age_and_contact_information') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">3</span>
                <strong><?= __('upload_documents') ?></strong>
                <p><?= __('upload_passport_copy_photos_and_any_required_medical_certificates') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">4</span>
                <strong><?= __('set_member_pricing') ?></strong>
                <p><?= __('enter_individual_member_costs_if_different_from_family_package') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">5</span>
                <strong><?= __('process_member_payment') ?></strong>
                <p><?= __('collect_payment_for_the_additional_member_if_required') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">6</span>
                <strong><?= __('generate_member_id') ?></strong>
                <p><?= __('save_member_information_and_issue_unique_member_identification') ?></p>
            </div>
        </div>
    </div>
</div>

<div id="edit-umrah" class="tutorial-content">
    <div class="card">
        <div class="card-header" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
            <h4 class="mb-0"><i class="fas fa-edit me-2"></i><?= __('how_to_edit_umrah_records') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('edit-umrah')"></button>
        </div>
        <div class="card-body">
            <div class="step-item">
                <span class="step-number">1</span>
                <strong><?= __('find_record') ?></strong>
                <p><?= __('search_for_the_family_or_member_record_you_want_to_edit') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">2</span>
                <strong><?= __('click_edit') ?></strong>
                <p><?= __('click_the_edit_button_next_to_the_record_you_want_to_modify') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">3</span>
                <strong><?= __('make_changes') ?></strong>
                <p><?= __('update_the_necessary_information_such_as_dates_contact_details_or_package_details') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">4</span>
                <strong><?= __('verify_information') ?></strong>
                <p><?= __('double_check_all_changes_for_accuracy_before_saving') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">5</span>
                <strong><?= __('process_payment_adjustments') ?></strong>
                <p><?= __('if_price_changes_affect_payments_process_any_additional_charges_or_refunds') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">6</span>
                <strong><?= __('save_changes') ?></strong>
                <p><?= __('save_all_modifications_and_notify_the_family_of_any_important_changes') ?></p>
            </div>
        </div>
    </div>
</div>

<div id="umrah-transactions" class="tutorial-content">
    <div class="card">
        <div class="card-header" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
            <h4 class="mb-0"><i class="fas fa-exchange-alt me-2"></i><?= __('how_to_manage_umrah_transactions') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('umrah-transactions')"></button>
        </div>
        <div class="card-body">
            <div class="step-item">
                <span class="step-number">1</span>
                <strong><?= __('select_family_member') ?></strong>
                <p><?= __('choose_the_family_or_individual_member_for_transaction_processing') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">2</span>
                <strong><?= __('choose_transaction_type') ?></strong>
                <p><?= __('select_payment_type_advance_payment_installment_full_payment_or_refund') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">3</span>
                <strong><?= __('enter_amount') ?></strong>
                <p><?= __('input_the_transaction_amount_and_select_currency_if_applicable') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">4</span>
                <strong><?= __('select_payment_method') ?></strong>
                <p><?= __('choose_payment_method_cash_bank_transfer_credit_card_or_check') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">5</span>
                <strong><?= __('add_transaction_notes') ?></strong>
                <p><?= __('include_any_relevant_notes_or_references_for_the_transaction') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">6</span>
                <strong><?= __('process_and_receipt') ?></strong>
                <p><?= __('complete_the_transaction_and_generate_receipt_for_the_customer') ?></p>
            </div>
        </div>
    </div>
</div>
<!-- Ticket Transaction Management Tutorial -->
<div id="manage-ticket-transactions" class="tutorial-content">
    <div class="card">
        <div class="card-header bg-success text-white">
            <h4 class="mb-0"><i class="fas fa-credit-card me-2"></i><?= __('how_to_manage_ticket_transactions') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('manage-ticket-transactions')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                <strong><?= __('purpose') ?>:</strong> <?= __('this_tutorial_shows_how_to_add_edit_and_manage_payment_transactions_for_existing_tickets') ?>
            </div>

            <!-- Step 1: Locate Ticket -->
            <div class="step-item">
                <span class="step-number">1</span>
                <strong><?= __('find_the_ticket') ?></strong>
                <p><?= __('navigate_to_the_ticket_management_page_and_locate_the_ticket_for_which_you_want_to_manage_transactions_you_can') ?></p>
                <ul class="mt-2 mb-2">
                    <li><?= __('browse_through_the_ticket_list') ?></li>
                    <li><?= __('use_the_search_box_to_find_by_pnr_passenger_name_or_airline') ?></li>
                    <li><?= __('look_for_tickets_with_payment_status_indicators_redunpaid_yellowpartial_greenfully_paid') ?></li>
                </ul>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot_ticket_list_with_search_functionality') ?>
                </div>
            </div>

            <!-- Step 2: Access Actions Menu -->
            <div class="step-item">
                <span class="step-number">2</span>
                <strong><?= __('open_actions_menu') ?></strong>
                <p><?= __('in_the_ticket_row_click_the_three-dot_menu_in_the_action_column_to_see_available_options') ?></p>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot_clicking_the_actions_dropdown_menu') ?>
                </div>
            </div>

            <!-- Step 3: Select Manage Transactions -->
            <div class="step-item">
                <span class="step-number">3</span>
                <strong><?= __('select_manage_transactions') ?></strong>
                <p><?= __('from_the_dropdown_menu_click_on_manage_transactions_shown_with_a_dollar_sign_icon_this_will_open_the_transaction_management_modal') ?></p>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot_dropdown_menu_with_manage_transactions_highlighted') ?>
                </div>
            </div>

            <!-- Step 4: Review Ticket Information -->
            <div class="step-item">
                <span class="step-number">4</span>
                <strong><?= __('review_ticket_information') ?></strong>
                <p><?= __('the_transaction_modal_displays_comprehensive_ticket_and_payment_information') ?></p>
                <ul class="mt-2 mb-2">
                    <li><strong><?= __('ticket_details') ?>:</strong> <?= __('passenger_name_and_pnr_number') ?></li>
                    <li><strong><?= __('total_amount') ?>:</strong> <?= __('full_ticket_price_in_original_currency') ?></li>
                    <li><strong><?= __('exchange_rate') ?>:</strong> <?= __('current_rate_used_for_conversions') ?></li>
                    <li><strong><?= __('exchanged_amount') ?>:</strong> <?= __('price_converted_to_local_currency') ?></li>
                    <li><strong><?= __('payment_status') ?>:</strong> <?= __('paid_and_remaining_amounts_by_currency') ?></li>
                </ul>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot_transaction_modal_showing_ticket_information') ?>
                </div>
            </div>

            <!-- Step 5: Review Transaction History -->
            <div class="step-item">
                <span class="step-number">5</span>
                <strong><?= __('check_existing_transactions') ?></strong>
                <p><?= __('the_transaction_history_table_shows_all_previous_payments_for_this_ticket_including') ?></p>
                <ul class="mt-2 mb-2">
                    <li><strong><?= __('date') ?>:</strong> <?= __('when_the_payment_was_made') ?></li>
                    <li><strong><?= __('description') ?>:</strong> <?= __('payment_notes_or_type') ?></li>
                    <li><strong><?= __('payment_method') ?>:</strong> <?= __('how_the_payment_was_received') ?></li>
                    <li><strong><?= __('amount') ?>:</strong> <?= __('payment_amount_and_currency') ?></li>
                    <li><strong><?= __('actions') ?>:</strong> <?= __('edit_or_delete_options_for_each_transaction') ?></li>
                </ul>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot_transaction_history_table') ?>
                </div>
            </div>

            <!-- Step 6: Add New Transaction -->
            <div class="step-item">
                <span class="step-number">6</span>
                <strong><?= __('start_adding_new_transaction') ?></strong>
                <p><?= __('to_add_a_new_payment_click_the_new_transaction_button_in_the_transaction_history_section_header_this_will_expand_the_add_transaction_form_below_the_table') ?></p>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot_clicking_new_transaction_button') ?>
                </div>
            </div>

            <!-- Step 7: Fill Transaction Form -->
            <div class="step-item">
                <span class="step-number">7</span>
                <strong><?= __('complete_payment_details') ?></strong>
                <p><?= __('fill_out_the_transaction_form_with_accurate_payment_information') ?></p>
                <ul class="mt-2 mb-2">
                    <li><strong><?= __('payment_date') ?>:</strong> <?= __('select_the_date_when_payment_was_received') ?></li>
                    <li><strong><?= __('payment_time') ?>:</strong> <?= __('enter_the_time_hhmmss_format_used_for_tracking') ?></li>
                    <li><strong><?= __('amount') ?>:</strong> <?= __('enter_the_payment_amount_numeric_value_only') ?></li>
                    <li><strong><?= __('currency') ?>:</strong> <?= __('select_usdafs_eur_or_darham_from_dropdown') ?></li>
                    <li><strong><?= __('description') ?>:</strong> <?= __('add_notes_like_partial_payment_full_settlement_cash_payment_via_agent_etc') ?></li>
                </ul>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong><?= __('important') ?>:</strong> <?= __('make_sure_the_currency_matches_the_actual_payment_received') ?>
                </div>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot_transaction_form_filled_with_payment_details') ?>
                </div>
            </div>

            <!-- Step 8: Save Transaction -->
            <div class="step-item">
                <span class="step-number">8</span>
                <strong><?= __('save_the_transaction') ?></strong>
                <p><?= __('click_the_add_transaction_button_to_save_the_system_will') ?></p>
                <ul class="mt-2 mb-2">
                    <li><?= __('validate_all_required_fields') ?></li>
                    <li><?= __('convert_amounts_based_on_exchange_rates') ?></li>
                    <li><?= __('update_payment_status_calculations') ?></li>
                    <li><?= __('add_transaction_to_the_history_table') ?></li>
                    <li><?= __('show_success_confirmation') ?></li>
                    <li><?= __('collapse_the_form_automatically') ?></li>
                </ul>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot_success_message_after_saving_transaction') ?>
                </div>
            </div>

            <!-- Step 9: Verify Updates -->
            <div class="step-item">
                <span class="step-number">9</span>
                <strong><?= __('confirm_payment_status_updates') ?></strong>
                <p><?= __('after_saving_verify_that_all_information_has_been_updated_correctly') ?></p>
                <ul class="mt-2 mb-2">
                    <li><strong><?= __('payment_summary') ?>:</strong> <?= __('paid_amounts_should_increase_remaining_amounts_should_decrease') ?></li>
                    <li><strong><?= __('transaction_table') ?>:</strong> <?= __('new_transaction_appears_at_the_top_of_the_list') ?></li>
                    <li><strong><?= __('status_indicators') ?>:</strong> <?= __('currency_sections_show_updated_balances') ?></li>
                    <li><strong><?= __('color_coding') ?>:</strong> <?= __('green_for_paid_amounts_red_for_remaining_balances') ?></li>
                </ul>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot_updated_payment_status_and_new_transaction_in_history') ?>
                </div>
            </div>

            <!-- Step 10: Edit Existing Transactions -->
            <div class="step-item">
                <span class="step-number">10</span>
                <strong><?= __('edit_existing_transactions_optional') ?></strong>
                <p><?= __('to_modify_an_existing_transaction') ?></p>
                <ol class="mt-2 mb-2">
                    <li><?= __('find_the_transaction_in_the_history_table') ?></li>
                    <li><?= __('click_the_edit_icon_in_the_actions_column') ?></li>
                    <li><?= __('modify_the_details_in_the_popup_form') ?></li>
                    <li><?= __('save_changes_to_update_the_record') ?></li>
                </ol>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong><?= __('caution') ?>:</strong> <?= __('editing_transactions_affects_financial_calculations_only_edit_when_necessary') ?>
                </div>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot_editing_an_existing_transaction') ?>
                </div>
            </div>

            <!-- Step 11: Delete Transactions -->
            <div class="step-item">
                <span class="step-number">11</span>
                <strong><?= __('delete_transactions_if_needed') ?></strong>
                <p><?= __('to_remove_an_incorrect_transaction') ?></p>
                <ol class="mt-2 mb-2">
                    <li><?= __('locate_the_transaction_in_the_history_table') ?></li>
                    <li><?= __('click_the_delete_icon_in_the_actions_column') ?></li>
                    <li><?= __('confirm_the_deletion_in_the_popup_dialog') ?></li>
                    <li><?= __('the_transaction_will_be_removed_and_balances_recalculated') ?></li>
                </ol>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong><?= __('warning') ?>:</strong> <?= __('deleted_transactions_cannot_be_recovered_use_this_feature_carefully') ?>
                </div>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot_deleting_a_transaction_with_confirmation_dialog') ?>
                </div>
            </div>

            <!-- Step 12: Close and Return -->
            <div class="step-item">
                <span class="step-number">12</span>
                <strong><?= __('complete_transaction_management') ?></strong>
                <p><?= __('when_finished_managing_transactions') ?></p>
                <ol class="mt-2 mb-2">
                    <li><?= __('review_all_changes_and_verify_accuracy') ?></li>
                    <li><?= __('click_the_close_button_to_exit_the_modal') ?></li>
                    <li><?= __('return_to_the_ticket_list_where_payment_indicators_will_be_updated') ?></li>
                    <li><?= __('the_ticket_s_payment_status_icon_will_reflect_the_new_payment_status') ?></li>
                </ol>
                <div class="screenshot-placeholder mt-2">
                    <i class="fas fa-image me-1"></i> <?= __('screenshot_updated_ticket_list_showing_new_payment_status') ?>
                </div>
            </div>

            <div class="alert alert-success mt-4">
                <i class="fas fa-check-circle me-2"></i>
                <strong><?= __('transaction_management_complete') ?>!</strong> <?= __('you_have_successfully_managed_payment_transactions_for_the_ticket_all_financial_records_are_now_up_to_date') ?>
            </div>
        </div>
    </div>
</div>

<!-- Financial Management Tutorials -->
<div id="add-transactions" class="tutorial-content">
    <div class="card">
        <div class="card-header" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white;">
            <h4 class="mb-0"><i class="fas fa-plus me-2"></i><?= __('how_to_add_transactions') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('add-transactions')"></button>
        </div>
        <div class="card-body">
            <div class="step-item">
                <span class="step-number">1</span>
                <strong><?= __('access_accounts') ?></strong>
                <p><?= __('navigate_to_accounts_from_the_main_menu') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">2</span>
                <strong><?= __('select_account_type') ?></strong>
                <p><?= __('choose_the_account_type_main_account_client_account_or_supplier_account') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">3</span>
                <strong><?= __('click_add_transaction') ?></strong>
                <p><?= __('click_the_add_transaction_button_for_the_selected_account') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">4</span>
                <strong><?= __('enter_transaction_details') ?></strong>
                <p><?= __('fill_in_transaction_type_debitcredit_amount_description_and_reference_number') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">5</span>
                <strong><?= __('select_category') ?></strong>
                <p><?= __('choose_appropriate_transaction_category_for_proper_bookkeeping') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">6</span>
                <strong><?= __('save_transaction') ?></strong>
                <p><?= __('review_all_details_and_save_the_transaction_account_balance_will_update_automatically') ?></p>
            </div>
        </div>
    </div>
</div>

<div id="edit-transactions" class="tutorial-content">
    <div class="card">
        <div class="card-header" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white;">
            <h4 class="mb-0"><i class="fas fa-edit me-2"></i><?= __('how_to_edit_transactions') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('edit-transactions')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                <strong><?= __('note') ?>:</strong> <?= __('editing_transactions_affects_account_balances_use_caution_when_modifying_existing_records') ?>
            </div>
            <div class="step-item">
                <span class="step-number">1</span>
                <strong><?= __('find_transaction') ?></strong>
                <p><?= __('use_the_search_function_to_locate_the_transaction_you_want_to_edit') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">2</span>
                <strong><?= __('click_edit_button') ?></strong>
                <p><?= __('click_the_edit_icon_next_to_the_transaction_in_the_transaction_list') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">3</span>
                <strong><?= __('modify_details') ?></strong>
                <p><?= __('update_the_transaction_amount_description_category_or_other_details_as_needed') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">4</span>
                <strong><?= __('add_edit_reason') ?></strong>
                <p><?= __('enter_a_reason_for_the_modification_for_audit_trail_purposes') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">5</span>
                <strong><?= __('verify_impact') ?></strong>
                <p><?= __('review_how_the_changes_will_affect_account_balances_and_reports') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">6</span>
                <strong><?= __('save_changes') ?></strong>
                <p><?= __('confirm_and_save_the_transaction_modifications') ?></p>
            </div>
        </div>
    </div>
</div>

<div id="fund-main-accounts" class="tutorial-content">
    <div class="card">
        <div class="card-header" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white;">
            <h4 class="mb-0"><i class="fas fa-piggy-bank me-2"></i><?= __('how_to_fund_main_accounts') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('fund-main-accounts')"></button>
        </div>
        <div class="card-body">
            <div class="step-item">
                <span class="step-number">1</span>
                <strong><?= __('access_main_accounts') ?></strong>
                <p><?= __('go_to_accounts_and_locate_the_main_accounts_section') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">2</span>
                <strong><?= __('select_account') ?></strong>
                <p><?= __('choose_the_main_account_you_want_to_fund_eg_cash_bank_etc') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">3</span>
                <strong><?= __('click_fund_account') ?></strong>
                <p><?= __('click_the_fund_account_or_add_funds_button_for_the_selected_account') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">4</span>
                <strong><?= __('enter_fund_details') ?></strong>
                <p><?= __('input_the_funding_amount_source_of_funds_and_transaction_reference') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">5</span>
                <strong><?= __('add_documentation') ?></strong>
                <p><?= __('upload_or_reference_any_supporting_documents_for_the_funding_transaction') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">6</span>
                <strong><?= __('process_funding') ?></strong>
                <p><?= __('confirm_the_funding_transaction_the_account_balance_will_be_updated_immediately') ?></p>
            </div>
        </div>
    </div>
</div>

<div id="fund-suppliers" class="tutorial-content">
    <div class="card">
        <div class="card-header" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white;">
            <h4 class="mb-0"><i class="fas fa-truck me-2"></i><?= __('how_to_fund_suppliers') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('fund-suppliers')"></button>
        </div>
        <div class="card-body">
            <div class="step-item">
                <span class="step-number">1</span>
                <strong><?= __('navigate_to_suppliers') ?></strong>
                <p><?= __('go_to_supplier_from_the_main_menu_or_access_via_accounts') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">2</span>
                <strong><?= __('select_supplier') ?></strong>
                <p><?= __('choose_the_supplier_account_you_want_to_fund_from_the_supplier_list') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">3</span>
                <strong><?= __('view_transaction_history') ?></strong>
                <p><?= __('review_the_supplier_s_current_balance_and_transaction_history') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">4</span>
                <strong><?= __('add_payment') ?></strong>
                <p><?= __('click_add_payment_and_enter_payment_amount_and_method') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">5</span>
                <strong><?= __('include_payment_details') ?></strong>
                <p><?= __('add_payment_reference_invoice_numbers_and_payment_description') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">6</span>
                <strong><?= __('process_payment') ?></strong>
                <p><?= __('complete_the_payment_transaction_and_update_supplier_account_balance') ?></p>
            </div>
        </div>
    </div>
</div>
<div id="fund-clients" class="tutorial-content">
    <div class="card">
        <div class="card-header" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white;">
            <h4 class="mb-0"><i class="fas fa-user-tie me-2"></i><?= __('how_to_fund_clients') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('fund-clients')"></button>
        </div>
        <div class="card-body">
            <div class="step-item">
                <span class="step-number">1</span>
                <strong><?= __('access_client_management') ?></strong>
                <p><?= __('navigate_to_client_from_the_main_menu') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">2</span>
                <strong><?= __('find_client_account') ?></strong>
                <p><?= __('search_for_and_select_the_client_account_you_want_to_fund') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">3</span>
                <strong><?= __('check_account_status') ?></strong>
                <p><?= __('review_the_client_s_current_balance_and_account_standing') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">4</span>
                <strong><?= __('add_credit') ?></strong>
                <p><?= __('click_add_credit_or_fund_account_and_enter_the_credit_amount') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">5</span>
                <strong><?= __('select_payment_source') ?></strong>
                <p><?= __('choose_the_source_account_from_which_the_funds_will_be_transferred') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">6</span>
                <strong><?= __('complete_transaction') ?></strong>
                <p><?= __('process_the_funding_and_update_both_client_and_source_account_balances') ?></p>
            </div>
        </div>
    </div>
</div>

<div id="transaction-history" class="tutorial-content">
    <div class="card">
        <div class="card-header" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white;">
            <h4 class="mb-0"><i class="fas fa-history me-2"></i><?= __('how_to_view_transaction_history') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('transaction-history')"></button>
        </div>
        <div class="card-body">
            <div class="step-item">
                <span class="step-number">1</span>
                <strong><?= __('select_account_type') ?></strong>
                <p><?= __('choose_between_main_accounts_client_accounts_or_supplier_accounts') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">2</span>
                <strong><?= __('pick_specific_account') ?></strong>
                <p><?= __('select_the_specific_account_for_which_you_want_to_view_transaction_history') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">3</span>
                <strong><?= __('set_date_range') ?></strong>
                <p><?= __('choose_the_date_range_for_the_transaction_history_you_want_to_review') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">4</span>
                <strong><?= __('apply_filters') ?></strong>
                <p><?= __('use_filters_to_narrow_down_transactions_by_type_amount_range_or_category') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">5</span>
                <strong><?= __('review_transactions') ?></strong>
                <p><?= __('examine_individual_transactions_including_amounts_dates_and_descriptions') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">6</span>
                <strong><?= __('export_or_print') ?></strong>
                <p><?= __('export_the_transaction_history_to_excel_or_print_for_record_keeping_purposes') ?></p>
            </div>
        </div>
    </div>
</div>
<!-- Sarafi Hawala and Currency Exchange Tutorials -->
<div id="sarafi-hawala-exchanges" class="tutorial-content">
    <div class="card">
        <div class="card-header bg-finance text-white">
            <h4 class="mb-0"><i class="fas fa-globe me-2"></i><?= __('sarafi_hawala_transfers_and_currency_exchanges') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('sarafi-hawala-exchanges')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i>
                <strong><?= __('overview') ?>:</strong> <?= __('comprehensive_guide_to_managing_hawala_transfers_and_currency_exchanges_in_the_sarafi_system') ?>
            </div>

            <h4 class="mt-4 mb-3"><i class="fas fa-repeat me-2"></i><?= __('hawala_transfer_process') ?></h4>

            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('navigate_to_hawala_transfer_section') ?></h6>
                    <p><?= __('access_the_sarafi_dashboard_and_locate_the_hawala_transfer_button') ?></p>
                    <div class="screenshot-placeholder" data-description="Sarafi dashboard with Hawala transfer button"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">2</span>
                <div class="step-content">
                    <h6><?= __('open_hawala_transfer_modal') ?></h6>
                    <p><?= __('click_the_hawala_transfer_button_to_initiate_a_new_transfer') ?></p>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle mr-2"></i>
                        <?= __('ensure_you_have_the_necessary_permissions_to_process_hawala_transfers') ?>
                    </div>
                    <div class="screenshot-placeholder" data-description="Hawala transfer modal opening"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">3</span>
                <div class="step-content">
                    <h6><?= __('select_sender') ?></h6>
                    <p><?= __('choose_the_sender_from_the_customer_dropdown_menu') ?></p>
                    <ul>
                        <li><?= __('dropdown_shows_customer_names') ?></li>
                        <li><?= __('displays_current_wallet_balances') ?></li>
                        <li><?= __('search_and_filter_options_available') ?></li>
                    </ul>
                    <div class="screenshot-placeholder" data-description="Sender selection dropdown"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">4</span>
                <div class="step-content">
                    <h6><?= __('enter_transfer_details') ?></h6>
                    <p><?= __('fill_in_the_hawala_transfer_details') ?></p>
                    <ul>
                        <li><?= __('send_amount') ?></li>
                        <li><?= __('send_currency') ?></li>
                        <li><?= __('commission_amount') ?></li>
                        <li><?= __('commission_currency') ?></li>
                    </ul>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <?= __('ensure_the_sender_has_sufficient_balance_for_the_transfer') ?>
                    </div>
                    <div class="screenshot-placeholder" data-description="Hawala transfer amount and currency inputs"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">5</span>
                <div class="step-content">
                    <h6><?= __('generate_secret_code') ?></h6>
                    <p><?= __('create_a_unique_secret_code_for_the_hawala_transfer') ?></p>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle mr-2"></i>
                        <?= __('the_secret_code_is_used_by_the_receiver_to_claim_the_transfer') ?>
                    </div>
                    <div class="screenshot-placeholder" data-description="Secret code generation interface"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">6</span>
                <div class="step-content">
                    <h6><?= __('select_main_account') ?></h6>
                    <p><?= __('choose_the_main_account_for_the_hawala_transfer_transaction') ?></p>
                    <div class="screenshot-placeholder" data-description="Main account selection dropdown"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">7</span>
                <div class="step-content">
                    <h6><?= __('add_optional_notes') ?></h6>
                    <p><?= __('include_any_additional_notes_or_context_for_the_transfer') ?></p>
                    <div class="screenshot-placeholder" data-description="Notes textarea"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">8</span>
                <div class="step-content">
                    <h6><?= __('process_hawala_transfer') ?></h6>
                    <p><?= __('click_process_payment_to_complete_the_hawala_transfer') ?></p>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle mr-2"></i>
                        <?= __('the_system_will') ?>:
                        <ul>
                            <li><?= __('deduct_transfer_amount_from_sender_s_wallet') ?></li>
                            <li><?= __('record_the_hawala_transaction') ?></li>
                            <li><?= __('update_main_account_balance') ?></li>
                            <li><?= __('generate_a_unique_transaction_record') ?></li>
                        </ul>
                    </div>
                    <div class="screenshot-placeholder" data-description="Hawala transfer confirmation and success message"></div>
                </div>
            </div>

            <h4 class="mt-4 mb-3"><i class="fas fa-exchange-alt me-2"></i><?= __('currency_exchange_process') ?></h4>

            <div class="step-item">
                <span class="step-number">9</span>
                <div class="step-content">
                    <h6><?= __('navigate_to_currency_exchange_section') ?></h6>
                    <p><?= __('access_the_sarafi_dashboard_and_locate_the_currency_exchange_button') ?></p>
                    <div class="screenshot-placeholder" data-description="Sarafi dashboard with currency exchange button"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">10</span>
                <div class="step-content">
                    <h6><?= __('open_currency_exchange_modal') ?></h6>
                    <p><?= __('click_the_currency_exchange_button_to_initiate_a_new_exchange') ?></p>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle mr-2"></i>
                        <?= __('ensure_you_have_the_necessary_permissions_to_process_currency_exchanges') ?>
                    </div>
                    <div class="screenshot-placeholder" data-description="Currency exchange modal opening"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">11</span>
                <div class="step-content">
                    <h6><?= __('select_customer') ?></h6>
                    <p><?= __('choose_the_customer_for_the_currency_exchange_from_the_dropdown_menu') ?></p>
                    <ul>
                        <li><?= __('dropdown_shows_customer_names') ?></li>
                        <li><?= __('displays_current_wallet_balances') ?></li>
                        <li><?= __('search_and_filter_options_available') ?></li>
                    </ul>
                    <div class="screenshot-placeholder" data-description="Customer selection dropdown for exchange"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">12</span>
                <div class="step-content">
                    <h6><?= __('enter_exchange_details') ?></h6>
                    <p><?= __('fill_in_the_currency_exchange_details') ?></p>
                    <ul>
                        <li><?= __('from_amount') ?></li>
                        <li><?= __('from_currency') ?></li>
                        <li><?= __('to_amount') ?></li>
                        <li><?= __('to_currency') ?></li>
                        <li><?= __('exchange_rate') ?></li>
                    </ul>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?= __('ensure_the_customer_has_sufficient_balance_in_the_source_currency') ?>
                    </div>
                    <div class="screenshot-placeholder" data-description="Currency exchange amount and rate inputs"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">13</span>
                <div class="step-content">
                    <h6><?= __('add_optional_notes') ?></h6>
                    <p><?= __('include_any_additional_notes_or_context_for_the_currency_exchange') ?></p>
                    <div class="screenshot-placeholder" data-description="Notes textarea for exchange"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">14</span>
                <div class="step-content">
                    <h6><?= __('process_currency_exchange') ?></h6>
                    <p><?= __('click_exchange_to_complete_the_currency_conversion') ?></p>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle mr-2"></i>
                        <?= __('the_system_will') ?>:
                        <ul>
                            <li><?= __('deduct_amount_from_source_currency_wallet') ?></li>
                            <li><?= __('add_converted_amount_to_destination_currency_wallet') ?></li>
                            <li><?= __('record_the_currency_exchange_transaction') ?></li>
                            <li><?= __('generate_a_unique_transaction_record') ?></li>
                        </ul>
                    </div>
                    <div class="screenshot-placeholder" data-description="Currency exchange confirmation and success message"></div>
                </div>
            </div>

            <div class="alert alert-warning mt-4">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <strong><?= __('important_considerations') ?>:</strong>
                <ul>
                    <li><?= __('always_verify_customer_identity_before_processing_transactions') ?></li>
                    <li><?= __('ensure_sufficient_balance_for_transfers_and_exchanges') ?></li>
                    <li><?= __('double_check_exchange_rates_and_amounts') ?></li>
                    <li><?= __('maintain_accurate_documentation') ?></li>
                    <li><?= __('follow_company_financial_policies') ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>
<!-- Reports & System Management Tutorials -->
<div id="export-reports" class="tutorial-content">
    <div class="card">
        <div class="card-header" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white;">
            <h4 class="mb-0"><i class="fas fa-file-export me-2"></i><?= __('how_to_export_reports') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('export-reports')"></button>
        </div>
        <div class="card-body">
            <div class="step-item">
                <span class="step-number">1</span>
                <strong><?= __('access_reports') ?></strong>
                <p><?= __('navigate_to_reports_from_the_main_menu') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">2</span>
                <strong><?= __('select_report_type') ?></strong>
                <p><?= __('choose_the_type_of_report_financial_transaction_client_supplier_or_custom_report') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">3</span>
                <strong><?= __('set_parameters') ?></strong>
                <p><?= __('configure_report_parameters_including_date_range_accounts_and_filters') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">4</span>
                <strong><?= __('generate_preview') ?></strong>
                <p><?= __('click_generate_report_to_preview_the_report_data_before_exporting') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">5</span>
                <strong><?= __('choose_export_format') ?></strong>
                <p><?= __('select_export_format_pdf_excel_csv_or_word_document') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">6</span>
                <strong><?= __('download_report') ?></strong>
                <p><?= __('click_export_to_download_the_report_to_your_computer') ?></p>
            </div>
        </div>
    </div>
</div>

<div id="db-backup" class="tutorial-content">
    <div class="card">
        <div class="card-header" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); color: white;">
            <h4 class="mb-0"><i class="fas fa-database me-2"></i>How to Create Database Backup</h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('db-backup')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong><?= __('important') ?>:</strong> <?= __('regular_backups_are_crucial_for_data_protection_schedule_automatic_backups_for_best_results') ?>
            </div>
            <div class="step-item">
                <span class="step-number">1</span>
                <strong><?= __('access_backup_management') ?></strong>
                <p><?= __('navigate_to_backup_management_from_the_main_menu') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">2</span>
                <strong><?= __('select_backup_type') ?></strong>
                <p><?= __('choose_between_full_backup_incremental_backup_or_specific_table_backup') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">3</span>
                <strong><?= __('set_backup_options') ?></strong>
                <p><?= __('configure_backup_compression_encryption_and_storage_location_options') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">4</span>
                <strong><?= __('add_description') ?></strong>
                <p><?= __('enter_a_description_for_the_backup_to_help_identify_it_later') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">5</span>
                <strong><?= __('start_backup_process') ?></strong>
                <p><?= __('click_create_backup_to_begin_the_backup_process_monitor_progress') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">6</span>
                <strong><?= __('verify_backup') ?></strong>
                <p><?= __('once_complete_verify_the_backup_file_integrity_and_store_securely') ?></p>
            </div>
            <div class="step-item">
                <span class="step-number">7</span>
                <strong><?= __('schedule_automatic_backups') ?></strong>
                <p><?= __('set_up_automatic_daily_weekly_or_monthly_backup_schedules_for_ongoing_protection') ?></p>
            </div>
        </div>
    </div>
</div>
<!-- Ticket Reservation Tutorial -->
<div id="ticket-reservations" class="tutorial-content">
    <div class="card">
        <div class="card-header bg-info text-white">
            <h4 class="mb-0"><i class="fas fa-plane me-2"></i><?= __('how_to_reserve_tickets') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('ticket-reservations')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i>
                <strong><?= __('overview') ?>:</strong> <?= __('this_tutorial_covers_the_complete_process_of_reserving_tickets_including_basic_information_entry_flight_details_pricing_and_payment_setup') ?>
            </div>

            <div class="step-item">
                <span class="step-number">1</span>
                <strong><?= __('navigate_to_ticket_reservations') ?></strong>
                <p><?= __('go_to_bookings_ticket_reservations_from_the_main_menu_to_access_the_reservation_management_page') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_ticket_reservations_page_with_search_bar_and_reserve_ticket_button') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">2</span>
                <strong><?= __('start_reservation_process') ?></strong>
                <p><?= __('click_the_reserve_ticket_button_to_open_the_reservation_booking_modal') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_clicking_the_reserve_ticket_button') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">3</span>
                <strong><?= __('select_supplier') ?></strong>
                <p><?= __('choose_the_supplier_from_the_dropdown_list_this_field_is_searchable_for_quick_selection') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_supplier_dropdown_with_search_functionality') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">4</span>
                <strong><?= __('choose_client_sold_to') ?></strong>
                <p><?= __('select_the_client_who_is_purchasing_the_ticket_from_the_searchable_dropdown_list') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_client_selection_dropdown_with_active_clients') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">5</span>
                <strong><?= __('set_trip_type') ?></strong>
                <p><?= __('choose_between_one_way_or_round_trip_this_will_showhide_return_journey_fields') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_trip_type_dropdown_selection') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">6</span>
                <strong><?= __('enter_passenger_details') ?></strong>
                <p><?= __('fill_in_passenger_information') ?></p>
                <ul>
                    <li><strong><?= __('title') ?>:</strong> <?= __('mr_mrs_or_child') ?></li>
                    <li><strong><?= __('gender') ?>:</strong> <?= __('male_or_female') ?></li>
                    <li><strong><?= __('passenger_name') ?>:</strong> <?= __('full_name_as_on_passport') ?></li>
                    <li><strong><?= __('pnr') ?>:</strong> <?= __('booking_reference_number') ?></li>
                    <li><strong><?= __('phone') ?>:</strong> <?= __('contact_number') ?></li>
                </ul>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_passenger_details_form_fields_filled') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">7</span>
                <strong><?= __('configure_flight_details') ?></strong>
                <p><?= __('enter_flight_information') ?></p>
                <ul>
                    <li><strong><?= __('origin_from') ?>:</strong> <?= __('departure_city_airport') ?></li>
                    <li><strong><?= __('destination_to') ?>:</strong> <?= __('arrival_city_airport') ?></li>
                    <li><strong><?= __('return_destination') ?>:</strong> <?= __('only_for_round_trips') ?></li>
                    <li><strong><?= __('airline') ?>:</strong> <?= __('select_from_searchable_dropdown') ?></li>
                </ul>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_flight_details_section_with_origin_destination_and_airline') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">8</span>
                <strong><?= __('set_travel_dates') ?></strong>
                <p><?= __('enter_important_dates') ?></p>
                <ul>
                    <li><strong><?= __('issue_date') ?>:</strong> <?= __('when_the_ticket_was_issued') ?></li>
                    <li><strong><?= __('departure_date') ?>:</strong> <?= __('travel_departure_date') ?></li>
                    <li><strong><?= __('return_date') ?>:</strong> <?= __('only_for_round_trips') ?></li>
                </ul>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_date_fields_with_date_pickers') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">9</span>
                <strong><?= __('enter_pricing_information') ?></strong>
                <p><?= __('configure_ticket_pricing') ?></p>
                <ul>
                    <li><strong><?= __('base_price') ?>:</strong> <?= __('cost_price_from_supplier') ?></li>
                    <li><strong><?= __('sold_price') ?>:</strong> <?= __('price_charged_to_customer') ?></li>
                    <li><strong><?= __('profit') ?>:</strong> <?= __('automatically_calculated_readonly') ?></li>
                    <li><strong><?= __('currency') ?>:</strong> <?= __('automatically_set_readonly') ?></li>
                </ul>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_pricing_fields_with_base_sold_and_calculated_profit') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">10</span>
                <strong><?= __('set_exchange_rates') ?></strong>
                <p><?= __('configure_currency_exchange_information') ?></p>
                <ul>
                    <li><strong><?= __('market_exchange_rate') ?>:</strong> <?= __('current_market_rate') ?></li>
                    <li><strong><?= __('exchange_rate') ?>:</strong> <?= __('rate_used_for_this_transaction') ?></li>
                </ul>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_exchange_rate_fields') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">11</span>
                <strong><?= __('select_payment_account') ?></strong>
                <p><?= __('choose_the_main_account_where_the_payment_will_be_received_from_the_paid_to_dropdown') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_main_account_selection_dropdown') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">12</span>
                <strong><?= __('add_description') ?></strong>
                <p><?= __('enter_a_brief_description_or_notes_about_the_reservation_for_future_reference') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_description_field_with_sample_text') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">13</span>
                <strong><?= __('complete_reservation') ?></strong>
                <p><?= __('review_all_information_and_click_book_to_save_the_ticket_reservation') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_completed_form_with_book_button_highlighted') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">14</span>
                <strong><?= __('verify_reservation_creation') ?></strong>
                <p><?= __('check_that_the_new_ticket_appears_in_the_reservations_list_with_correct_details_and_payment_status_indicator') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_updated_reservation_list_showing_new_ticket') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">15</span>
                <strong><?= __('manage_reservation_transactions') ?></strong>
                <p><?= __('click_the_actions_dropdown_and_select_manage_transactions_to_add_payments_for_this_reservation') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_actions_dropdown_with_manage_transactions_option') ?>
                </div>
            </div>

            <div class="alert alert-warning mt-4">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <strong><?= __('important_notes') ?>:</strong>
                <ul class="mb-0">
                    <li><?= __('pnr_must_be_unique_for_each_reservation') ?></li>
                    <li><?= __('profit_is_automatically_calculated_as_sold_base') ?></li>
                    <li><?= __('exchange_rates_affect_payment_calculations_for_different_currencies') ?></li>
                    <li><?= __('payment_status_indicators_show_red_unpaid_yellow_partial_or_green_paid_for_agency_clients') ?></li>
                </ul>
            </div>

            <div class="alert alert-success mt-3">
                <i class="fas fa-lightbulb mr-2"></i>
                <strong><?= __('pro_tips') ?>:</strong>
                <ul class="mb-0">
                    <li><?= __('use_the_search_function_to_quickly_find_suppliers_and_clients') ?></li>
                    <li><?= __('double_check_dates_to_avoid_booking_errors') ?></li>
                    <li><?= __('keep_descriptions_informative_for_easy_identification_later') ?></li>
                    <li><?= __('set_up_transactions_immediately_after_booking_for_proper_payment_tracking') ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>
<!-- Manage Reservation Transactions Tutorial -->
<div id="manage-reservation-transactions" class="tutorial-content">
    <div class="card">
        <div class="card-header bg-success text-white">
            <h4 class="mb-0"><i class="fas fa-credit-card me-2"></i><?= __('how_to_manage_reservation_transactions') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('manage-reservation-transactions')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i>
                <strong><?= __('overview') ?>:</strong> <?= __('this_tutorial_covers_managing_payments_and_transactions_for_ticket_reservations_including_adding_payments_viewing_history_and_tracking_payment_status') ?>
            </div>

            <div class="step-item">
                <span class="step-number">1</span>
                <strong><?= __('access_transaction_management') ?></strong>
                <p><?= __('from_the_reservations_list_click_the_actions_dropdown_next_to_any_reservation_and_select_manage_transactions') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_actions_dropdown_with_manage_transactions_highlighted') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">2</span>
                <strong><?= __('review_reservation_details') ?></strong>
                <p><?= __('the_transaction_modal_shows_reservation_information_including_passenger_name_pnr_total_amount_and_exchange_rate_details') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_transaction_modal_header_with_reservation_details') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">3</span>
                <strong><?= __('check_payment_status_summary') ?></strong>
                <p><?= __('review_the_payment_summary_showing') ?></p>
                <ul>
                    <li><strong><?= __('total_amount') ?>:</strong> <?= __('reservation_total') ?></li>
                    <li><strong><?= __('exchange_rate') ?>:</strong> <?= __('current_rate_used') ?></li>
                    <li><strong><?= __('exchanged_amount') ?>:</strong> <?= __('converted_amount') ?></li>
                    <li><strong><?= __('paid_remaining') ?>:</strong> <?= __('by_currency_usd_afs_eur_aed') ?></li>
                </ul>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_payment_status_summary_with_amounts_by_currency') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">4</span>
                <strong><?= __('view_transaction_history') ?></strong>
                <p><?= __('check_existing_transactions_in_the_history_table_showing_date_description_payment_method_and_amount') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_transaction_history_table_with_existing_payments') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">5</span>
                <strong><?= __('add_new_transaction') ?></strong>
                <p><?= __('click_the_new_transaction_button_to_expand_the_payment_form') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_new_transaction_button_highlighted') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">6</span>
                <strong><?= __('set_payment_date_and_time') ?></strong>
                <p><?= __('enter_the_exact_date_and_time_when_the_payment_was_received') ?></p>
                <ul>
                    <li><strong><?= __('payment_date') ?>:</strong> <?= __('date_picker_for_transaction_date') ?></li>
                    <li><strong><?= __('payment_time') ?>:</strong> <?= __('time_picker_with_seconds_precision') ?></li>
                </ul>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_date_and_time_fields_filled') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">7</span>
                <strong><?= __('enter_payment_amount') ?></strong>
                <p><?= __('specify_the_payment_amount_received_this_should_be_the_actual_amount_paid_by_the_customer') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_payment_amount_field_with_value') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">8</span>
                <strong><?= __('select_payment_currency') ?></strong>
                <p><?= __('choose_the_currency_of_the_payment_from_the_dropdown') ?></p>
                <ul>
                    <li><strong><?= __('usd') ?>:</strong> <?= __('us_dollars') ?></li>
                    <li><strong><?= __('afs') ?>:</strong> <?= __('afghan_afghani') ?></li>
                    <li><strong><?= __('eur') ?>:</strong> <?= __('euros') ?></li>
                    <li><strong><?= __('aed') ?>:</strong> <?= __('uae_dirham') ?></li>
                </ul>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_currency_dropdown_selection') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">9</span>
                <strong><?= __('add_payment_description') ?></strong>
                <p><?= __('enter_a_detailed_description_of_the_payment_including_method_reference_numbers_or_any_special_notes') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_payment_description_textarea_with_sample_text') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">10</span>
                <strong><?= __('save_transaction') ?></strong>
                <p><?= __('click_add_transaction_to_save_the_payment_record_to_the_system') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_add_transaction_button_highlighted') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">11</span>
                <strong><?= __('verify_transaction_added') ?></strong>
                <p><?= __('check_that_the_new_transaction_appears_in_the_history_table_and_payment_summaries_are_updated') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_updated_transaction_history_with_new_payment') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">12</span>
                <strong><?= __('monitor_payment_status') ?></strong>
                <p><?= __('return_to_the_main_reservations_list_to_verify_the_payment_status_indicator_has_been_updated_green_for_paid_yellow_for_partial') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_reservations_list_showing_updated_payment_status_indicator') ?>
                </div>
            </div>

            <div class="alert alert-warning mt-4">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <strong><?= __('important_notes') ?>:</strong>
                <ul class="mb-0">
                    <li><?= __('payment_status_indicators_only_show_for_agency_clients') ?></li>
                    <li><?= __('currency_conversions_are_automatically_calculated_based_on_exchange_rates') ?></li>
                    <li><?= __('all_transactions_are_permanently_recorded_and_cannot_be_easily_deleted') ?></li>
                    <li><?= __('overpayments_will_show_in_the_payment_summary') ?></li>
                </ul>
            </div>

            <div class="alert alert-success mt-3">
                <i class="fas fa-lightbulb mr-2"></i>
                <strong><?= __('pro_tips') ?>:</strong>
                <ul class="mb-0">
                    <li><?= __('record_payments_immediately_when_received') ?></li>
                    <li><?= __('include_payment_method_and_reference_numbers_in_descriptions') ?></li>
                    <li><?= __('use_precise_time_stamps_for_accurate_record_keeping') ?></li>
                    <li><?= __('check_payment_summaries_after_each_transaction') ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>
<!-- Hotel Booking Management Tutorial -->
<div id="hotel-bookings" class="tutorial-content">
    <div class="card">
        <div class="card-header bg-info text-white">
            <h4 class="mb-0"><i class="fas fa-hotel me-2"></i><?= __('how_to_create_hotel_bookings') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('hotel-bookings')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i>
                <strong><?= __('overview') ?>:</strong> <?= __('this_tutorial_covers_the_complete_process_of_creating_hotel_bookings_including_guest_information_booking_details_stay_information_and_financial_setup') ?>
            </div>

            <div class="step-item">
                <span class="step-number">1</span>
                <strong><?= __('navigate_to_hotel_bookings') ?></strong>
                <p><?= __('go_to_bookings_hotel_bookings_from_the_main_menu_to_access_the_hotel_management_page') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_hotel_bookings_page_with_search_functionality_and_new_booking_button') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">2</span>
                <strong><?= __('start_booking_process') ?></strong>
                <p><?= __('click_the_new_booking_button_to_open_the_hotel_booking_modal') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_clicking_the_new_booking_button') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">3</span>
                <strong><?= __('enter_guest_information') ?></strong>
                <p><?= __('fill_in_the_guest_details_section') ?></p>
                <ul>
                    <li><strong><?= __('title') ?>:</strong> <?= __('mr_mrs_or_ms') ?></li>
                    <li><strong><?= __('first_name') ?>:</strong> <?= __('guest_s_first_name') ?></li>
                    <li><strong><?= __('last_name') ?>:</strong> <?= __('guest_s_last_name') ?></li>
                    <li><strong><?= __('gender') ?>:</strong> <?= __('male_or_female') ?></li>
                </ul>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_guest_information_section_with_all_fields_filled') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">4</span>
                <strong><?= __('configure_booking_details') ?></strong>
                <p><?= __('enter_essential_booking_information') ?></p>
                <ul>
                    <li><strong><?= __('order_id') ?>:</strong> <?= __('unique_booking_reference_number') ?></li>
                    <li><strong><?= __('issue_date') ?>:</strong> <?= __('date_when_booking_was_created_defaults_to_today') ?></li>
                    <li><strong><?= __('contact_number') ?>:</strong> <?= __('guest_s_phone_number_for_communication') ?></li>
                </ul>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_booking_details_section_with_order_id_issue_date_and_contact_number') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">5</span>
                <strong><?= __('set_stay_details') ?></strong>
                <p><?= __('configure_the_accommodation_period') ?></p>
                <ul>
                    <li><strong><?= __('check_in_date') ?>:</strong> <?= __('guest_arrival_date') ?></li>
                    <li><strong><?= __('check_out_date') ?>:</strong> <?= __('guest_departure_date') ?></li>
                    <li><strong><?= __('accommodation_details') ?>:</strong> <?= __('hotel_name_room_type_and_special_requirements') ?></li>
                </ul>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_stay_details_section_with_dates_and_accommodation_description') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">6</span>
                <strong><?= __('enter_financial_details') ?></strong>
                <p><?= __('configure_pricing_information') ?></p>
                <ul>
                    <li><strong><?= __('base_amount') ?>:</strong> <?= __('cost_price_from_hotel_supplier') ?></li>
                    <li><strong><?= __('sold_amount') ?>:</strong> <?= __('price_charged_to_customer') ?></li>
                    <li><strong><?= __('profit') ?>:</strong> <?= __('automatically_calculated_sold_base') ?></li>
                </ul>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_financial_details_section_with_base_amount_sold_amount_and_calculated_profit') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">7</span>
                <strong><?= __('select_business_partners') ?></strong>
                <p><?= __('choose_the_relevant_business_entities') ?></p>
                <ul>
                    <li><strong><?= __('supplier') ?>:</strong> <?= __('hotel_or_accommodation_provider') ?></li>
                    <li><strong><?= __('sold_to') ?>:</strong> <?= __('client_purchasing_the_booking') ?></li>
                    <li><strong><?= __('paid_to') ?>:</strong> <?= __('main_account_receiving_payment') ?></li>
                </ul>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_additional_details_section_with_supplier_client_and_account_dropdowns') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">8</span>
                <strong><?= __('configure_currency_settings') ?></strong>
                <p><?= __('set_exchange_rate_and_currency_information') ?></p>
                <ul>
                    <li><strong><?= __('exchange_rate') ?>:</strong> <?= __('current_conversion_rate') ?></li>
                    <li><strong><?= __('currency') ?>:</strong> <?= __('usd_or_afs') ?></li>
                </ul>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_exchange_rate_and_currency_selection_fields') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">9</span>
                <strong><?= __('add_remarks') ?></strong>
                <p><?= __('enter_any_additional_notes_or_special_instructions_for_the_booking') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_remarks_textarea_with_sample_booking_notes') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">10</span>
                <strong><?= __('complete_booking') ?></strong>
                <p><?= __('review_all_information_and_click_add_booking_to_save_the_hotel_reservation') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_completed_form_with_add_booking_button_highlighted') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">11</span>
                <strong><?= __('verify_booking_creation') ?></strong>
                <p><?= __('check_that_the_new_booking_appears_in_the_hotel_bookings_list_with_all_correct_details') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_updated_hotel_bookings_list_showing_new_reservation') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">12</span>
                <strong><?= __('access_booking_actions') ?></strong>
                <p><?= __('use_the_action_buttons_to_view_details_edit_manage_transactions_or_process_refunds_for_the_booking') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_booking_action_buttons_view_edit_transactions_more_options') ?>
                </div>
            </div>

            <div class="alert alert-warning mt-4">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <strong><?= __('important_notes') ?>:</strong>
                <ul class="mb-0">
                    <li><?= __('order_id_must_be_unique_for_each_booking') ?></li>
                    <li><?= __('check_out_date_must_be_after_check_in_date') ?></li>
                    <li><?= __('profit_calculation_is_automatic_based_on_base_and_sold_amounts') ?></li>
                    <li><?= __('all_required_fields_must_be_completed_before_saving') ?></li>
                </ul>
            </div>

            <div class="alert alert-success mt-3">
                <i class="fas fa-lightbulb mr-2"></i>
                <strong><?= __('pro_tips') ?>:</strong>
                <ul class="mb-0">
                    <li><?= __('use_clear_and_descriptive_accommodation_details') ?></li>
                    <li><?= __('verify_exchange_rates_before_creating_bookings') ?></li>
                    <li><?= __('include_special_requirements_in_remarks_field') ?></li>
                    <li><?= __('set_up_transactions_immediately_after_booking_for_payment_tracking') ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Hotel Transaction Management Tutorial -->
<div id="hotel-transactions" class="tutorial-content">
    <div class="card">
        <div class="card-header bg-success text-white">
            <h4 class="mb-0"><i class="fas fa-credit-card me-2"></i><?= __('how_to_manage_hotel_booking_transactions') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('hotel-transactions')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i>
                <strong><?= __('overview') ?>:</strong> <?= __('this_tutorial_covers_managing_payments_and_transactions_for_hotel_bookings_including_adding_payments_viewing_history_and_tracking_payment_status') ?>
            </div>

            <div class="step-item">
                <span class="step-number">1</span>
                <strong><?= __('access_transaction_management') ?></strong>
                <p><?= __('from_the_hotel_bookings_list_click_the_dollar_sign_icon_next_to_any_booking_to_manage_its_transactions') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_transaction_management_button_highlighted_in_booking_actions') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">2</span>
                <strong><?= __('review_booking_summary') ?></strong>
                <p><?= __('the_transaction_modal_displays_booking_information_including_guest_name_order_id_and_financial_summary_with_exchange_rate_details') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_transaction_modal_header_with_booking_summary_card') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">3</span>
                <strong><?= __('check_payment_status') ?></strong>
                <p><?= __('review_the_payment_summary_showing') ?></p>
                <ul>
                    <li><strong><?= __('original_amount') ?>:</strong> <?= __('total_booking_amount') ?></li>
                    <li><strong><?= __('exchange_rate') ?>:</strong> <?= __('currency_conversion_rate') ?></li>
                    <li><strong><?= __('converted_amount') ?>:</strong> <?= __('amount_in_local_currency') ?></li>
                    <li><strong><?= __('paid_amounts') ?>:</strong> <?= __('by_currency_usd_afs') ?></li>
                </ul>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_payment_status_summary_with_amounts_by_currency') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">4</span>
                <strong><?= __('view_transaction_history') ?></strong>
                <p><?= __('check_existing_transactions_in_the_history_table_showing_date_description_type_and_amount_for_all_payments') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_transaction_history_table_with_existing_payments') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">5</span>
                <strong><?= __('add_new_transaction') ?></strong>
                <p><?= __('click_the_add_transaction_button_to_expand_the_payment_form') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_add_transaction_button_highlighted') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">6</span>
                <strong><?= __('set_payment_date_and_time') ?></strong>
                <p><?= __('enter_when_the_payment_was_received') ?></p>
                <ul>
                    <li><strong><?= __('payment_date') ?>:</strong> <?= __('date_picker_for_transaction_date') ?></li>
                    <li><strong><?= __('payment_time') ?>:</strong> <?= __('time_picker_with_seconds_precision') ?></li>
                </ul>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_date_and_time_fields_in_transaction_form') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">7</span>
                <strong><?= __('enter_payment_amount') ?></strong>
                <p><?= __('specify_the_payment_amount_received_from_the_guest_this_should_be_the_actual_amount_paid') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_payment_amount_field_with_currency_symbol') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">8</span>
                <strong><?= __('select_payment_currency') ?></strong>
                <p><?= __('choose_the_currency_of_the_payment') ?></p>
                <ul>
                    <li><strong><?= __('usd') ?>:</strong> <?= __('us_dollars') ?></li>
                    <li><strong><?= __('afs') ?>:</strong> <?= __('afghan_afghani') ?></li>
                    <li><strong><?= __('eur') ?>:</strong> <?= __('euros') ?></li>
                    <li><strong><?= __('darham') ?>:</strong> <?= __('uae_dirham') ?></li>
                </ul>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_currency_dropdown_with_options') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">9</span>
                <strong><?= __('add_payment_description') ?></strong>
                <p><?= __('enter_detailed_description_including_payment_method_reference_numbers_and_any_special_notes_about_the_transaction') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_payment_description_textarea_with_sample_text') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">10</span>
                <strong><?= __('save_transaction') ?></strong>
                <p><?= __('click_add_transaction_to_save_the_payment_record_to_the_system') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_add_transaction_button_in_form_footer') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">11</span>
                <strong><?= __('verify_transaction_added') ?></strong>
                <p><?= __('check_that_the_new_transaction_appears_in_the_history_table_and_payment_summaries_are_updated_accordingly') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_updated_transaction_history_with_new_payment_entry') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">12</span>
                <strong><?= __('manage_existing_transactions') ?></strong>
                <p><?= __('use_the_action_buttons_in_the_transaction_history_to_edit_or_delete_existing_payment_records_as_needed') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_transaction_actions_edit_delete_in_history_table') ?>
                </div>
            </div>

            <div class="alert alert-warning mt-4">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <strong><?= __('important_notes') ?>:</strong>
                <ul class="mb-0">
                    <li><?= __('all_transactions_are_permanently_recorded_in_the_system') ?></li>
                    <li><?= __('currency_conversions_are_calculated_based_on_exchange_rates') ?></li>
                    <li><?= __('payment_status_is_automatically_updated_based_on_total_payments') ?></li>
                    <li><?= __('transaction_times_should_be_accurate_for_proper_record_keeping') ?></li>
                </ul>
            </div>

            <div class="alert alert-success mt-3">
                <i class="fas fa-lightbulb mr-2"></i>
                <strong><?= __('pro_tips') ?>:</strong>
                <ul class="mb-0">
                    <li><?= __('record_payments_immediately_when_received') ?></li>
                    <li><?= __('include_payment_method_and_reference_numbers_in_descriptions') ?></li>
                    <li><?= __('use_precise_timestamps_for_audit_trail_purposes') ?></li>
                    <li><?= __('monitor_payment_summaries_to_track_outstanding_balances') ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>
<!-- Hotel Booking Refund Tutorial -->
<div id="hotel-refunds" class="tutorial-content">
    <div class="card">
        <div class="card-header bg-warning text-white">
            <h4 class="mb-0"><i class="fas fa-undo me-2"></i><?= __('how_to_process_hotel_booking_refunds') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('hotel-refunds')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i>
                <strong><?= __('overview') ?>:</strong> <?= __('this_tutorial_covers_the_complete_process_of_processing_refunds_for_hotel_bookings_including_full_and_partial_refunds_with_proper_documentation') ?>
            </div>

            <div class="step-item">
                <span class="step-number">1</span>
                <strong><?= __('access_refund_options') ?></strong>
                <p><?= __('from_the_hotel_bookings_list_click_the_dropdown_menu_three_dots_next_to_any_booking_and_select_process_refund') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_dropdown_menu_with_process_refund_option_highlighted') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">2</span>
                <strong><?= __('review_booking_summary') ?></strong>
                <p><?= __('the_refund_modal_displays_the_original_booking_amount_and_profit_for_reference_before_processing_the_refund') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_refund_modal_with_booking_summary_showing_original_amounts') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">3</span>
                <strong><?= __('set_exchange_rate') ?></strong>
                <p><?= __('enter_the_current_exchange_rate_to_be_used_for_the_refund_calculation_the_current_rate_is_displayed_for_reference') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_exchange_rate_field_with_current_rate_displayed') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">4</span>
                <strong><?= __('choose_refund_type') ?></strong>
                <p><?= __('select_the_type_of_refund_to_process') ?></p>
                <ul>
                    <li><strong><?= __('full_refund') ?>:</strong> <?= __('complete_refund_of_the_booking_amount') ?></li>
                    <li><strong><?= __('partial_refund') ?>:</strong> <?= __('refund_of_a_specific_amount_less_than_the_total') ?></li>
                </ul>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_refund_type_selection_buttons_full_partial') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">5</span>
                <strong><?= __('enter_refund_amount_if_partial') ?></strong>
                <p><?= __('for_partial_refunds_specify_the_exact_amount_to_be_refunded_the_maximum_refund_amount_is_displayed_for_reference') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_refund_amount_field_with_maximum_amount_shown') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">6</span>
                <strong><?= __('provide_refund_reason') ?></strong>
                <p><?= __('enter_a_detailed_explanation_for_the_refund_including_the_reason_for_cancellation_and_any_relevant_circumstances') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_reason_for_refund_textarea_with_sample_text') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">7</span>
                <strong><?= __('process_refund') ?></strong>
                <p><?= __('review_all_refund_details_and_click_process_refund_to_execute_the_refund_transaction') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_completed_refund_form_with_process_refund_button') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">8</span>
                <strong><?= __('verify_refund_processing') ?></strong>
                <p><?= __('check_that_the_refund_has_been_processed_successfully_and_the_booking_status_has_been_updated_accordingly') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_success_message_and_updated_booking_status') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">9</span>
                <strong><?= __('review_refund_records') ?></strong>
                <p><?= __('access_the_hotel_refunds_page_to_view_all_processed_refunds_and_their_current_status') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_hotel_refunds_page_showing_processed_refund') ?>
                </div>
            </div>

            <div class="alert alert-warning mt-4">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <strong><?= __('important_notes') ?>:</strong>
                <ul class="mb-0">
                    <li><?= __('refunds_cannot_be_easily_reversed_once_processed') ?></li>
                    <li><?= __('partial_refunds_must_not_exceed_the_original_booking_amount') ?></li>
                    <li><?= __('exchange_rates_affect_the_final_refund_amount_calculation') ?></li>
                    <li><?= __('always_provide_detailed_reasons_for_refund_documentation') ?></li>
                </ul>
            </div>

            <div class="alert alert-success mt-3">
                <i class="fas fa-lightbulb mr-2"></i>
                <strong><?= __('pro_tips') ?>:</strong>
                <ul class="mb-0">
                    <li><?= __('verify_guest_identity_before_processing_refunds') ?></li>
                    <li><?= __('check_hotel_cancellation_policies_before_refunding') ?></li>
                    <li><?= __('document_all_communication_regarding_the_refund') ?></li>
                    <li><?= __('use_current_exchange_rates_for_accurate_calculations') ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>
<div id="visa-applications" class="tutorial-content">
    <div class="card">
        <div class="card-header bg-visa text-white">
            <h4 class="mb-0"><i class="fas fa-passport me-2"></i><?= __('how_to_create_visa_applications') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('visa-applications')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i>
                <strong><?= __('overview') ?>:</strong> <?= __('this_tutorial_covers_the_complete_process_of_creating_visa_applications_including_applicant_details_financial_setup_and_documentation') ?>
            </div>

            <div class="step-item">
                <span class="step-number">1</span>
                <strong><?= __('navigate_to_visa_management') ?></strong>
                <p><?= __('go_to_visa_management_from_the_main_menu_to_access_the_visa_applications_page') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_visa_management_page_with_search_functionality_and_new_visa_button') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">2</span>
                <strong><?= __('start_visa_application') ?></strong>
                <p><?= __('click_the_new_visa_application_button_to_open_the_visa_application_modal') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_clicking_the_new_visa_application_button') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">3</span>
                <strong><?= __('select_business_partners') ?></strong>
                <p><?= __('configure_the_business_relationships_for_this_visa_application') ?></p>
                <ul>
                    <li><strong><?= __('supplier') ?>:</strong> <?= __('select_the_visa_service_provider') ?></li>
                    <li><strong><?= __('sold_to') ?>:</strong> <?= __('choose_the_client_purchasing_the_visa') ?></li>
                    <li><strong><?= __('paid_via') ?>:</strong> <?= __('select_the_main_account_for_payment') ?></li>
                    <li><strong><?= __('phone') ?>:</strong> <?= __('contact_number_for_the_application') ?></li>
                </ul>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_business_partner_selection_section') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">4</span>
                <strong><?= __('enter_applicant_details') ?></strong>
                <p><?= __('fill_in_the_personal_information_for_the_visa_applicant') ?></p>
                <ul>
                    <li><strong><?= __('title') ?>:</strong> <?= __('mr_mrs_or_child') ?></li>
                    <li><strong><?= __('gender') ?>:</strong> <?= __('male_or_female') ?></li>
                    <li><strong><?= __('applicant_name') ?>:</strong> <?= __('full_name_as_per_passport') ?></li>
                    <li><strong><?= __('passport_number') ?>:</strong> <?= __('unique_passport_identifier') ?></li>
                    <li><strong><?= __('country') ?>:</strong> <?= __('applicant_s_country_of_origin') ?></li>
                    <li><strong><?= __('visa_type') ?>:</strong> <?= __('tourist_business_work_etc') ?></li>
                </ul>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_applicant_details_section_with_all_fields') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">5</span>
                <strong><?= __('set_visa_dates') ?></strong>
                <p><?= __('configure_important_dates_for_the_visa_application') ?></p>
                <ul>
                    <li><strong><?= __('received_date') ?>:</strong> <?= __('when_the_application_was_received') ?></li>
                    <li><strong><?= __('applied_date') ?>:</strong> <?= __('date_of_visa_application_submission') ?></li>
                    <li><strong><?= __('issued_date') ?>:</strong> <?= __('optional_date_of_visa_issuance') ?></li>
                </ul>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_dates_section_with_date_pickers') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">6</span>
                <strong><?= __('configure_financial_details') ?></strong>
                <p><?= __('enter_pricing_and_financial_information') ?></p>
                <ul>
                    <li><strong><?= __('base_price') ?>:</strong> <?= __('cost_of_visa_from_supplier') ?></li>
                    <li><strong><?= __('sold_price') ?>:</strong> <?= __('price_charged_to_client') ?></li>
                    <li><strong><?= __('profit') ?>:</strong> <?= __('automatically_calculated') ?></li>
                    <li><strong><?= __('exchange_rate') ?>:</strong> <?= __('currency_conversion_rate') ?></li>
                    <li><strong><?= __('currency') ?>:</strong> <?= __('usd_eur_darham_or_afs') ?></li>
                </ul>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_financial_details_section') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">7</span>
                <strong><?= __('add_description') ?></strong>
                <p><?= __('enter_any_additional_notes_or_remarks_about_the_visa_application') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_description_textarea') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">8</span>
                <strong><?= __('complete_visa_application') ?></strong>
                <p><?= __('review_all_information_and_click_add_visa_to_save_the_application') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_completed_form_with_add_visa_button') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">9</span>
                <strong><?= __('verify_visa_application') ?></strong>
                <p><?= __('check_that_the_new_visa_application_appears_in_the_visa_applications_list') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_updated_visa_applications_list') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">10</span>
                <strong><?= __('access_visa_actions') ?></strong>
                <p><?= __('use_action_buttons_to_view_details_edit_manage_transactions_or_process_refunds') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_visa_application_action_buttons') ?>
                </div>
            </div>

            <div class="alert alert-warning mt-4">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <strong><?= __('important_notes') ?>:</strong>
                <ul class="mb-0">
                    <li><?= __('passport_number_must_be_unique') ?></li>
                    <li><?= __('verify_all_dates_and_personal_information') ?></li>
                    <li><?= __('profit_is_automatically_calculated') ?></li>
                    <li><?= __('choose_the_correct_currency_and_exchange_rate') ?></li>
                </ul>
            </div>

            <div class="alert alert-success mt-3">
                <i class="fas fa-lightbulb mr-2"></i>
                <strong><?= __('pro_tips') ?>:</strong>
                <ul class="mb-0">
                    <li><?= __('double_check_passport_details') ?></li>
                    <li><?= __('use_current_exchange_rates') ?></li>
                    <li><?= __('include_detailed_remarks') ?></li>
                    <li><?= __('verify_supplier_and_client_information') ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Visa Transaction Management Tutorial -->
<div id="visa-transactions" class="tutorial-content">
    <div class="card">
        <div class="card-header bg-visa text-white">
            <h4 class="mb-0"><i class="fas fa-credit-card me-2"></i><?= __('how_to_manage_visa_transactions') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('visa-transactions')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i>
                <strong><?= __('overview') ?>:</strong> <?= __('this_tutorial_covers_managing_payments_and_transactions_for_visa_applications_including_adding_payments_tracking_status_and_handling_multi_currency_transactions') ?>
            </div>

            <div class="step-item">
                <span class="step-number">1</span>
                <strong><?= __('access_transaction_management') ?></strong>
                <p><?= __('from_the_visa_applications_list_click_the_dollar_sign_icon_next_to_any_visa_application_to_manage_its_transactions') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_transaction_management_button_in_visa_actions') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">2</span>
                <strong><?= __('review_visa_summary') ?></strong>
                <p><?= __('the_transaction_modal_displays_visa_application_information_including') ?></p>
                <ul>
                    <li><strong><?= __('visa_id') ?></strong></li>
                    <li><strong><?= __('total_amount') ?></strong></li>
                    <li><strong><?= __('exchange_rate') ?></strong></li>
                    <li><strong><?= __('converted_amount') ?></strong></li>
                </ul>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_transaction_modal_header_with_visa_summary') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">3</span>
                <strong><?= __('check_payment_status') ?></strong>
                <p><?= __('review_payment_details_across_multiple_currencies') ?></p>
                <ul>
                    <li><strong><?= __('usd_section') ?>:</strong> <?= __('paid_and_remaining_amounts') ?></li>
                    <li><strong><?= __('afs_section') ?>:</strong> <?= __('paid_and_remaining_amounts') ?></li>
                    <li><strong><?= __('eur_section') ?>:</strong> <?= __('paid_and_remaining_amounts') ?></li>
                    <li><strong><?= __('aed_section') ?>:</strong> <?= __('paid_and_remaining_amounts') ?></li>
                </ul>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_payment_status_summary_with_multi_currency_breakdown') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">4</span>
                <strong><?= __('view_transaction_history') ?></strong>
                <p><?= __('check_existing_transactions_in_the_history_table_showing') ?></p>
                <ul>
                    <li><strong><?= __('date') ?></strong></li>
                    <li><strong><?= __('description') ?></strong></li>
                    <li><strong><?= __('payment_type') ?></strong></li>
                    <li><strong><?= __('amount') ?></strong></li>
                </ul>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_transaction_history_table') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">5</span>
                <strong><?= __('add_new_transaction') ?></strong>
                <p><?= __('click_the_new_transaction_button_to_expand_the_payment_form') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_new_transaction_button_highlighted') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">6</span>
                <strong><?= __('set_payment_date_and_time') ?></strong>
                <p><?= __('enter_precise_payment_details') ?></p>
                <ul>
                    <li><strong><?= __('payment_date') ?>:</strong> <?= __('date_of_transaction') ?></li>
                    <li><strong><?= __('payment_time') ?>:</strong> <?= __('exact_time_of_payment_hhmmss') ?></li>
                </ul>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_date_and_time_input_fields') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">7</span>
                <strong><?= __('enter_payment_amount') ?></strong>
                <p><?= __('specify_the_payment_amount_received_for_the_visa_application') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_payment_amount_input_field') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">8</span>
                <strong><?= __('select_payment_currency') ?></strong>
                <p><?= __('choose_the_currency_of_the_payment') ?></p>
                <ul>
                    <li><strong><?= __('usd') ?>:</strong> <?= __('us_dollars') ?></li>
                    <li><strong><?= __('afs') ?>:</strong> <?= __('afghan_afghani') ?></li>
                    <li><strong><?= __('eur') ?>:</strong> <?= __('euros') ?></li>
                    <li><strong><?= __('darham') ?>:</strong> <?= __('uae_dirham') ?></li>
                </ul>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_currency_dropdown') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">9</span>
                <strong><?= __('add_payment_description') ?></strong>
                <p><?= __('enter_a_detailed_description_of_the_payment_including_method_and_reference_details') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_payment_description_textarea') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">10</span>
                <strong><?= __('save_transaction') ?></strong>
                <p><?= __('click_add_transaction_to_record_the_payment') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_add_transaction_button') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">11</span>
                <strong><?= __('verify_transaction') ?></strong>
                <p><?= __('confirm_that_the_new_transaction_appears_in_the_history_and_payment_summaries_update') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_updated_transaction_history') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">12</span>
                <strong><?= __('manage_existing_transactions') ?></strong>
                <p><?= __('use_action_buttons_to_edit_or_delete_existing_transactions') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_transaction_edit_delete_actions') ?>
                </div>
            </div>

            <div class="alert alert-warning mt-4">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <strong><?= __('important_notes') ?>:</strong>
                <ul class="mb-0">
                    <li><?= __('transactions_are_permanently_recorded') ?></li>
                    <li><?= __('currency_conversions_use_current_exchange_rates') ?></li>
                    <li><?= __('payment_status_updates_automatically') ?></li>
                    <li><?= __('precise_timestamps_are_crucial') ?></li>
                </ul>
            </div>

            <div class="alert alert-success mt-3">
                <i class="fas fa-lightbulb mr-2"></i>
                <strong><?= __('pro_tips') ?>:</strong>
                <ul class="mb-0">
                    <li><?= __('record_payments_immediately') ?></li>
                    <li><?= __('include_transaction_references') ?></li>
                    <li><?= __('use_exact_timestamps') ?></li>
                    <li><?= __('monitor_payment_completeness') ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Visa Refund Management Tutorial -->
<div id="visa-refunds" class="tutorial-content">
    <div class="card">
        <div class="card-header bg-visa text-white">
            <h4 class="mb-0"><i class="fas fa-undo me-2"></i><?= __('how_to_process_visa_refunds') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('visa-refunds')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i>
                <strong><?= __('overview') ?>:</strong> <?= __('this_tutorial_covers_processing_refunds_for_visa_applications_including_full_and_partial_refunds_with_detailed_documentation') ?>
            </div>

            <div class="step-item">
                <span class="step-number">1</span>
                <strong><?= __('access_refund_options') ?></strong>
                <p><?= __('from_the_visa_applications_list_click_the_dropdown_menu_three_dots_and_select_refund_visa') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_dropdown_menu_with_refund_visa_option') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">2</span>
                <strong><?= __('review_visa_details') ?></strong>
                <p><?= __('the_refund_modal_displays_key_financial_information') ?></p>
                <ul>
                    <li><strong><?= __('visa_amount') ?>:</strong> <?= __('total_visa_cost') ?></li>
                    <li><strong><?= __('profit_amount') ?>:</strong> <?= __('generated_profit') ?></li>
                </ul>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_refund_modal_with_visa_amount_and_profit') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">3</span>
                <strong><?= __('set_exchange_rate') ?></strong>
                <p><?= __('enter_the_current_exchange_rate_for_accurate_refund_calculation') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_exchange_rate_input_field') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">4</span>
                <strong><?= __('choose_refund_type') ?></strong>
                <p><?= __('select_the_refund_method') ?></p>
                <ul>
                    <li><strong><?= __('full_refund') ?>:</strong> <?= __('complete_visa_amount_refund') ?></li>
                    <li><strong><?= __('partial_refund') ?>:</strong> <?= __('refund_a_specific_amount') ?></li>
                </ul>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_refund_type_selection_buttons') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">5</span>
                <strong><?= __('enter_partial_refund_amount') ?></strong>
                <p><?= __('for_partial_refunds_specify_the_exact_amount_to_refund') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_partial_refund_amount_input') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">6</span>
                <strong><?= __('provide_refund_reason') ?></strong>
                <p><?= __('enter_a_detailed_explanation_for_the_visa_refund') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_refund_reason_textarea') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">7</span>
                <strong><?= __('process_refund') ?></strong>
                <p><?= __('review_details_and_click_process_refund') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_process_refund_button') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">8</span>
                <strong><?= __('verify_refund_processing') ?></strong>
                <p><?= __('confirm_that_the_refund_has_been_processed_successfully') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_refund_confirmation_message') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">9</span>
                <strong><?= __('review_refund_records') ?></strong>
                <p><?= __('access_the_visa_refunds_page_to_view_processed_refunds') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_visa_refunds_page') ?>
                </div>
            </div>

            <div class="alert alert-warning mt-4">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <strong><?= __('important_notes') ?>:</strong>
                <ul class="mb-0">
                    <li><?= __('refunds_cannot_be_easily_reversed') ?></li>
                    <li><?= __('partial_refunds_must_not_exceed_total_amount') ?></li>
                    <li><?= __('exchange_rates_affect_refund_calculations') ?></li>
                    <li><?= __('detailed_documentation_is_crucial') ?></li>
                </ul>
            </div>

            <div class="alert alert-success mt-3">
                <i class="fas fa-lightbulb mr-2"></i>
                <strong><?= __('pro_tips') ?>:</strong>
                <ul class="mb-0">
                    <li><?= __('verify_client_identity') ?></li>
                    <li><?= __('check_visa_cancellation_policies') ?></li>
                    <li><?= __('document_all_communication') ?></li>
                    <li><?= __('use_current_exchange_rates') ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>
<!-- Umrah Management Tutorials -->
<div id="umrah-family-management" class="tutorial-content">
    <div class="card">
        <div class="card-header bg-umrah text-white">
            <h4 class="mb-0"><i class="fas fa-users me-2"></i><?= __('how_to_manage_umrah_families') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('umrah-family-management')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i>
                <strong><?= __('overview') ?>:</strong> <?= __('this_tutorial_covers_creating_and_managing_umrah_family_groups_including_adding_family_details_tracking_visa_status_and_managing_group_information') ?>
            </div>

            <div class="step-item">
                <span class="step-number">1</span>
                <strong><?= __('navigate_to_umrah_management') ?></strong>
                <p><?= __('go_to_the_umrah_management_page_to_view_and_manage_family_groups') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_umrah_management_main_page') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">2</span>
                <strong><?= __('create_new_family_group') ?></strong>
                <p><?= __('click_the_add_new_family_button_to_open_the_family_creation_modal') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_add_new_family_button') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">3</span>
                <strong><?= __('enter_family_head_information') ?></strong>
                <p><?= __('fill_in_details_for_the_family_head') ?></p>
                <ul>
                    <li><?= __('family_head_name') ?></li>
                    <li><?= __('contact_number') ?></li>
                    <li><?= __('address') ?></li>
                    <li><?= __('province') ?></li>
                    <li><?= __('district') ?></li>
                </ul>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_family_head_information_form') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">4</span>
                <strong><?= __('configure_package_details') ?></strong>
                <p><?= __('select_package_type_and_location') ?></p>
                <ul>
                    <li><?= __('full_package') ?></li>
                    <li><?= __('visa_only') ?></li>
                    <li><?= __('services') ?></li>
                    <li><?= __('ticket_visa') ?></li>
                    <li><?= __('visa_services') ?></li>
                </ul>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_package_type_selection') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">5</span>
                <strong><?= __('set_visa_and_tazmin_status') ?></strong>
                <p><?= __('configure_visa_and_tazmin_details') ?></p>
                <ul>
                    <li><?= __('visa_status') ?>: <?= __('not_applied_applied_issued') ?></li>
                    <li><?= __('tazmin_status') ?>: <?= __('done_not_done') ?></li>
                </ul>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_visa_and_tazmin_status_selection') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">6</span>
                <strong><?= __('complete_family_group_creation') ?></strong>
                <p><?= __('review_all_information_and_click_create_family') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_family_creation_confirmation') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">7</span>
                <strong><?= __('manage_family_group') ?></strong>
                <p><?= __('use_action_buttons_to') ?></p>
                <ul>
                    <li><?= __('add_family_members') ?></li>
                    <li><?= __('view_members') ?></li>
                    <li><?= __('edit_family_details') ?></li>
                    <li><?= __('generate_documents') ?></li>
                </ul>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_family_management_action_buttons') ?>
                </div>
            </div>

            <div class="alert alert-warning mt-4">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <strong><?= __('important_notes') ?>:</strong>
                <ul class="mb-0">
                    <li><?= __('ensure_accurate_family_head_information') ?></li>
                    <li><?= __('select_appropriate_package_type') ?></li>
                    <li><?= __('track_visa_and_tazmin_status') ?></li>
                    <li><?= __('keep_contact_information_updated') ?></li>
                </ul>
            </div>

            <div class="alert alert-success mt-3">
                <i class="fas fa-lightbulb mr-2"></i>
                <strong><?= __('pro_tips') ?>:</strong>
                <ul class="mb-0">
                    <li><?= __('double_check_all_entered_information') ?></li>
                    <li><?= __('use_consistent_naming_conventions') ?></li>
                    <li><?= __('update_visa_status_regularly') ?></li>
                    <li><?= __('maintain_clear_communication_with_family_members') ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>
<div id="umrah-booking-management" class="tutorial-content">
    <div class="card">
        <div class="card-header bg-umrah text-white">
            <h4 class="mb-0"><i class="fas fa-book me-2"></i><?= __('how_to_add_umrah_bookings') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('umrah-booking-management')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i>
                <strong><?= __('overview') ?>:</strong> <?= __('this_tutorial_covers_adding_umrah_bookings_to_a_family_group_including_member_details_travel_information_and_financial_setup') ?>
            </div>

            <div class="step-item">
                <span class="step-number">1</span>
                <strong><?= __('select_family_group') ?></strong>
                <p><?= __('choose_the_family_group_to_which_you_want_to_add_a_new_member') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_family_group_selection') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">2</span>
                <strong><?= __('open_umrah_booking_modal') ?></strong>
                <p><?= __('click_add_new_member_to_open_the_booking_form') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_add_new_member_button') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">3</span>
                <strong><?= __('configure_business_partners') ?></strong>
                <p><?= __('select_key_business_relationships') ?></p>
                <ul>
                    <li><?= __('supplier') ?></li>
                    <li><?= __('sold_to_client') ?></li>
                    <li><?= __('paid_to_main_account') ?></li>
                </ul>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_business_partner_selection') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">4</span>
                <strong><?= __('enter_personal_information') ?></strong>
                <p><?= __('fill_in_member_s_personal_details') ?></p>
                <ul>
                    <li><?= __('entry_date') ?></li>
                    <li><?= __('name') ?></li>
                    <li><?= __('date_of_birth') ?></li>
                    <li><?= __('gender') ?></li>
                    <li><?= __('fathers_name') ?></li>
                    <li><?= __('passport_details') ?></li>
                </ul>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_personal_information_form') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">5</span>
                <strong><?= __('configure_travel_details') ?></strong>
                <p><?= __('set_travel_and_accommodation_information') ?></p>
                <ul>
                    <li><?= __('flight_date') ?></li>
                    <li><?= __('return_date') ?></li>
                    <li><?= __('duration') ?></li>
                    <li><?= __('room_type') ?></li>
                </ul>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_travel_details_form') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">6</span>
                <strong><?= __('set_financial_information') ?></strong>
                <p><?= __('configure_pricing_and_payment_details') ?></p>
                <ul>
                    <li><?= __('base_price') ?></li>
                    <li><?= __('sold_price') ?></li>
                    <li><?= __('discount') ?></li>
                    <li><?= __('profit_auto_calculated') ?></li>
                    <li><?= __('exchange_rate') ?></li>
                </ul>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_financial_information_form') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">7</span>
                <strong><?= __('handle_payment_information') ?></strong>
                <p><?= __('enter_payment_and_bank_details') ?></p>
                <ul>
                    <li><?= __('bank_payment') ?></li>
                    <li><?= __('receipt_number') ?></li>
                    <li><?= __('amount_paid') ?></li>
                    <li><?= __('due_amount') ?></li>
                </ul>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_payment_information_form') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">8</span>
                <strong><?= __('optional_passport_scanning') ?></strong>
                <p><?= __('use_the_passport_scanning_feature_to_auto_fill_details') ?></p>
                <ul>
                    <li><?= __('upload_passport_image') ?></li>
                    <li><?= __('scan_and_extract_information') ?></li>
                    <li><?= __('verify_extracted_details') ?></li>
                </ul>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_passport_scanning_process') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">9</span>
                <strong><?= __('complete_booking') ?></strong>
                <p><?= __('review_all_information_and_click_add_booking') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_booking_confirmation') ?>
                </div>
            </div>

            <div class="alert alert-warning mt-4">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <strong><?= __('important_notes') ?>:</strong>
                <ul class="mb-0">
                    <li><?= __('verify_all_personal_and_travel_details') ?></li>
                    <li><?= __('ensure_accurate_financial_calculations') ?></li>
                    <li><?= __('keep_passport_and_identification_documents_secure') ?></li>
                    <li><?= __('double_check_payment_information') ?></li>
                </ul>
            </div>

            <div class="alert alert-success mt-3">
                <i class="fas fa-lightbulb mr-2"></i>
                <strong><?= __('pro_tips') ?>:</strong>
                <ul class="mb-0">
                    <li><?= __('use_passport_scanning_for_quick_data_entry') ?></li>
                    <li><?= __('confirm_exchange_rates_before_booking') ?></li>
                    <li><?= __('track_payment_status_carefully') ?></li>
                    <li><?= __('maintain_clear_communication_with_clients') ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<div id="umrah-transaction-management" class="tutorial-content">
    <div class="card">
        <div class="card-header bg-umrah text-white">
            <h4 class="mb-0"><i class="fas fa-credit-card me-2"></i><?= __('how_to_manage_umrah_transactions') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('umrah-transaction-management')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i>
                <strong><?= __('overview') ?>:</strong> <?= __('this_tutorial_covers_managing_financial_transactions_for_umrah_bookings_including_adding_payments_tracking_balances_and_handling_multi_currency_transactions') ?>
            </div>

            <div class="step-item">
                <span class="step-number">1</span>
                <strong><?= __('access_transaction_management') ?></strong>
                <p><?= __('navigate_to_a_specific_umrah_booking_and_click_the_transaction_management_button') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_transaction_management_button') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">2</span>
                <strong><?= __('review_booking_summary') ?></strong>
                <p><?= __('check_the_booking_s_financial_overview') ?></p>
                <ul>
                    <li><?= __('total_amount') ?></li>
                    <li><?= __('exchange_rate') ?></li>
                    <li><?= __('converted_amount') ?></li>
                </ul>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_booking_financial_summary') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">3</span>
                <strong><?= __('view_transaction_history') ?></strong>
                <p><?= __('review_existing_transactions_in_the_history_table') ?></p>
                <ul>
                    <li><?= __('date') ?></li>
                    <li><?= __('description') ?></li>
                    <li><?= __('payment_type') ?></li>
                    <li><?= __('transaction_to') ?></li>
                    <li><?= __('amount') ?></li>
                </ul>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_transaction_history_table') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">4</span>
                <strong><?= __('add_new_transaction') ?></strong>
                <p><?= __('click_new_transaction_to_expand_the_payment_form') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_new_transaction_button') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">5</span>
                <strong><?= __('set_transaction_details') ?></strong>
                <p><?= __('enter_transaction_information') ?></p>
                <ul>
                    <li><?= __('payment_date') ?></li>
                    <li><?= __('transaction_to_internal_account_bank') ?></li>
                    <li><?= __('payment_amount') ?></li>
                    <li><?= __('currency') ?></li>
                    <li><?= __('description') ?></li>
                </ul>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_transaction_details_form') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">6</span>
                <strong><?= __('handle_bank_transactions') ?></strong>
                <p><?= __('for_bank_transactions_enter_additional_details') ?></p>
                <ul>
                    <li><?= __('receipt_number') ?></li>
                    <li><?= __('bank_payment_details') ?></li>
                </ul>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_bank_transaction_details') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">7</span>
                <strong><?= __('save_transaction') ?></strong>
                <p><?= __('review_details_and_click_add_transaction') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_transaction_confirmation') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">8</span>
                <strong><?= __('edit_or_delete_transactions') ?></strong>
                <p><?= __('use_action_buttons_to_modify_existing_transactions') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_transaction_edit_delete_actions') ?>
                </div>
            </div>

            <div class="alert alert-warning mt-4">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <strong><?= __('important_notes') ?>:</strong>
                <ul class="mb-0">
                    <li><?= __('transactions_are_permanently_recorded') ?></li>
                    <li><?= __('verify_currency_and_exchange_rates') ?></li>
                    <li><?= __('maintain_accurate_transaction_descriptions') ?></li>
                    <li><?= __('keep_bank_receipts_for_reference') ?></li>
                </ul>
            </div>

            <div class="alert alert-success mt-3">
                <i class="fas fa-lightbulb mr-2"></i>
                <strong><?= __('pro_tips') ?>:</strong>
                <ul class="mb-0">
                    <li><?= __('record_transactions_immediately') ?></li>
                    <li><?= __('use_consistent_transaction_descriptions') ?></li>
                    <li><?= __('track_payment_status_carefully') ?></li>
                    <li><?= __('reconcile_transactions_regularly') ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>
<div id="umrah-refund-management" class="tutorial-content">
    <div class="card">
        <div class="card-header bg-umrah text-white">
            <h4 class="mb-0"><i class="fas fa-undo me-2"></i><?= __('how_to_process_umrah_refunds') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('umrah-refund-management')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i>
                <strong><?= __('overview') ?>:</strong> <?= __('this_tutorial_covers_processing_refunds_for_umrah_bookings_including_full_and_partial_refunds_with_detailed_documentation_and_financial_tracking') ?>
            </div>

            <div class="step-item">
                <span class="step-number">1</span>
                <strong><?= __('access_refund_options') ?></strong>
                <p><?= __('navigate_to_a_specific_umrah_booking_and_select_the_refund_option') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_refund_option_in_booking_actions') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">2</span>
                <strong><?= __('review_booking_details') ?></strong>
                <p><?= __('check_the_booking_s_financial_information') ?></p>
                <ul>
                    <li><?= __('original_amount') ?></li>
                    <li><?= __('original_profit') ?></li>
                    <li><?= __('current_exchange_rate') ?></li>
                </ul>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_booking_financial_summary') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">3</span>
                <strong><?= __('set_exchange_rate') ?></strong>
                <p><?= __('enter_the_current_exchange_rate_for_accurate_refund_calculation') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_exchange_rate_input') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">4</span>
                <strong><?= __('choose_refund_type') ?></strong>
                <p><?= __('select_the_refund_method') ?></p>
                <ul>
                    <li><?= __('full_refund') ?></li>
                    <li><?= __('partial_refund') ?></li>
                </ul>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_refund_type_selection') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">5</span>
                <strong><?= __('enter_partial_refund_amount') ?></strong>
                <p><?= __('for_partial_refunds_specify_the_exact_refund_amount') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_partial_refund_amount_input') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">6</span>
                <strong><?= __('provide_refund_reason') ?></strong>
                <p><?= __('enter_a_detailed_explanation_for_the_refund') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_refund_reason_textarea') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">7</span>
                <strong><?= __('process_refund') ?></strong>
                <p><?= __('review_details_and_click_process_refund') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_process_refund_button') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">8</span>
                <strong><?= __('verify_refund_processing') ?></strong>
                <p><?= __('confirm_the_refund_has_been_processed_successfully') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_refund_confirmation') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">9</span>
                <strong><?= __('generate_refund_documentation') ?></strong>
                <p><?= __('create_and_save_refund_related_documents') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_refund_document_generation') ?>
                </div>
            </div>

            <div class="alert alert-warning mt-4">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <strong><?= __('important_notes') ?>:</strong>
                <ul class="mb-0">
                    <li><?= __('refunds_cannot_be_easily_reversed') ?></li>
                    <li><?= __('partial_refunds_must_not_exceed_total_amount') ?></li>
                    <li><?= __('maintain_clear_documentation') ?></li>
                    <li><?= __('communicate_refund_details_with_the_client') ?></li>
                </ul>
            </div>

            <div class="alert alert-success mt-3">
                <i class="fas fa-lightbulb mr-2"></i>
                <strong><?= __('pro_tips') ?>:</strong>
                <ul class="mb-0">
                    <li><?= __('verify_client_identity') ?></li>
                    <li><?= __('check_booking_cancellation_policies') ?></li>
                    <li><?= __('document_all_communication') ?></li>
                    <li><?= __('use_current_exchange_rates') ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<div id="umrah-document-generation" class="tutorial-content">
    <div class="card">
        <div class="card-header bg-umrah text-white">
            <h4 class="mb-0"><i class="fas fa-file-alt me-2"></i><?= __('how_to_generate_umrah_documents') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('umrah-document-generation')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i>
                <strong><?= __('overview') ?>:</strong> <?= __('this_tutorial_covers_generating_various_documents_for_umrah_bookings_including_agreements_receipts_id_cards_and_completion_forms') ?>
            </div>

            <div class="step-item">
                <span class="step-number">1</span>
                <strong><?= __('select_document_type') ?></strong>
                <p><?= __('choose_from_various_document_generation_options') ?></p>
                <ul>
                    <li><?= __('tazmin_agreement') ?></li>
                    <li><?= __('family_agreement') ?></li>
                    <li><?= __('family_receipt') ?></li>
                    <li><?= __('completion_form') ?></li>
                    <li><?= __('cancellation_form') ?></li>
                    <li><?= __('id_cards') ?></li>
                </ul>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_document_type_selection') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">2</span>
                <strong><?= __('select_language') ?></strong>
                <p><?= __('choose_the_document_language') ?></p>
                <ul>
                    <li><?= __('english') ?></li>
                    <li><?= __('dari') ?></li>
                    <li><?= __('pashto') ?></li>
                </ul>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_language_selection_modal') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">3</span>
                <strong><?= __('configure_document_details') ?></strong>
                <p><?= __('enter_specific_details_for_different_document_types') ?></p>
                <ul>
                    <li><?= __('family_head_information') ?></li>
                    <li><?= __('booking_details') ?></li>
                    <li><?= __('financial_information') ?></li>
                    <li><?= __('travel_specifics') ?></li>
                </ul>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_document_configuration_form') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">4</span>
                <strong><?= __('generate_id_cards') ?></strong>
                <p><?= __('for_id_card_generation') ?></p>
                <ul>
                    <li><?= __('select_up_to_8_pilgrims') ?></li>
                    <li><?= __('upload_pilgrim_photos') ?></li>
                    <li><?= __('configure_card_details') ?></li>
                </ul>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_id_card_generation_process') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">5</span>
                <strong><?= __('add_guide_information') ?></strong>
                <p><?= __('for_id_cards_and_umrah_letters_enter_guide_details') ?></p>
                <ul>
                    <li><?= __('makkah_guide_name_and_phone') ?></li>
                    <li><?= __('madina_guide_name_and_phone') ?></li>
                    <li><?= __('group_name') ?></li>
                </ul>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_guide_information_input') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">6</span>
                <strong><?= __('generate_document') ?></strong>
                <p><?= __('review_all_details_and_generate_the_document') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_document_generation_confirmation') ?>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">7</span>
                <strong><?= __('save_and_print') ?></strong>
                <p><?= __('save_the_generated_document_and_print_as_needed') ?></p>
                <div class="screenshot-placeholder">
                    <i class="fas fa-image"></i> <?= __('screenshot_needed_document_saving_and_printing') ?>
                </div>
            </div>

            <div class="alert alert-warning mt-4">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <strong><?= __('important_notes') ?>:</strong>
                <ul class="mb-0">
                    <li><?= __('verify_all_document_details_before_generation') ?></li>
                    <li><?= __('maintain_document_confidentiality') ?></li>
                    <li><?= __('keep_digital_and_physical_copies') ?></li>
                    <li><?= __('use_high_quality_images_for_id_cards') ?></li>
                </ul>
            </div>

            <div class="alert alert-success mt-3">
                <i class="fas fa-lightbulb mr-2"></i>
                <strong><?= __('pro_tips') ?>:</strong>
                <ul class="mb-0">
                    <li><?= __('prepare_documents_in_advance') ?></li>
                    <li><?= __('use_consistent_formatting') ?></li>
                    <li><?= __('double_check_guide_and_group_information') ?></li>
                    <li><?= __('maintain_a_systematic_document_filing_system') ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Account Management Tutorials -->
<div id="account-management" class="tutorial-content">
    <div class="card">
        <div class="card-header bg-finance text-white">
            <h4 class="mb-0"><i class="fas fa-university me-2"></i><?= __('account_management_overview') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('account-management')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i>
                <strong><?= __('overview') ?>:</strong> <?= __('this_tutorial_covers_comprehensive_account_management_including_main_accounts_supplier_accounts_and_client_accounts') ?>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-briefcase me-2"></i><?= __('main_account_management') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('navigate_to_accounts_page') ?></h6>
                    <p><?= __('open_the_admin_dashboard_and_click_on_the_accounts_menu_item_to_access_the_account_management_section') ?></p>
                    <div class="screenshot-placeholder" data-description="Screenshot of admin dashboard with Accounts menu highlighted"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">2</span>
                <div class="step-content">
                    <h6><?= __('view_main_accounts') ?></h6>
                    <p><?= __('locate_the_internal_accounts_section_here_you_ll_see_a_list_of_main_accounts_with_their_current_balances_in_multiple_currencies_usd_afs_eur_darham') ?></p>
                    <div class="screenshot-placeholder" data-description="Screenshot of main accounts section showing account details"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">3</span>
                <div class="step-content">
                    <h6><?= __('add_new_main_account') ?></h6>
                    <p><?= __('click_the_add_account_button_to_open_the_new_account_modal_fill_in_the_following_details') ?>:
                        <ul>
                            <li><?= __('account_name') ?></li>
                            <li><?= __('account_type_internal_or_bank') ?></li>
                            <li><?= __('bank_account_number_if_applicable') ?></li>
                            <li><?= __('initial_usd_and_afs_balances') ?></li>
                            <li><?= __('account_status_active_inactive') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="Screenshot of Add Main Account modal with form fields"></div>
                </div>
            </div>

            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <strong><?= __('important') ?>:</strong> <?= __('ensure_all_account_details_are_accurate_before_saving_you_can_edit_account_details_later_but_initial_setup_is_crucial') ?>
            </div>
        </div>
    </div>
</div>
<div id="supplier-account-management" class="tutorial-content">
    <div class="card">
        <div class="card-header bg-finance text-white">
            <h4 class="mb-0"><i class="fas fa-users me-2"></i><?= __('supplier_account_management') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('supplier-account-management')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i>
                <strong><?= __('overview') ?>:</strong> <?= __('learn_how_to_manage_supplier_accounts_view_balances_and_perform_financial_transactions') ?>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-user-tie me-2"></i><?= __('supplier_account_overview') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('navigate_to_supplier_accounts') ?></h6>
                    <p><?= __('scroll_down_to_the_supplier_accounts_section_in_the_accounts_page_here_you_ll_find_a_comprehensive_list_of_all_supplier_accounts') ?></p>
                    <div class="screenshot-placeholder" data-description="Screenshot of supplier accounts section"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">2</span>
                <div class="step-content">
                    <h6><?= __('view_supplier_account_details') ?></h6>
                    <p><?= __('each_supplier_account_displays') ?>:
                        <ul>
                            <li><?= __('supplier_name') ?></li>
                            <li><?= __('currency') ?></li>
                            <li><?= __('current_balance') ?></li>
                            <li><?= __('account_status') ?></li>
                            <li><?= __('last_updated_timestamp') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="Screenshot showing detailed supplier account information"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">3</span>
                <div class="step-content">
                    <h6><?= __('fund_supplier_account') ?></h6>
                    <p><?= __('to_add_funds_to_a_supplier_account') ?>:
                        <ol>
                            <li><?= __('click_the_actions_dropdown_for_the_specific_supplier') ?></li>
                            <li><?= __('select_fund_option') ?></li>
                            <li><?= __('choose_the_main_account_for_funding') ?></li>
                            <li><?= __('enter_the_amount_and_currency') ?></li>
                            <li><?= __('provide_a_receipt_number_and_optional_remarks') ?></li>
                            <li><?= __('confirm_the_transaction') ?></li>
                        </ol>
                    </p>
                    <div class="screenshot-placeholder" data-description="Screenshot of fund supplier modal with transaction details"></div>
                </div>
            </div>

            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <strong><?= __('tip') ?>:</strong> <?= __('use_the_search_and_filter_options_to_quickly_find_specific_supplier_accounts_by_name_currency_or_balance_type') ?>
            </div>
        </div>
    </div>
</div>

<div id="client-account-management" class="tutorial-content">
    <div class="card">
        <div class="card-header bg-finance text-white">
            <h4 class="mb-0"><i class="fas fa-user-friends me-2"></i><?= __('client_account_management') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('client-account-management')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i>
                <strong><?= __('overview') ?>:</strong> <?= __('comprehensive_guide_to_managing_client_accounts_tracking_balances_and_processing_payments') ?>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-user me-2"></i><?= __('client_account_overview') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('navigate_to_client_accounts') ?></h6>
                    <p><?= __('scroll_to_the_client_accounts_section_in_the_accounts_page_this_area_displays_all_active_client_accounts_with_their_financial_details') ?></p>
                    <div class="screenshot-placeholder" data-description="Screenshot of client accounts section"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">2</span>
                <div class="step-content">
                    <h6><?= __('view_client_account_information') ?></h6>
                    <p><?= __('each_client_account_card_shows') ?>:
                        <ul>
                            <li><?= __('client_name') ?></li>
                            <li><?= __('usd_balance') ?></li>
                            <li><?= __('afs_balance') ?></li>
                            <li><?= __('account_status') ?></li>
                            <li><?= __('last_updated_timestamp') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="Screenshot showing detailed client account card"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">3</span>
                <div class="step-content">
                    <h6><?= __('process_client_payment') ?></h6>
                    <p><?= __('to_make_a_payment_for_a_client') ?>:
                        <ol>
                            <li><?= __('click_make_payment_button_on_client_account_card') ?></li>
                            <li><?= __('select_payment_currency_usd_or_afs') ?></li>
                            <li><?= __('enter_payment_amount') ?></li>
                            <li><?= __('set_exchange_rate_if_needed') ?></li>
                            <li><?= __('choose_main_account_for_transaction') ?></li>
                            <li><?= __('add_receipt_number_and_remarks') ?></li>
                            <li><?= __('confirm_payment') ?></li>
                        </ol>
                    </p>
                    <div class="screenshot-placeholder" data-description="Screenshot of client payment modal"></div>
                </div>
            </div>

            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <strong><?= __('pro_tip') ?>:</strong> <?= __('use_currency_and_balance_filters_to_quickly_analyze_client_financial_status') ?>
            </div>
        </div>
    </div>
</div>

<div id="account-transactions" class="tutorial-content">
    <div class="card">
        <div class="card-header bg-finance text-white">
            <h4 class="mb-0"><i class="fas fa-exchange-alt me-2"></i><?= __('account_transactions_management') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('account-transactions')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i>
                <strong><?= __('overview') ?>:</strong> <?= __('learn_how_to_view_filter_and_manage_transaction_histories_for_main_supplier_and_client_accounts') ?>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-list me-2"></i><?= __('viewing_transaction_history') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('access_transaction_history') ?></h6>
                    <p><?= __('for_each_account_type_click_the_view_transactions_or_similar_button_to_open_the_transaction_history_modal') ?></p>
                    <div class="screenshot-placeholder" data-description="Screenshot of transaction history access buttons"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">2</span>
                <div class="step-content">
                    <h6><?= __('filter_transactions') ?></h6>
                    <p><?= __('use_advanced_filtering_options') ?>:
                        <ul>
                            <li><?= __('currency_filter') ?></li>
                            <li><?= __('date_range_selection') ?></li>
                            <li><?= __('receipt_number_search') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="Screenshot of transaction filtering options"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">3</span>
                <div class="step-content">
                    <h6><?= __('export_transaction_report') ?></h6>
                    <p><?= __('click_the_export_pdf_button_to_generate_a_comprehensive_transaction_report_with_all_current_filters_applied') ?></p>
                    <div class="screenshot-placeholder" data-description="Screenshot of export PDF button and generated report"></div>
                </div>
            </div>

            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <strong><?= __('note') ?>:</strong> <?= __('transaction_histories_are_read_only_you_can_view_and_export_but_cannot_modify_past_transactions_directly') ?>
            </div>
        </div>
    </div>
</div>
<div id="account-transfers" class="tutorial-content">
    <div class="card">
        <div class="card-header bg-finance text-white">
            <h4 class="mb-0"><i class="fas fa-random me-2"></i><?= __('account_transfers_and_balance_management') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('account-transfers')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i>
                <strong><?= __('overview') ?>:</strong> <?= __('learn_how_to_transfer_balances_between_accounts_and_manage_multi_currency_transactions') ?>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-exchange-alt me-2"></i><?= __('performing_account_transfers') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('open_transfer_modal') ?></h6>
                    <p><?= __('click_the_transfer_balance_button_in_the_main_accounts_section_to_open_the_transfer_modal') ?></p>
                    <div class="screenshot-placeholder" data-description="Screenshot of transfer balance button and initial modal"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">2</span>
                <div class="step-content">
                    <h6><?= __('configure_transfer_details') ?></h6>
                    <p><?= __('set_up_the_transfer_by_specifying') ?>:
                        <ul>
                            <li><?= __('source_account') ?></li>
                            <li><?= __('source_currency') ?></li>
                            <li><?= __('destination_account') ?></li>
                            <li><?= __('destination_currency') ?></li>
                            <li><?= __('transfer_amount') ?></li>
                            <li><?= __('exchange_rate') ?></li>
                            <li><?= __('optional_description') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="Screenshot of transfer modal with all fields filled"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">3</span>
                <div class="step-content">
                    <h6><?= __('complete_transfer') ?></h6>
                    <p><?= __('review_all_details_and_click_the_transfer_button_to_process_the_inter_account_balance_movement') ?></p>
                    <div class="screenshot-placeholder" data-description="Screenshot of transfer confirmation"></div>
                </div>
            </div>

            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <strong><?= __('caution') ?>:</strong> <?= __('always_double_check_transfer_details_especially_the_exchange_rate_and_destination_account_before_confirming') ?>
            </div>
        </div>
    </div>
</div>

<!-- Debtors Management Tutorials -->
<div id="debtors-overview" class="tutorial-content">
    <div class="card">
        <div class="card-header bg-finance text-white">
            <h4 class="mb-0"><i class="fas fa-user-friends me-2"></i><?= __('debtors_management_overview') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('debtors-overview')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i>
                <strong><?= __('overview') ?>:</strong> <?= __('comprehensive_guide_to_managing_debtors_tracking_balances_and_processing_payments') ?>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-users me-2"></i><?= __('debtors_dashboard') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('navigate_to_debtors_page') ?></h6>
                    <p><?= __('access_the_debtors_management_section_from_the_admin_dashboard_you_ll_see_an_overview_of_all_debtors_with_their_current_balances') ?></p>
                    <div class="screenshot-placeholder" data-description="Screenshot of debtors dashboard main page"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">2</span>
                <div class="step-content">
                    <h6><?= __('understand_debtors_dashboard') ?></h6>
                    <p><?= __('the_dashboard_provides_key_information') ?>:
                        <ul>
                            <li><?= __('total_debts_by_currency') ?></li>
                            <li><?= __('active_and_inactive_debtors_tabs') ?></li>
                            <li><?= __('debtors_table_with_detailed_information') ?></li>
                            <li><?= __('pagination_for_large_lists') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="Screenshot showing dashboard sections and key elements"></div>
                </div>
            </div>

            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <strong><?= __('tip') ?>:</strong> <?= __('use_the_search_and_filter_options_to_quickly_find_specific_debtors_or_analyze_debt_status') ?>
            </div>
        </div>
    </div>
</div>

<div id="add-new-debtor" class="tutorial-content">
    <div class="card">
        <div class="card-header bg-finance text-white">
            <h4 class="mb-0"><i class="fas fa-user-plus me-2"></i><?= __('adding_a_new_debtor') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('add-new-debtor')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i>
                <strong><?= __('overview') ?>:</strong> <?= __('step_by_step_guide_to_adding_a_new_debtor_to_the_system') ?>
            </div>

            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('open_add_debtor_modal') ?></h6>
                    <p><?= __('click_the_add_new_debtor_button_at_the_top_of_the_debtors_page_to_open_the_add_debtor_modal') ?></p>
                    <div class="screenshot-placeholder" data-description="Screenshot of 'Add New Debtor' button and initial modal"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">2</span>
                <div class="step-content">
                    <h6><?= __('enter_debtor_details') ?></h6>
                    <p><?= __('fill_in_the_required_information') ?>:
                        <ul>
                            <li><?= __('name_required') ?></li>
                            <li><?= __('email_optional') ?></li>
                            <li><?= __('phone_optional') ?></li>
                            <li><?= __('address_optional') ?></li>
                            <li><?= __('initial_balance_required') ?></li>
                            <li><?= __('currency_required') ?></li>
                            <li><?= __('main_account_required') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="Screenshot of add debtor form with all fields"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">3</span>
                <div class="step-content">
                    <h6><?= __('optional_deduction_settings') ?></h6>
                    <p><?= __('choose_whether_to_skip_deduction_from_the_main_account_this_allows_flexibility_in_how_initial_balances_are_handled') ?></p>
                    <div class="screenshot-placeholder" data-description="Screenshot of deduction settings checkbox"></div>
                </div>
            </div>

            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <strong><?= __('important') ?>:</strong> <?= __('ensure_all_required_fields_are_filled_accurately_double_check_the_initial_balance_and_selected_main_account') ?>
            </div>
        </div>
    </div>
</div>

<div id="process-debtor-payment" class="tutorial-content">
    <div class="card">
        <div class="card-header bg-finance text-white">
            <h4 class="mb-0"><i class="fas fa-credit-card me-2"></i><?= __('processing_debtor_payments') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('process-debtor-payment')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i>
                <strong><?= __('overview') ?>:</strong> <?= __('comprehensive_guide_to_processing_payments_for_debtors') ?>
            </div>

            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('open_payment_modal') ?></h6>
                    <p><?= __('in_the_debtors_table_click_the_credit_card_icon_for_the_specific_debtor_to_open_the_payment_modal') ?></p>
                    <div class="screenshot-placeholder" data-description="Screenshot of payment button and initial payment modal"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">2</span>
                <div class="step-content">
                    <h6><?= __('review_debtor_information') ?></h6>
                    <p><?= __('the_modal_will_display') ?>:
                        <ul>
                            <li><?= __('debtor_name') ?></li>
                            <li><?= __('current_balance') ?></li>
                            <li><?= __('debtor_s_primary_currency') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="Screenshot of payment modal with debtor details"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">3</span>
                <div class="step-content">
                    <h6><?= __('configure_payment_details') ?></h6>
                    <p><?= __('enter_payment_information') ?>:
                        <ul>
                            <li><?= __('payment_amount') ?></li>
                            <li><?= __('payment_currency_can_differ_from_debtor_s_currency') ?></li>
                            <li><?= __('exchange_rate_if_currencies_differ') ?></li>
                            <li><?= __('payment_date') ?></li>
                            <li><?= __('description_optional') ?></li>
                            <li><?= __('main_account_for_payment') ?></li>
                            <li><?= __('receipt_number_optional') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="Screenshot of payment form with all fields"></div>
                </div>
            </div>

            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <strong><?= __('tip') ?>:</strong> <?= __('when_paying_in_a_different_currency_always_verify_the_exchange_rate_to_ensure_accurate_accounting') ?>
            </div>
        </div>
    </div>
</div>

<div id="manage-debtor-transactions" class="tutorial-content">
    <div class="card">
        <div class="card-header bg-finance text-white">
            <h4 class="mb-0"><i class="fas fa-list-alt me-2"></i><?= __('managing_debtor_transactions') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('manage-debtor-transactions')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i>
                <strong><?= __('overview') ?>:</strong> <?= __('how_to_view_edit_and_manage_debtor_transaction_history') ?>
            </div>

            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('access_transaction_history') ?></h6>
                    <p><?= __('click_the_list_icon_in_the_debtor_s_actions_column_to_open_the_transactions_modal') ?></p>
                    <div class="screenshot-placeholder" data-description="Screenshot of transactions list button and modal"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">2</span>
                <div class="step-content">
                    <h6><?= __('view_transaction_details') ?></h6>
                    <p><?= __('the_transactions_modal_shows') ?>:
                        <ul>
                            <li><?= __('transaction_date') ?></li>
                            <li><?= __('amount') ?></li>
                            <li><?= __('transaction_type_credit_debt') ?></li>
                            <li><?= __('description') ?></li>
                            <li><?= __('receipt_number') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="Screenshot of transaction list with details"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">3</span>
                <div class="step-content">
                    <h6><?= __('edit_or_delete_transactions') ?></h6>
                    <p><?= __('for_each_transaction_you_can') ?>:
                        <ul>
                            <li><?= __('edit_transaction_details') ?></li>
                            <li><?= __('delete_transaction_reverses_the_payment') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="Screenshot of edit and delete transaction buttons"></div>
                </div>
            </div>

            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <strong><?= __('caution') ?>:</strong> <?= __('deleting_a_transaction_will_reverse_the_payment_use_this_feature_carefully_and_only_when_absolutely_necessary') ?>
            </div>
        </div>
    </div>
</div>

<div id="debtor-status-management" class="tutorial-content">
    <div class="card">
        <div class="card-header bg-finance text-white">
            <h4 class="mb-0"><i class="fas fa-toggle-on me-2"></i><?= __('debtor_status_management') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('debtor-status-management')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i>
                <strong><?= __('overview') ?>:</strong> <?= __('how_to_manage_debtor_account_statuses_and_generate_reports') ?>
            </div>

            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('switch_between_active_and_inactive_debtors') ?></h6>
                    <p><?= __('use_the_tabs_at_the_top_of_the_page_to_toggle_between') ?>:
                        <ul>
                            <li><?= __('active_debtors') ?></li>
                            <li><?= __('inactive_debtors') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="Screenshot of active/inactive debtor tabs"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">2</span>
                <div class="step-content">
                    <h6><?= __('deactivate_a_debtor') ?></h6>
                    <p><?= __('for_active_debtors_with_zero_balance_you_can') ?>:
                        <ul>
                            <li><?= __('click_the_deactivate_button_user_x_icon') ?></li>
                            <li>Confirm the deactivation</li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="Screenshot of deactivate debtor button and confirmation"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">3</span>
                <div class="step-content">
                    <h6><?= __('generate_debtor_reports') ?></h6>
                    <p><?= __('for_each_debtor_you_can') ?>:
                        <ul>
                            <li><?= __('print_debtor_statement') ?></li>
                            <li><?= __('print_debtor_agreement') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="Screenshot of print statement and agreement buttons"></div>
                </div>
            </div>

            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <strong><?= __('important') ?>:</strong> <?= __('only_debtors_with_a_zero_balance_can_be_deactivated_ensure_all_payments_are_settled_before_changing_status') ?>
            </div>
        </div>
    </div>
</div>

<!-- Creditors Management Tutorials -->
<div id="creditors-overview" class="tutorial-content">
    <div class="card">
        <div class="card-header bg-finance text-white">
            <h4 class="mb-0"><i class="fas fa-users me-2"></i><?= __('creditors_management_overview') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('creditors-overview')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i>
                <strong><?= __('overview') ?>:</strong> <?= __('comprehensive_guide_to_managing_creditors_tracking_credits_and_processing_transactions') ?>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-users me-2"></i><?= __('creditors_dashboard') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('navigate_to_creditors_page') ?></h6>
                    <p><?= __('access_the_creditors_management_section_from_the_admin_dashboard_you_ll_see_an_overview_of_all_creditors_with_their_current_credit_balances') ?></p>
                    <div class="screenshot-placeholder" data-description="Screenshot of creditors dashboard main page"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">2</span>
                <div class="step-content">
                    <h6><?= __('understand_creditors_dashboard') ?></h6>
                    <p><?= __('the_dashboard_provides_key_information') ?>:
                        <ul>
                            <li><?= __('total_credits_by_currency') ?></li>
                            <li><?= __('active_and_inactive_creditors_tabs') ?></li>
                            <li><?= __('creditors_table_with_detailed_information') ?></li>
                            <li><?= __('pagination_for_large_lists') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="Screenshot showing dashboard sections and key elements"></div>
                </div>
            </div>

            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <strong><?= __('tip') ?>:</strong> <?= __('use_the_search_and_filter_options_to_quickly_find_specific_creditors_or_analyze_credit_status') ?>
            </div>
        </div>
    </div>
</div>

<div id="add-new-creditor" class="tutorial-content">
    <div class="card">
        <div class="card-header bg-finance text-white">
            <h4 class="mb-0"><i class="fas fa-user-plus me-2"></i><?= __('adding_a_new_creditor') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('add-new-creditor')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i>
                <strong><?= __('overview') ?>:</strong> <?= __('step_by_step_guide_to_adding_a_new_creditor_to_the_system') ?>
            </div>

            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('open_add_creditor_modal') ?></h6>
                    <p><?= __('click_the_add_new_creditor_button_at_the_top_of_the_creditors_page_to_open_the_add_creditor_modal') ?></p>
                    <div class="screenshot-placeholder" data-description="Screenshot of 'Add New Creditor' button and initial modal"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">2</span>
                <div class="step-content">
                    <h6><?= __('enter_creditor_details') ?></h6>
                    <p><?= __('fill_in_the_required_information') ?>:
                        <ul>
                            <li><?= __('name_required') ?></li>
                            <li><?= __('email_optional') ?></li>
                            <li><?= __('phone_optional') ?></li>
                            <li><?= __('address_optional') ?></li>
                            <li><?= __('initial_balance_required') ?></li>
                            <li><?= __('currency_required') ?></li>
                            <li><?= __('main_account_required') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="Screenshot of add creditor form with all fields"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">3</span>
                <div class="step-content">
                    <h6><?= __('optional_deduction_settings') ?></h6>
                    <p><?= __('choose_whether_to_skip_adding_the_initial_balance_to_the_main_account_this_allows_flexibility_in_how_initial_credits_are_handled') ?></p>
                    <div class="screenshot-placeholder" data-description="Screenshot of deduction settings checkbox"></div>
                </div>
            </div>

            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <strong><?= __('important') ?>:</strong> <?= __('ensure_all_required_fields_are_filled_accurately_double_check_the_initial_balance_and_selected_main_account') ?>
            </div>
        </div>
    </div>
</div>
<div id="process-creditor-payment" class="tutorial-content">
    <div class="card">
        <div class="card-header bg-finance text-white">
            <h4 class="mb-0"><i class="fas fa-credit-card me-2"></i><?= __('processing_creditor_payments') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('process-creditor-payment')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i>
                <strong><?= __('overview') ?>:</strong> <?= __('comprehensive_guide_to_processing_payments_for_creditors') ?>
            </div>

            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('open_payment_modal') ?></h6>
                    <p><?= __('in_the_creditors_table_click_the_credit_card_icon_for_the_specific_creditor_to_open_the_payment_modal') ?></p>
                    <div class="screenshot-placeholder" data-description="Screenshot of payment button and initial payment modal"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">2</span>
                <div class="step-content">
                    <h6><?= __('review_creditor_information') ?></h6>
                    <p><?= __('the_modal_will_display') ?>:
                        <ul>
                            <li><?= __('creditor_name') ?></li>
                            <li><?= __('current_balance') ?></li>
                            <li><?= __('creditor_s_primary_currency') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="Screenshot of payment modal with creditor details"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">3</span>
                <div class="step-content">
                    <h6><?= __('configure_payment_details') ?></h6>
                    <p><?= __('enter_payment_information') ?>:
                        <ul>
                            <li><?= __('payment_amount') ?></li>
                            <li><?= __('payment_currency_can_differ_from_creditor_s_currency') ?></li>
                            <li><?= __('exchange_rate_if_currencies_differ') ?></li>
                            <li><?= __('payment_date') ?></li>
                            <li><?= __('description_optional') ?></li>
                            <li><?= __('main_account_for_payment') ?></li>
                            <li><?= __('receipt_number_optional') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="Screenshot of payment form with all fields"></div>
                </div>
            </div>

            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <strong><?= __('tip') ?>:</strong> <?= __('when_paying_in_a_different_currency_always_verify_the_exchange_rate_to_ensure_accurate_accounting') ?>
            </div>
        </div>
    </div>
</div>

<div id="manage-creditor-transactions" class="tutorial-content">
    <div class="card">
        <div class="card-header bg-finance text-white">
            <h4 class="mb-0"><i class="fas fa-list-alt me-2"></i><?= __('managing_creditor_transactions') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('manage-creditor-transactions')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i>
                <strong><?= __('overview') ?>:</strong> <?= __('how_to_view_edit_and_manage_creditor_transaction_history') ?>
            </div>

            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('access_transaction_history') ?></h6>
                    <p><?= __('click_the_list_icon_in_the_creditor_s_actions_column_to_open_the_transactions_modal') ?></p>
                    <div class="screenshot-placeholder" data-description="Screenshot of transactions list button and modal"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">2</span>
                <div class="step-content">
                    <h6><?= __('view_transaction_details') ?></h6>
                    <p><?= __('the_transactions_modal_shows') ?>:
                        <ul>
                            <li><?= __('transaction_date') ?></li>
                            <li><?= __('amount') ?></li>
                            <li><?= __('transaction_type_debit_credit') ?></li>
                            <li><?= __('description') ?></li>
                            <li><?= __('receipt_number') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="Screenshot of transaction list with details"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">3</span>
                <div class="step-content">
                    <h6><?= __('edit_or_delete_transactions') ?></h6>
                    <p><?= __('for_each_transaction_you_can') ?>:
                        <ul>
                            <li><?= __('edit_transaction_details') ?></li>
                            <li><?= __('delete_transaction_reverses_the_payment') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="Screenshot of edit and delete transaction buttons"></div>
                </div>
            </div>

            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <strong><?= __('caution') ?>:</strong> <?= __('deleting_a_transaction_will_reverse_the_payment_use_this_feature_carefully_and_only_when_absolutely_necessary') ?>
            </div>
        </div>
    </div>
</div>

<div id="creditor-status-management" class="tutorial-content">
    <div class="card">
        <div class="card-header bg-finance text-white">
            <h4 class="mb-0"><i class="fas fa-toggle-on me-2"></i><?= __('creditor_status_management') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('creditor-status-management')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i>
                <strong><?= __('overview') ?>:</strong> <?= __('how_to_manage_creditor_account_statuses_and_generate_reports') ?>
            </div>

            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('switch_between_active_and_inactive_creditors') ?></h6>
                    <p><?= __('use_the_tabs_at_the_top_of_the_page_to_toggle_between') ?>:
                        <ul>
                            <li><?= __('active_creditors') ?></li>
                            <li><?= __('inactive_creditors') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="Screenshot of active/inactive creditor tabs"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">2</span>
                <div class="step-content">
                    <h6><?= __('delete_a_creditor') ?></h6>
                    <p><?= __('for_creditors_with_a_zero_balance_you_can') ?>:
                        <ul>
                            <li><?= __('click_the_delete_button_trash_icon') ?></li>
                            <li><?= __('confirm_the_deletion') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="Screenshot of delete creditor button and confirmation"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">3</span>
                <div class="step-content">
                    <h6><?= __('generate_creditor_reports') ?></h6>
                    <p><?= __('for_each_creditor_you_can') ?>:
                        <ul>
                            <li><?= __('print_creditor_statement') ?></li>
                        </ul>
                    </p>
                    <div class="screenshot-placeholder" data-description="Screenshot of print statement button"></div>
                </div>
            </div>

            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <strong><?= __('important') ?>:</strong> <?= __('only_creditors_with_a_zero_balance_can_be_deleted_ensure_all_credits_are_settled_before_deleting') ?>
            </div>
        </div>
    </div>
</div>

<!-- Additional Payments Management Tutorials -->
<div id="additional-payments-overview" class="tutorial-content">
    <div class="card">
        <div class="card-header bg-finance text-white">
            <h4 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i><?= __('additional_payments_management_overview') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('additional-payments-overview')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i>
                <strong><?= __('overview') ?>:</strong> <?= __('comprehensive_guide_to_managing_additional_payments_including_creating_editing_and_tracking_financial_transactions') ?>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-list me-2"></i><?= __('additional_payments_dashboard') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('navigate_to_additional_payments_page') ?></h6>
                    <p><?= __('access_the_additional_payments_management_section_from_the_main_admin_dashboard') ?></p>
                    <div class="screenshot-placeholder" data-description="Main additional payments page with table of existing payments"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">2</span>
                <div class="step-content">
                    <h6><?= __('understand_payment_table_columns') ?></h6>
                    <p><?= __('the_table_displays_key_information_for_each_additional_payment') ?>:
                    <ul>
                        <li><strong><?= __('actions') ?>:</strong> <?= __('edit_add_transactions_delete_payment') ?></li>
                        <li><strong><?= __('payment_type') ?>:</strong> <?= __('category_or_purpose_of_the_payment') ?></li>
                        <li><strong><?= __('description') ?>:</strong> <?= __('detailed_notes_about_the_payment') ?></li>
                        <li><strong><?= __('financial_details') ?>:</strong> <?= __('base_amount_sold_amount_and_profit') ?></li>
                        <li><strong><?= __('accounts') ?>:</strong> <?= __('main_account_supplier_and_client_information') ?></li>
                        <li><strong><?= __('created_by') ?>:</strong> <?= __('user_who_created_the_payment_entry') ?></li>
                    </ul>
                    <div class="screenshot-placeholder" data-description="Detailed view of additional payments table columns"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="create-additional-payment" class="tutorial-content">
    <div class="card">
        <div class="card-header bg-finance text-white">
            <h4 class="mb-0"><i class="fas fa-plus me-2"></i><?= __('how_to_create_additional_payments') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('create-additional-payment')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i>
                <strong><?= __('overview') ?>:</strong> <?= __('step_by_step_guide_to_creating_new_additional_payments_with_detailed_financial_tracking') ?>
            </div>

            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('open_new_payment_modal') ?></h6>
                    <p><?= __('click_the_add_new_payment_button_at_the_top_right_of_the_additional_payments_page') ?></p>
                    <div class="screenshot-placeholder" data-description="Button to open new payment modal"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">2</span>
                <div class="step-content">
                    <h6><?= __('enter_payment_type') ?></h6>
                    <p><?= __('specify_the_type_or_category_of_the_additional_payment_e_g_service_fee_consultation_equipment') ?></p>
                    <div class="screenshot-placeholder" data-description="Payment type input field"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">3</span>
                <div class="step-content">
                    <h6><?= __('select_main_account') ?></h6>
                    <p><?= __('choose_the_primary_account_associated_with_this_payment_from_the_dropdown_menu') ?></p>
                    <div class="screenshot-placeholder" data-description="Main account selection dropdown"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">4</span>
                <div class="step-content">
                    <h6><?= __('add_payment_description') ?></h6>
                    <p><?= __('provide_a_detailed_description_of_the_payment_for_record_keeping_purposes') ?></p>
                    <div class="screenshot-placeholder" data-description="Description textarea"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">5</span>
                <div class="step-content">
                    <h6><?= __('enter_financial_details') ?></h6>
                    <p><?= __('input_the_base_amount_sold_amount_and_profit_will_be_automatically_calculated') ?></p>
                    <ul>
                        <li><strong><?= __('base_amount') ?>:</strong> <?= __('original_cost_or_expense') ?></li>
                        <li><strong><?= __('sold_amount') ?>:</strong> <?= __('total_amount_charged_or_received') ?></li>
                        <li><strong><?= __('profit') ?>:</strong> <?= __('automatically_calculated_sold_amount_base_amount') ?></li>
                    </ul>
                    <div class="screenshot-placeholder" data-description="Financial details input fields"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">6</span>
                <div class="step-content">
                    <h6><?= __('select_currency') ?></h6>
                    <p><?= __('choose_the_currency_for_the_payment_usd_afs_eur_darham') ?></p>
                    <div class="screenshot-placeholder" data-description="Currency selection dropdown"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">7</span>
                <div class="step-content">
                    <h6><?= __('optional_link_to_supplier') ?></h6>
                    <p><?= __('check_bought_from_supplier_and_select_the_supplier_if_applicable') ?></p>
                    <div class="screenshot-placeholder" data-description="Supplier selection checkbox and dropdown"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">8</span>
                <div class="step-content">
                    <h6><?= __('optional_link_to_client') ?></h6>
                    <p><?= __('check_sold_to_client_and_select_the_client_if_applicable') ?></p>
                    <div class="screenshot-placeholder" data-description="Client selection checkbox and dropdown"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">9</span>
                <div class="step-content">
                    <h6><?= __('save_payment') ?></h6>
                    <p><?= __('click_save_payment_to_create_the_additional_payment_entry') ?></p>
                    <div class="screenshot-placeholder" data-description="Save payment button and confirmation"></div>
                </div>
            </div>
        </div>
    </div>
</div>
<div id="manage-additional-payment-transactions" class="tutorial-content">
    <div class="card">
        <div class="card-header bg-finance text-white">
            <h4 class="mb-0"><i class="fas fa-exchange-alt me-2"></i><?= __('managing_additional_payment_transactions') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('manage-additional-payment-transactions')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i>
                <strong><?= __('overview') ?>:</strong> <?= __('comprehensive_guide_to_adding_editing_and_tracking_transactions_for_additional_payments') ?>
            </div>

            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('open_transactions_modal') ?></h6>
                    <p><?= __('click_the_plus_button_in_the_actions_column_for_the_specific_payment') ?></p>
                    <div class="screenshot-placeholder" data-description="Add transaction button in payment table"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">2</span>
                <div class="step-content">
                    <h6><?= __('review_payment_summary') ?></h6>
                    <p><?= __('check_the_payment_details_total_amount_and_remaining_balance_before_adding_a_transaction') ?></p>
                    <div class="screenshot-placeholder" data-description="Payment summary section in transaction modal"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">3</span>
                <div class="step-content">
                    <h6><?= __('select_transaction_date_and_time') ?></h6>
                    <p><?= __('choose_the_precise_date_and_time_of_the_transaction') ?></p>
                    <div class="screenshot-placeholder" data-description="Date and time picker for transaction"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">4</span>
                <div class="step-content">
                    <h6><?= __('enter_transaction_amount') ?></h6>
                    <p><?= __('input_the_transaction_amount_which_can_be_in_the_same_or_a_different_currency') ?></p>
                    <div class="screenshot-placeholder" data-description="Transaction amount input field"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">5</span>
                <div class="step-content">
                    <h6><?= __('select_transaction_currency') ?></h6>
                    <p><?= __('choose_the_currency_for_this_specific_transaction_usd_afs_eur_darham') ?></p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <?= __('if_the_transaction_currency_differs_from_the_payment_currency_you_must_enter_an_exchange_rate') ?>
                    </div>
                    <div class="screenshot-placeholder" data-description="Currency selection for transaction"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">6</span>
                <div class="step-content">
                    <h6><?= __('add_exchange_rate_if_needed') ?></h6>
                    <p><?= __('when_using_a_different_currency_enter_the_exchange_rate_between_the_transaction_and_payment_currencies') ?></p>
                    <div class="screenshot-placeholder" data-description="Exchange rate input field"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">7</span>
                <div class="step-content">
                    <h6><?= __('add_transaction_description') ?></h6>
                    <p><?= __('provide_details_about_the_transaction_for_record_keeping') ?></p>
                    <div class="screenshot-placeholder" data-description="Transaction description textarea"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">8</span>
                <div class="step-content">
                    <h6><?= __('save_transaction') ?></h6>
                    <p><?= __('click_add_transaction_to_record_the_payment') ?></p>
                    <div class="screenshot-placeholder" data-description="Add transaction button and confirmation"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">9</span>
                <div class="step-content">
                    <h6><?= __('review_transaction_history') ?></h6>
                    <p><?= __('view_all_transactions_for_the_payment_including_amounts_currencies_and_descriptions') ?></p>
                    <div class="screenshot-placeholder" data-description="Transaction history table in modal"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JV Payments Management Tutorials -->
<div id="jv-payments-overview" class="tutorial-content">
    <div class="card">
        <div class="card-header bg-finance text-white">
            <h4 class="mb-0"><i class="fas fa-exchange-alt me-2"></i><?= __('jv_payments_management_overview') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('jv-payments-overview')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i>
                <strong><?= __('overview') ?>:</strong> <?= __('comprehensive_guide_to_managing_journal_voucher_jv_payments_between_clients_and_suppliers') ?>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-list me-2"></i><?= __('jv_payments_dashboard') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('navigate_to_jv_payments_page') ?></h6>
                    <p><?= __('access_the_jv_payments_management_section_from_the_main_admin_dashboard') ?></p>
                    <div class="screenshot-placeholder" data-description="Main JV payments page with table of existing payments"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">2</span>
                <div class="step-content">
                    <h6><?= __('understand_jv_payments_table') ?></h6>
                    <p><?= __('the_table_displays_key_information_for_each_jv_payment') ?>:
                    <ul>
                        <li><strong><?= __('date') ?>:</strong> <?= __('when_the_payment_was_created') ?></li>
                        <li><strong><?= __('jv_name') ?>:</strong> <?= __('unique_identifier_for_the_payment') ?></li>
                        <li><strong><?= __('client') ?>:</strong> <?= __('source_of_the_payment') ?></li>
                        <li><strong><?= __('supplier') ?>:</strong> <?= __('recipient_of_the_payment') ?></li>
                        <li><strong><?= __('amount') ?>:</strong> <?= __('total_payment_amount') ?></li>
                        <li><strong><?= __('currency') ?>:</strong> <?= __('payment_currency') ?></li>
                        <li><strong><?= __('receipt') ?>:</strong> <?= __('payment_receipt_number') ?></li>
                        <li><strong><?= __('actions') ?>:</strong> <?= __('view_edit_or_delete_payment') ?></li>
                    </ul>
                    <div class="screenshot-placeholder" data-description="Detailed view of JV payments table columns"></div>
                </div>
            </div>

            <div class="alert alert-warning mt-4">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <strong><?= __('important_notes') ?>:</strong>
                <ul>
                    <li><?= __('jv_payments_allow_direct_transfers_between_clients_and_suppliers') ?></li>
                    <li><?= __('supports_multiple_currencies_with_exchange_rate_conversion') ?></li>
                    <li><?= __('automatically_updates_client_and_supplier_account_balances') ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>
<div id="create-jv-payment" class="tutorial-content">
    <div class="card">
        <div class="card-header bg-finance text-white">
            <h4 class="mb-0"><i class="fas fa-plus me-2"></i><?= __('how_to_create_a_jv_payment') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('create-jv-payment')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i>
                <strong><?= __('overview') ?>:</strong> <?= __('step_by_step_guide_to_creating_a_new_journal_voucher_jv_payment_between_a_client_and_a_supplier') ?>
            </div>

            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('open_new_payment_modal') ?></h6>
                    <p><?= __('click_the_add_new_payment_button_at_the_top_right_of_the_jv_payments_page') ?></p>
                    <div class="screenshot-placeholder" data-description="Button to open new JV payment modal"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">2</span>
                <div class="step-content">
                    <h6><?= __('enter_jv_payment_name') ?></h6>
                    <p><?= __('provide_a_descriptive_name_for_the_payment_default_is_client_supplier_payment') ?></p>
                    <div class="screenshot-placeholder" data-description="JV payment name input field"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">3</span>
                <div class="step-content">
                    <h6><?= __('select_client') ?></h6>
                    <p><?= __('choose_the_client_who_will_be_the_source_of_the_payment_from_the_dropdown_menu') ?></p>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle mr-2"></i>
                        <?= __('the_dropdown_shows_client_names_with_their_usd_and_afs_account_balances') ?>
                    </div>
                    <div class="screenshot-placeholder" data-description="Client selection dropdown"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">4</span>
                <div class="step-content">
                    <h6><?= __('select_supplier') ?></h6>
                    <p><?= __('choose_the_supplier_who_will_receive_the_payment_from_the_dropdown_menu') ?></p>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle mr-2"></i>
                        <?= __('the_dropdown_shows_supplier_names_with_their_current_balance_and_currency') ?>
                    </div>
                    <div class="screenshot-placeholder" data-description="Supplier selection dropdown"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">5</span>
                <div class="step-content">
                    <h6><?= __('select_payment_currency') ?></h6>
                    <p><?= __('choose_the_currency_for_the_payment_usd_or_afs') ?></p>
                    <div class="screenshot-placeholder" data-description="Currency selection dropdown"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">6</span>
                <div class="step-content">
                    <h6><?= __('enter_payment_amount') ?></h6>
                    <p><?= __('input_the_total_amount_to_be_transferred_between_the_client_and_supplier') ?></p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <?= __('ensure_the_client_has_sufficient_balance_in_the_selected_currency') ?>
                    </div>
                    <div class="screenshot-placeholder" data-description="Payment amount input field"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">7</span>
                <div class="step-content">
                    <h6><?= __('add_exchange_rate_if_needed') ?></h6>
                    <p><?= __('if_the_client_and_supplier_use_different_currencies_enter_the_exchange_rate') ?></p>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle mr-2"></i>
                        <?= __('the_exchange_rate_field_appears_automatically_when_currencies_differ') ?>
                    </div>
                    <div class="screenshot-placeholder" data-description="Exchange rate input field"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">8</span>
                <div class="step-content">
                    <h6><?= __('enter_receipt_number') ?></h6>
                    <p><?= __('provide_a_unique_receipt_or_reference_number_for_the_payment') ?></p>
                    <div class="screenshot-placeholder" data-description="Receipt number input field"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">9</span>
                <div class="step-content">
                    <h6><?= __('add_optional_remarks') ?></h6>
                    <p><?= __('include_any_additional_notes_or_context_for_the_payment') ?></p>
                    <div class="screenshot-placeholder" data-description="Remarks textarea"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">10</span>
                <div class="step-content">
                    <h6><?= __('process_payment') ?></h6>
                    <p><?= __('click_process_payment_to_complete_the_jv_transaction') ?></p>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle mr-2"></i>
                        <?= __('the_system_will') ?>:
                        <ul>
                            <li><?= __('deduct_the_amount_from_the_clients_account') ?></li>
                            <li><?= __('add_the_amount_to_the_suppliers_account') ?></li>
                            <li><?= __('record_the_transaction_with_all_details') ?></li>
                        </ul>
                    </div>
                    <div class="screenshot-placeholder" data-description="Process payment button and confirmation"></div>
                </div>
            </div>
        </div>
    </div>
</div>
<div id="manage-jv-payment-details" class="tutorial-content">
    <div class="card">
        <div class="card-header bg-finance text-white">
            <h4 class="mb-0"><i class="fas fa-eye me-2"></i><?= __('managing_jv_payment_details') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('manage-jv-payment-details')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i>
                <strong><?= __('overview') ?>:</strong> <?= __('how_to_view_edit_and_manage_existing_jv_payments') ?>
            </div>

            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('view_payment_details') ?></h6>
                    <p><?= __('click_the_view_eye_button_in_the_actions_column_to_see_full_payment_information') ?></p>
                    <div class="screenshot-placeholder" data-description="View payment details button and modal"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">2</span>
                <div class="step-content">
                    <h6><?= __('understand_payment_details_modal') ?></h6>
                    <p><?= __('the_modal_provides_comprehensive_information_about_the_jv_payment') ?>:</p>
                    <ul>
                        <li><strong><?= __('payment_header') ?>:</strong> <?= __('id_name_date_and_total_amount') ?></li>
                        <li><strong><?= __('client_information') ?>:</strong> <?= __('name_and_payment_source') ?></li>
                        <li><strong><?= __('supplier_information') ?>:</strong> <?= __('name_and_payment_recipient') ?></li>
                        <li><strong><?= __('payment_details') ?>:</strong> <?= __('exchange_rate_created_by_and_update_time') ?></li>
                        <li><strong><?= __('remarks') ?>:</strong> <?= __('additional_context_for_the_payment') ?></li>
                    </ul>
                    <div class="screenshot-placeholder" data-description="Detailed view of payment details modal"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">3</span>
                <div class="step-content">
                    <h6><?= __('edit_payment') ?></h6>
                    <p><?= __('click_the_edit_button_in_the_details_modal_or_actions_column_to_modify_the_payment') ?></p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <?= __('editing_is_restricted_to_payments_with_complete_client_and_supplier_information') ?>
                    </div>
                    <div class="screenshot-placeholder" data-description="Edit payment button and modal"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">4</span>
                <div class="step-content">
                    <h6><?= __('modify_payment_details') ?></h6>
                    <p><?= __('in_the_edit_modal_you_can_update') ?>:</p>
                    <ul>
                        <li><?= __('jv_payment_name') ?></li>
                        <li><?= __('client') ?></li>
                        <li><?= __('supplier') ?></li>
                        <li><?= __('currency') ?></li>
                        <li><?= __('total_amount') ?></li>
                        <li><?= __('exchange_rate') ?></li>
                        <li><?= __('receipt_number') ?></li>
                        <li><?= __('remarks') ?></li>
                    </ul>
                    <div class="screenshot-placeholder" data-description="Edit payment form with modifiable fields"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">5</span>
                <div class="step-content">
                    <h6><?= __('delete_payment') ?></h6>
                    <p><?= __('click_the_delete_button_to_remove_a_jv_payment') ?></p>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <?= __('deleting_a_payment_will') ?>:
                        <ul>
                            <li><?= __('return_funds_to_the_client_account') ?></li>
                            <li><?= __('deduct_the_amount_from_the_supplier_balance') ?></li>
                            <li><?= __('delete_all_associated_transaction_records') ?></li>
                        </ul>
                        <strong><?= __('this_action_cannot_be_undone') ?>!</strong>
                    </div>
                    <div class="screenshot-placeholder" data-description="Delete payment button and confirmation modal"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Sarafi Management Tutorials -->
<div id="sarafi-overview" class="tutorial-content">
    <div class="card">
        <div class="card-header bg-finance text-white">
            <h4 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i><?= __('sarafi_management_overview') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('sarafi-overview')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i>
                <strong><?= __('overview') ?>:</strong> <?= __('comprehensive_guide_to_managing_financial_transactions_including_deposits_withdrawals_hawala_transfers_and_currency_exchanges') ?>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-list me-2"></i><?= __('sarafi_dashboard_components') ?></h5>
            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('authentication_and_security') ?></h6>
                    <p><?= __('the_sarafi_management_system_implements_robust_security_measures') ?>:</p>
                    <ul>
                        <li><?= __('enforces_user_authentication') ?></li>
                        <li><?= __('restricts_access_to_admin_users') ?></li>
                        <li><?= __('manages_user_sessions') ?></li>
                        <li><?= __('provides_secure_database_connections') ?></li>
                    </ul>
                    <div class="screenshot-placeholder" data-description="Authentication and security flow"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">2</span>
                <div class="step-content">
                    <h6><?= __('message_management') ?></h6>
                    <p><?= __('handles_system_messages_with_a_clean_user_friendly_approach') ?>:</p>
                    <ul>
                        <li><?= __('stores_success_and_error_messages_in_session') ?></li>
                        <li><?= __('automatically_clears_messages_after_display') ?></li>
                        <li><?= __('supports_multilingual_message_handling') ?></li>
                    </ul>
                    <div class="screenshot-placeholder" data-description="Message management system"></div>
                </div>
            </div>

            <div class="alert alert-warning mt-4">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <strong><?= __('key_security_features') ?>:</strong>
                <ul>
                    <li><?= __('enforced_authentication_for_all_sarafi_operations') ?></li>
                    <li><?= __('session_based_access_control') ?></li>
                    <li><?= __('secure_database_connection_management') ?></li>
                    <li><?= __('dynamic_url_handling_to_prevent_manipulation') ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Sarafi Deposits and Withdrawals Tutorials -->
<div id="sarafi-deposits-withdrawals" class="tutorial-content">
    <div class="card">
        <div class="card-header bg-finance text-white">
            <h4 class="mb-0"><i class="fas fa-exchange-alt me-2"></i><?= __('sarafi_deposits_and_withdrawals_management') ?></h4>
            <button type="button" class="btn-close btn-close-white float-end" onclick="hideTutorial('sarafi-deposits-withdrawals')"></button>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i>
                <strong><?= __('overview') ?>:</strong> <?= __('comprehensive_guide_to_managing_customer_deposits_and_withdrawals_in_the_sarafi_system') ?>
            </div>

            <div class="step-item">
                <span class="step-number">1</span>
                <div class="step-content">
                    <h6><?= __('navigate_to_deposits_withdrawals_section') ?></h6>
                    <p><?= __('access_the_sarafi_management_dashboard_and_locate_the_deposit_or_withdrawal_buttons') ?></p>
                    <div class="screenshot-placeholder" data-description="Sarafi dashboard with deposit and withdrawal buttons"></div>
                </div>
            </div>

            <h4 class="mt-4 mb-3"><i class="fas fa-plus-circle me-2"></i><?= __('deposit_process') ?></h4>

            <div class="step-item">
                <span class="step-number">2</span>
                <div class="step-content">
                    <h6><?= __('open_deposit_modal') ?></h6>
                    <p><?= __('click_the_new_deposit_button_to_open_the_deposit_transaction_modal') ?></p>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle mr-2"></i>
                        <?= __('ensure_you_have_the_necessary_permissions_to_process_deposits') ?>
                    </div>
                    <div class="screenshot-placeholder" data-description="New deposit button and modal opening"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">3</span>
                <div class="step-content">
                    <h6><?= __('select_customer') ?></h6>
                    <p><?= __('choose_the_customer_for_the_deposit_from_the_dropdown_menu') ?></p>
                    <ul>
                        <li><?= __('dropdown_shows_customer_names') ?></li>
                        <li><?= __('displays_current_wallet_balances') ?></li>
                        <li><?= __('search_and_filter_options_available') ?></li>
                    </ul>
                    <div class="screenshot-placeholder" data-description="Customer selection dropdown"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">4</span>
                <div class="step-content">
                    <h6><?= __('select_main_account') ?></h6>
                    <p><?= __('choose_the_main_account_for_the_deposit_transaction') ?></p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <?= __('ensure_you_select_the_correct_account_for_accurate_financial_tracking') ?>
                    </div>
                    <div class="screenshot-placeholder" data-description="Main account selection dropdown"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">5</span>
                <div class="step-content">
                    <h6><?= __('enter_deposit_details') ?></h6>
                    <p><?= __('fill_in_the_deposit_transaction_details') ?>:</p>
                    <ul>
                        <li><?= __('amount') ?></li>
                        <li><?= __('currency_usd_afs_eur_darham') ?></li>
                        <li><?= __('reference_number') ?></li>
                        <li><?= __('optional_notes') ?></li>
                    </ul>
                    <div class="screenshot-placeholder" data-description="Deposit details input fields"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">6</span>
                <div class="step-content">
                    <h6><?= __('upload_receipt_optional') ?></h6>
                    <p><?= __('upload_a_receipt_or_supporting_document_for_the_deposit') ?></p>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle mr-2"></i>
                        <?= __('recommended_for_record_keeping_and_audit_trails') ?>
                    </div>
                    <div class="screenshot-placeholder" data-description="Receipt upload interface"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">7</span>
                <div class="step-content">
                    <h6><?= __('process_deposit') ?></h6>
                    <p><?= __('click_save_to_complete_the_deposit_transaction') ?></p>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle mr-2"></i>
                        <?= __('the_system_will') ?>:
                        <ul>
                            <li><?= __('update_customer_wallet_balance') ?></li>
                            <li><?= __('record_the_transaction') ?></li>
                            <li><?= __('update_main_account_balance') ?></li>
                            <li><?= __('generate_a_transaction_record') ?></li>
                        </ul>
                    </div>
                    <div class="screenshot-placeholder" data-description="Deposit confirmation and success message"></div>
                </div>
            </div>

            <h4 class="mt-4 mb-3"><i class="fas fa-minus-circle me-2"></i><?= __('withdrawal_process') ?></h4>

            <div class="step-item">
                <span class="step-number">8</span>
                <div class="step-content">
                    <h6><?= __('open_withdrawal_modal') ?></h6>
                    <p><?= __('click_the_new_withdrawal_button_to_initiate_a_withdrawal_transaction') ?></p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <?= __('verify_customer_has_sufficient_balance_before_processing') ?>
                    </div>
                    <div class="screenshot-placeholder" data-description="New withdrawal button and modal"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">9</span>
                <div class="step-content">
                    <h6><?= __('select_customer_and_verify_balance') ?></h6>
                    <p><?= __('choose_the_customer_and_check_their_current_wallet_balance') ?></p>
                    <ul>
                        <li><?= __('dropdown_shows_customer_names') ?></li>
                        <li><?= __('displays_current_wallet_balances') ?></li>
                        <li><?= __('system_prevents_withdrawals_exceeding_available_balance') ?></li>
                    </ul>
                    <div class="screenshot-placeholder" data-description="Customer selection with balance verification"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">10</span>
                <div class="step-content">
                    <h6><?= __('select_main_account') ?></h6>
                    <p><?= __('choose_the_main_account_for_the_withdrawal_transaction') ?></p>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle mr-2"></i>
                        <?= __('the_selected_account_will_be_credited_with_the_withdrawal_amount') ?>
                    </div>
                    <div class="screenshot-placeholder" data-description="Main account selection for withdrawal"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">11</span>
                <div class="step-content">
                    <h6><?= __('enter_withdrawal_details') ?></h6>
                    <p><?= __('fill_in_the_withdrawal_transaction_details') ?>:</p>
                    <ul>
                        <li><?= __('amount_must_not_exceed_wallet_balance') ?></li>
                        <li><?= __('currency_usd_afs_eur_darham') ?></li>
                        <li><?= __('reference_number') ?></li>
                        <li><?= __('optional_notes') ?></li>
                    </ul>
                    <div class="screenshot-placeholder" data-description="Withdrawal details input fields"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">12</span>
                <div class="step-content">
                    <h6><?= __('upload_receipt_optional') ?></h6>
                    <p><?= __('upload_a_receipt_or_supporting_document_for_the_withdrawal') ?></p>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle mr-2"></i>
                        <?= __('helps_maintain_accurate_financial_records') ?>
                    </div>
                    <div class="screenshot-placeholder" data-description="Withdrawal receipt upload"></div>
                </div>
            </div>

            <div class="step-item">
                <span class="step-number">13</span>
                <div class="step-content">
                    <h6><?= __('process_withdrawal') ?></h6>
                    <p><?= __('click_save_to_complete_the_withdrawal_transaction') ?></p>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle mr-2"></i>
                        <?= __('the_system_will') ?>:
                        <ul>
                            <li><?= __('deduct_amount_from_customer_wallet') ?></li>
                            <li><?= __('record_the_transaction') ?></li>
                            <li><?= __('update_main_account_balance') ?></li>
                            <li><?= __('generate_a_transaction_record') ?></li>
                        </ul>
                    </div>
                    <div class="screenshot-placeholder" data-description="Withdrawal confirmation and success message"></div>
                </div>
            </div>

            <div class="alert alert-warning mt-4">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <strong><?= __('important_considerations') ?>:</strong>
                <ul>
                    <li><?= __('always_verify_customer_identity_before_processing_transactions') ?></li>
                    <li><?= __('ensure_sufficient_balance_for_withdrawals') ?></li>
                    <li><?= __('maintain_accurate_documentation') ?></li>
                    <li><?= __('follow_company_financial_policies') ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>