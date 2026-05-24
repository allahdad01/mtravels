function viewMemberDetails(bookingId) {
     // Show loading state
     showToast('info', '<?= __("loading") ?>... <?= __("please_wait") ?>');

    // Fetch member details
    fetch(`../api/umrah/get_member_details.php?booking_id=${bookingId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.member) {
                const member = data.member;

                // Personal Information
                document.getElementById('memberName').textContent = member.name || '-';
                document.getElementById('memberGender').textContent = member.gender || '-';
                document.getElementById('memberDob').textContent = member.dob || '-';
                document.getElementById('memberPassport').textContent = member.passport_number || '-';
                document.getElementById('memberPassportExpiry').textContent = member.passport_expiry || '-';
                document.getElementById('memberId').textContent = member.id_type || '-';
                document.getElementById('memberRemarks').textContent = member.remarks || '-';

                // Travel Information
                document.getElementById('memberEntryDate').textContent = member.entry_date || '-';
                document.getElementById('memberFlightDate').textContent = member.flight_date || '-';
                document.getElementById('memberReturnDate').textContent = member.return_date || '-';
                document.getElementById('memberDuration').textContent = member.duration ? member.duration + ' days' : '-';
                document.getElementById('memberRoomType').textContent = member.room_type || '-';

                // Member Photo
                 const photoDisplay = document.getElementById('memberPhotoDisplay');
                 if (member.photo_path) {
                     // Construct full image path
                     let photoSrc = member.photo_path;
                     // If path doesn't start with http, prepend base path
                     if (!photoSrc.startsWith('http')) {
                         // Remove leading slash and prepend correct base path
                         photoSrc = photoSrc.startsWith('/') ? photoSrc : '/' + photoSrc;
                         photoSrc = '' + photoSrc;
                     }
                     
                     photoDisplay.innerHTML = `
                         <img id="memberPhotoImg"
                              src="${photoSrc}" 
                              alt="Member Photo" 
                              style="max-width: 150px; max-height: 200px; border-radius: 0.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1);" 
                              loading="lazy">
                         <p style="color: #6b7280; font-size: 0.875rem; margin-top: 0.5rem;">Photo extracted from passport</p>
                     `;
                     
                     // Add error handler after image is created
                     const photoImg = document.getElementById('memberPhotoImg');
                     if (photoImg) {
                         photoImg.onerror = function() {
                             photoDisplay.innerHTML = `
                                 <div style="width: 150px; height: 200px; background: #f3f4f6; border: 2px dashed #d1d5db; border-radius: 0.5rem; display: flex; align-items: center; justify-content: center; margin: 0 auto; color: #9ca3af;">
                                     <i class="fas fa-image" style="font-size: 2rem;"></i>
                                 </div>
                                 <p style="color: #9ca3af; font-size: 0.875rem; margin-top: 0.5rem;">Photo not found</p>
                             `;
                         };
                     }
                 } else {
                     photoDisplay.innerHTML = `
                         <div style="width: 150px; height: 200px; background: #f3f4f6; border: 2px dashed #d1d5db; border-radius: 0.5rem; display: flex; align-items: center; justify-content: center; margin: 0 auto; color: #9ca3af;">
                             <i class="fas fa-image" style="font-size: 2rem;"></i>
                         </div>
                         <p style="color: #9ca3af; font-size: 0.875rem; margin-top: 0.5rem;">No photo available</p>
                     `;
                 }

                // Account Information
                document.getElementById('memberSoldTo').textContent = member.client_name || '-';
                document.getElementById('memberPaidTo').textContent = member.main_account_name || '-';
                document.getElementById('memberCreatedBy').textContent = member.created_by || '-';

                // Services Information
                const servicesContainer = document.getElementById('memberServices');
                if (member.services && member.services.length > 0) {
                    let servicesHTML = '';
                    member.services.forEach(service => {
                        const serviceType = service.service_type === 'all' ? 'All Services' :
                                          service.service_type === 'ticket' ? 'Ticket' :
                                          service.service_type === 'visa' ? 'Visa' :
                                          service.service_type === 'hotel' ? 'Hotel' :
                                          service.service_type === 'transport' ? 'Transport' : service.service_type;
                        const supplierName = service.supplier_name || 'No Supplier';
                        servicesHTML += '<div style="padding: 0.75rem; background: #f0f9ff; border-radius: 0.375rem; border-left: 3px solid #06b6d4; display: flex; align-items: flex-start; gap: 0.5rem;"><i class="fas fa-check" style="color: #06b6d4; margin-top: 0.1rem;"></i><div style="flex: 1;"><div style="color: #1f2937; font-weight: 600; font-size: 0.9rem;">' + serviceType + '</div><div style="color: #6b7280; font-size: 0.8rem; margin-top: 0.2rem;">Supplier: ' + supplierName + '</div></div></div>';
                    });
                    servicesContainer.innerHTML = servicesHTML;
                } else {
                    servicesContainer.innerHTML = '<div style="padding: 0.75rem; background: #f0f9ff; border-radius: 0.375rem; border-left: 3px solid #06b6d4; color: #6b7280; font-size: 0.85rem;">No services assigned</div>';
                }

                // Financial Information
                document.getElementById('memberPrice').textContent = member.price ? `${member.price} ${member.currency}` : '-';
                document.getElementById('memberSoldPrice').textContent = member.sold_price ? `${member.sold_price} ${member.currency}` : '-';
                document.getElementById('memberDiscount').textContent = member.discount ? `${member.discount} ${member.currency}` : '-';
                document.getElementById('memberProfit').textContent = member.profit ? `${member.profit} ${member.currency}` : '-';
                document.getElementById('memberPaid').textContent = member.paid ? `${member.paid} ${member.currency}` : '-';
                document.getElementById('memberBankPayment').textContent = member.received_bank_payment ? `${member.received_bank_payment} ${member.currency}` : '-';
                document.getElementById('memberReceiptNumber').textContent = member.bank_receipt_number || '-';
                document.getElementById('memberDue').textContent = member.due ? `${member.due} ${member.currency}` : '-';

                // Load date change history
                loadDateChangeHistory(bookingId);

                // Close loading and show modal
                $('#memberDetailsModal').modal('show');
                } else {
                showToast('error', data.message || '<?= __("failed_to_load_member_details") ?>');
                }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('error', '<?= __("failed_to_load_member_details") ?>');
        });
}
