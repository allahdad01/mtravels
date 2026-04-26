 // Currency display mapping
 const getCurrencyDisplay = function(currencyCode) {
     const currencyMap = {
         'USD': 'USD',
         'AFS': 'AFS',
         'EUR': 'EUR',
         'DARHAM': 'AED'
     };
     return currencyMap[currencyCode] || currencyCode;
 };

 // Generate dynamic exchange rate example
 const getExchangeRateExample = function(baseCurrency, targetCurrency) {
     const examples = {
        // USD pairs - show "1 USD = X" format
        'USD-AFS': 'Example: 1 USD = 88 AFS, enter 88',
        'USD-EUR': 'Example: 1 USD = 0.95 EUR, enter 0.95',
        'USD-AED': 'Example: 1 USD = 3.67 AED, enter 3.67',
        'AFS-USD': 'Example: 1 USD = 88 AFS, enter 88',
        'EUR-USD': 'Example: 1 USD = 0.95 EUR, enter 0.95',
        'AED-USD': 'Example: 1 USD = 3.67 AED, enter 3.67',
        
        // EUR pairs - show "1 EUR = X" format
        'EUR-AFS': 'Example: 1 EUR = 92.5 AFS, enter 92.5',
        'AFS-EUR': 'Example: 1 EUR = 92.5 AFS, enter 92.5',
        'EUR-AED': 'Example: 1 EUR = 3.86 AED, enter 3.86',
        'AED-EUR': 'Example: 1 EUR = 3.86 AED, enter 3.86',
        
        // AED pairs - show "1 AED = X" format
        'AED-AFS': 'Example: 1 AED = 23.99 AFS, enter 23.99',
        'AFS-AED': 'Example: 1 AED = 23.99 AFS, enter 23.99'
     };
     const key = `${baseCurrency}-${targetCurrency}`;
     return examples[key] || 'Enter the exchange rate';
 };

  // Reset form when modal is closed
   $('#addTransactionModal').on('hidden.bs.modal', function() {
      $('#addTransactionForm')[0].reset();
      $('#searchResultsContainer').hide();
      $('#weightDetailsContainer').hide();
      $('#saveTransactionBtn').hide();
      $('#selectedTicketId').val('');
  });

// Function to manage transactions
window.manageTransactions = function(weightId) {
    // Set the weight ID in the form
    $('#weightId').val(weightId);

    // Set today's date and current time as default for new transactions
    const now = new Date();
    const today = now.toISOString().split('T')[0];
    const currentTime = now.toTimeString().split(' ')[0].slice(0, 5); // Format: HH:mm
    $('#transactionDate').val(today);
    $('#transactionTime').val(currentTime);

    // Reset exchange rate field
    $('#exchangeRateField').hide();
    $('#transactionExchangeRate').attr('required', false);
    $('#transactionExchangeRate').val('');

    // Load weight details and transactions
    loadWeightDetails(weightId);
    loadTransactions(weightId);
    // Show the modal
    $('#transactionsModal').modal('show');
};

// Function to toggle exchange rate field based on currency selection
function toggleExchangeRateField() {
    const selectedCurrency = $('#transactionCurrency').val();
    if (selectedCurrency && window.weightCurrency && selectedCurrency !== window.weightCurrency) {
        $('#exchangeRateField').show();
        $('#transactionExchangeRate').attr('required', true);
        
        // Get display names for currencies
        const baseDisplay = getCurrencyDisplay(window.weightCurrency);
        const targetDisplay = getCurrencyDisplay(selectedCurrency);
        
        // Determine anchor currency (USD, EUR, AED, or AFS)
        let anchorCurrency = window.weightCurrency;
        const currencies = [selectedCurrency, window.weightCurrency];
        if (currencies.includes('USD')) {
            anchorCurrency = 'USD';
        } else if (currencies.includes('EUR')) {
            anchorCurrency = 'EUR';
        } else if (currencies.includes('AED')) {
            anchorCurrency = 'AED';
        } else if (currencies.includes('AFS')) {
            anchorCurrency = 'AFS';
        }
        
        const anchorDisplay = getCurrencyDisplay(anchorCurrency);
        const otherDisplay = anchorCurrency === window.weightCurrency ? targetDisplay : baseDisplay;
        
        // Update label to match example rule: "1 ANCHOR = OTHER"
        const label = `<i class="feather icon-refresh-cw mr-1"></i>${anchorDisplay} to ${otherDisplay} Exchange Rate <span class="text-danger">*</span>`;
        $('#exchangeRateLabel').html(label);
        
        // Update helper text to match anchor currency concept
        // Always show "1 ANCHOR = X OTHER, enter X"
        $('#exchangeRateBase').text(anchorDisplay);
        $('#exchangeRateTarget').text(otherDisplay);
        
        // Update the instruction text dynamically
        const instructionText = `Enter how many ${otherDisplay} equals 1 ${anchorDisplay}`;
        $('#exchangeRateInstruction').text(instructionText);
        
        // Update example based on currency pair
        const example = getExchangeRateExample(baseDisplay, targetDisplay);
        $('#exchangeRateExample').text(example);
    } else {
        $('#exchangeRateField').hide();
        $('#transactionExchangeRate').attr('required', false);
        $('#transactionExchangeRate').val(''); // Clear value when hidden
    }
}

// Add event listener for currency change
$('#transactionCurrency').on('change', toggleExchangeRateField);

// Function to load weight details
function loadWeightDetails(weightId) {
    $.ajax({
        url: '../api/ticket_weight/get_weight.php',
        type: 'GET',
        data: { id: weightId },
        success: function(response) {
            try {
                const result = JSON.parse(response);
                if (result.success) {
                    const weight = result.weight;

                    // Store weight currency for exchange rate logic
                    window.weightCurrency = weight.currency;

                    // Update weight details in the modal
                    $('#trans-passenger-name').text(weight.passenger_name);
                    $('#trans-pnr').text(weight.pnr);
                    $('#trans-weight').text(weight.weight + ' kg');

                    // Update financial details
                    $('#totalAmount').text(weight.currency + ' ' + parseFloat(weight.sold_price).toFixed(2));
                    updatePaymentStatus(weight);
                }
            } catch (e) {

            }
        }
    });
}
// Function to load transactions
function loadTransactions(weightId) {
    $.ajax({
        url: '../api/ticket_weight/get_weight_transactions.php',
        type: 'GET',
        data: { weight_id: weightId },
        dataType: 'json',
        success: function(result) {
            try {
                if (result.success) {
                    const transactions = result.transactions;
                    const tbody = $('#transactionsTableBody');
                    tbody.empty();

                    if (!Array.isArray(transactions) || transactions.length === 0) {
                        tbody.html('<tr><td colspan="6" class="text-center">No transactions found</td></tr>');
                        $('#exchangeRateDisplay').text('No exchange rates found');
                        $('#exchangedAmount').text('No conversions available');
                        return;
                    }

                    const baseCurrency = window.weightCurrency || 'USD';
                    const totalAmount = parseFloat($('#totalAmount').text().split(' ')[1]) || 0;

                    // Collect exchange rates from DB transactions
                    let rates = {}; // { EUR: 87, AFS: 70, DARHAM: 18.5 }
                    transactions.forEach(tx => {
                        if (tx.currency !== baseCurrency && tx.exchange_rate) {
                            rates[tx.currency] = parseFloat(tx.exchange_rate);
                        }
                    });

                    // Track currencies present in transactions
                    let hasCurrency = { USD: false, AFS: false, EUR: false, DARHAM: false };

                    // Render transactions table
                    transactions.forEach(tx => {
                        const currency = tx.currency;
                        const amount = parseFloat(tx.amount);
                        const exchangeRate = tx.exchange_rate ? parseFloat(tx.exchange_rate) : null;

                        if (currency in hasCurrency) hasCurrency[currency] = true;

                        tbody.append(`
                            <tr>
                                <td>${formatDate(tx.created_at)}</td>
                                <td>${tx.description || ''}</td>
                                <td>${currency} ${amount.toFixed(2)}</td> 
                                <td>${exchangeRate ? exchangeRate.toFixed(2) : 'N/A'}</td>
                                <td class="text-center">
                                    <button class="btn btn-primary btn-sm" onclick="editWeightTransaction(${tx.id}, '${(tx.description||'').replace(/'/g,"\\'")}', ${amount}, '${tx.created_at}', '${currency}', ${tx.exchange_rate || 'null'})">
                                        <i class="feather icon-edit"></i>
                                    </button>
                                                                    <button class="btn btn-info btn-sm mr-1" title="Print Receipt"
                                        onclick="printReceipt(${tx.id})">
                                    <i class="feather icon-printer"></i>
                                </button>
                                    <button class="btn btn-danger btn-sm" onclick="deleteTransaction(${tx.id}, ${$('#weightId').val()}, ${amount})">
                                        <i class="feather icon-trash-2"></i>
                                    </button>
                                </td>
                            </tr>
                        `);
                    });

                    // Display exchange rates
                    const exchangeText = Object.entries(rates).map(([cur,val]) => `${cur}: ${val}`).join(', ');
                    $('#exchangeRateDisplay').text(exchangeText || 'No exchange rates found');

                    // Calculate total paid in base currency
                    let totalPaidBase = 0;
                    transactions.forEach(tx => {
                        const amount = parseFloat(tx.amount);
                        const currency = tx.currency;

                        if (currency === baseCurrency) {
                            totalPaidBase += amount;
                        } else if (rates[currency]) {
                            // Convert foreign currency to base currency
                            if (baseCurrency === 'AFS') totalPaidBase += amount * rates[currency];
                            else totalPaidBase += amount / rates[currency];
                        }
                    });

                    const remainingBase = Math.max(0, totalAmount - totalPaidBase);

                    // Display paid and remaining amounts for each currency
                    ['USD','AFS','EUR','DARHAM'].forEach(cur => {
                        if (hasCurrency[cur]) {
                            const paid = transactions.filter(t => t.currency === cur)
                                                     .reduce((a,b) => a + parseFloat(b.amount), 0);
                            const displayCur = getCurrencyDisplay(cur);
                            $(`#paidAmount${cur}`).text(`${displayCur} ${paid.toFixed(2)}`);

                            let remaining = 0;
                            if (cur === baseCurrency) {
                                remaining = remainingBase;
                            } else if (rates[cur]) {
                                // Convert base currency remaining to foreign
                                if (baseCurrency === 'AFS') remaining = remainingBase / rates[cur];
                                else remaining = remainingBase * rates[cur];
                            } else {
                                remaining = 'N/A';
                            }

                            $(`#remainingAmount${cur}`).text(`${displayCur} ${typeof remaining==='number'?remaining.toFixed(2):remaining}`);
                        }
                    });

                    // Display exchanged amounts
                    const exchangedAmounts = [];
                    exchangedAmounts.push(`${baseCurrency} ${totalAmount.toFixed(2)}`);
                    Object.keys(rates).forEach(cur => {
                        const val = (baseCurrency === 'AFS') ? totalAmount / rates[cur] : totalAmount * rates[cur];
                        exchangedAmounts.push(`${cur} ${val.toFixed(2)}`);
                    });
                    $('#exchangedAmount').text(exchangedAmounts.join(', '));

                    // Show/hide currency sections
                    $('#usdSection').toggle(hasCurrency.USD);
                    $('#afsSection').toggle(hasCurrency.AFS);
                    $('#eurSection').toggle(hasCurrency.EUR);
                    $('#darhamSection').toggle(hasCurrency.DARHAM);

                } else {

                }

            } catch(e) {

                $('#transactionsTableBody').html('<tr><td colspan="6" class="text-center">error_loading_transactions</td></tr>');
                $('#exchangeRateDisplay').text('Error loading exchange rates');
                $('#exchangedAmount').text('Error calculating amounts');
            }
        },
        error: function(xhr, status, error){

            $('#transactionsTableBody').html('<tr><td colspan="6" class="text-center">error_loading_transactions</td></tr>');
            $('#exchangeRateDisplay').text('Error loading exchange rates');
            $('#exchangedAmount').text('Error calculating amounts');
        }
    });
}

function editWeightTransaction(id, remarks, amount, transaction_date, currency, exchange_rate) {
    // Parse the datetime string
    const dateTime = new Date(transaction_date);

    // Format date for input field (YYYY-MM-DD)
    const formattedDate = dateTime.toISOString().split('T')[0];

    // Format time for input field (HH:MM:SS)
    const hours = String(dateTime.getHours()).padStart(2, '0');
    const minutes = String(dateTime.getMinutes()).padStart(2, '0');
    const seconds = String(dateTime.getSeconds()).padStart(2, '0');
    const formattedTime = `${hours}:${minutes}:${seconds}`;

    // Get the current weight ID from the weightId field
    const weightId = $('#weightId').val();



    // Create edit transaction modal if it doesn't exist
    if (!$('#editWeightTransactionModal').length) {
        const modalHtml = `
            <div class="modal fade" id="editWeightTransactionModal" tabindex="-1" role="dialog">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title">
                                <i class="feather icon-edit mr-2"></i>Edit Weight Transaction
                            </h5>
                            <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                        </div>
                        <form id="editWeightTransactionForm">
                            <div class="modal-body">
                                <input type="hidden" id="editWeightTransactionId" name="transaction_id">
                                <input type="hidden" id="editWeightId" name="weight_id">
                                <input type="hidden" id="originalAmount" name="original_amount">

                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="editWeightDate">
                                                <i class="feather icon-calendar mr-1"></i>Transaction Date
                                            </label>
                                            <input type="date" class="form-control" id="editWeightDate" name="transaction_date" required>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="editWeightTime">
                                                <i class="feather icon-clock mr-1"></i>Transaction Time
                                            </label>
                                            <input type="time" class="form-control" id="editWeightTime" name="transaction_time" step="1" required>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="editWeightAmount">
                                                <i class="feather icon-dollar-sign mr-1"></i>Amount
                                            </label>
                                            <input type="number" class="form-control" id="editWeightAmount"
                                                   name="transaction_amount" step="0.01" min="0.01" required>
                                            <small class="form-text text-muted">
                                                Changing this amount will update all subsequent balances.
                                            </small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="editWeightRemarks">
                                                <i class="feather icon-file-text mr-1"></i>Remarks
                                            </label>
                                            <textarea class="form-control" id="editWeightRemarks"
                                                      name="transaction_remarks" rows="2" required></textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="editWeightCurrency">
                                                <i class="feather icon-dollar-sign mr-1"></i>Currency
                                            </label>
                                            <select class="form-control" id="editWeightCurrency" name="transaction_currency" required disabled>
                                                <option value="USD">USD</option>
                                                <option value="AFS">AFS</option>
                                                <option value="EUR">EUR</option>
                                                <option value="DARHAM">DARHAM</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group" id="editWeightExchangeRateField" style="display: none;">
                                            <label for="editWeightExchangeRate">
                                                <i class="feather icon-refresh-cw mr-1"></i>Exchange Rate
                                            </label>
                                            <input type="number" class="form-control" id="editWeightExchangeRate"
                                                   name="exchange_rate" step="0.01" placeholder="Enter exchange rate">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                                    <i class="feather icon-x mr-1"></i>Cancel
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="feather icon-check mr-1"></i>Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;
        $('body').append(modalHtml);

        // Bind the change event for the edit currency select
        $('#editWeightCurrency').on('change', function() {
            // Always show exchange rate field for edit form
            $('#editWeightExchangeRateField').show();
            $('#editWeightExchangeRate').attr('required', true);
        });

        // Add submit handler for the edit form
        $('#editWeightTransactionForm').on('submit', function(e) {
            e.preventDefault();

            // Disable submit button to prevent multiple clicks
            const submitBtn = $(this).find('button[type="submit"]');
            const originalText = submitBtn.html();
            submitBtn.prop('disabled', true);
            submitBtn.html('<i class="feather icon-refresh-cw mr-2 spinner-border spinner-border-sm" role="status" aria-hidden="true"></i>Saving...');

            // Create FormData from the form
            const formData = new FormData(this);

            // Explicitly set the weight ID again to ensure it's included
            const currentWeightId = $('#weightId').val();
            formData.set('weight_id', currentWeightId);

            // Ensure transaction_id and weight_id are set
            if (!formData.get('transaction_id')) {
                // Re-enable submit button on validation error
                submitBtn.prop('disabled', false);
                submitBtn.html(originalText);
                alert('Error: Missing transaction ID');
                return;
            }

            if (!formData.get('weight_id')) {
                // Re-enable submit button on validation error
                submitBtn.prop('disabled', false);
                submitBtn.html(originalText);
                alert('Error: Missing weight ID');
                return;
            }

            // Combine date and time into a datetime string in MySQL format
            const date = formData.get('transaction_date');
            const time = formData.get('transaction_time');
            if (date && time) {
                formData.set('transaction_date', `${date} ${time}`);
            }

            // Add exchange rate if provided
            const exchangeRate = $('#editWeightExchangeRate').val();
            if (exchangeRate && $('#editWeightExchangeRateField').is(':visible')) {
                formData.set('transaction_exchange_rate', exchangeRate);
            }

            // Log the form data for debugging

            for (let pair of formData.entries()) {

            }

            $.ajax({
                url: '../api/ticket_weight/update_weight_transaction.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    try {
                        const result = typeof response === 'string' ? JSON.parse(response) : response;
                        if (result.success) {
                            alert('Transaction updated successfully');
                            $('#editWeightTransactionModal').modal('hide');
                            loadTransactions(currentWeightId);
                        } else {
                            // Re-enable submit button on business logic error
                            submitBtn.prop('disabled', false);
                            submitBtn.html(originalText);
                            alert('Error updating transaction: ' + (result.message || 'Unknown error'));
                        }
                    } catch (e) {

                        // Re-enable submit button on parsing error
                        submitBtn.prop('disabled', false);
                        submitBtn.html(originalText);
                        alert('Error processing the request');
                    }
                },
                error: function(xhr, status, error) {


                    // Re-enable submit button on network error
                    submitBtn.prop('disabled', false);
                    submitBtn.html(originalText);
                    alert('Error updating transaction');
                }
            });

            // Re-enable submit button after 10 seconds as safety measure
            setTimeout(function() {
                if (submitBtn.prop('disabled')) {
                    submitBtn.prop('disabled', false);
                    submitBtn.html(originalText);
                }
            }, 10000);
        });
    }

    // Populate the edit form with the current values
    $('#editWeightTransactionId').val(id);
    $('#editWeightId').val(weightId);
    $('#originalAmount').val(amount);
    $('#editWeightDate').val(formattedDate);
    $('#editWeightTime').val(formattedTime);
    $('#editWeightAmount').val(parseFloat(amount).toFixed(2));
    $('#editWeightRemarks').val(remarks);

    // Set the currency from the transaction
    $('#editWeightCurrency').val(currency);

    // Show exchange rate field (always shown for edit)
    $('#editWeightExchangeRateField').show();

    // Set exchange rate from parameter
    if (exchange_rate && exchange_rate !== 'null') {
        $('#editWeightExchangeRate').val(exchange_rate);
        $('#editWeightExchangeRateField').show();
    } else {
        $('#editWeightExchangeRateField').hide();
        $('#editWeightExchangeRate').val('');
    }

    // Log values for debugging
    console.log({
        transactionId: id,
        weightId: weightId,
        amount: amount,
        date: formattedDate,
        time: formattedTime,
        remarks: remarks,
        currency: currency,
        exchangeRate: exchange_rate
    });

    // Show the modal
    $('#editWeightTransactionModal').modal('show');
}

function updatePaymentStatus(weight, transactions = []) {
const baseCurrency = weight.currency; // e.g., "AFS"
const totalAmount = parseFloat(weight.sold_price) || 0;

// --- STEP 1: Build rates map from transactions ---
const ratesToBase = {};
transactions.forEach(t => {
const curr = t.currency;
let rate = parseFloat(t.exchange_rate);
if (!rate) rate = (curr === baseCurrency) ? 1 : null;
if (rate) ratesToBase[curr] = rate;
});
ratesToBase[baseCurrency] = 1; // ensure base currency included

// --- STEP 2: Sum paid amounts per currency ---
const paidAmounts = {};
transactions.forEach(t => {
const curr = t.currency;
const amount = parseFloat(t.amount) || 0;
if (!paidAmounts[curr]) paidAmounts[curr] = 0;
paidAmounts[curr] += amount;
});

// --- STEP 3: Conversion helper ---
function convert(amount, from, to) {
if (from === to) return amount;
const rateFrom = ratesToBase[from];
const rateTo = ratesToBase[to];
if (!rateFrom || !rateTo) return 0;
return (amount * rateFrom) / rateTo;
}

// --- STEP 4: Total paid in base currency ---
let totalPaidInBase = 0;
Object.keys(paidAmounts).forEach(curr => {
totalPaidInBase += convert(paidAmounts[curr], curr, baseCurrency);
});
const remainingInBase = Math.max(0, totalAmount - totalPaidInBase);

// --- STEP 5: Update info cards ---
$('#totalAmount').text(`${baseCurrency} ${totalAmount.toFixed(2)}`);

const exchangedAmounts = Object.keys(ratesToBase).map(curr => {
const converted = convert(totalAmount, baseCurrency, curr).toFixed(2);
return `${curr} ${converted}`;
});
$('#exchangedAmount').text(exchangedAmounts.join(', '));

$('#exchangeRateDisplay').text(
Object.keys(ratesToBase)
.filter(c => c !== baseCurrency)
.map(c => `${c}: ${ratesToBase[c]}`)
.join(', ')
);

// --- STEP 6: Update all payment cards dynamically ---
$('#paymentStatusContainer').show();
const currencies = ['USD', 'AFS', 'EUR', 'DARHAM']; // your card IDs
currencies.forEach(curr => {
const paid = paidAmounts[curr] || 0;
const remaining = convert(remainingInBase, baseCurrency, curr);

const sectionId = `#${curr.toLowerCase()}Section`;
if (paid > 0 || remaining > 0) $(sectionId).show();

$(`#paidAmount${curr}`).text(`${getCurrencyDisplay(curr)} ${paid.toFixed(2)}`);
$(`#remainingAmount${curr}`).text(`${getCurrencyDisplay(curr)} ${remaining.toFixed(2)}`);
});
}

// Handle transaction form submission
$('#weightTransactionForm').on('submit', function(e) {
    e.preventDefault();

    // Disable submit button to prevent multiple clicks
    const submitBtn = $(this).find('button[type="submit"]');
    const originalText = submitBtn.html();
    submitBtn.prop('disabled', true);
    submitBtn.html('<i class="feather icon-refresh-cw mr-2 spinner-border spinner-border-sm" role="status" aria-hidden="true"></i>Saving...');

    // Create FormData object
    const formData = new FormData(this);

    // Remove the transaction_time field since we'll combine it with date
    formData.delete('transaction_time');

    // Combine date and time
    const date = $('#transactionDate').val();
    const time = $('#transactionTime').val();
    if (date && time) {
        formData.set('transaction_date', `${date} ${time}`);
    }

    // Add exchange rate if field is visible
    if ($('#exchangeRateField').is(':visible')) {
        const exchangeRate = $('#transactionExchangeRate').val();
        if (exchangeRate) {
            formData.set('exchange_rate', exchangeRate);
        }
    }
    const receiptNumber = ($('#receiptNumber').val() || '').trim();
    formData.set('receipt_number', receiptNumber);

    $.ajax({
        url: '../api/ticket_weight/save_weight_transaction.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            try {
                const result = JSON.parse(response);
                if (result.success) {
                    // Show success message
                    showToast('Transaction saved successfully', 'success');
                    
                    // Reload transactions
                    loadTransactions($('#weightId').val());
                    
                    // Reset form
                    $('#weightTransactionForm')[0].reset();
                    
                    // Set today's date and current time again
                    const now = new Date();
                    $('#transactionDate').val(now.toISOString().split('T')[0]);
                    $('#transactionTime').val(now.toTimeString().split(' ')[0].slice(0, 5));
                } else {
                    // Re-enable submit button on business logic error
                    submitBtn.prop('disabled', false);
                    submitBtn.html(originalText);
                    showToast(result.message || 'Failed to save transaction', 'error');
                }
            } catch (e) {
                // Re-enable submit button on parsing error
                submitBtn.prop('disabled', false);
                submitBtn.html(originalText);
                showToast('Error processing request', 'error');
            }
        },
        error: function(xhr, status, error) {



            // Re-enable submit button on network error
            submitBtn.prop('disabled', false);
            submitBtn.html(originalText);
            alert('Error saving transaction');
        }
    });

    // Re-enable submit button after 10 seconds as safety measure
    setTimeout(function() {
        if (submitBtn.prop('disabled')) {
            submitBtn.prop('disabled', false);
            submitBtn.html(originalText);
        }
    }, 10000);
});
// Function to format date
function formatDate(dateString) {
    // Handle datetime strings like "2026-04-01 12:17:00"
    // Convert to ISO format for proper parsing
    if (dateString && typeof dateString === 'string') {
        // Replace space with 'T' to create ISO format: "2026-04-01T12:17:00"
        const isoString = dateString.replace(' ', 'T');
        const date = new Date(isoString);
        
        // Check if date is valid
        if (!isNaN(date.getTime())) {
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        }
    }
    return 'Invalid Date';
}
    // Print receipt function
    function printReceipt(transactionId) {
        window.open(`../api/ticket_weight/print_weight_receipt.php?id=${transactionId}`, '_blank');
    }
