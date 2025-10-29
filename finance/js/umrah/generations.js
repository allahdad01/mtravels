function generateTazminAgreement(bookingId) {
    if (!bookingId) {
        Swal.fire({
            icon: 'error',
            title: '<?= __("error") ?>',
            text: '<?= __("invalid_booking") ?>'
        });
        return;
    }

    // Prompt for guarantor name
    Swal.fire({
        title: '<?= __("enter_guarantor_name") ?>',
        input: 'text',
        inputLabel: '<?= __("guarantor_name") ?>',
        showCancelButton: true,
        inputValidator: (value) => {
            if (!value) {
                return '<?= __("guarantor_name_required") ?>';
            }
        }
    }).then((result) => {
        if (result.isConfirmed) {
            var guarantorName = result.value;
            // Open Tazmin agreement in new window
            window.open('templates/tazmin_agreement_template.php?pilgrim_ids=' + bookingId + '&guarantor_name=' + encodeURIComponent(guarantorName), '_blank');
        }
    });
}

// Function to generate Tazmin for an entire family
function generateFamilyTazmin(familyId) {
    if (!familyId) {
        Swal.fire({
            icon: 'error',
            title: '<?= __("error") ?>',
            text: '<?= __("invalid_family") ?>'
        });
        return;
    }

    // First get all booking IDs for this family
    fetch('ajax/get_family_bookings.php?family_id=' + familyId)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.bookings && data.bookings.length > 0) {
                // Prompt for guarantor name
                Swal.fire({
                    title: '<?= __("enter_guarantor_name") ?>',
                    input: 'text',
                    inputLabel: '<?= __("guarantor_name") ?>',
                    showCancelButton: true,
                    inputValidator: (value) => {
                        if (!value) {
                            return '<?= __("guarantor_name_required") ?>';
                        }
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        var guarantorName = result.value;
                        // Open Tazmin agreement in new window with all booking IDs
                        const bookingIds = data.bookings.map(booking => booking.booking_id).join(',');
                        window.open('templates/tazmin_agreement_template.php?pilgrim_ids=' + bookingIds + '&guarantor_name=' + encodeURIComponent(guarantorName), '_blank');
                    }
                });
            } else {
                Swal.fire({
                    icon: 'warning',
                    title: '<?= __("no_members") ?>',
                    text: '<?= __("no_family_members_found") ?>'
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: '<?= __("error") ?>',
                text: '<?= __("failed_to_fetch_family_members") ?>'
            });
        });
}
