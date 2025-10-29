// Modal Enhancements
document.addEventListener('DOMContentLoaded', function() {
    // Add smooth backdrop transition
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        modal.addEventListener('show.bs.modal', function() {
            document.body.classList.add('modal-open');
        });

        modal.addEventListener('hidden.bs.modal', function() {
            document.body.classList.remove('modal-open');
        });
    });

    // Keyboard Shortcuts
    document.addEventListener('keydown', function(event) {
        // Close modal on Escape key
        if (event.key === 'Escape') {
            const openModal = document.querySelector('.modal.show');
            if (openModal) {
                const closeButton = openModal.querySelector('.close');
                if (closeButton) {
                    closeButton.click();
                }
            }
        }
    });

    // Form Validation Enhancements
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
                
                // Highlight invalid fields
                const invalidFields = form.querySelectorAll(':invalid');
                invalidFields.forEach(field => {
                    field.classList.add('is-invalid');
                    field.addEventListener('input', function() {
                        field.classList.remove('is-invalid');
                    }, { once: true });
                });
            }
            form.classList.add('was-validated');
        });
    });

    // Dynamic Form Field Interactions
    const dynamicFields = {
        'refund_type': {
            element: document.getElementById('refund_type'),
            handler: function() {
                const refundAmountGroup = document.getElementById('refundAmountGroup');
                const refundAmount = document.getElementById('refund_amount');
                
                if (this.value === 'partial') {
                    refundAmountGroup.style.display = 'block';
                    refundAmount.setAttribute('required', 'required');
                } else {
                    refundAmountGroup.style.display = 'none';
                    refundAmount.removeAttribute('required');
                }
            }
        }
    };

    // Attach dynamic field handlers
    Object.values(dynamicFields).forEach(field => {
        if (field.element) {
            field.element.addEventListener('change', field.handler);
        }
    });

    // Modal Scroll Shadows
    const modalBodies = document.querySelectorAll('.modal-body');
    modalBodies.forEach(body => {
        body.addEventListener('scroll', function() {
            if (this.scrollTop > 0) {
                this.classList.add('scrolled');
            } else {
                this.classList.remove('scrolled');
            }
        });
    });

    // Prevent form submission on Enter key for certain inputs
    const preventEnterSubmit = (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
        }
    };

    const inputsToPrevent = document.querySelectorAll('input:not([type="submit"]):not([type="button"])');
    inputsToPrevent.forEach(input => {
        input.addEventListener('keydown', preventEnterSubmit);
    });
});

// Floating Label Enhancement
function setupFloatingLabels() {
    const floatingLabelInputs = document.querySelectorAll('.floating-label input, .floating-label textarea');
    
    floatingLabelInputs.forEach(input => {
        input.addEventListener('focus', function() {
            this.closest('.floating-label').classList.add('focused');
        });

        input.addEventListener('blur', function() {
            this.closest('.floating-label').classList.remove('focused');
        });
    });
}

// Initialize on DOM load
document.addEventListener('DOMContentLoaded', setupFloatingLabels); 