// Set currency value based on supplier selection
$('#supplier').on('change', function() {
    var supplierId = $(this).val();
    if (supplierId) {
        $.ajax({
            url: 'ajax/get_supplier_currency.php',
            type: 'POST',
            data: {supplier_id: supplierId},
            dataType: 'json',
            success: function(response) {
                if (response.currency) {
                    $('#curr').val(response.currency);
                }
            }
        });
    }
}); 