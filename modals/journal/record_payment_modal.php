<?php
/**
 * Payments Journal — Action Hub (Record Payment) modal.
 * Include from admin pages: <?php include '../modals/journal/record_payment_modal.php'; ?>
 *
 * Two-step flow:
 *   Step 1 (hub): pick the payment category.
 *   Step 2:        search & select the specific entity (ticket/visa/umrah/hotel/
 *                  additional payment/client/supplier) — selecting it opens the
 *                  module's OWN transaction modal in place on the journal page
 *                  (see api/journal/modal_render.php).
 *
 * Requires: language helpers loaded (for __()).
 */
?>
<!-- ═══════════════════════════════════════════════════════
     RECORD PAYMENT (ACTION HUB) MODAL
     ════════════════════════════════════════════════════════ -->
<div class="pjl-backdrop" id="pjlRecordModal" style="display:none" onclick="pjlBackdropClick(event,'pjlRecordModal')">
  <div class="pjl-modal pjl-modal-lg">
    <div class="pjl-modal-head">
      <div id="pjlHubHead">
        <h2><?php echo __('record_payment'); ?></h2>
        <p><?php echo __('choose_where_to_post_the_payment'); ?></p>
      </div>
      <div id="pjlPickerHead" style="display:none">
        <h2><button type="button" class="pjl-btn-icon" id="pjlPickerBack" title="<?php echo __('back'); ?>">
          <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M11 7H3M6.5 3.5L3 7l3.5 3.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button> <span id="pjlPickerTitle"></span></h2>
        <p id="pjlPickerSubtitle"></p>
      </div>
      <button type="button" class="pjl-btn-icon" onclick="pjlCloseModal('pjlRecordModal')">
        <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M11 3L3 11M3 3l8 8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
      </button>
    </div>
    <div class="pjl-modal-body">

      <!-- ── STEP 1: hub ────────────────────────────────── -->
      <div id="pjlHubView">

        <div class="pjl-section-label"><?php echo __('ticket'); ?></div>
        <div class="pjl-hub-grid">
          <button type="button" class="pjl-hub-card pjl-hub-btn" data-module="ticket">
            <span class="pjl-hub-icon ticket"><svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M2 5l4-1.5L14.5 8 11 12l-2-1-1.5 2-2-1L3 14l-1.5-5L3 7.5z" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/></svg></span>
            <span>
              <strong><?php echo __('ticket_payment'); ?></strong>
              <small><?php echo __('ticket_payment_desc'); ?></small>
            </span>
          </button>
          <button type="button" class="pjl-hub-card pjl-hub-btn" data-module="ticket_date_change">
            <span class="pjl-hub-icon additional"><svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M3 5h10M3 8h10M3 11h6" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg></span>
            <span>
              <strong><?php echo __('date_change'); ?></strong>
              <small><?php echo __('date_change_desc'); ?></small>
            </span>
          </button>
          <button type="button" class="pjl-hub-card pjl-hub-btn" data-module="ticket_refund">
            <span class="pjl-hub-icon refund"><svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M4.5 4.5a5 5 0 1 0 1.2 5.2M4.5 2.5v3h3" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
            <span>
              <strong><?php echo __('ticket_refund'); ?></strong>
              <small><?php echo __('ticket_refund_desc'); ?></small>
            </span>
          </button>
          <button type="button" class="pjl-hub-card pjl-hub-btn" data-module="ticket_weight">
            <span class="pjl-hub-icon weight"><svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="5" r="2.5" stroke="currentColor" stroke-width="1.2"/><path d="M5 14l1.2-4h3.6L11 14M3.5 11h9" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
            <span>
              <strong><?php echo __('ticket_weight'); ?></strong>
              <small><?php echo __('ticket_weight_desc'); ?></small>
            </span>
          </button>
          <button type="button" class="pjl-hub-card pjl-hub-btn" data-module="ticket_reserve">
            <span class="pjl-hub-icon reserve"><svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M3 12.5V5l5-2.5L13 5v7.5M3 12.5l5 2 5-2M8 2.5v10" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/></svg></span>
            <span>
              <strong><?php echo __('ticket_reserve'); ?></strong>
              <small><?php echo __('ticket_reserve_desc'); ?></small>
            </span>
          </button>
        </div>

        <div class="pjl-section-label" style="margin-top:22px"><?php echo __('visa'); ?></div>
        <div class="pjl-hub-grid">
          <button type="button" class="pjl-hub-card pjl-hub-btn" data-module="visa">
            <span class="pjl-hub-icon visa"><svg width="16" height="16" viewBox="0 0 16 16" fill="none"><rect x="2" y="3.5" width="12" height="9" rx="1.5" stroke="currentColor" stroke-width="1.2"/><path d="M2 6.5h12M6 3.5v9" stroke="currentColor" stroke-width="1.2"/></svg></span>
            <span>
              <strong><?php echo __('visa_payment'); ?></strong>
              <small><?php echo __('visa_payment_desc'); ?></small>
            </span>
          </button>
          <button type="button" class="pjl-hub-card pjl-hub-btn" data-module="visa_refund">
            <span class="pjl-hub-icon refund"><svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M4.5 4.5a5 5 0 1 0 1.2 5.2M4.5 2.5v3h3" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
            <span>
              <strong><?php echo __('visa_refund'); ?></strong>
              <small><?php echo __('visa_refund_desc'); ?></small>
            </span>
          </button>
        </div>

        <div class="pjl-section-label" style="margin-top:22px"><?php echo __('umrah'); ?></div>
        <div class="pjl-hub-grid">
          <button type="button" class="pjl-hub-card pjl-hub-btn" data-module="umrah">
            <span class="pjl-hub-icon umrah"><svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M8 2.5s3.5 3 3.5 6a3.5 3.5 0 0 1-7 0c0-3 3.5-6 3.5-6z" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/></svg></span>
            <span>
              <strong><?php echo __('umrah_payment'); ?></strong>
              <small><?php echo __('umrah_payment_desc'); ?></small>
            </span>
          </button>
          <button type="button" class="pjl-hub-card pjl-hub-btn" data-module="umrah_refund">
            <span class="pjl-hub-icon refund"><svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M4.5 4.5a5 5 0 1 0 1.2 5.2M4.5 2.5v3h3" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
            <span>
              <strong><?php echo __('umrah_refund'); ?></strong>
              <small><?php echo __('umrah_refund_desc'); ?></small>
            </span>
          </button>
        </div>

        <div class="pjl-section-label" style="margin-top:22px"><?php echo __('hotel'); ?></div>
        <div class="pjl-hub-grid">
          <button type="button" class="pjl-hub-card pjl-hub-btn" data-module="hotel">
            <span class="pjl-hub-icon hotel"><svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M2.5 13.5v-9M2.5 5h11v8.5M5 13.5v-3h6v3M4 7.5h2M4 9.5h2M10 7.5h2M10 9.5h2" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg></span>
            <span>
              <strong><?php echo __('hotel_payment'); ?></strong>
              <small><?php echo __('hotel_payment_desc'); ?></small>
            </span>
          </button>
          <button type="button" class="pjl-hub-card pjl-hub-btn" data-module="hotel_refund">
            <span class="pjl-hub-icon refund"><svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M4.5 4.5a5 5 0 1 0 1.2 5.2M4.5 2.5v3h3" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
            <span>
              <strong><?php echo __('hotel_refund'); ?></strong>
              <small><?php echo __('hotel_refund_desc'); ?></small>
            </span>
          </button>
        </div>

        <div class="pjl-section-label" style="margin-top:22px"><?php echo __('additional_payment'); ?></div>
        <div class="pjl-hub-grid">
          <button type="button" class="pjl-hub-card pjl-hub-btn" data-module="additional_payment">
            <span class="pjl-hub-icon additional"><svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="5.5" stroke="currentColor" stroke-width="1.2"/><path d="M8 5.5v5M5.5 8h5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg></span>
            <span>
              <strong><?php echo __('additional_payment'); ?></strong>
              <small><?php echo __('additional_payment_desc'); ?></small>
            </span>
          </button>
          <button type="button" class="pjl-hub-card pjl-hub-btn" data-module="jv_payment">
            <span class="pjl-hub-icon jv"><svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M4 12L12 4M8 3l5 5M4 8l4 4" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg></span>
            <span>
              <strong><?php echo __('jv_payment'); ?></strong>
              <small><?php echo __('jv_payment_desc'); ?></small>
            </span>
          </button>
        </div>

        <div class="pjl-section-label" style="margin-top:22px"><?php echo __('account_operations'); ?></div>
        <div class="pjl-hub-grid">
          <button type="button" class="pjl-hub-card pjl-hub-btn" data-module="fund_client">
            <span class="pjl-hub-icon fund"><svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M8 2v12M3.5 5.5h9M5 5.5c0-1 1.3-2 3-2s3 1 3 2c0 1.3-2 2-2.8 2.3L7.8 8.5c-.8.3-3 1-3 2.6 0 1.1 1.3 2 3 2s3-.9 3-2" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg></span>
            <span>
              <strong><?php echo __('fund_client'); ?></strong>
              <small><?php echo __('fund_client_desc'); ?></small>
            </span>
          </button>
          <button type="button" class="pjl-hub-card pjl-hub-btn" data-module="fund_supplier">
            <span class="pjl-hub-icon fund"><svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M8 2v12M3.5 5.5h9M5 5.5c0-1 1.3-2 3-2s3 1 3 2c0 1.3-2 2-2.8 2.3L7.8 8.5c-.8.3-3 1-3 2.6 0 1.1 1.3 2 3 2s3-.9 3-2" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg></span>
            <span>
              <strong><?php echo __('fund_supplier'); ?></strong>
              <small><?php echo __('fund_supplier_desc'); ?></small>
            </span>
          </button>
          <button type="button" class="pjl-hub-card pjl-hub-btn" data-module="withdraw_main">
            <span class="pjl-hub-icon transfer"><svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M13 4H4M6 2L3.5 4 6 6M3 12h9M10 10l2.5 2L10 14" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
            <span>
              <strong><?php echo __('withdraw_main'); ?></strong>
              <small><?php echo __('withdraw_main_desc'); ?></small>
            </span>
          </button>
          <button type="button" class="pjl-hub-card pjl-hub-btn" data-module="withdraw_supplier">
            <span class="pjl-hub-icon transfer"><svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M13 4H4M6 2L3.5 4 6 6M3 12h9M10 10l2.5 2L10 14" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
            <span>
              <strong><?php echo __('withdraw_supplier'); ?></strong>
              <small><?php echo __('withdraw_supplier_desc'); ?></small>
            </span>
          </button>
          <button type="button" class="pjl-hub-card pjl-hub-btn" data-module="withdraw_client">
            <span class="pjl-hub-icon transfer"><svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M13 4H4M6 2L3.5 4 6 6M3 12h9M10 10l2.5 2L10 14" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
            <span>
              <strong><?php echo __('withdraw_client'); ?></strong>
              <small><?php echo __('withdraw_client_desc'); ?></small>
            </span>
          </button>
          <button type="button" class="pjl-hub-card pjl-hub-btn" data-module="transfer">
            <span class="pjl-hub-icon transfer"><svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M4 12L12 4M8 3l5 5M4 8l4 4" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg></span>
            <span>
              <strong><?php echo __('account_transfer'); ?></strong>
              <small><?php echo __('account_transfer_desc'); ?></small>
            </span>
          </button>
          <button type="button" class="pjl-hub-card pjl-hub-btn" data-module="expense">
            <span class="pjl-hub-icon expense"><svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M3 4.5h10v7H3z" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/><path d="M5.5 8h5M8 6v4" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg></span>
            <span>
              <strong><?php echo __('expense'); ?></strong>
              <small><?php echo __('expense_desc'); ?></small>
            </span>
          </button>
          <button type="button" class="pjl-hub-card pjl-hub-btn" data-module="salary_regular">
            <span class="pjl-hub-icon salary"><svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="5" r="2.5" stroke="currentColor" stroke-width="1.2"/><path d="M3 13.5c0-2.5 2.2-4 5-4s5 1.5 5 4" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg></span>
            <span>
              <strong><?php echo __('salary_regular'); ?></strong>
              <small><?php echo __('salary_regular_desc'); ?></small>
            </span>
          </button>
          <button type="button" class="pjl-hub-card pjl-hub-btn" data-module="salary_advance">
            <span class="pjl-hub-icon salary"><svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M3 5.5h10M5 3.5l3 2 3-2M3 10.5h10M5 12.5l3-2 3 2" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
            <span>
              <strong><?php echo __('salary_advance'); ?></strong>
              <small><?php echo __('salary_advance_desc'); ?></small>
            </span>
          </button>
          <button type="button" class="pjl-hub-card pjl-hub-btn" data-module="salary_bonus">
            <span class="pjl-hub-icon salary"><svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M8 2.5l1.6 3.2 3.5.5-2.5 2.5.6 3.5L8 10.8l-3.2 1.7.6-3.5L2.9 6.2l3.5-.5L8 2.5z" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/></svg></span>
            <span>
              <strong><?php echo __('salary_bonus'); ?></strong>
              <small><?php echo __('salary_bonus_desc'); ?></small>
            </span>
          </button>
          <button type="button" class="pjl-hub-card pjl-hub-btn" data-module="salary_deduction">
            <span class="pjl-hub-icon salary"><svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="5.5" stroke="currentColor" stroke-width="1.2"/><path d="M5.2 8h5.6" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg></span>
            <span>
              <strong><?php echo __('salary_deduction'); ?></strong>
              <small><?php echo __('salary_deduction_desc'); ?></small>
            </span>
          </button>
        </div>

        <div class="pjl-section-label" style="margin-top:22px"><?php echo __('sarafi_operations'); ?></div>
        <div class="pjl-hub-grid">
          <button type="button" class="pjl-hub-card pjl-hub-btn" data-module="sarafi_deposit">
            <span class="pjl-hub-icon sarafi"><svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M8 2v12M3.5 5.5h9M5 5.5c0-1 1.3-2 3-2s3 1 3 2c0 1.3-2 2-2.8 2.3L7.8 8.5c-.8.3-3 1-3 2.6 0 1.1 1.3 2 3 2s3-.9 3-2" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg></span>
            <span>
              <strong><?php echo __('sarafi_deposit'); ?></strong>
              <small><?php echo __('sarafi_deposit_desc'); ?></small>
            </span>
          </button>
          <button type="button" class="pjl-hub-card pjl-hub-btn" data-module="sarafi_withdraw">
            <span class="pjl-hub-icon sarafi"><svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M13 4H4M6 2L3.5 4 6 6M3 12h9M10 10l2.5 2L10 14" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
            <span>
              <strong><?php echo __('sarafi_withdrawal'); ?></strong>
              <small><?php echo __('sarafi_withdrawal_desc'); ?></small>
            </span>
          </button>
          <button type="button" class="pjl-hub-card pjl-hub-btn" data-module="sarafi_hawala">
            <span class="pjl-hub-icon sarafi"><svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M3 12.5V5l5-2.5L13 5v7.5M3 12.5l5 2 5-2M8 2.5v10" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/></svg></span>
            <span>
              <strong><?php echo __('sarafi_hawala'); ?></strong>
              <small><?php echo __('sarafi_hawala_desc'); ?></small>
            </span>
          </button>
          <button type="button" class="pjl-hub-card pjl-hub-btn" data-module="sarafi_exchange">
            <span class="pjl-hub-icon sarafi"><svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M4 12L12 4M8 3l5 5M4 8l4 4" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg></span>
            <span>
              <strong><?php echo __('sarafi_exchange'); ?></strong>
              <small><?php echo __('sarafi_exchange_desc'); ?></small>
            </span>
          </button>
        </div>

      </div>

      <!-- ── STEP 2: entity picker ───────────────────────── -->
      <div id="pjlPickerView" style="display:none">
        <div class="pjl-search-wrap pjl-picker-search">
          <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><circle cx="7" cy="7" r="5" stroke="currentColor" stroke-width="1.5"/><path d="M11 11l3 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
          <input type="text" id="pjlPickerQ" placeholder="<?php echo __('search_picker_placeholder'); ?>…" autocomplete="off">
        </div>
        <div class="pjl-picker-list" id="pjlPickerList"></div>
      </div>

    </div>
    <div class="pjl-modal-foot" id="pjlHubFoot">
      <button type="button" class="pjl-btn pjl-btn-ghost" onclick="pjlCloseModal('pjlRecordModal')"><?php echo __('close'); ?></button>
    </div>
    <div class="pjl-modal-foot" id="pjlPickerFoot" style="display:none">
      <button type="button" class="pjl-btn pjl-btn-ghost" id="pjlPickerCancel"><?php echo __('back'); ?></button>
    </div>
  </div>
</div>
