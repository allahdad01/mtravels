$(document).ready(function() {
    // Set today's date as default for payment date
    $('#payment_date').val(new Date().toISOString().split('T')[0]);
    
    // Prevent default form submission for edit form
    $('#editPaymentForm').submit(function(e) {
        e.preventDefault();
        // The form will be submitted via AJAX by the updatePayment click handler
    });
    
    // Handle supplier checkbox in add form
    $('#is_from_supplier').change(function() {
        if($(this).is(':checked')) {
            $('.supplier-group').show();
            $('#supplier_id').prop('required', true);
        } else {
            $('.supplier-group').hide();
            $('#supplier_id').prop('required', false);
        }
    });

    // Handle client checkbox in add form
    $('#is_for_client').change(function() {
        if($(this).is(':checked')) {
            $('.client-group').show();
            $('#client_id').prop('required', true);
        } else {
            $('.client-group').hide();
            $('#client_id').prop('required', false);
        }
    });

    // Handle supplier checkbox in edit form
    $('#edit_is_from_supplier').change(function() {
        if($(this).is(':checked')) {
            $('.supplier-group').show();
            $('#edit_supplier_id').prop('required', true);
        } else {
            $('.supplier-group').hide();
            $('#edit_supplier_id').prop('required', false);
        }
    });

    // Handle client checkbox in edit form
    $('#edit_is_for_client').change(function() {
        if($(this).is(':checked')) {
            $('.client-group').show();
            $('#edit_client_id').prop('required', true);
        } else {
            $('.client-group').hide();
            $('#edit_client_id').prop('required', false);
        }
    });

    // When editing payment, check if it has supplier and client
    $('.edit-payment').click(function() {
        const id = $(this).data('id');
        const paymentType = $(this).data('payment-type');
        const description = $(this).data('description');
        const baseAmount = $(this).data('base-amount');
        const profit = $(this).data('profit');
        const soldAmount = $(this).data('sold-amount');
        const currency = $(this).data('currency');
        const mainAccount = $(this).data('main-account');
        const supplier = $(this).data('supplier');
        const client = $(this).data('client');
        const receipt = $(this).data('receipt');

        // Set form values
        $('#edit_id').val(id);
        $('#edit_payment_type').val(paymentType);
        $('#edit_description').val(description);
        $('#edit_base_amount').val(baseAmount);
        $('#edit_sold_amount').val(soldAmount);
        $('#edit_profit').val(profit);
        $('#edit_currency').val(currency);
        $('#edit_main_account_id').val(mainAccount);
        $('#edit_supplier_id').val(supplier);
        $('#edit_client_id').val(client);

        // Handle supplier checkbox and fields
        if (supplier) {
            $('#edit_is_from_supplier').prop('checked', true);
            $('.supplier-group').show();
            $('#edit_supplier_id').prop('required', true);
        } else {
            $('#edit_is_from_supplier').prop('checked', false);
            $('.supplier-group').hide();
            $('#edit_supplier_id').prop('required', false);
        }

        // Handle client checkbox and fields
        if (client) {
            $('#edit_is_for_client').prop('checked', true);
            $('.client-group').show();
            $('#edit_client_id').prop('required', true);
        } else {
            $('#edit_is_for_client').prop('checked', false);
            $('.client-group').hide();
            $('#edit_client_id').prop('required', false);
        }

        // Store the original base amount for comparison
        $('#updatePayment').data('original-base-amount', baseAmount);

        $('#editPaymentModal').modal('show');
    });

    // Save Payment button click handler - SINGLE HANDLER
    $('#savePayment').off('click').on('click', function(e) {
        e.preventDefault(); // Prevent any default behavior
        var $btn = $(this);
        var originalHtml = $btn.html();
        $btn.prop('disabled', true);
        $btn.html('<i class="fas fa-spinner fa-spin mr-1"></i>Saving Payment...');

        var form = $('#addPaymentForm');
        var formData = new FormData(form[0]);
        
        // Add checkbox values
        formData.append('is_from_supplier', $('#is_from_supplier').is(':checked') ? 1 : 0);
        formData.append('is_for_client', $('#is_for_client').is(':checked') ? 1 : 0);
        
        $.ajax({
            url: form.attr('action'),
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                try {
                    var result = typeof response === 'object' ? response : JSON.parse(response);
                    if (result.success) {
                        $('#addPaymentModal').modal('hide');
                        location.reload();
                    } else {
                        alert("Error: " + (result.message || "An unknown error occurred."));
                    }
                } catch (e) {

                    alert("Error: Invalid response from server.");
                }
                // Always reset button state after success handler
                $btn.prop('disabled', false);
                $btn.html(originalHtml);
            },
            error: function(xhr, status, error) {
                // Always reset button state on error
                $btn.prop('disabled', false);
                $btn.html(originalHtml);

                try {
                    var errorResponse = JSON.parse(xhr.responseText);
                    alert("Error: " + (errorResponse.message || "Failed to save payment."));
                } catch (e) {
                    alert("Failed to save payment. Please try again.");
                }
            }
        });
    });

    // Update Payment button click handler
    $('#updatePayment').click(function(e) {
        e.preventDefault();
        
        var $btn = $(this);
        var originalHtml = $btn.html();
        $btn.prop('disabled', true);
        $btn.html('<i class="fas fa-spinner fa-spin mr-1"></i>Updating Payment...');
        
        // Collect form data
        var formData = {
            id: $('#edit_id').val(),
            action: 'edit',
            payment_type: $('#edit_payment_type').val(),
            description: $('#edit_description').val(),
            base_amount: $('#edit_base_amount').val(),
            profit: $('#edit_profit').val(),
            sold_amount: $('#edit_sold_amount').val(),
            currency: $('#edit_currency').val(),
            main_account_id: $('#edit_main_account_id').val(),
            is_from_supplier: $('#edit_is_from_supplier').is(':checked') ? 1 : 0,
            supplier_id: $('#edit_is_from_supplier').is(':checked') ? $('#edit_supplier_id').val() : '',
            is_for_client: $('#edit_is_for_client').is(':checked') ? 1 : 0,
            client_id: $('#edit_is_for_client').is(':checked') ? $('#edit_client_id').val() : '',
            csrf_token: $('input[name="csrf_token"]').val()
        };
        
        // Debug: Log the form data being sent

        
        // Use the current page URL
        var ajaxUrl = '../api/additional_payment/update_additional_payment_base.php';

        
        // Submit via AJAX
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: formData,
            beforeSend: function(xhr) {

            },
            success: function(response) {

                
                try {
                    var result = typeof response === 'object' ? response : JSON.parse(response);

                    
                    if (result.success) {

                        // Show success message
                        alert("Payment updated successfully.");
                        // Close modal and reload page
                        $('#editPaymentModal').modal('hide');
                        location.reload();
                    } else {
                        alert("Error: " + (result.message || "An unknown error occurred."));
                    }
                } catch (e) {
                    alert("Error: Invalid response from server.");
                }
                // Always reset button state after success handler
                $btn.prop('disabled', false);
                $btn.html(originalHtml);
            },
            error: function(xhr, status, error) {
                // Always reset button state on error
                $btn.prop('disabled', false);
                $btn.html(originalHtml);
                
                // If we get a 404 error, try direct form submission as a fallback
                if (xhr.status === 404) {

                    
                    // Create a temporary form for direct submission
                    var tempForm = $('<form>', {
                        'action': '../api/additional_payment/additional_payments.php',
                        'method': 'post',
                        'style': 'display: none;'
                    });
                    
                    // Add all the form data as hidden fields
                    $.each(formData, function(key, value) {
                        $('<input>').attr({
                            type: 'hidden',
                            name: key,
                            value: value
                        }).appendTo(tempForm);
                    });
                    
                    // Add the form to the body and submit it
                    tempForm.appendTo('body').submit();
                    return;
                }
                
                try {
                    var errorResponse = JSON.parse(xhr.responseText);

                    alert("Error: " + (errorResponse.message || "Failed to update payment."));
                } catch (e) {

                    alert("Failed to update payment. Please try again.");
                }
            }
        });
    });

    // Load transactions when modal is shown
    $('#addTransactionModal').on('show.bs.modal', function() {
        var paymentId = $('#transaction_payment_id').val();
        transactionManager.loadTransactionHistory(paymentId);
    });

    // Currency dropdown change event
    $('#transaction_currency').change(function() {
        var selectedCurrency = $(this).val();
        var originalCurrency = $('#original_payment_currency').val();
        
        // Show/hide exchange rate field if currencies are different
        if (selectedCurrency !== originalCurrency) {
            $('#exchange_rate_group').show();
            $('#exchange_rate').prop('required', true);
        } else {
            $('#exchange_rate_group').hide();
            $('#exchange_rate').prop('required', false);
        }
    });
    
    // Save transaction
     $('#AddTransaction').click(function() {
         var $btn = $(this);
         var originalHtml = $btn.html();
         $btn.prop('disabled', true);
         $btn.html('<i class="fas fa-spinner fa-spin mr-1"></i>Adding Transaction...');

         var selectedCurrency = $('#transaction_currency').val();
         var originalCurrency = $('#original_payment_currency').val();
         var description = $('#payment_description').val();
         var exchangeRate = $('#exchange_rate').val();
         
         // Exchange rate is stored in separate field, no need to modify description
         if (selectedCurrency !== originalCurrency) {
             if (!exchangeRate) {
                 $btn.prop('disabled', false);
                 $btn.html(originalHtml);
                 alert("Please enter an exchange rate.");
                 return;
             }
         }
         
         // Get CSRF token
         var csrfToken = $('input[name="csrf_token"]').val();
         
         var formData = {
             payment_id: $('#transaction_payment_id').val(),
             payment_type: $('#transaction_payment_type').val(),
             currency: selectedCurrency,
             original_currency: originalCurrency,
             exchange_rate: exchangeRate,
             main_account_id: $('#transaction_main_account_id').val(),
             payment_amount: $('#payment_amount').val(),
             payment_date: $('#payment_date').val(),
             payment_time: $('#payment_time').val(),
             payment_description: $('#payment_description').val(),
             receipt_number: ($('#receipt_number').val() || '').trim(),
             csrf_token: csrfToken
         };

        var url = '../api/additional_payment/add_additional_payment_transaction.php';
        var transactionId = $('#transaction_id').val();
        if (transactionId) {
            url = '../api/additional_payment/update_additional_payment_transaction.php';
            formData.transaction_id = transactionId;
        }

        $.ajax({
            url: url,
            type: 'POST',
            data: formData,
            success: function(response) {
                try {
                    // Handle both string and parsed JSON responses
                    var result = typeof response === 'object' ? response : JSON.parse(response);
                    if (result.success) {
                        alert("Transaction saved successfully.");
                        // Reset form fields
                        $('#payment_amount').val('');
                        $('#payment_description').val('');
                        $('#receipt_number').val('');
                        $('#exchange_rate').val('');
                        // Reload transactions
                        transactionManager.loadTransactionHistory($('#transaction_payment_id').val());
                    } else {
                        alert("Error: " + (result.message || "An unknown error occurred."));
                    }
                } catch (e) {
                        alert("Error: Invalid response from server.");
                }
                // Always reset button state after success handler
                $btn.prop('disabled', false);
                $btn.html(originalHtml);
            },
            error: function(xhr, status, error) {
                // Always reset button state on error
                $btn.prop('disabled', false);
                $btn.html(originalHtml);

                try {
                    var errorResponse = JSON.parse(xhr.responseText);
                    alert("Error: " + (errorResponse.message || "Failed to save transaction."));
                } catch (e) {
                    alert("Failed to save transaction. Please try again.");
                }
            }
        });
    });


    // Add transaction button click handler
    $('.add-transaction').click(function() {
        var id = $(this).data('id');
        var paymentType = $(this).data('payment-type');
        var currency = $(this).data('currency');
        var mainAccount = $(this).data('main-account');
        var client = $(this).data('client');
        var supplier = $(this).data('supplier');
        var description = $(this).data('description');
        var amount = $(this).data('sold-amount');
        
        // Set transaction form data
        $('#transaction_payment_id').val(id);
        $('#transaction_payment_type').val(paymentType);
        $('#original_payment_currency').val(currency);
        $('#transaction_currency').val(currency); // Set default currency same as payment currency
        $('#transaction_main_account_id').val(mainAccount);
        
        // Set display information
        $('#trans-payment-type').text(paymentType);
        $('#trans-description').text(description);
        $('#totalAmount').text(`${currency} ${parseFloat(amount).toFixed(2)}`);
        $('#remainingAmount').text(`${currency} ${parseFloat(amount).toFixed(2)}`);
        
        // Get account name
        var accountName = $("#main_account_id option[value='" + mainAccount + "']").text();
        $('#trans-account').text(accountName);
        
        // Reset exchange rate field
        $('#exchange_rate').val('');
        $('#exchange_rate_group').hide();
        
        // Set today's date and current time
        var now = new Date();
        var today = now.toISOString().split('T')[0];
        $('#payment_date').val(today);
        
        // Format time as HH:MM:SS
        var hours = String(now.getHours()).padStart(2, '0');
        var minutes = String(now.getMinutes()).padStart(2, '0');
        var seconds = String(now.getSeconds()).padStart(2, '0');
        $('#payment_time').val(`${hours}:${minutes}:${seconds}`);
        
        $('#addTransactionModal').modal('show');
    });

    // Edit transaction
    $(document).on('click', '.edit-transaction', function() {
        var id = $(this).data('id');
        var amount = $(this).data('amount');
        var currency = $(this).data('currency');
        var date = $(this).data('date');
        var description = $(this).data('description');
        var receipt = $(this).data('receipt');
        var paymentId = $('#transaction_payment_id').val();
        var originalCurrency = $('#original_payment_currency').val();

        $('#edit_transaction_id').val(id);
        $('#edit_transaction_payment_id').val(paymentId);
        $('#edit_original_payment_currency').val(originalCurrency);
        $('#edit_payment_amount').val(amount);
        $('#edit_transaction_currency').val(currency);
        
        // Parse the datetime string
        var txDate = new Date(date);
        var formattedDate = txDate.toISOString().split('T')[0];
        
        // Format time as HH:MM:SS
        var hours = String(txDate.getHours()).padStart(2, '0');
        var minutes = String(txDate.getMinutes()).padStart(2, '0');
        var seconds = String(txDate.getSeconds()).padStart(2, '0');
        var formattedTime = `${hours}:${minutes}:${seconds}`;
        
        $('#edit_payment_date').val(formattedDate);
        $('#edit_payment_time').val(formattedTime);
        $('#edit_payment_description').val(description);
        $('#edit_receipt').val(receipt);
        
        // Show/hide exchange rate field based on currency
        if (currency !== originalCurrency) {
            $('#edit_exchange_rate_group').show();
            $('#edit_exchange_rate').prop('required', true);
            
            // Exchange rate comes directly from the database field
            // No need to extract from description
        } else {
            $('#edit_exchange_rate_group').hide();
            $('#edit_exchange_rate').prop('required', false);
        }
        
        // Add event listener for currency change
        $('#edit_transaction_currency').off('change').on('change', function() {
            var selectedCurrency = $(this).val();
            if (selectedCurrency !== originalCurrency) {
                $('#edit_exchange_rate_group').show();
                $('#edit_exchange_rate').prop('required', true);
            } else {
                $('#edit_exchange_rate_group').hide();
                $('#edit_exchange_rate').prop('required', false);
            }
        });
        
        $('#editTransactionModal').modal('show');
    });

    // Update transaction
     $('#updateTransaction').click(function() {
         var $btn = $(this);
         var originalHtml = $btn.html();
         $btn.prop('disabled', true);
         $btn.html('<i class="fas fa-spinner fa-spin mr-1"></i>Updating Transaction...');

         var selectedCurrency = $('#edit_transaction_currency').val();
         var originalCurrency = $('#edit_original_payment_currency').val();
         var description = $('#edit_payment_description').val();
         var exchangeRate = $('#edit_exchange_rate').val();
         
         // Get CSRF token
         var csrfToken = $('input[name="csrf_token"]').val();
         
         var formData = {
             transaction_id: $('#edit_transaction_id').val(),
             payment_id: $('#edit_transaction_payment_id').val(),
             payment_amount: $('#edit_payment_amount').val(),
             currency: selectedCurrency,
             original_currency: originalCurrency,
             exchange_rate: exchangeRate,
             payment_date: $('#edit_payment_date').val(),
             payment_time: $('#edit_payment_time').val(),
             payment_description: $('#edit_payment_description').val(),
             receipt: $('#edit_receipt').val(),
             csrf_token: csrfToken
         };

        $.ajax({
            url: '../api/additional_payment/update_additional_payment_transaction.php',
            type: 'POST',
            data: formData,
            success: function(response) {
                try {
                    var result = typeof response === 'object' ? response : JSON.parse(response);
                    if (result.success) {
                        alert("Transaction updated successfully.");
                        $('#editTransactionModal').modal('hide');
                        transactionManager.loadTransactionHistory($('#transaction_payment_id').val());
                    } else {
                        alert('Error: ' + (result.message || 'Unknown error occurred'));
                    }
                } catch (e) {

                    alert("Error: Invalid response from server.");
                }
                // Always reset button state after success handler
                $btn.prop('disabled', false);
                $btn.html(originalHtml);
            },
            error: function(xhr, status, error) {
                // Always reset button state on error
                $btn.prop('disabled', false);
                $btn.html(originalHtml);

                try {
                    var errorResponse = JSON.parse(xhr.responseText);
                    alert("Error: " + (errorResponse.message || "Failed to update transaction."));
                } catch (e) {
                    alert("Failed to update transaction. Please try again.");
                }
            }
        });
    });

    // Delete transaction
    $(document).on('click', '.delete-transaction', function() {
        var $btn = $(this);
        var originalHtml = $btn.html();
        if (confirm("Are you sure you want to delete this transaction?")) {
            $btn.prop('disabled', true);
            $btn.html('<i class="fas fa-spinner fa-spin mr-1"></i>');
            var id = $btn.data('id');
            var paymentId = $('#transaction_payment_id').val();

            // Get CSRF token from meta tag or hidden input
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ||
                             document.querySelector('input[name="csrf_token"]')?.value;
            
            $.ajax({
                url: '../api/additional_payment/delete_additional_payment_transaction.php',
                type: 'POST',
                data: {
                    transaction_id: id,
                    payment_id: paymentId,
                    csrf_token: csrfToken
                },
                success: function(response) {
                    try {
                        var result = typeof response === 'object' ? response : JSON.parse(response);
                        if (result.success) {
                            alert("Transaction deleted successfully.");
                            transactionManager.loadTransactionHistory(paymentId);
                        } else {
                            alert("Error: " + (result.message || "An unknown error occurred."));
                        }
                    } catch (e) {

                        alert("Error: Invalid response from server.");
                    }
                    // Always reset button state after success handler
                    $btn.prop('disabled', false);
                    $btn.html(originalHtml);
                },
                error: function(xhr, status, error) {
                    // Always reset button state on error
                    $btn.prop('disabled', false);
                    $btn.html(originalHtml);

                    try {
                        var errorResponse = JSON.parse(xhr.responseText);
                            alert("Error: " + (errorResponse.message || "Failed to delete transaction."));
                    } catch (e) {
                        alert("Failed to delete transaction. Please try again.");
                    }
                }
            });
        }
    });
});
function calculateProfit() {
    const baseAmount = parseFloat(document.getElementById('base_amount').value) || 0;
    const soldAmount = parseFloat(document.getElementById('sold_amount').value) || 0;
    const profit = soldAmount - baseAmount;
    document.getElementById('profit').value = profit.toFixed(2);
}

function calculateEditProfit() {
    const baseAmount = parseFloat(document.getElementById('edit_base_amount').value) || 0;
    const soldAmount = parseFloat(document.getElementById('edit_sold_amount').value) || 0;
    const profit = soldAmount - baseAmount;
    document.getElementById('edit_profit').value = profit.toFixed(2);
}

document.addEventListener('DOMContentLoaded', function() {
    // Calculate profit when the page loads if values exist
    calculateProfit();
    calculateEditProfit();

    // Add input event listeners for real-time calculation
    document.getElementById('base_amount').addEventListener('input', calculateProfit);
    document.getElementById('sold_amount').addEventListener('input', calculateProfit);
    document.getElementById('edit_base_amount').addEventListener('input', calculateEditProfit);
    document.getElementById('edit_sold_amount').addEventListener('input', calculateEditProfit);




    // Save Payment button click handler (DOMContentLoaded scope)
    $('#savePayment').click(function() {
        var $btn = $(this);
        var originalHtml = $btn.html();
        $btn.prop('disabled', true);
        $btn.html('<i class="fas fa-spinner fa-spin mr-1"></i>Saving Payment...');

        var form = $('#addPaymentForm');
        var formData = new FormData(form[0]);
        
        // Add checkbox values
        formData.append('is_from_supplier', $('#is_from_supplier').is(':checked') ? 1 : 0);
        formData.append('is_for_client', $('#is_for_client').is(':checked') ? 1 : 0);
        
        $.ajax({
            url: form.attr('action'),
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                try {
                    var result = typeof response === 'object' ? response : JSON.parse(response);
                    if (result.success) {
                        $('#addPaymentModal').modal('hide');
                        location.reload();
                    } else {
                        alert("Error: " + (result.message || "An unknown error occurred."));
                    }
                } catch (e) {

                    alert("Error: Invalid response from server.");
                }
                // Always reset button state after success handler
                $btn.prop('disabled', false);
                $btn.html(originalHtml);
            },
            error: function(xhr, status, error) {
                // Always reset button state on error
                $btn.prop('disabled', false);
                $btn.html(originalHtml);

                try {
                    var errorResponse = JSON.parse(xhr.responseText);
                    alert("Error: " + (errorResponse.message || "Failed to save payment."));
                } catch (e) {
                    alert("Failed to save payment. Please try again.");
                }
            }
        });
    });

    // Delete Payment button click handler
    $('.delete-payment').click(function() {
        var $btn = $(this);
        var originalHtml = $btn.html();
        var id = $btn.data('id');
        if (confirm("Are you sure you want to delete this payment?")) {
            $btn.prop('disabled', true);
            $btn.html('<i class="fas fa-spinner fa-spin mr-1"></i>Deleting...');

            // Get CSRF token from meta tag or hidden input
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ||
                             document.querySelector('input[name="csrf_token"]')?.value;
            
            $.ajax({
                url: '../api/additional_payment/delete_additional_payment.php',
                type: 'POST',
                data: {
                    action: 'delete',
                    id: id,
                    csrf_token: csrfToken
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.message || "Cannot delete payment. Please delete any associated transactions first.");
                    }
                    // Always reset button state after success handler
                    $btn.prop('disabled', false);
                    $btn.html(originalHtml);
                },
                error: function(xhr) {
                    // Always reset button state on error
                    $btn.prop('disabled', false);
                    $btn.html(originalHtml);

                    try {
                        var errorResponse = JSON.parse(xhr.responseText);
                            alert(errorResponse.message || "Error deleting payment. Please check if it has transactions.");
                    } catch (e) {
                        alert("Error deleting payment. Please check if it has transactions.");
                    }
                }
            });
        }
    });

    // Add Transaction button click handler
    $('.add-transaction').click(function() {
        var id = $(this).data('id');
        var paymentType = $(this).data('payment-type');
        var currency = $(this).data('currency');
        var mainAccount = $(this).data('main-account');
        var client = $(this).data('client');
        var isSupplier = $(this).data('is-supplier');
        
        $('#transaction_payment_id').val(id);
        $('#transaction_payment_type').val(paymentType);
        $('#original_payment_currency').val(currency);
        $('#transaction_currency').val(currency); // Set default currency same as payment currency
        $('#transaction_main_account_id').val(mainAccount);
        $('#transaction_client_id').val(client);
        $('#transaction_is_from_supplier').val(isSupplier);
        
        // Reset exchange rate field
        $('#exchange_rate').val('');
        $('#exchange_rate_group').hide();
        
        $('#addTransactionModal').modal('show');
    });

    // Handle client checkbox in add form
    $('#is_from_supplier').change(function() {
        if($(this).is(':checked')) {
            $('.client-group').show();
            $('#client_id').prop('required', true);
        } else {
            $('.client-group').hide();
            $('#client_id').prop('required', false);
        }
    });

    // Handle client checkbox in edit form
    $('#edit_is_from_supplier').change(function() {
        if($(this).is(':checked')) {
            $('.client-group').show();
            $('#edit_client_id').prop('required', true);
        } else {
            $('.client-group').hide();
            $('#edit_client_id').prop('required', false);
        }
    });

    // When editing payment, check if it has client
    $('.edit-payment').click(function() {
        const isSupplier = $(this).data('is-supplier');
        const clientId = $(this).data('client');
        
        if(isSupplier) {
            $('#edit_is_from_supplier').prop('checked', true);
            $('.client-group').show();
            $('#edit_client_id').prop('required', true);
            $('#edit_client_id').val(clientId);
        } else {
            $('#edit_is_from_supplier').prop('checked', false);
            $('.client-group').hide();
            $('#edit_client_id').prop('required', false);
        }
    });
});
