         // Function to delete weight
         function deleteWeight(weightId) {
            // Get the button that was clicked
            const clickedBtn = event?.target?.closest('button') || document.activeElement;
            let originalContent = '';
            
            Swal.fire({
                title: 'Are you sure you want to delete this weight',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes delete it',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Store original content and show loading state if button found
                    if (clickedBtn && clickedBtn.tagName === 'BUTTON') {
                        originalContent = clickedBtn.innerHTML;
                        clickedBtn.disabled = true;
                        clickedBtn.innerHTML = '<i class="feather icon-loader"></i>';
                    }
                    
                    $.ajax({
                        url: '../api/ticket_weight/delete_weight.php',
                        type: 'POST',
                        data: { id: weightId },
                        success: function(response) {
                            // Restore button state if button was found
                            if (clickedBtn && clickedBtn.tagName === 'BUTTON' && originalContent) {
                                clickedBtn.disabled = false;
                                clickedBtn.innerHTML = originalContent;
                            }
                            
                            try {
                                const result = JSON.parse(response);
                                if (result.success) {
                                    showToast('Weight deleted successfully', 'success');
                                    location.reload();
                                } else {
                                    showToast(result.message || 'Failed to delete weight', 'error');
                                }
                            } catch (e) {
                                showToast('Error processing request', 'error');
                            }
                        },
                        error: function() {
                            // Restore button state if button was found
                            if (clickedBtn && clickedBtn.tagName === 'BUTTON' && originalContent) {
                                clickedBtn.disabled = false;
                                clickedBtn.innerHTML = originalContent;
                            }
                            showToast('Error deleting weight', 'error');
                        }
                    });
                }
            });
        }

        // Calculate profit automatically in edit modal
        $('#editBasePrice, #editSoldPrice').on('input', function() {
            const basePrice = parseFloat($('#editBasePrice').val()) || 0;
            const soldPrice = parseFloat($('#editSoldPrice').val()) || 0;
            const profit = soldPrice - basePrice;
            $('#editProfit').val(profit.toFixed(2));
        });

        // Handle edit form submission
        $('#editWeightForm').on('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = $(this).find('button[type="submit"]');
            const originalText = submitBtn.html();
            
            // Disable button and show loading state
            submitBtn.prop('disabled', true);
            submitBtn.html('<i class="feather icon-loader mr-2"></i>Processing...');
            
            const formData = new FormData(this);
            
            $.ajax({
                url: '../api/ticket_weight/update_weight.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    // Restore button state
                    submitBtn.prop('disabled', false);
                    submitBtn.html(originalText);
                    
                    try {
                        const result = JSON.parse(response);
                        if (result.success) {
                            showToast('Weight updated successfully', 'success');
                            location.reload();
                        } else {
                            showToast(result.message || 'Failed to update weight', 'error');
                        }
                    } catch (e) {
                        showToast('Error processing request', 'error');
                    }
                },
                error: function() {
                    // Restore button state
                    submitBtn.prop('disabled', false);
                    submitBtn.html(originalText);
                    showToast('Error updating weight', 'error');
                }
            });
        });

                // Function to edit weight
                function editWeight(weightId) {
                    $.ajax({
                        url: '../api/ticket_weight/get_weight.php',
                        type: 'GET',
                        data: { id: weightId },
                        success: function(response) {
                            try {
                                const result = JSON.parse(response);
                                if (result.success) {
                                    const weight = result.weight;
                                    
                                    // Populate the edit form
                                    $('#editWeightId').val(weight.id);
                                    $('#editWeight').val(weight.weight);
                                    $('#editBasePrice').val(weight.base_price);
                                    $('#editSoldPrice').val(weight.sold_price);
                                    $('#editMarketExchangeRate').val(weight.market_exchange_rate);
                                    $('#editExchangeRate').val(weight.exchange_rate);
                                    $('#editProfit').val(weight.profit);
                                    $('#editRemarks').val(weight.remarks);
                                    
                                    // Show the modal
                                    $('#editWeightModal').modal('show');
                                } else {
                                    alert(result.message || 'Failed to load weight details');
                                }
                            } catch (e) {
                                alert('Error loading weight details');
                            }
                        },
                        error: function() {
                            showToast('Error loading weight details', 'error');
                        }
                    });
                }

                function deleteTransaction(transactionId, reference_id, amount) {
                    // Get the button that was clicked
                    const clickedBtn = event?.target?.closest('button') || document.activeElement;
                    let originalContent = '';
                    
                    Swal.fire({
                        title: 'Are you sure you want to delete this transaction',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Yes delete it',
                        cancelButtonText: 'Cancel'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Store original content and show loading state if button found
                            if (clickedBtn && clickedBtn.tagName === 'BUTTON') {
                                originalContent = clickedBtn.innerHTML;
                                clickedBtn.disabled = true;
                                clickedBtn.innerHTML = '<i class="feather icon-loader"></i>';
                            }
                            
                            $.ajax({
                                url: '../api/ticket_weight/delete_weight_transaction.php',
                                type: 'POST',
                                dataType: 'json',
                                data: {
                                    transaction_id: transactionId,
                                    weight_id: reference_id,
                                    amount: amount
                                },
                                success: function(result) {
                                    // Restore button state if button was found
                                    if (clickedBtn && clickedBtn.tagName === 'BUTTON' && originalContent) {
                                        clickedBtn.disabled = false;
                                        clickedBtn.innerHTML = originalContent;
                                    }
                                    
                                    if (result && result.success) {
                                        showToast(result.message || 'Transaction deleted successfully', 'success');
                                        location.reload();
                                    } else {
                                        showToast(result?.message || 'Failed to delete transaction', 'error');
                                    }
                                },
                                error: function(xhr, status, error) {
                                    // Restore button state if button was found
                                    if (clickedBtn && clickedBtn.tagName === 'BUTTON' && originalContent) {
                                        clickedBtn.disabled = false;
                                        clickedBtn.innerHTML = originalContent;
                                    }
                                    showToast('Error processing request', 'error');
                                }
                            });
                        }
                    });
                }
