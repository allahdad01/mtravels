<!-- View Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="feather icon-eye mr-2"></i><?= __('booking_details') ?>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="bookingDetails">
                    <!-- Details will be populated dynamically -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
            </div>
        </div>
    </div>
</div>

<style>
/* Scoped styling for hotel booking details modal */
#detailsModal .modal-body {
    background: #f9fafb;
}

.hotel-details-modal {
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 10px 25px rgba(15, 23, 42, 0.09);
    padding: 18px 18px 14px;
}

.hdm-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    border-bottom: 1px solid #e5e7eb;
    padding-bottom: 14px;
    margin-bottom: 14px;
}

.hdm-header-main {
    min-width: 0;
}

.hdm-guest-name {
    font-size: 1.05rem;
    font-weight: 600;
    color: #111827;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.hdm-order-pill {
    display: inline-flex;
    align-items: center;
    margin-top: 6px;
    padding: 3px 10px;
    border-radius: 999px;
    background: #eff6ff;
    color: #1d4ed8;
    font-size: 0.75rem;
    font-weight: 600;
}

.hdm-header-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    justify-content: flex-end;
}

.hdm-meta-item {
    min-width: 110px;
    padding: 6px 10px;
    border-radius: 10px;
    background: #f3f4f6;
}

.hdm-meta-label {
    display: block;
    font-size: 0.68rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #9ca3af;
    margin-bottom: 2px;
}

.hdm-meta-value {
    display: block;
    font-size: 0.8rem;
    font-weight: 600;
    color: #111827;
}

.hdm-body {
    font-size: 0.85rem;
    color: #4b5563;
}

.hdm-section {
    margin-bottom: 12px;
}

.hdm-section-title {
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #6b7280;
    margin-bottom: 6px;
}

.hdm-definition-list {
    margin: 0;
}

.hdm-definition-row {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    padding: 4px 0;
    border-bottom: 1px dashed #e5e7eb;
}

.hdm-definition-row:last-child {
    border-bottom: none;
}

.hdm-definition-row dt {
    flex: 0 0 40%;
    margin: 0;
    font-weight: 500;
    color: #6b7280;
}

.hdm-definition-row dd {
    flex: 1;
    margin: 0;
    text-align: right;
    color: #111827;
    word-break: break-word;
}

.hdm-financial-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
}

.hdm-financial-item {
    padding: 8px 10px;
    border-radius: 10px;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
}

.hdm-financial-label {
    display: block;
    font-size: 0.72rem;
    color: #6b7280;
    margin-bottom: 2px;
}

.hdm-financial-value {
    display: block;
    font-size: 0.85rem;
    font-weight: 600;
    color: #111827;
}

.hdm-financial-value.hdm-profit {
    color: #059669;
}

.hdm-text-block {
    margin-bottom: 0;
    padding: 8px 10px;
    border-radius: 10px;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    font-size: 0.84rem;
    line-height: 1.5;
}

.hdm-section-full {
    margin-top: 8px;
}

.hdm-row-spacing {
    margin-top: 4px;
}

@media (max-width: 768px) {
    .hotel-details-modal {
        padding: 14px 12px 10px;
    }

    .hdm-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .hdm-header-meta {
        width: 100%;
        justify-content: flex-start;
    }

    .hdm-definition-row {
        flex-direction: column;
        align-items: flex-start;
    }

    .hdm-definition-row dd {
        text-align: left;
    }
}
</style>