$(document).ready(function() {
    $('#supplier').select2({
        theme: 'bootstrap-5',
        width: '100%',
        dropdownParent: $('#supplier').closest('.modal-body'),
        placeholder: 'select_supplier',
        allowClear: true
    });
    // Fetch supplier currency when supplier changes
                                  $('#supplier').on('select2:select change', function() {
                                      const supplierId = $(this).val();
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
                                  });

                                  // Fetch currency for initial value if exists
                                  const initialSupplierId = $('#supplier').val();
                                  if (initialSupplierId) {
                                      $('#supplier').trigger('change');
                                  }
    $('#soldTo').select2({
        theme: 'bootstrap-5',
        width: '100%',
        dropdownParent: $('#soldTo').closest('.modal-body'),
        placeholder: 'select_client',
        allowClear: true
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