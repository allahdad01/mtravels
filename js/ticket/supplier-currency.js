function fetchSupplierCurrency(supplierId) {


    if (supplierId) {
        fetch(`../api/ticket/get_supplier_currency.php?supplier_id=${supplierId}`)
            .then(response => {

                return response.json();
            })
            .then(data => {

                const currInput = document.getElementById('curr');
                if (data.currency) {
                    currInput.value = data.currency;

                } else {
                    currInput.value = '';

                }
            })
            .catch(error => {

            });
    } else {

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
