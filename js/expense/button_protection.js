// Button Protection for Expense Management
document.addEventListener('DOMContentLoaded', function() {
    // Protect Category Form submission
    const categoryForm = document.getElementById('categoryForm');
    if (categoryForm) {
        categoryForm.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn && !submitBtn.disabled) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Saving...';
            }
        });
    }

    // Protect Expense Form submission
    const expenseForm = document.getElementById('expenseForm');
    if (expenseForm) {
        expenseForm.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn && !submitBtn.disabled) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Saving...';
            }
        });
    }

    // Protect Expense Filter Form submission
    const expenseFilterForm = document.getElementById('expenseFilterForm');
    if (expenseFilterForm) {
        expenseFilterForm.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn && !submitBtn.disabled) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Applying...';
            }
        });
    }

    // Protect Date Range Form submission
    const dateRangeForm = document.getElementById('dateRangeForm');
    if (dateRangeForm) {
        dateRangeForm.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn && !submitBtn.disabled) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Applying...';
            }
        });
    }

    // Protect Export Comprehensive Report button
    const exportComprehensiveBtn = document.getElementById('exportComprehensiveReport');
    if (exportComprehensiveBtn) {
        exportComprehensiveBtn.addEventListener('click', function(e) {
            if (!this.disabled) {
                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Exporting...';
            }
        });
    }

    // Protect Edit Category buttons
    const editCategoryButtons = document.querySelectorAll('.edit-category');
    editCategoryButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!this.disabled) {
                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Loading...';
            }
        });
    });

    // Protect Delete Category buttons
    const deleteCategoryButtons = document.querySelectorAll('.delete-category');
    deleteCategoryButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!this.disabled) {
                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Deleting...';
            }
        });
    });

    // Protect Edit Expense buttons
    const editExpenseButtons = document.querySelectorAll('.edit-expense');
    editExpenseButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!this.disabled) {
                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Loading...';
            }
        });
    });

    // Protect Delete Expense buttons
    const deleteExpenseButtons = document.querySelectorAll('.delete-expense');
    deleteExpenseButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!this.disabled) {
                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Deleting...';
            }
        });
    });

    // Protect Print Category buttons
    const printCategoryButtons = document.querySelectorAll('.print-category');
    printCategoryButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!this.disabled) {
                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Generating...';
            }
        });
    });

    // Protect Chart Export buttons
    const exportButtons = document.querySelectorAll('[onclick*="exportChart"], [onclick*="exportToExcel"]');
    exportButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!this.disabled) {
                this.disabled = true;
                const originalText = this.innerHTML;
                this.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Exporting...';
                // Store original text for potential restoration
                this.setAttribute('data-original-text', originalText);
            }
        });
    });

    // Re-enable buttons on AJAX errors
    $(document).ajaxError(function(event, xhr, settings, thrownError) {
        // Re-enable all disabled buttons on any AJAX error
        const disabledButtons = document.querySelectorAll('button:disabled');
        disabledButtons.forEach(button => {
            if (button.innerHTML.includes('spinner')) {
                // Restore original button text
                if (button.id === 'exportComprehensiveReport') {
                    button.innerHTML = '<i class="feather icon-file-text"></i><span>Export Financial Report</span><i class="feather icon-download"></i>';
                } else if (button.classList.contains('edit-category') || button.classList.contains('edit-expense')) {
                    button.innerHTML = '<i class="feather icon-edit"></i>';
                } else if (button.classList.contains('delete-category') || button.classList.contains('delete-expense')) {
                    button.innerHTML = '<i class="feather icon-trash-2"></i>';
                } else if (button.classList.contains('print-category')) {
                    button.innerHTML = '<i class="feather icon-printer"></i>';
                } else if (button.hasAttribute('data-original-text')) {
                    button.innerHTML = button.getAttribute('data-original-text');
                } else if (button.type === 'submit') {
                    if (button.form && button.form.id === 'categoryForm') {
                        button.innerHTML = 'Save';
                    } else if (button.form && button.form.id === 'expenseForm') {
                        button.innerHTML = 'Save';
                    } else if (button.form && button.form.id === 'expenseFilterForm') {
                        button.innerHTML = '<i class="feather icon-search mr-1"></i>Apply Filter';
                    } else if (button.form && button.form.id === 'dateRangeForm') {
                        button.innerHTML = '<i class="feather icon-filter mr-2"></i>Apply Filter';
                    } else {
                        button.innerHTML = 'Save';
                    }
                }
                button.disabled = false;
            }
        });
    });

    // Re-enable buttons on successful AJAX completions
    $(document).ajaxSuccess(function(event, xhr, settings) {
        // Re-enable buttons after successful AJAX calls
        const disabledButtons = document.querySelectorAll('button:disabled');
        disabledButtons.forEach(button => {
            if (button.innerHTML.includes('spinner') && !button.classList.contains('delete-category') && !button.classList.contains('delete-expense')) {
                // Restore original button text for non-delete operations
                if (button.id === 'exportComprehensiveReport') {
                    button.innerHTML = '<i class="feather icon-file-text"></i><span>Export Financial Report</span><i class="feather icon-download"></i>';
                } else if (button.classList.contains('edit-category') || button.classList.contains('edit-expense')) {
                    button.innerHTML = '<i class="feather icon-edit"></i>';
                } else if (button.classList.contains('print-category')) {
                    button.innerHTML = '<i class="feather icon-printer"></i>';
                } else if (button.hasAttribute('data-original-text')) {
                    button.innerHTML = button.getAttribute('data-original-text');
                } else if (button.type === 'submit') {
                    if (button.form && button.form.id === 'categoryForm') {
                        button.innerHTML = 'Save';
                    } else if (button.form && button.form.id === 'expenseForm') {
                        button.innerHTML = 'Save';
                    } else if (button.form && button.form.id === 'expenseFilterForm') {
                        button.innerHTML = '<i class="feather icon-search mr-1"></i>Apply Filter';
                    } else if (button.form && button.form.id === 'dateRangeForm') {
                        button.innerHTML = '<i class="feather icon-filter mr-2"></i>Apply Filter';
                    } else {
                        button.innerHTML = 'Save';
                    }
                }
                button.disabled = false;
            }
        });
    });
});
