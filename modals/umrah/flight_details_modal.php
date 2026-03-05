<!-- Family Flight Details Modal -->
<div class="modal fade" id="flightDetailsModal" tabindex="-1" role="dialog" aria-labelledby="flightDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%); color: white; border: none;">
                <h5 class="modal-title" id="flightDetailsModalLabel" style="font-size: 18px; font-weight: 600;">
                    <i class="fas fa-plane mr-2"></i>Flight Details
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="padding: 30px 20px; background: #f8f9fa;">
                <!-- Family Info Banner -->
                <div style="
                    background: linear-gradient(135deg, #f0f7ff 0%, #e6f2ff 100%);
                    border-radius: 8px;
                    padding: 16px;
                    margin-bottom: 24px;
                    border-left: 4px solid #4099ff;
                ">
                    <div style="color: #333; font-size: 16px; font-weight: 600;" id="flightFamilyName">Family Name</div>
                    <div style="color: #666; font-size: 13px; margin-top: 4px;">Group Ticket Information</div>
                </div>

                <!-- Tickets Container -->
                <div id="flightTicketsContainer">
                    <!-- Tickets will be populated here dynamically -->
                </div>

                <!-- Loading State -->
                <div id="flightDetailsLoading" style="text-align: center; padding: 40px 20px; display: none;">
                    <div style="font-size: 48px; margin-bottom: 16px;">
                        <i class="fas fa-spinner fa-spin" style="color: #667eea;"></i>
                    </div>
                    <div style="color: #666; font-weight: 500;">Loading flight details...</div>
                </div>

                <!-- Error State -->
                <div id="flightDetailsError" style="display: none;">
                    <div style="
                        background: #fee;
                        border: 1px solid #fcc;
                        border-radius: 8px;
                        padding: 16px;
                        color: #c33;
                        text-align: center;
                    ">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <span id="flightDetailsErrorMessage">An error occurred while loading flight details.</span>
                    </div>
                </div>

                <!-- Empty State -->
                <div id="flightDetailsEmpty" style="display: none;">
                    <div style="
                        background: #f0f7ff;
                        border: 2px dashed #667eea;
                        border-radius: 8px;
                        padding: 40px 20px;
                        text-align: center;
                        color: #667eea;
                    ">
                        <div style="font-size: 48px; margin-bottom: 16px;">✈️</div>
                        <div style="font-size: 16px; font-weight: 600; margin-bottom: 8px;">No Flight Details Available</div>
                        <div style="font-size: 14px; opacity: 0.8;">
                            No group flight tickets have been created for this family yet.
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="background: #f8f9fa; border-top: 1px solid #e9ecef;">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="feather icon-x mr-2"></i>Close
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    .flight-ticket {
        background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
        border-radius: 12px;
        color: white;
        margin-bottom: 20px;
        overflow: hidden;
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
    }

    .flight-ticket-header {
        background: rgba(0, 0, 0, 0.1);
        padding: 16px 20px;
        border-bottom: 2px dashed rgba(255, 255, 255, 0.3);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .flight-ticket-header-label {
        font-size: 12px;
        opacity: 0.8;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .flight-ticket-header-value {
        font-size: 18px;
        font-weight: bold;
        margin-top: 4px;
    }

    .flight-ticket-pnr {
        font-size: 16px;
        font-weight: bold;
        font-family: 'Courier New', monospace;
        margin-top: 4px;
    }

    .flight-ticket-body {
        padding: 24px 20px;
    }

    .flight-journey {
        margin-bottom: 24px;
        display: grid;
        grid-template-columns: 1fr auto 1fr;
        gap: 20px;
        align-items: center;
    }

    .flight-journey-point {
        text-align: left;
    }

    .flight-journey-point.return {
        text-align: right;
    }

    .flight-journey-label {
        font-size: 11px;
        opacity: 0.8;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 8px;
    }

    .flight-journey-date {
        font-size: 20px;
        font-weight: bold;
    }

    .flight-journey-time {
        font-size: 14px;
        opacity: 0.9;
        margin-top: 4px;
    }

    .flight-journey-icon {
        text-align: center;
    }

    .flight-journey-plane {
        font-size: 28px;
        opacity: 0.9;
    }

    .flight-journey-type {
        font-size: 10px;
        opacity: 0.7;
        margin-top: 4px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .flight-details-section {
        background: rgba(0, 0, 0, 0.1);
        border-radius: 8px;
        padding: 16px;
        margin-bottom: 16px;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }

    .flight-detail-item-label {
        font-size: 11px;
        opacity: 0.8;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 4px;
    }

    .flight-detail-item-value {
        font-size: 14px;
        font-weight: 600;
    }

    .flight-ticket-footer {
        background: rgba(0, 0, 0, 0.1);
        padding: 12px 20px;
        border-top: 1px dashed rgba(255, 255, 255, 0.3);
        text-align: center;
        font-size: 11px;
        opacity: 0.7;
    }

    .flight-passenger-info {
        border-top: 1px dashed rgba(255, 255, 255, 0.3);
        padding-top: 16px;
        font-size: 12px;
        opacity: 0.9;
    }

    .flight-passenger-info-item {
        margin-bottom: 8px;
    }

    .flight-status-active {
        color: #28a745;
        font-weight: 600;
    }
</style>
