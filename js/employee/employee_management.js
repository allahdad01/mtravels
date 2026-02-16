// Global toast function
function createToast(message, type = 'success') {
    // Create toast container if it doesn't exist
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.style.position = 'fixed';
        toastContainer.style.top = '20px';
        toastContainer.style.right = '20px';
        toastContainer.style.zIndex = '99999';
        document.body.appendChild(toastContainer);
    }

    // Create toast element
    const toast = document.createElement('div');
    toast.className = `toast-notification ${type}`;
    toast.innerHTML = `
        <div class="toast-content">
            <div class="toast-message">${message}</div>
            <button type="button" class="toast-close" onclick="this.parentElement.parentElement.remove()">×</button>
        </div>
    `;

    // Add to container
    toastContainer.appendChild(toast);

    // Force reflow to trigger animation
    toast.offsetHeight;

    // Show toast
    toast.classList.add('show');

    // Auto remove after delay
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);

    return toast;
}

document.addEventListener('DOMContentLoaded', function() {
    // DataTables removed - using server-side PHP filtering instead
    
    // Initialize modals with proper backdrop handling
    $('.modal').on('show.bs.modal', function() {
        const zIndex = 1040 + (10 * $('.modal:visible').length);
        $(this).css('z-index', zIndex);
        setTimeout(() => {
            $('.modal-backdrop').not('.modal-stack')
                .css('z-index', zIndex - 1)
                .addClass('modal-stack');
        });
    });

    // Handle modal close
    $('.modal').on('hidden.bs.modal', function() {
        if ($('.modal:visible').length) {
            $('body').addClass('modal-open');
        }
    });
});

// Global variable to store user ID for language selection
let selectedUserId = null;
let selectedGuarantorUserId = null;
let selectedTawseahUserId = null;
let selectedIkhtarUserId = null;
let selectedFineLetterUserId = null;
let selectedTerminationLetterUserId = null;

// Function to show language selection modal
function showLanguageModal(userId) {
    selectedUserId = userId;
    $('#languageSelectionModal').modal('show');
}

// Function to show guarantor letter language selection modal
function showGuarantorLanguageModal(userId) {
    selectedGuarantorUserId = userId;
    $('#guarantorLanguageModal').modal('show');
}

// Function to show tawseah language selection modal
function showTawseahModal(userId) {
    selectedTawseahUserId = userId;
    $('#tawseahModal').modal('show');
}

// Function to show ikhtar language selection modal
function showIkhtarModal(userId) {
    selectedIkhtarUserId = userId;
    $('#ikhtarModal').modal('show');
}

// Function to show fine letter language selection modal
function showFineLetterModal(userId) {
    selectedFineLetterUserId = userId;
    $('#fineLetterModal').modal('show');
}

// Function to show termination letter language selection modal
function showTerminationLetterModal(userId) {
    selectedTerminationLetterUserId = userId;
    $('#terminationLetterModal').modal('show');
}

// Function to generate agreement based on selected language
function generateAgreement(language) {
    if (!selectedUserId) {
        createToast('No user selected', 'danger');
        return;
    }

    // Get the rule input value at the time of click
    const ruleValue = document.getElementById('rule').value;

    // Close the language selection modal
    $('#languageSelectionModal').modal('hide');

    // Determine the correct agreement generation URL based on language
    let agreementUrl = '';
    switch(language) {
        case 'en':
            agreementUrl = '../api/employee/generate_user_agreement.php';
            break;
        case 'fa':
            agreementUrl = '../api/employee/generate_user_dari_agreement.php';
            break;
        case 'ps':
            agreementUrl = '../api/employee/generate_user_pashto_agreement.php';
            break;
        default:
            createToast('Invalid language selected', 'danger');
            return;
    }

    // Open the agreement in a new tab
    window.open(`${agreementUrl}?user_id=${selectedUserId}&rule=${encodeURIComponent(ruleValue)}`, '_blank');
}

function generateGuarantorLetter(language) {
    if (!selectedGuarantorUserId) {
        createToast('No user selected', 'danger');
        return;
    }

    // Close the language selection modal
    $('#guarantorLanguageModal').modal('hide');

    // Determine the correct guarantor letter generation URL based on language
    let guarantorLetterUrl = '';
    switch(language) {
        case 'fa':
            guarantorLetterUrl = '../api/employee/generate_guarantor_letter.php';
            break;
        case 'ps':
            guarantorLetterUrl = '../api/employee/generate_guarantor_pashto_letter.php';
            break;
        default:
            createToast('Invalid language selected', 'danger');
            return;
    }

    // Open the guarantor letter in a new tab
    window.open(`${guarantorLetterUrl}?user_id=${selectedGuarantorUserId}`, '_blank');
}

function generateTawseah(event, language) {
    event.preventDefault();

    if (!selectedTawseahUserId) {
        createToast('No user selected', 'danger');
        return;
    }

    // Get the takhaluf input value safely
    const takhalufValue = document.getElementById('takhaluf').value;
    const jobtitleValue = document.getElementById('job_title').value;

    // Close the modal
    $('#tawseahModal').modal('hide');

    // Determine URL
    let tawseahUrl = '';
    switch(language) {
        case 'fa':
            tawseahUrl = '../api/employee/generate_tawseah.php';
            break;
        case 'ps':
            tawseahUrl = '../api/employee/generate_tawseah_pashto.php';
            break;
        default:
            createToast('Invalid language selected', 'danger');
            return;
    }

    // Open in new tab with encoded value
    window.open(`${tawseahUrl}?user_id=${selectedTawseahUserId}&language=${language}&takhaluf=${encodeURIComponent(takhalufValue)}&job_title=${encodeURIComponent(jobtitleValue)}`, '_blank');
}

function generateIkhtar(event, language) {
    event.preventDefault();

    if (!selectedIkhtarUserId) {
        createToast('No user selected', 'danger');
        return;
    }

    const jobTitleInput = document.getElementById('job_title_ikhtar');
    if (!jobTitleInput) {
        createToast('Job title field not found.', 'danger');
        return;
    }

    const jobtitleValue = jobTitleInput.value.trim();
    if (!jobtitleValue) {
        createToast('Job title is required', 'warning');
        return;
    }

    $('#ikhtarModal').modal('hide');

    let ikhtarUrl = '';
    switch(language) {
        case 'fa':
            ikhtarUrl = '../api/employee/generate_ikhtar.php';
            break;
        case 'ps':
            ikhtarUrl = '../api/employee/generate_ikhtar_pashto.php';
            break;
        default:
            createToast('Invalid language selected', 'danger');
            return;
    }

    const finalUrl = `${ikhtarUrl}?user_id=${selectedIkhtarUserId}&language=${language}&job_title=${encodeURIComponent(jobtitleValue)}`;

    window.open(finalUrl, '_blank');
}

function generateFineLetter(event, language) {
    event.preventDefault();

    if (!selectedFineLetterUserId) {
        createToast('No user selected', 'danger');
        return;
    }

    const jobTitleInput = document.getElementById('job_title_fine');
    if (!jobTitleInput) {
        createToast('Job title field not found.', 'danger');
        return;
    }
    const takhalufInput = document.getElementById('takhaluf_fine');
    if (!takhalufInput) {
        createToast('Takhaluf field not found.', 'danger');
        return;
    }

    const jobtitleValue = jobTitleInput.value.trim();
    if (!jobtitleValue) {
        createToast('Job title is required', 'warning');
        return;
    }

    const takhalufValue = takhalufInput.value.trim();
    if (!takhalufValue) {
        createToast('Takhaluf is required', 'warning');
        return;
    }

    const fineAmountInput = document.getElementById('fine_amount');
    if (!fineAmountInput) {
        createToast('Fine amount field not found.', 'danger');
        return;
    }

    const fineAmountValue = fineAmountInput.value.trim();
    if (!fineAmountValue) {
        createToast('Fine amount is required', 'warning');
        return;
    }

    const currencyInput = document.getElementById('currency');
    if (!currencyInput) {
        createToast('Currency field not found.', 'danger');
        return;
    }

    const currencyValue = currencyInput.value.trim();
    if (!currencyValue) {
        createToast('Currency is required', 'warning');
        return;
    }

    $('#fineLetterModal').modal('hide');

    let fineLetterUrl = '';
    switch(language) {
        case 'fa':
            fineLetterUrl = '../api/employee/generate_fine.php';
            break;
        case 'ps':
            fineLetterUrl = '../api/employee/generate_fine_pashto.php';
            break;
        default:
            createToast('Invalid language selected', 'danger');
            return;
    }

    const finalUrl = `${fineLetterUrl}?user_id=${selectedFineLetterUserId}&language=${language}&job_title=${encodeURIComponent(jobtitleValue)}&takhaluf=${encodeURIComponent(takhalufValue)}&fine_amount=${encodeURIComponent(fineAmountValue)}&currency=${encodeURIComponent(currencyValue)}`;

    window.open(finalUrl, '_blank');
}

function generateTerminationLetter(event, language) {
    event.preventDefault();

    if (!selectedTerminationLetterUserId) {
        createToast('No user selected', 'danger');
        return;
    }

    const jobTitleInput = document.getElementById('job_title_termination');
    if (!jobTitleInput) {
        createToast('Job title field not found.', 'danger');
        return;
    }

    const jobtitleValue = jobTitleInput.value.trim();
    if (!jobtitleValue) {
        createToast('Job title is required', 'warning');
        return;
    }

    const terminationDateInput = document.getElementById('termination_date');
    if (!terminationDateInput) {
        createToast('Termination date field not found.', 'danger');
        return;
    }

    const terminationDateValue = terminationDateInput.value.trim();
    if (!terminationDateValue) {
        createToast('Termination date is required', 'warning');
        return;
    }

    $('#terminationLetterModal').modal('hide');

    let terminationLetterUrl = '';
    switch(language) {
        case 'fa':
            terminationLetterUrl = '../api/employee/generate_termination.php';
            break;
        case 'ps':
            terminationLetterUrl = '../api/employee/generate_termination_pashto.php';
            break;
        default:
            createToast('Invalid language selected', 'danger');
            return;
    }

    const finalUrl = `${terminationLetterUrl}?user_id=${selectedTerminationLetterUserId}&language=${language}&job_title=${encodeURIComponent(jobtitleValue)}&termination_date=${encodeURIComponent(terminationDateValue)}`;

    window.open(finalUrl, '_blank');
}

function terminateEmployee(employeeId, employeeName) {
    $('#terminateEmployeeId').val(employeeId);
    $('#terminateEmployeeName').text(employeeName);
    $('#terminationModal').modal('show');
}

function reinstateEmployee(employeeId, employeeName) {
    if (confirm('Are you sure you want to reinstate ' + employeeName + '?')) {
        $.post('../api/employee/terminate_employee.php', {
            employee_id: employeeId,
            action: 'reinstate',
            csrf_token: window.csrfToken || ''
        })
        .done(function(response) {
            if (response.success) {
                location.reload();
            } else {
                createToast(response.message || 'An error occurred', 'danger');
            }
        })
        .fail(function() {
            createToast('An error occurred', 'danger');
        });
    }
}

$('#terminationForm').on('submit', function(e) {
    e.preventDefault();

    $.post('../api/employee/terminate_employee.php', {
        employee_id: $('#terminateEmployeeId').val(),
        reason: $('#termination_reason').val(),
        action: 'terminate',
        csrf_token: window.csrfToken || ''
    })
    .done(function(response) {
        if (response.success) {
            $('#terminationModal').modal('hide');
            location.reload();
        } else {
            createToast(response.message || 'An error occurred', 'danger');
        }
    })
    .fail(function() {
        createToast('An error occurred', 'danger');
    });
});

function showAddEmployeeModal() {
    // Redirect to add employee page
    window.location.href = 'add_employee.php';
}

// Add CSS to head
const style = document.createElement('style');
style.textContent = `
    #toast-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 99999;
    }
    .toast-notification {
        min-width: 300px;
        margin-bottom: 10px;
        padding: 15px;
        border-radius: 4px;
        font-size: 14px;
        opacity: 0;
        transform: translateX(100%);
        transition: all 0.3s ease-in-out;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
    .toast-notification.show {
        opacity: 1;
        transform: translateX(0);
    }
    .toast-notification.success {
        background-color: #d4edda;
        border-color: #c3e6cb;
        color: #155724;
    }
    .toast-notification.danger {
        background-color: #f8d7da;
        border-color: #f5c6cb;
        color: #721c24;
    }
    .toast-notification.warning {
        background-color: #fff3cd;
        border-color: #ffeaa7;
        color: #856404;
    }
    .toast-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .toast-message {
        flex-grow: 1;
        margin-right: 10px;
    }
    .toast-close {
        background: none;
        border: none;
        font-size: 20px;
        font-weight: bold;
        color: inherit;
        cursor: pointer;
        padding: 0 5px;
    }
    .toast-close:hover {
        opacity: 0.7;
    }
`;
document.head.appendChild(style);
