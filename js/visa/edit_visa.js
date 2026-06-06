function editVisa(id) {
    fetch(`../api/visa/fetch_visa_by_id.php?id=${id}`)
        .then(response => response.json())
        .then(visa => {
            if (visa) {
                // Show the modal
                $('#editVisaModal').modal('show');
                
                // Populate the fields with database values
                document.getElementById('editVisaId').value = visa.id;

                // Set supplier and sold-to dropdowns
                $('#editSupplier').selectpicker('val', visa.supplier);
                $('#editSoldTo').selectpicker('val', visa.sold_to);

                // Populate other fields
                document.getElementById('editPhone').value = visa.phone;
                document.getElementById('editTitle').value = visa.title;
                document.getElementById('editGender').value = visa.gender;
                document.getElementById('editApplicantName').value = visa.applicant_name;
                document.getElementById('editPassportNumber').value = visa.passport_number;
                
                // Set country and visa type dropdowns
                $('#editCountry').selectpicker('val', visa.country);
                $('#editVisaType').selectpicker('val', visa.visa_type);
                document.getElementById('editReceiveDate').value = visa.receive_date;
                document.getElementById('editAppliedDate').value = visa.applied_date;
                document.getElementById('editIssuedDate').value = visa.issued_date || ''; // Handle nullable field
                document.getElementById('editBase').value = visa.base;
                document.getElementById('editSold').value = visa.sold;
                document.getElementById('editPro').value = visa.profit;
                document.getElementById('editCurrency').value = visa.currency;
                document.getElementById('editRemarks').value = visa.remarks;
                
                // Set the paid_to/main account dropdown
                $('#editPaidTo').selectpicker('val', visa.paid_to);
                
                // Log for debugging



            } else {

            }
        })

}

 // Add event listeners for edit form profit calculation
 document.addEventListener('DOMContentLoaded', () => {
    const editBaseInput = document.getElementById('editBase');
    const editSoldInput = document.getElementById('editSold');
    const editProInput = document.getElementById('editPro');
    
    // Store original values when the modal opens
    let originalBase = 0;
    let originalSold = 0;
    
    // When the edit modal is shown, store the original values
    $('#editVisaModal').on('shown.bs.modal', function() {
        originalBase = parseFloat(editBaseInput.value) || 0;
        originalSold = parseFloat(editSoldInput.value) || 0;
        

    });

    // Function to calculate and update the profit field
    function calculateEditPro() {
        const base = parseFloat(editBaseInput.value) || 0;
        const sold = parseFloat(editSoldInput.value) || 0;
        const pro = sold - base;
        
        editProInput.value = pro.toFixed(2);
    }

    // Add event listeners for real-time calculation
    editBaseInput.addEventListener('input', calculateEditPro);
    editSoldInput.addEventListener('input', calculateEditPro);
    
    // Handle form submission for updating visa
    document.getElementById('editVisaForm').addEventListener('submit', function (event) {
        event.preventDefault();
        
        // Disable the submit button
        const submitButton = this.querySelector('button[type="submit"]');
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> processing...';
        
        // Add original values to the form data
        const formData = new FormData(this);
        formData.append('originalBase', originalBase);
        formData.append('originalSold', originalSold);

        fetch('../api/visa/update_visa.php', {
            method: 'POST',
            body: formData,
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('visa_updated_successfully', 'success');
                $('#editVisaModal').modal('hide');
                location.reload(); // Refresh page after updating
            } else {
                showToast('error_updating_visa: ' + data.message, 'error');
                // Re-enable the button if there's an error
                submitButton.disabled = false;
                submitButton.innerHTML = 'save_changes';
            }
        })
        .catch(error => {

            showToast('an_unexpected_error_occurred', 'error');
            // Re-enable the button if there's an error
            submitButton.disabled = false;
            submitButton.innerHTML = 'save_changes';
        });
    });

    // Auto-update currency when supplier changes in edit modal
    const editSupplierSelect = document.getElementById('editSupplier');
    if (editSupplierSelect) {
        editSupplierSelect.addEventListener('change', function () {
            const supplierId = this.value;
            const currencyInput = document.getElementById('editCurrency');
            if (!supplierId || !currencyInput) return;

            fetch(`../api/visa/get_supplier_currency.php?supplier_id=${supplierId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.currency) {
                        currencyInput.value = data.currency;
                    }
                })
                .catch(() => {});
        });
    }
});
