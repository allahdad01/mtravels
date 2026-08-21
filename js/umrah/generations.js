function generateAgreement(bookingId) {
    if (!bookingId) {
        showToast('error', 'Invalid booking');
        return;
    }
    window.open('../api/umrah/generate_umrah_agreement.php?booking_id=' + bookingId, '_blank');
}

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
                 // Prompt for language selection
                 Swal.fire({
                     title: 'Select Language',
                     html: `
                         <div style="display: flex; gap: 10px; justify-content: center; margin-bottom: 15px;">
                             <label style="cursor: pointer; padding: 10px 20px; border: 2px solid #ddd; border-radius: 5px;">
                                 <input type="radio" name="language" value="ps" checked> Pashto (پشتو)
                             </label>
                             <label style="cursor: pointer; padding: 10px 20px; border: 2px solid #ddd; border-radius: 5px;">
                                 <input type="radio" name="language" value="dari"> Dari (دری)
                             </label>
                         </div>
                     `,
                     input: 'text',
                     inputLabel: 'Guarantor name',
                     inputPlaceholder: 'Enter guarantor name',
                     showCancelButton: true,
                     confirmButtonText: 'Generate',
                     inputValidator: (value) => {
                         if (!value) {
                             return 'Guarantor name required';
                         }
                     }
                 }).then((result) => {
                     if (result.isConfirmed) {
                         var guarantorName = result.value;
                         var language = document.querySelector('input[name="language"]:checked').value;
                         // Open Tazmin agreement in new window with all booking IDs
                         const bookingIds = data.bookings.map(booking => booking.booking_id).join(',');
                         window.open('../api/umrah/tazmin_agreement_template.php?pilgrim_ids=' + bookingIds + '&guarantor_name=' + encodeURIComponent(guarantorName) + '&language=' + language, '_blank');
                     }
                 });
             } else {
                 showToast('warning', 'No family members found');
             }
         })
         .catch(error => {

             showToast('error', 'Failed to fetch family members');
         });
 }
