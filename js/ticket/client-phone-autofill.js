// Auto-fill phone number when a regular client is selected
document.addEventListener('DOMContentLoaded', function() {
    const soldToSelect = document.getElementById('soldTo');
    const phoneInput = document.getElementById('phone');
    
    if (soldToSelect && phoneInput) {
        // Listen for changes to the sold_to (client) dropdown
        soldToSelect.addEventListener('change', function() {
            const clientId = this.value;
            
            if (!clientId) {
                // Clear phone field if no client is selected
                phoneInput.value = '';
                return;
            }
            
            // Fetch client information including phone and type
            fetch(`../api/ticket/get_client_info.php?client_id=${clientId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Only auto-fill phone if client type is 'regular'
                        if (data.is_regular && data.phone) {
                            phoneInput.value = data.phone;
                        } else {
                            phoneInput.value = '';
                        }
                    } else {

                        phoneInput.value = '';
                    }
                })
                .catch(error => {

                    phoneInput.value = '';
                });
        });
    }
});
