// Initialize when document is ready
jQuery(document).ready(function($) {
    // Initialize tooltips
    $('[data-toggle="tooltip"]').tooltip();
    
    // View maktob button click
    $(document).on('click', '.view-maktob', function(e) {
        e.preventDefault();
        var button = $(this);
        var subject = button.data('subject');
        var content = button.data('content');
        var company = button.data('company');
        var number = button.data('number');
        var date = button.data('date');
        var status = button.data('status');
        var language = button.data('language');
        var filePath = button.data('file-path');
        var pdfPath = button.data('pdf-path');
        
        // Set modal content
        $('#maktobSubject').text(subject);
        $('#maktobNumber').text(number);
        $('#maktobCompany').text(company);
        $('#maktobDate').text(date);
        $('#maktobContent').text(content);
        
        // Get translated language name
        var translatedLang;
        switch(language) {
            case 'dari':
                translatedLang = 'Dari';
                break;
            case 'pashto':
                translatedLang = 'Pashto';
                break;
            default:
                translatedLang = 'English';
        }
        $('#maktobLanguage').text(translatedLang);
        
        // Set the status indicator with translation
        if (status === 'sent') {
            $('#maktobStatus').html('<span class="badge-success"><i class="feather icon-check mr-1"></i>Sent</span>');
        } else {
            $('#maktobStatus').html('<span class="badge-warning"><i class="feather icon-clock mr-1"></i>Draft</span>');
        }

        // Display file links if available
        var fileLinksHtml = '';
        if (pdfPath) {
            fileLinksHtml += '<p><strong>📄 Letter PDF:</strong> <a href="../' + pdfPath + '" target="_blank">View Letter Document</a></p>';
        }
        if (filePath) {
            fileLinksHtml += '<p><strong>📎 Supporting Document:</strong> <a href="../' + filePath + '" target="_blank">View Attachment</a></p>';
        }
        $('#fileLinks').html(fileLinksHtml);
        
        // Show modal
        $('#viewMaktobModal').modal('show');
    });
    
    // Edit maktob button click
    $(document).on('click', '.edit-maktob', function(e) {
        e.preventDefault();
        var button = $(this);
        var id = button.data('id');
        var subject = button.data('subject');
        var content = button.data('content');
        var company = button.data('company');
        var number = button.data('number');
        var date = button.data('date');
        var language = button.data('language');
        
        // Set form values
        $('#edit_maktob_id').val(id);
        $('#edit_subject').val(subject);
        $('#edit_content').val(content);
        $('#edit_company_name').val(company);
        $('#edit_maktob_number').val(number);
        $('#edit_maktob_date').val(date);
        $('#edit_language').val(language);
        
        // Show modal
        $('#editMaktobModal').modal('show');
    });
    
    // Delete maktob button click
    $(document).on('click', '.delete-maktob', function(e) {
        e.preventDefault();
        var id = $(this).data('id');
        $('#delete_maktob_id').val(id);
        $('#deleteMaktobModal').modal('show');
    });
});
