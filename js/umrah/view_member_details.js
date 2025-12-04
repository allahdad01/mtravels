function viewMemberDetails(bookingId) {
    // Show loading state
    Swal.fire({
        title: '<?= __("loading") ?>',
        text: '<?= __("please_wait") ?>',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // Fetch member details
    fetch(`../api/umrah/get_member_details.php?booking_id=${bookingId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.member) {
                const member = data.member;

                // Update modal fields
                document.getElementById('memberName').textContent = member.name;
                document.getElementById('memberGender').textContent = member.gender;
                document.getElementById('memberDob').textContent = member.dob;
                document.getElementById('memberPassport').textContent = member.passport_number;
                document.getElementById('memberPassportExpiry').textContent = member.passport_expiry;
                document.getElementById('memberId').textContent = member.id_type;
                document.getElementById('memberRemarks').textContent = member.remarks || '-';

                document.getElementById('memberEntryDate').textContent = member.entry_date;
                document.getElementById('memberFlightDate').textContent = member.flight_date;
                document.getElementById('memberReturnDate').textContent = member.return_date;
                document.getElementById('memberDuration').textContent = member.duration;
                document.getElementById('memberRoomType').textContent = member.room_type;
                document.getElementById('memberDiscount').textContent = `${member.discount} ${member.currency}`;
                document.getElementById('memberPrice').textContent = `${member.price} ${member.currency}`;
                document.getElementById('memberSoldPrice').textContent = `${member.sold_price} ${member.currency}`;

                document.getElementById('memberProfit').textContent = `${member.profit} ${member.currency}`;
                document.getElementById('memberPaid').textContent = `${member.paid} ${member.currency}`;
                document.getElementById('memberBankPayment').textContent = `${member.received_bank_payment} ${member.currency}`;
                document.getElementById('memberReceiptNumber').textContent = member.bank_receipt_number || '-';
                document.getElementById('memberDue').textContent = `${member.due} ${member.currency}`;

                // Load date change history
                loadDateChangeHistory(bookingId);

                // Close loading and show modal
                Swal.close();
                $('#memberDetailsModal').modal('show');
            } else {
                Swal.fire({
                    icon: 'error',
                    title: '<?= __("error") ?>',
                    text: data.message || '<?= __("failed_to_load_member_details") ?>'
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: '<?= __("error") ?>',
                text: '<?= __("failed_to_load_member_details") ?>'
            });
        });
}