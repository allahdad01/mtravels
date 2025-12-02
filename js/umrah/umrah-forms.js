/**
 * Umrah Forms JavaScript
 * Handles form functionality for Umrah booking management
 */

document.addEventListener('DOMContentLoaded', function() {
    // Show/hide Mahram information based on gender selection
    initGenderDependentFields();
    
    // Set default dates
    setDefaultDates();
    
    // Initialize financial calculations
    initFinancialCalculations();
    
    // Initialize passport expiry validation
    initPassportExpiryValidation();
});

/**
 * Initialize gender-dependent fields visibility
 */
function initGenderDependentFields() {
    // For the add member form
    const genderSelect = document.getElementById('gender');
    const mahramInfo = document.querySelector('.mahram-info');
    
    if (genderSelect && mahramInfo) {
        genderSelect.addEventListener('change', function() {
            if (this.value === 'Female') {
                mahramInfo.style.display = 'block';
                document.getElementById('mahram_info').setAttribute('required', 'required');
            } else {
                mahramInfo.style.display = 'none';
                document.getElementById('mahram_info').removeAttribute('required');
                document.getElementById('mahram_info').value = '';
            }
        });
        
        // Trigger change event to set initial state
        genderSelect.dispatchEvent(new Event('change'));
    }
    
    // For the edit member form
    const editGenderSelect = document.getElementById('editGender');
    const editMahramInfo = document.querySelector('.edit-mahram-info');
    
    if (editGenderSelect && editMahramInfo) {
        editGenderSelect.addEventListener('change', function() {
            if (this.value === 'Female') {
                editMahramInfo.style.display = 'block';
                document.getElementById('editMahramInfo').setAttribute('required', 'required');
            } else {
                editMahramInfo.style.display = 'none';
                document.getElementById('editMahramInfo').removeAttribute('required');
                document.getElementById('editMahramInfo').value = '';
            }
        });
        
        // Trigger change event to set initial state
        if (editGenderSelect.value) {
            editGenderSelect.dispatchEvent(new Event('change'));
        }
    }
}

/**
 * Set default dates for date fields
 */
function setDefaultDates() {
    // Set default date for entry_date field
    const entryDateField = document.getElementById('entry_date');
    if (entryDateField && !entryDateField.value) {
        const today = new Date().toISOString().split('T')[0];
        entryDateField.value = today;
    }
    
    // Set minimum date for passport expiry (must be at least 6 months from today)
    const passportExpiryField = document.getElementById('passport_expiry');
    if (passportExpiryField) {
        const sixMonthsFromNow = new Date();
        sixMonthsFromNow.setMonth(sixMonthsFromNow.getMonth() + 6);
        const minDate = sixMonthsFromNow.toISOString().split('T')[0];
        passportExpiryField.setAttribute('min', minDate);
    }
}

/**
 * Initialize financial calculations
 */
function initFinancialCalculations() {
    // Calculate profit and due on input change
    const priceInput = document.getElementById('price');
    const soldPriceInput = document.getElementById('sold_price');
    const paidInput = document.getElementById('paid');
    
    if (priceInput && soldPriceInput && paidInput) {
        const profitInput = document.getElementById('profit');
        const dueInput = document.getElementById('due');
        
        const calculateFinancials = function() {
            let price = parseFloat(priceInput.value) || 0;
            let soldPrice = parseFloat(soldPriceInput.value) || 0;
            let paid = parseFloat(paidInput.value) || 0;

            let profit = soldPrice - price;
            let due = soldPrice - paid;

            if (profitInput) profitInput.value = profit.toFixed(2);
            if (dueInput) dueInput.value = due.toFixed(2);
        };
        
        priceInput.addEventListener('input', calculateFinancials);
        soldPriceInput.addEventListener('input', calculateFinancials);
        paidInput.addEventListener('input', calculateFinancials);
    }
    
    // Same for edit form
    const editPriceInput = document.getElementById('editPrice');
    const editSoldPriceInput = document.getElementById('editSoldPrice');
    const editPaidInput = document.getElementById('editPaid');
    
    if (editPriceInput && editSoldPriceInput && editPaidInput) {
        const editProfitInput = document.getElementById('editProfit');
        const editDueInput = document.getElementById('editDue');
        
        const calculateEditFinancials = function() {
            let price = parseFloat(editPriceInput.value) || 0;
            let soldPrice = parseFloat(editSoldPriceInput.value) || 0;
            let paid = parseFloat(editPaidInput.value) || 0;

            let profit = soldPrice - price;
            let due = soldPrice - paid;

            if (editProfitInput) editProfitInput.value = profit.toFixed(2);
            if (editDueInput) editDueInput.value = due.toFixed(2);
        };
        
        editPriceInput.addEventListener('input', calculateEditFinancials);
        editSoldPriceInput.addEventListener('input', calculateEditFinancials);
        editPaidInput.addEventListener('input', calculateEditFinancials);
    }
}

/**
 * Initialize passport expiry validation
 */
function initPassportExpiryValidation() {
    const passportExpiryField = document.getElementById('passport_expiry');
    if (passportExpiryField) {
        passportExpiryField.addEventListener('change', function() {
            const selectedDate = new Date(this.value);
            const today = new Date();
            
            // Calculate six months from today
            const sixMonthsFromNow = new Date();
            sixMonthsFromNow.setMonth(sixMonthsFromNow.getMonth() + 6);
            
            if (selectedDate < sixMonthsFromNow) {
                alert('Passport must be valid for at least 6 months from today for Umrah visa requirements.');
                this.value = '';
            }
        });
    }
    
    // Same for edit form
    const editPassportExpiryField = document.getElementById('editPassportExpiry');
    if (editPassportExpiryField) {
        editPassportExpiryField.addEventListener('change', function() {
            const selectedDate = new Date(this.value);
            const today = new Date();
            
            // Calculate six months from today
            const sixMonthsFromNow = new Date();
            sixMonthsFromNow.setMonth(sixMonthsFromNow.getMonth() + 6);
            
            if (selectedDate < sixMonthsFromNow) {
                alert('Passport must be valid for at least 6 months from today for Umrah visa requirements.');
                this.value = '';
            }
        });
    }
} 