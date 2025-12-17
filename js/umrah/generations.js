function generateTazminAgreement(bookingId) {
     if (!bookingId) {
         showToast('error', '<?= __("invalid_booking") ?>');
         return;
     }

    // Prompt for guarantor name
    Swal.fire({
        title: 'Eenter guarantor name',
        input: 'text',
        inputLabel: 'Guarantor Name',
        showCancelButton: true,
        inputValidator: (value) => {
            if (!value) {
                return 'Guarantor name required';
            }
        }
    }).then((result) => {
        if (result.isConfirmed) {
            var guarantorName = result.value;
            // Open Tazmin agreement in new window
            window.open('../api/umrah/tazmin_agreement_template.php?pilgrim_ids=' + bookingId + '&guarantor_name=' + encodeURIComponent(guarantorName), '_blank');
        }
    });
}

// Function to generate Tazmin for an entire family
 function generateFamilyTazmin(familyId) {
     if (!familyId) {
         showToast('error', 'Invalid family');
         return;
     }

    // First get all booking IDs for this family
    fetch('../api/umrah/get_family_bookings.php?family_id=' + familyId)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.bookings && data.bookings.length > 0) {
                // Prompt for guarantor name
                Swal.fire({
                    title: 'Enter guarantor name',
                    input: 'text',
                    inputLabel: 'Guarantor name',
                    showCancelButton: true,
                    inputValidator: (value) => {
                        if (!value) {
                            return 'Guarantor name required';
                        }
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        var guarantorName = result.value;
                        // Open Tazmin agreement in new window with all booking IDs
                        const bookingIds = data.bookings.map(booking => booking.booking_id).join(',');
                        window.open('../api/umrah/tazmin_agreement_template.php?pilgrim_ids=' + bookingIds + '&guarantor_name=' + encodeURIComponent(guarantorName), '_blank');
                    }
                });
            } else {
                showToast('warning', 'No family members found');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('error', 'Failed to fetch family members');
        });
}
