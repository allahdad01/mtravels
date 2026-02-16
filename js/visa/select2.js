$(document).ready(function() {
    $('#supplier').selectpicker({
        width: '100%',
        noneSelectedText: 'Select Supplier',
        liveSearch: true
    });
    // Fetch supplier currency when supplier changes
                                  $('#supplier').on('changed.bs.select change', function() {
                                      const supplierId = $(this).val();


                                      if (supplierId) {
                                          fetch(`../api/visa/get_supplier_currency.php?supplier_id=${supplierId}`)
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
                                  });

                                  // Fetch currency for initial value if exists
                                  const initialSupplierId = $('#supplier').val();
                                  if (initialSupplierId) {
                                      $('#supplier').trigger('change');
                                  }
    $('#soldTo').selectpicker({
        width: '100%',
        noneSelectedText: 'select_client',
        liveSearch: true
    });

    // Initialize country and visa type selects
    $('#country').selectpicker({
        width: '100%',
        noneSelectedText: 'select_country',
        liveSearch: true
    });

    $('#visaType').selectpicker({
        width: '100%',
        noneSelectedText: 'select_visa_type',
        liveSearch: true
    });

    $('#editCountry').selectpicker({
        width: '100%',
        noneSelectedText: 'select_country',
        liveSearch: true
    });

    $('#editVisaType').selectpicker({
        width: '100%',
        noneSelectedText: 'select_visa_type',
        liveSearch: true
    });

    $('#paidto').selectpicker({
        width: '100%',
        noneSelectedText: 'select_main_account',
        liveSearch: true
    });

    $('#editPaidTo').selectpicker({
        width: '100%',
        noneSelectedText: 'select_main_account',
        liveSearch: true
    });
});

function searchVisa() {
    // Get input value and convert to lowercase for case-insensitive search
    let input = document.getElementById('searchInput').value.toLowerCase();
    let table = document.querySelector('.table');
    let rows = table.getElementsByTagName('tr');

    // Loop through all table rows, starting from index 1 to skip header
    for (let i = 1; i < rows.length; i++) {
        let row = rows[i];
        let passportCell = row.getElementsByTagName('td')[6]; // Index 6 is the passport number column
        
        if (passportCell) {
            let passportNumber = passportCell.textContent || passportCell.innerText;
            
            // Show/hide row based on whether passport number contains the search input
            if (passportNumber.toLowerCase().indexOf(input) > -1) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        }
    }
}

// Add event listener for real-time search
document.getElementById('searchInput').addEventListener('input', function() {
    searchVisa();
});
