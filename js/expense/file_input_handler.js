// Update custom file input label with selected filename
$(document).on('change', '.custom-file-input', function() {
    let fileName = $(this).val().split('\\').pop();
    if (fileName) {
        $(this).next('.custom-file-label').html(fileName);
    } else {
        $(this).next('.custom-file-label').html('Choose File');
    }
});
