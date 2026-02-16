document.getElementById('supplier').addEventListener('change', function () {
    const supplierId = this.value;



    if (supplierId) {
        fetch(`../api/visa/get_supplier_currency.php?supplier_id=${supplierId}`)
            .then(response => {

                return response.json();
            })
            .then(data => {

                const currencyInput = document.getElementById('curr');
                if (data.currency) {
                    currencyInput.value = data.currency;


                } else {
                    currencyInput.value = '';

                }
            })
            .catch(error => {

            });
    } else {

        document.getElementById('curr').value = '';
    }
});
