function fetchSupplierCurrency(supplierId) {
    console.log('Selected Supplier ID:', supplierId);

    if (supplierId) {
        fetch(`get_supplier_currency.php?supplier_id=${supplierId}`)
            .then(response => {
                console.log('Response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                const currInput = document.getElementById('curr');
                if (data.currency) {
                    currInput.value = data.currency;
                    console.log('Currency input updated to:', data.currency);
                } else {
                    currInput.value = '';
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
}

// Handle Select2 select event
$('#supplier').on('select2:select', function () {
    const supplierId = $(this).val();
    fetchSupplierCurrency(supplierId);
});

// Handle regular change event
$('#supplier').on('change', function() {
    const supplierId = $(this).val();
    fetchSupplierCurrency(supplierId);
});

// Fetch currency for initial value if exists
$(document).ready(function() {
    const initialSupplierId = $('#supplier').val();
    if (initialSupplierId) {
        fetchSupplierCurrency(initialSupplierId);
    }
}); 