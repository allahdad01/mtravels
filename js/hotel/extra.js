// Initialize form validation
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();

// Add animation to new transactions
function animateNewTransaction(row) {
    row.classList.add('new-transaction');
    setTimeout(() => {
        row.classList.remove('new-transaction');
    }, 2000);
}

// Format currency
function formatCurrency(amount, currency = 'USD') {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency
    }).format(amount);
}

// Update payment sections visibility
function updatePaymentSections(currency) {
    const usdSection = document.getElementById('usdSection');
    const afsSection = document.getElementById('afsSection');
    
    if (currency === 'USD') {
        usdSection.style.display = 'block';
        afsSection.style.display = 'none';
    } else if (currency === 'AFS') {
        usdSection.style.display = 'none';
        afsSection.style.display = 'block';
    } else {
        usdSection.style.display = 'none';
        afsSection.style.display = 'none';
    }
}

// Initialize tooltips
$(function () {
    $('[data-toggle="tooltip"]').tooltip();
});
