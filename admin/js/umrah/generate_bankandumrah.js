let selectedBankReciptUserId = null;
let selectedUmrahPresidencyUserId = null;

function showBankLetterModal(userId) {
    selectedBankReciptUserId = userId;
    $('#bankReciptModal').modal('show');
    // Load family members for selection
    try {
        const container = document.getElementById('bankReciptMembers');
        if (container) {
            container.innerHTML = '<div class="text-muted small">Loading members...</div>';
            fetch(`ajax/get_family_members.php?family_id=${encodeURIComponent(userId)}`)
                .then(r => r.json())
                .then(json => {
                    // Support multiple response shapes:
                    // 1) [ { booking_id, name, passport_number, ... } ]
                    // 2) { members: [ ... ] }
                    // 3) { success: true, data: { members: [ ... ] } }
                    if (!json) {
                        container.innerHTML = '<div class="text-danger small">Failed to load members</div>';
                        return;
                    }

                    if (json.success === false) {
                        container.innerHTML = `<div class="text-danger small">${json.message || 'Failed to load members'}</div>`;
                        return;
                    }

                    let members = [];
                    if (Array.isArray(json)) {
                        members = json;
                    } else if (Array.isArray(json.members)) {
                        members = json.members;
                    } else if (json.data && Array.isArray(json.data.members)) {
                        members = json.data.members;
                    }

                    if (!members.length) {
                        container.innerHTML = '<div class="text-muted small">No members found</div>';
                        return;
                    }
                    const list = document.createElement('div');
                    list.className = 'list-group';
                    members.forEach(m => {
                        const id = `bank_member_${m.booking_id}`;
                        const item = document.createElement('label');
                        item.className = 'list-group-item d-flex align-items-center';
                        item.innerHTML = `
                            <input type="checkbox" class="mr-2" name="bank_member_ids" value="${m.booking_id}" ${m.is_head ? 'checked' : ''}>
                            <span class="flex-grow-1">${m.name || 'Unknown'}${m.passport_number ? ` — ${m.passport_number}` : ''}</span>
                            ${m.is_head ? '<span class="badge badge-info ml-2">Head</span>' : ''}
                        `;
                        list.appendChild(item);
                    });
                    container.innerHTML = '';
                    container.appendChild(list);
                })
                .catch(err => {
                    console.error(err);
                    container.innerHTML = '<div class="text-danger small">Error loading members</div>';
                });
        }
    } catch (e) {
        console.error('Error initializing bank members list', e);
    }
}

function showUmrahPresidencyModal(userId) {
    selectedUmrahPresidencyUserId = userId;
    $('#umrahPresidencyModal').modal('show');
}

function generateBankRecipt(event, language) {
        event.preventDefault();
        
        if (!selectedBankReciptUserId) {
            createToast('<?= __("error_no_user_selected") ?>', 'danger');
            return;
        }

        const bankNameInput = document.getElementById('bank_name');
        if (!bankNameInput) {
            createToast('Bank name field not found.', 'danger');
            return;
        }

        const bankNameValue = bankNameInput.value.trim();
        if (!bankNameValue) {
            createToast('<?= __("error_bank_name_required") ?>', 'warning');
            return;
        }

        const bankAccountNumberInput = document.getElementById('bank_account_number');
        if (!bankAccountNumberInput) {
            createToast('Bank account number field not found.', 'danger');
            return;
        }   

        const bankAccountNumberValue = bankAccountNumberInput.value.trim();
        if (!bankAccountNumberValue) {
            createToast('<?= __("error_bank_account_number_required") ?>', 'warning');
            return;
        }

        const accountNameInput = document.getElementById('account_name');
        if (!accountNameInput) {
            createToast('Account name field not found.', 'danger');
            return;
        }

        const accountNameValue = accountNameInput.value.trim();
        if (!accountNameValue) {
            createToast('<?= __("error_account_name_required") ?>', 'warning');
            return;
        }

        const paymentInput = document.getElementById('payment');

        const paymentValue = paymentInput.value.trim();
        if (!paymentValue) {
            createToast('<?= __("error_payment_required") ?>', 'warning');
            return;
        }

        $('#bankReciptModal').modal('hide');

        let bankReciptUrl = '';
        switch(language) {
            case 'fa':
                bankReciptUrl = 'generate_bank_recipt.php';
                break;
            case 'ps':
                bankReciptUrl = 'generate_bank_recipt_pashto.php';
                break;
            default:
                createToast('<?= __("error_invalid_language") ?>', 'danger');
                return;
        }   

        // Collect selected member IDs
        const selected = Array.from(document.querySelectorAll('#bankReciptMembers input[name="bank_member_ids"]:checked')).map(i => i.value);
        const memberIdsParam = selected.length ? `&member_ids=${encodeURIComponent(selected.join(','))}` : '';

        const finalUrl = `${bankReciptUrl}?family_id=${selectedBankReciptUserId}&language=${language}&bank_name=${encodeURIComponent(bankNameValue)}&bank_account_number=${encodeURIComponent(bankAccountNumberValue)}&account_name=${encodeURIComponent(accountNameValue)}&payment=${encodeURIComponent(paymentValue)}${memberIdsParam}`;
        console.log('Opening:', finalUrl);
        window.open(finalUrl, '_blank');
    }

    function generateUmrah(event, language) {
        event.preventDefault();
        
        if (!selectedUmrahPresidencyUserId) {
            createToast('<?= __("error_no_user_selected") ?>', 'danger');
            return;
        }


        const familyHeadFatherNameInput = document.getElementById('family_head_father_name');
        const familyHeadFatherNameValue = familyHeadFatherNameInput.value.trim();

        const familyHeadIdNumberInput = document.getElementById('family_head_id_number');
        const familyHeadIdNumberValue = familyHeadIdNumberInput.value.trim();

        const umrahVisaAmountInput = document.getElementById('umrah_visa_amount');
        const umrahVisaAmountValue = umrahVisaAmountInput.value.trim();

        const ticketAmountInput = document.getElementById('ticket_amount');
        const ticketAmountValue = ticketAmountInput.value.trim();

        const airlineNameInput = document.getElementById('airline_name');
        const airlineNameValue = airlineNameInput.value.trim();

        const makkahDayNumberInput = document.getElementById('makkah_day_number');
        const makkahDayNumberValue = makkahDayNumberInput.value.trim();

        const makkahNightNumberInput = document.getElementById('makkah_night_number');
        const makkahNightNumberValue = makkahNightNumberInput.value.trim();

        const madinaDayNumberInput = document.getElementById('madina_day_number');
        const madinaDayNumberValue = madinaDayNumberInput.value.trim();

        const madinaNightNumberInput = document.getElementById('madina_night_number');
        const madinaNightNumberValue = madinaNightNumberInput.value.trim();

        const amountAirportHotelInput = document.getElementById('amount_airport_hotel');
        const amountAirportHotelValue = amountAirportHotelInput.value.trim();

        const amountHotelAirportInput = document.getElementById('amount_hotel_airport');
        const amountHotelAirportValue = amountHotelAirportInput.value.trim();

        const visitingZiaratsAmountInput = document.getElementById('visiting_ziarats_amount');
        const visitingZiaratsAmountValue = visitingZiaratsAmountInput.value.trim();

        const halaqatDarsiAmountInput = document.getElementById('halaqat_darsi_amount');
        const halaqatDarsiAmountValue = halaqatDarsiAmountInput.value.trim();

        const totalAmountInput = document.getElementById('total_amount');
        const totalAmountValue = totalAmountInput.value.trim();

        const makkahHotelNameInput = document.getElementById('makkah_hotel_name');
        const makkahHotelNameValue = makkahHotelNameInput.value.trim();

        const makkahHotelDegreeInput = document.getElementById('makkah_hotel_degree');
        const makkahHotelDegreeValue = makkahHotelDegreeInput.value.trim();

        const makkahHotelDistanceInput = document.getElementById('makkah_hotel_distance');
        const makkahHotelDistanceValue = makkahHotelDistanceInput.value.trim();

        const makkahHotelAmountInput = document.getElementById('makkah_hotel_amount');
        const makkahHotelAmountValue = makkahHotelAmountInput.value.trim();

        const madinaHotelNameInput = document.getElementById('madina_hotel_name');
        const madinaHotelNameValue = madinaHotelNameInput.value.trim();

        const madinaHotelDegreeInput = document.getElementById('madina_hotel_degree');
        const madinaHotelDegreeValue = madinaHotelDegreeInput.value.trim();

        const madinaHotelDistanceInput = document.getElementById('madina_hotel_distance');
        const madinaHotelDistanceValue = madinaHotelDistanceInput.value.trim();

        const madinaHotelAmountInput = document.getElementById('madina_hotel_amount');
        const madinaHotelAmountValue = madinaHotelAmountInput.value.trim();

        const commissionAmountInput = document.getElementById('commission_amount');
        const commissionAmountValue = commissionAmountInput.value.trim();

        const childServicesAmountInput = document.getElementById('child_services_amount');
        const childServicesAmountValue = childServicesAmountInput.value.trim();

        const childCommissionAmountInput = document.getElementById('child_commission_amount');
        const childCommissionAmountValue = childCommissionAmountInput.value.trim();


        $('#umrahPresidencyModal').modal('hide');

        let umrahUrl = '';
        switch(language) {
            case 'fa':
                umrahUrl = 'generate_umrah_presidency.php';    
                break;
            case 'ps':
                umrahUrl = 'generate_umrah_presidency_pashto.php';
                break;
            default:
                createToast('<?= __("error_invalid_language") ?>', 'danger');
                return;
        }

        const finalUrl = `${umrahUrl}?family_id=${selectedUmrahPresidencyUserId}&language=${language}&family_head_father_name=${encodeURIComponent(familyHeadFatherNameValue)}&family_head_id_number=${encodeURIComponent(familyHeadIdNumberValue)}&umrah_visa_amount=${encodeURIComponent(umrahVisaAmountValue)}&ticket_amount=${encodeURIComponent(ticketAmountValue)}&airline_name=${encodeURIComponent(airlineNameValue)}&makkah_day_number=${encodeURIComponent(makkahDayNumberValue)}&makkah_night_number=${encodeURIComponent(makkahNightNumberValue)}&madina_day_number=${encodeURIComponent(madinaDayNumberValue)}&madina_night_number=${encodeURIComponent(madinaNightNumberValue)}&amount_airport_hotel=${encodeURIComponent(amountAirportHotelValue)}&amount_hotel_airport=${encodeURIComponent(amountHotelAirportValue)}&visiting_ziarats_amount=${encodeURIComponent(visitingZiaratsAmountValue)}&halaqat_darsi_amount=${encodeURIComponent(halaqatDarsiAmountValue)}&total_amount=${encodeURIComponent(totalAmountValue)}&makkah_hotel_name=${encodeURIComponent(makkahHotelNameValue)}&makkah_hotel_degree=${encodeURIComponent(makkahHotelDegreeValue)}&makkah_hotel_distance=${encodeURIComponent(makkahHotelDistanceValue)}&makkah_hotel_amount=${encodeURIComponent(makkahHotelAmountValue)}&madina_hotel_name=${encodeURIComponent(madinaHotelNameValue)}&madina_hotel_degree=${encodeURIComponent(madinaHotelDegreeValue)}&madina_hotel_distance=${encodeURIComponent(madinaHotelDistanceValue)}&madina_hotel_amount=${encodeURIComponent(madinaHotelAmountValue)}&commission_amount=${encodeURIComponent(commissionAmountValue)}&child_services_amount=${encodeURIComponent(childServicesAmountValue)}&child_commission_amount=${encodeURIComponent(childCommissionAmountValue)}}`;
        console.log('Opening:', finalUrl);
        window.open(finalUrl, '_blank');
    }