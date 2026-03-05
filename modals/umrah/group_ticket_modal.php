<!-- Group Ticket Generation Modal -->
<div class="modal fade" id="groupTicketModal" tabindex="-1" role="dialog" aria-labelledby="groupTicketModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="groupTicketModalLabel">
                    <i class="feather icon-airplay mr-2"></i><?= __('generate_group_ticket') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="feather icon-info mr-2"></i><?= __('select_members_and_enter_flight_details') ?>
                </div>

                <!-- Selected Members -->
                <div class="selected-members mb-3">
                    <h6><?= __('selected_members') ?>: <span id="selectedGroupCount">0</span></h6>
                    <div id="selectedGroupMembersList" class="row">
                        <!-- Members will appear here -->
                    </div>
                </div>

                <!-- Flight Date & Return Date (Required) -->
                <div class="card mb-3 border-warning">
                    <div class="card-header bg-warning text-dark">
                        <h6 class="mb-0"><i class="fas fa-calendar-alt mr-2"></i>Flight Dates (Required)</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="form-group col-md-6">
                                <label for="groupFlightDate"><?= __('flight_date') ?> *</label>
                                <input type="date" class="form-control" id="groupFlightDate" name="group_flight_date" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="groupReturnDate"><?= __('return_date') ?> *</label>
                                <input type="date" class="form-control" id="groupReturnDate" name="group_return_date" required>
                            </div>
                        </div>
                        <small class="form-text text-muted">These dates will be applied to all selected members</small>
                    </div>
                </div>

                <form id="groupTicketForm">
                    <!-- CSRF Protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                    <input type="hidden" name="selected_members" id="selectedGroupMembersInput">
                    <input type="hidden" name="flight_date" id="groupFlightDateInput">
                    <input type="hidden" name="return_date" id="groupReturnDateInput">

                    <!-- Airline & PNR -->
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label for="airlineName"><?= __('airline_name') ?></label>
                            <input type="text" class="form-control" id="airlineName" name="airline_name" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="pnr"><?= __('pnr_number') ?></label>
                            <input type="text" class="form-control" id="pnr" name="pnr" required>
                        </div>
                    </div>

                    <!-- Flight Type Selection -->
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label class="form-label"><?= __('flight_type') ?></label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="flight_type" id="directFlight" value="direct" checked>
                                <label class="form-check-label" for="directFlight">
                                    <i class="feather icon-arrow-right mr-1"></i><?= __('direct_flight') ?>
                                </label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="flight_type" id="indirectFlight" value="indirect">
                                <label class="form-check-label" for="indirectFlight">
                                    <i class="feather icon-shuffle mr-1"></i><?= __('connecting_flight') ?>
                                </label>
                            </div>
                        </div>
                    </div>

                    <hr>
                    <h6 class="text-primary mb-3"><i class="feather icon-calendar mr-2"></i><?= __('outbound_journey') ?></h6>

                    <!-- Direct Flight Fields (Default) -->
                    <div id="directFlightFields">
                        <!-- Flight Routes -->
                        <div class="row">
                            <div class="form-group col-md-6">
                                <label for="departureCity"><?= __('departure_city') ?></label>
                                <input type="text" class="form-control" id="departureCity" name="departure_city" value="Kabul" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="arrivalCity"><?= __('arrival_city') ?></label>
                                <input type="text" class="form-control" id="arrivalCity" name="arrival_city" value="Jeddah" required>
                            </div>
                        </div>

                        <!-- Flight Numbers -->
                        <div class="row">
                            <div class="form-group col-md-6">
                                <label for="flightNumber1"><?= __('outbound_flight_number') ?></label>
                                <input type="text" class="form-control" id="flightNumber1" name="flight_number_1" value="RQ993" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="flightNumber2"><?= __('return_flight_number') ?></label>
                                <input type="text" class="form-control" id="flightNumber2" name="flight_number_2" value="RQ994" required>
                            </div>
                        </div>
                    </div>

                    <!-- Indirect/Connecting Flight Fields (Hidden by default) -->
                    <div id="indirectFlightFields" style="display: none;">
                        <!-- First Leg -->
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="feather icon-arrow-right mr-2"></i><?= __('first_leg') ?></h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="form-group col-md-4">
                                        <label for="leg1DepartureCity"><?= __('departure_city') ?></label>
                                        <input type="text" class="form-control" id="leg1DepartureCity" name="leg1_departure_city" value="Kabul">
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="leg1ArrivalCity"><?= __('stopover_city') ?></label>
                                        <input type="text" class="form-control" id="leg1ArrivalCity" name="leg1_arrival_city" value="Dubai">
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="leg1FlightNumber"><?= __('flight_number') ?></label>
                                        <input type="text" class="form-control" id="leg1FlightNumber" name="leg1_flight_number" value="FZ341">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="form-group col-md-3">
                                        <label for="leg1DepartureDate"><?= __('departure_date') ?></label>
                                        <input type="date" class="form-control" id="leg1DepartureDate" name="leg1_departure_date">
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="leg1DepartureTime"><?= __('departure_time') ?></label>
                                        <input type="time" class="form-control" id="leg1DepartureTime" name="leg1_departure_time">
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="leg1ArrivalDate"><?= __('arrival_date') ?></label>
                                        <input type="date" class="form-control" id="leg1ArrivalDate" name="leg1_arrival_date">
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="leg1ArrivalTime"><?= __('arrival_time') ?></label>
                                        <input type="time" class="form-control" id="leg1ArrivalTime" name="leg1_arrival_time">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Stopover Duration -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="alert alert-warning">
                                    <i class="feather icon-clock mr-2"></i>
                                    <strong><?= __('stopover_duration') ?>:</strong> 
                                    <span id="stopoverDuration"><?= __('calculating') ?>...</span>
                                </div>
                            </div>
                        </div>

                        <!-- Second Leg -->
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="feather icon-arrow-right mr-2"></i><?= __('second_leg') ?></h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="form-group col-md-4">
                                        <label for="leg2DepartureCity"><?= __('departure_city') ?></label>
                                        <input type="text" class="form-control" id="leg2DepartureCity" name="leg2_departure_city" value="Dubai">
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="leg2ArrivalCity"><?= __('final_destination') ?></label>
                                        <input type="text" class="form-control" id="leg2ArrivalCity" name="leg2_arrival_city" value="Jeddah">
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="leg2FlightNumber"><?= __('flight_number') ?></label>
                                        <input type="text" class="form-control" id="leg2FlightNumber" name="leg2_flight_number" value="FZ415">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="form-group col-md-3">
                                        <label for="leg2DepartureDate"><?= __('departure_date') ?></label>
                                        <input type="date" class="form-control" id="leg2DepartureDate" name="leg2_departure_date">
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="leg2DepartureTime"><?= __('departure_time') ?></label>
                                        <input type="time" class="form-control" id="leg2DepartureTime" name="leg2_departure_time">
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="leg2ArrivalDate"><?= __('arrival_date') ?></label>
                                        <input type="date" class="form-control" id="leg2ArrivalDate" name="leg2_arrival_date">
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="leg2ArrivalTime"><?= __('arrival_time') ?></label>
                                        <input type="time" class="form-control" id="leg2ArrivalTime" name="leg2_arrival_time">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Return Journey -->
                        <hr>
                        <h6 class="text-success mb-3"><i class="feather icon-corner-up-left mr-2"></i><?= __('return_journey') ?></h6>
                        
                        <!-- Return First Leg -->
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="feather icon-arrow-left mr-2"></i><?= __('return_first_leg') ?></h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="form-group col-md-4">
                                        <label for="returnLeg1DepartureCity"><?= __('departure_city') ?></label>
                                        <input type="text" class="form-control" id="returnLeg1DepartureCity" name="return_leg1_departure_city" value="Jeddah">
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="returnLeg1ArrivalCity"><?= __('stopover_city') ?></label>
                                        <input type="text" class="form-control" id="returnLeg1ArrivalCity" name="return_leg1_arrival_city" value="Dubai">
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="returnLeg1FlightNumber"><?= __('flight_number') ?></label>
                                        <input type="text" class="form-control" id="returnLeg1FlightNumber" name="return_leg1_flight_number" value="FZ416">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="form-group col-md-3">
                                        <label for="returnLeg1DepartureDate"><?= __('departure_date') ?></label>
                                        <input type="date" class="form-control" id="returnLeg1DepartureDate" name="return_leg1_departure_date">
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="returnLeg1DepartureTime"><?= __('departure_time') ?></label>
                                        <input type="time" class="form-control" id="returnLeg1DepartureTime" name="return_leg1_departure_time">
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="returnLeg1ArrivalDate"><?= __('arrival_date') ?></label>
                                        <input type="date" class="form-control" id="returnLeg1ArrivalDate" name="return_leg1_arrival_date">
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="returnLeg1ArrivalTime"><?= __('arrival_time') ?></label>
                                        <input type="time" class="form-control" id="returnLeg1ArrivalTime" name="return_leg1_arrival_time">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Return Second Leg -->
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="feather icon-arrow-left mr-2"></i><?= __('return_second_leg') ?></h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="form-group col-md-4">
                                        <label for="returnLeg2DepartureCity"><?= __('departure_city') ?></label>
                                        <input type="text" class="form-control" id="returnLeg2DepartureCity" name="return_leg2_departure_city" value="Dubai">
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="returnLeg2ArrivalCity"><?= __('final_destination') ?></label>
                                        <input type="text" class="form-control" id="returnLeg2ArrivalCity" name="return_leg2_arrival_city" value="Kabul">
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="returnLeg2FlightNumber"><?= __('flight_number') ?></label>
                                        <input type="text" class="form-control" id="returnLeg2FlightNumber" name="return_leg2_flight_number" value="FZ342">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="form-group col-md-3">
                                        <label for="returnLeg2DepartureDate"><?= __('departure_date') ?></label>
                                        <input type="date" class="form-control" id="returnLeg2DepartureDate" name="return_leg2_departure_date">
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="returnLeg2DepartureTime"><?= __('departure_time') ?></label>
                                        <input type="time" class="form-control" id="returnLeg2DepartureTime" name="return_leg2_departure_time">
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="returnLeg2ArrivalDate"><?= __('arrival_date') ?></label>
                                        <input type="date" class="form-control" id="returnLeg2ArrivalDate" name="return_leg2_arrival_date">
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="returnLeg2ArrivalTime"><?= __('arrival_time') ?></label>
                                        <input type="time" class="form-control" id="returnLeg2ArrivalTime" name="return_leg2_arrival_time">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Direct Flight Dates (Hidden when indirect is selected) -->
                    <div id="directFlightDates">
                        <!-- Departure -->
                        <div class="row">
                            <div class="form-group col-md-6">
                                <label for="departureDate"><?= __('departure_date') ?></label>
                                <input type="date" class="form-control" id="departureDate" name="departure_date" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="departureTime"><?= __('departure_time') ?></label>
                                <input type="time" class="form-control" id="departureTime" name="departure_time" required>
                            </div>
                        </div>

                        <!-- Arrival -->
                        <div class="row">
                            <div class="form-group col-md-6">
                                <label for="arrivalDate"><?= __('arrival_date') ?></label>
                                <input type="date" class="form-control" id="arrivalDate" name="arrival_date" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="arrivalTime"><?= __('arrival_time') ?></label>
                                <input type="time" class="form-control" id="arrivalTime" name="arrival_time" required>
                            </div>
                        </div>

                        <hr>
                        <h6 class="text-primary mb-3"><i class="feather icon-corner-up-left mr-2"></i><?= __('return_flight_details') ?></h6>

                        <!-- Return Departure -->
                        <div class="row">
                            <div class="form-group col-md-6">
                                <label for="returnDate"><?= __('return_departure_date') ?></label>
                                <input type="date" class="form-control" id="returnDate" name="return_date" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="returnTime"><?= __('return_departure_time') ?></label>
                                <input type="time" class="form-control" id="returnTime" name="return_time" required>
                            </div>
                        </div>

                        <!-- Return Arrival -->
                        <div class="row">
                            <div class="form-group col-md-6">
                                <label for="retArrivalDate"><?= __('return_arrival_date') ?></label>
                                <input type="date" class="form-control" id="retArrivalDate" name="ret_arrival_date" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="retArrivalTime"><?= __('return_arrival_time') ?></label>
                                <input type="time" class="form-control" id="retArrivalTime" name="return_arrival_time" required>
                            </div>
                        </div>
                    </div>

                    <!-- Remarks -->
                    <div class="form-group">
                        <label for="remarks"><?= __('remarks') ?></label>
                        <textarea class="form-control" id="remarks" name="remarks" rows="3" placeholder="<?= __('additional_notes_or_special_instructions') ?>"></textarea>
                    </div>
                </form>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="feather icon-x mr-2"></i><?= __('cancel') ?>
                </button>
                <button type="button" class="btn btn-primary" id="generateGroupTicketBtn" disabled>
                    <i class="fas fa-save mr-2"></i>Save & Generate Group Ticket
                </button>
            </div>
        </div>
    </div>
</div>

