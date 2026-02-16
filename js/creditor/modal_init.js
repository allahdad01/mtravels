// Ensure modal buttons work correctly
$('[data-toggle="modal"]').on('click', function() {
    var target = $(this).attr('data-target');
    $(target).modal('show');
});

// Handle z-index for nested modals
$(document).on('show.bs.modal', '.modal', function() {
    var zIndex = 1040 + (10 * $('.modal:visible').length);
    $(this).css('z-index', zIndex);
    setTimeout(function() {
        $('.modal-backdrop').not('.modal-stack').css('z-index', zIndex - 1).addClass('modal-stack');
    }, 0);
});

$(document).on('hidden.bs.modal', '.modal', function() {
    $('.modal:visible').length && $('body').addClass('modal-open');
});
