function editVisa(id) {
    fetch(`fetch_visa_by_id.php?id=${id}`)
        .then(response => response.json())
        .then(visa => {
            if (visa) {
                // Show the modal
                $('#editVisaModal').modal('show');
                
                // Populate the fields with database values
                document.getElementById('editVisaId').value = visa.id;

                // Set supplier and sold-to dropdowns
                const editSupplier = document.getElementById('editSupplier');
                if (editSupplier) {
                    editSupplier.value = visa.supplier; // Match supplier ID
                }

                const editSoldTo = document.getElementById('editSoldTo');
                if (editSoldTo) {
                    editSoldTo.value = visa.sold_to; // Match client ID
                }

                // Populate other fields
                document.getElementById('editPhone').value = visa.phone;
                document.getElementById('editTitle').value = visa.title;
                document.getElementById('editGender').value = visa.gender;
                document.getElementById('editApplicantName').value = visa.applicant_name;
                document.getElementById('editPassportNumber').value = visa.passport_number;
                
                // Set country dropdown - make sure the option exists
                const editCountry = document.getElementById('editCountry');
                if (editCountry) {
                    // Check if the country exists in the dropdown
                    let countryExists = false;
                    for (let i = 0; i < editCountry.options.length; i++) {
                        if (editCountry.options[i].value === visa.country) {
                            countryExists = true;
                            break;
                        }
                    }
                    
                    // If country exists in dropdown, set it
                    if (countryExists) {
                        editCountry.value = visa.country;
                    } else {
                        // If country doesn't exist in dropdown, add it
                        const newOption = document.createElement('option');
                        newOption.value = visa.country;
                        newOption.text = visa.country;
                        editCountry.add(newOption);
                        editCountry.value = visa.country;
                    }
                }
                
                document.getElementById('editVisaType').value = visa.visa_type;
                document.getElementById('editReceiveDate').value = visa.receive_date;
                document.getElementById('editAppliedDate').value = visa.applied_date;
                document.getElementById('editIssuedDate').value = visa.issued_date || ''; // Handle nullable field
                document.getElementById('editBase').value = visa.base;
                document.getElementById('editSold').value = visa.sold;
                document.getElementById('editPro').value = visa.profit;
                document.getElementById('editCurrency').value = visa.currency;
                document.getElementById('editStatus').value = visa.status;
                document.getElementById('editRemarks').value = visa.remarks;
                
                // Set the paid_to/main account dropdown
                const editPaidTo = document.getElementById('editPaidTo');
                if (editPaidTo) {
                    editPaidTo.value = visa.paid_to;
                }
                
                // Log for debugging
                console.log('Country from database:', visa.country);
                console.log('Country dropdown value after setting:', document.getElementById('editCountry').value);
                console.log('Paid To value:', visa.paid_to);
            } else {
                console.error('Visa not found');
            }
        })
        .catch(error => console.error('Error fetching visa details:', error));
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
        
        console.log('Original values stored - Base:', originalBase, 'Sold:', originalSold);
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

        fetch('update_visa.php', {
            method: 'POST',
            body: formData,
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('visa_updated_successfully');
                $('#editVisaModal').modal('hide');
                location.reload(); // Refresh page after updating
            } else {
                    alert('error_updating_visa: ' + data.message);
                // Re-enable the button if there's an error
                submitButton.disabled = false;
                submitButton.innerHTML = 'save_changes';
            }
        })
        .catch(error => {
            console.error('Error updating visa:', error);
            alert('an_unexpected_error_occurred');
            // Re-enable the button if there's an error
            submitButton.disabled = false;
            submitButton.innerHTML = 'save_changes';
        });
    });
});