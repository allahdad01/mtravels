// Initialize DataTable
$(document).ready(function() {
    if ($('#refundTicketTable').length) {
        $('#refundTicketTable').DataTable({
            responsive: true,
            autoWidth: false,
            paging: true,
            pageLength: 10,
            lengthChange: true,
            searching: true,
            info: true,
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                 '<"row"<"col-sm-12"tr>>' +
                 '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
            language: {
                search: window.translations.search + ":",
                lengthMenu: window.translations.show + " _MENU_ " + window.translations.entries,
                info: window.translations.showing + " _START_ " + window.translations.to + " _END_ " + window.translations.of + " _TOTAL_ " + window.translations.entries,
                infoEmpty: window.translations.showing + " 0 " + window.translations.to + " 0 " + window.translations.of + " 0 " + window.translations.entries,
                infoFiltered: "(" + window.translations.filtered_from + " _MAX_ " + window.translations.total_entries + ")",
                paginate: {
                    first: window.translations.first,
                    last: window.translations.last,
                    next: window.translations.next,
                    previous: window.translations.previous
                }
            },
            columnDefs: [
                { orderable: false, targets: 'no-sort' }
            ],
            order: [[0, 'asc']]
        });
    }
}); 