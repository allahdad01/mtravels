document.getElementById('supplier').addEventListener('change', function () {
    const supplierId = this.value;

    console.log('Selected Supplier ID:', supplierId);

    if (supplierId) {
        fetch(`get_supplier_currency.php?supplier_id=${supplierId}`)
            .then(response => {
                console.log('Response status:', response.status); // Log status
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data); // Log full response
                const currencyInput = document.getElementById('curr');
                if (data.currency) {
                    currencyInput.value = data.currency;

                    console.log('Currency input updated to:', data.currency);
                } else {
                    currencyInput.value = '';
                    console.warn('No currency found in response!');
                }
            })
            .catch(error => {
                console.error('Error fetching supplier currency:', error);
            });
    } else {
        console.log('No supplier selected, clearing input.');
        document.getElementById('curr').value = '';
    }
});